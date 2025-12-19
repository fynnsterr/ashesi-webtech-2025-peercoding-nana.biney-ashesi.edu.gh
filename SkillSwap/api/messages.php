<?php

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_GET['mode'] ?? '';

if ($method === 'GET') {
    requireLogin();
    
    if ($action === 'list') {
        $pdo = getDB();
        $userId = getUserId();
        $bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;
        
        if ($bookingId) {
            // Get messages for a specific booking
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       sender.full_name as sender_name, sender.profile_image as sender_image,
                       receiver.full_name as receiver_name, receiver.profile_image as receiver_image
                FROM messages m
                JOIN users sender ON m.sender_id = sender.user_id
                JOIN users receiver ON m.receiver_id = receiver.user_id
                WHERE m.booking_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$bookingId, $userId, $userId]);
        } else {
            // Get all conversations for user
            $stmt = $pdo->prepare("
                SELECT DISTINCT 
                    CASE 
                        WHEN m.sender_id = ? THEN m.receiver_id 
                        ELSE m.sender_id 
                    END as other_user_id,
                    CASE 
                        WHEN m.sender_id = ? THEN receiver.full_name 
                        ELSE sender.full_name 
                    END as other_user_name,
                    CASE 
                        WHEN m.sender_id = ? THEN receiver.profile_image 
                        ELSE sender.profile_image 
                    END as other_user_image,
                    (SELECT message_text FROM messages m2 
                     WHERE (m2.sender_id = ? AND m2.receiver_id = other_user_id) 
                        OR (m2.receiver_id = ? AND m2.sender_id = other_user_id)
                     ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages m2 
                     WHERE (m2.sender_id = ? AND m2.receiver_id = other_user_id) 
                        OR (m2.receiver_id = ? AND m2.sender_id = other_user_id)
                     ORDER BY m2.created_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*) FROM messages m2 
                     WHERE m2.receiver_id = ? AND m2.sender_id = other_user_id AND m2.is_read = 0) as unread_count
                FROM messages m
                JOIN users sender ON m.sender_id = sender.user_id
                JOIN users receiver ON m.receiver_id = receiver.user_id
                WHERE m.sender_id = ? OR m.receiver_id = ?
                ORDER BY last_message_time DESC
            ");
            $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        }
        
        $messages = $stmt->fetchAll();
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }
    
    if ($action === 'unread_count') {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([getUserId()]);
        $result = $stmt->fetch();
        
        echo json_encode(['success' => true, 'count' => (int)$result['count']]);
        exit;
    }
}

if ($method === 'POST') {
    requireLogin();
    
    if ($action === 'send') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $messageText = trim($_POST['message_text'] ?? '');
        $exchangeId = isset($_POST['exchange_id']) ? (int)$_POST['exchange_id'] : null;
        
        if ($receiverId <= 0 || empty($messageText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Receiver ID and message text are required']);
            exit;
        }
        
        $pdo = getDB();
        
        // Verify receiver exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $stmt->execute([$receiverId]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Receiver not found']);
            exit;
        }
        
        // If exchange_id provided, verify user has access to it
        if ($exchangeId) {
            $stmt = $pdo->prepare("
                SELECT proposer_id, match_user_id 
                FROM exchange_proposals 
                WHERE exchange_id = ?
            ");
            $stmt->execute([$exchangeId]);
            $exchange = $stmt->fetch();
            
            if (!$exchange) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Exchange not found']);
                exit;
            }
            
            $userId = getUserId();
            if ($exchange['proposer_id'] != $userId && $exchange['match_user_id'] != $userId) {
                // Also check exchange_matches if not in proposals directly (e.g. accepted match)
                // But typically match_user_id is updated in proposals on accept.
                // Let's assume match_user_id is sufficient or we can check exchange_matches too.
                 $stmtMatches = $pdo->prepare("SELECT acceptor_id FROM exchange_matches WHERE exchange_id = ? AND acceptor_id = ?");
                 $stmtMatches->execute([$exchangeId, $userId]);
                 if (!$stmtMatches->fetch()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit;
                 }
            }
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, exchange_id, message_text)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                getUserId(),
                $receiverId,
                $exchangeId,
                $messageText
            ]);
            
            $messageId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'message' => 'Message sent', 'message_id' => $messageId]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to send message']);
        }
        exit;
    }
    
    if ($action === 'mark_read') {
        $messageIds = $_POST['message_ids'] ?? [];
        
        if (empty($messageIds) || !is_array($messageIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid message IDs']);
            exit;
        }
        
        $pdo = getDB();
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        
        try {
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE message_id IN ($placeholders) AND receiver_id = ?
            ");
            $params = array_merge($messageIds, [getUserId()]);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Messages marked as read']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update messages']);
        }
        exit;
    }
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Not found']);

