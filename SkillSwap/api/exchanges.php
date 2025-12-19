<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';

if ($method === 'POST') {
    requireLogin();
    $userId = getUserId();
    $pdo = getDB();

    if ($mode === 'confirm_completion') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        $exchangeId = (int)($_POST['exchange_id'] ?? 0);
        if ($exchangeId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid exchange ID']);
            exit;
        }

        // Get exchange details to determine role (proposer or match)
        $stmt = $pdo->prepare("
            SELECT ep.exchange_id, ep.proposer_id, ep.match_user_id, ep.status,
                   em.match_id
            FROM exchange_proposals ep
            JOIN exchange_matches em ON ep.exchange_id = em.exchange_id
            WHERE ep.exchange_id = ?
        ");
        $stmt->execute([$exchangeId]);
        $exchange = $stmt->fetch();

        if (!$exchange) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Exchange not found']);
            exit;
        }

        // Determine user role
        $isProposer = ($userId == $exchange['proposer_id']);
        $isMatch = ($userId == $exchange['match_user_id']);

        if (!$isProposer && !$isMatch) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // Update confirmation
        try {
            $pdo->beginTransaction();

            if ($isProposer) {
                $stmt = $pdo->prepare("UPDATE exchange_matches SET proposer_confirmed = 1 WHERE exchange_id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE exchange_matches SET acceptor_confirmed = 1 WHERE exchange_id = ?");
            }
            $stmt->execute([$exchangeId]);

            // Check if BOTH confirmed
            $stmt = $pdo->prepare("SELECT proposer_confirmed, acceptor_confirmed FROM exchange_matches WHERE exchange_id = ?");
            $stmt->execute([$exchangeId]);
            $flags = $stmt->fetch();

            $completed = false;
            if ($flags['proposer_confirmed'] && $flags['acceptor_confirmed']) {
                // Mark as completed
                $pdo->prepare("
                    UPDATE exchange_proposals 
                    SET status = 'completed', completed_at = NOW(), updated_at = NOW() 
                    WHERE exchange_id = ?
                ")->execute([$exchangeId]);

                $pdo->prepare("
                    UPDATE exchange_matches 
                    SET match_status = 'completed', updated_at = NOW() 
                    WHERE exchange_id = ?
                ")->execute([$exchangeId]);
                
                $completed = true;
            }

            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Exchange completion confirmed.',
                'completed' => $completed
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request']);
