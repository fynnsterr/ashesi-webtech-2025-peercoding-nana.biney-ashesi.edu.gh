<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_GET['mode'] ?? '';

if ($action === 'create') {
    try {
        $pdo = getDB();
        
        // Get and validate inputs
        $matchUserId = (int)($_POST['match_user_id'] ?? 0);
        $skillToLearnId = (int)($_POST['skill_to_learn_id'] ?? 0);
        $skillToTeachId = (int)($_POST['skill_to_teach_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($matchUserId <= 0 || $skillToLearnId <= 0 || $skillToTeachId <= 0 || empty($title)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Start transaction
        $pdo->beginTransaction();

        // 1. Create Exchange Proposal
        $stmt = $pdo->prepare("
            INSERT INTO exchange_proposals (
                proposer_id, 
                skill_to_learn_id, 
                skill_to_teach_id, 
                title, 
                description, 
                match_user_id, 
                status,
                exchange_type,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'one_on_one', NOW(), NOW())
        ");
        
        $stmt->execute([
            getUserId(),
            $skillToLearnId,
            $skillToTeachId,
            $title,
            $description,
            $matchUserId
        ]);
        
        $exchangeId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO exchange_matches (
                exchange_id,
                acceptor_id,
                proposer_skill_id,
                acceptor_skill_id,
                match_status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, 'proposed', NOW(), NOW())
        ");

        $stmt->execute([
            $exchangeId,
            $matchUserId,
            $skillToTeachId,
            $skillToLearnId
        ]);

        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Proposal sent successfully', 'exchange_id' => $exchangeId]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
