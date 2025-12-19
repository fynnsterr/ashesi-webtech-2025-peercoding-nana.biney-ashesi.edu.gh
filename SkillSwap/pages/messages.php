<?php
$pageTitle = 'Messages';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$pdo = getDB();
$userId = getUserId();
$userRole = getUserRole();
$exchangeId = isset($_GET['exchange_id']) ? (int)$_GET['exchange_id'] : null;
$otherUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Get conversations list
$conversations = [];

if ($userRole === 'admin') {
    // Admin can see all non-admin users
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id as other_user_id,
            u.full_name as other_user_name,
            u.profile_image as other_user_image,
            (SELECT MAX(m.created_at) FROM messages m 
             WHERE (m.sender_id = u.user_id OR m.receiver_id = u.user_id)) as last_message_time
        FROM users u
        WHERE u.role != 'admin' AND u.user_id != ?
        ORDER BY last_message_time DESC, u.full_name ASC
    ");
    $stmt->execute([$userId]);
    $conversations = $stmt->fetchAll();
} else {
    // Regular users only see their message partners
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as other_user_id,
            CASE 
                WHEN m.sender_id = ? THEN u2.full_name 
                ELSE u1.full_name 
            END as other_user_name,
            CASE 
                WHEN m.sender_id = ? THEN u2.profile_image 
                ELSE u1.profile_image 
            END as other_user_image,
            MAX(m.created_at) as last_message_time
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.user_id
        JOIN users u2 ON m.receiver_id = u2.user_id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY other_user_id, other_user_name, other_user_image
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $conversations = $stmt->fetchAll();
}

// Get last message and unread count for each conversation
foreach ($conversations as &$conv) {
    $otherId = $conv['other_user_id'];
    
    // Get last message
    $stmt2 = $pdo->prepare("
        SELECT message_text, created_at 
        FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt2->execute([$userId, $otherId, $otherId, $userId]);
    $lastMsg = $stmt2->fetch();
    $conv['last_message'] = $lastMsg ? $lastMsg['message_text'] : '';
    $conv['last_message_time'] = $lastMsg ? $lastMsg['created_at'] : $conv['last_message_time'];
    
    // Get unread count
    $stmt3 = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt3->execute([$userId, $otherId]);
    $unread = $stmt3->fetch();
    $conv['unread_count'] = (int)$unread['count'];
}
unset($conv);

// Get messages for selected conversation
$messages = [];
$otherUser = null;
if ($exchangeId || $otherUserId) {
    if ($exchangeId) {
        // Get messages for exchange
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   sender.full_name as sender_name, sender.profile_image as sender_image,
                   receiver.full_name as receiver_name
            FROM messages m
            JOIN users sender ON m.sender_id = sender.user_id
            JOIN users receiver ON m.receiver_id = receiver.user_id
            WHERE m.exchange_id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$exchangeId, $userId, $userId]);
        $messages = $stmt->fetchAll();
        
        // Get other user from exchange
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN ep.proposer_id = ? THEN ep.match_user_id
                    ELSE ep.proposer_id
                END as other_user_id,
                CASE 
                    WHEN ep.proposer_id = ? THEN mu.full_name
                    ELSE pu.full_name
                END as other_user_name,
                CASE 
                    WHEN ep.proposer_id = ? THEN mu.profile_image
                    ELSE pu.profile_image
                END as other_user_image
            FROM exchange_proposals ep
            JOIN users pu ON ep.proposer_id = pu.user_id
            LEFT JOIN users mu ON ep.match_user_id = mu.user_id
            WHERE ep.exchange_id = ?
        ");
        $stmt->execute([$userId, $userId, $userId, $exchangeId]);
        $otherUser = $stmt->fetch();
    } elseif ($otherUserId) {
        // Get messages with specific user
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   sender.full_name as sender_name, sender.profile_image as sender_image,
                   receiver.full_name as receiver_name
            FROM messages m
            JOIN users sender ON m.sender_id = sender.user_id
            JOIN users receiver ON m.receiver_id = receiver.user_id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
        $messages = $stmt->fetchAll();
        
        // Get other user info
        $stmt = $pdo->prepare("SELECT user_id, full_name, profile_image FROM users WHERE user_id = ?");
        $stmt->execute([$otherUserId]);
        $otherUser = $stmt->fetch();
        if ($otherUser) {
            // Normalize keys for template
            $otherUser['other_user_id'] = $otherUser['user_id'];
            $otherUser['other_user_name'] = $otherUser['full_name'];
            $otherUser['other_user_image'] = $otherUser['profile_image'];
        }
    }
    
    // Mark messages as read
    if (!empty($messages)) {
        $messageIds = array_column($messages, 'message_id'); 
        if (empty($messageIds)) {
           
        }
        
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE message_id IN ($placeholders) AND receiver_id = ?
        ");
        $params = array_merge($messageIds, [$userId]);
        $stmt->execute($params);
    }
}
?>

<div class="container-fluid py-4">
    <div class="dashboard-header mb-4 d-flex justify-content-between align-items-center">
        <h1 style="color: var(--primary-color);">
            <i class="fas fa-envelope me-2"></i>Messages
        </h1>
    </div>

    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; height: calc(100vh - 200px); min-height: 500px;">
        <!-- Conversations List -->
        <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); display: flex; flex-direction: column; overflow: hidden;">
            <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
                <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                    <i class="fas fa-users me-2" style="color: var(--primary-color);"></i>
                    <?php echo $userRole === 'admin' ? 'All Users' : 'Conversations'; ?>
                </h2>
            </div>
            <div class="card-body p-0" style="overflow-y: auto; flex: 1;">
                <?php if (empty($conversations)): ?>
                    <div style="padding: 2rem; text-align: center; color: #6B7280;">
                        <i class="fas fa-comments fa-3x mb-3" style="opacity: 0.3;"></i>
                        <p class="mb-0">No conversations yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($conversations as $conv): 
                            $isActive = $otherUserId == $conv['other_user_id'];
                            // Generate initials
                            $names = explode(' ', trim($conv['other_user_name']));
                            $initials = isset($names[0]) ? strtoupper(substr($names[0], 0, 1)) : '';
                            if (count($names) > 1) {
                                $initials .= strtoupper(substr(end($names), 0, 1));
                            }
                            // Random color based on name
                            $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                            $bgColor = $colors[strlen($conv['other_user_name']) % count($colors)];
                        ?>
                            <a href="<?php echo BASE_URL; ?>/pages/messages.php?user_id=<?php echo $conv['other_user_id']; ?>" 
                               class="list-group-item list-group-item-action"
                               style="border: none; padding: 1rem 1.25rem; transition: all 0.2s; background-color: <?php echo $isActive ? '#f0f9ff' : 'white'; ?>;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <?php if (!empty($conv['other_user_image'])): ?>
                                        <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo e($conv['other_user_image']); ?>" 
                                             alt="<?php echo e($conv['other_user_name']); ?>"
                                             class="rounded-circle"
                                             style="width: 42px; height: 42px; min-width: 42px; object-fit: cover; border: 1px solid #e5e7eb;">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                             style="width: 42px; height: 42px; min-width: 42px; font-size: 0.85rem; background-color: <?php echo $bgColor; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                            <span style="font-weight: 600; color: <?php echo $isActive ? 'var(--primary-color)' : '#1f2937'; ?>;"><?php echo e($conv['other_user_name']); ?></span>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                                <span class="badge rounded-pill" style="background: var(--primary-color); color: white; font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                                                    <?php echo $conv['unread_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($conv['last_message'])): ?>
                                            <p style="margin: 0; font-size: 0.85rem; color: #6B7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?php echo e(substr($conv['last_message'], 0, 40)); ?><?php echo strlen($conv['last_message']) > 40 ? '...' : ''; ?>
                                            </p>
                                        <?php else: ?>
                                            <p style="margin: 0; font-size: 0.85rem; font-style: italic; color: #9CA3AF;">
                                                No messages yet
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($conv['last_message_time']): ?>
                                            <small style="color: #9CA3AF; font-size: 0.75rem;">
                                                <?php echo date('M d, g:i A', strtotime($conv['last_message_time'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Messages Area -->
        <div class="card" style="border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); display: flex; flex-direction: column; overflow: hidden;">
            <?php if ($otherUser): ?>
                <div class="card-header" style="background: white; border-bottom: 2px solid #F3F4F6; padding: 1.25rem 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <?php
                            $names = explode(' ', trim($otherUser['other_user_name']));
                            $initials = isset($names[0]) ? strtoupper(substr($names[0], 0, 1)) : '';
                            if (count($names) > 1) {
                                $initials .= strtoupper(substr(end($names), 0, 1));
                            }
                            $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
                            $bgColor = $colors[strlen($otherUser['other_user_name']) % count($colors)];
                        ?>
                        <?php if (!empty($otherUser['other_user_image'])): ?>
                            <img src="<?php echo BASE_URL; ?>/uploads/profiles/<?php echo e($otherUser['other_user_image']); ?>" 
                                 alt="<?php echo e($otherUser['other_user_name']); ?>"
                                 class="rounded-circle"
                                 style="width: 40px; height: 40px; min-width: 40px; object-fit: cover; border: 1px solid #e5e7eb;">
                        <?php else: ?>
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                 style="width: 40px; height: 40px; min-width: 40px; font-size: 0.9rem; background-color: <?php echo $bgColor; ?>;">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        <h2 class="card-title" style="color: var(--primary-color); font-size: 1.1rem; margin: 0;">
                            <?php echo e($otherUser['other_user_name']); ?>
                        </h2>
                    </div>
                </div>
                
                <div id="messagesContainer" class="card-body" style="flex: 1; overflow-y: auto; padding: 1.5rem; background-color: #f9fafb;">
                    <?php if (empty($messages)): ?>
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #6B7280;">
                            <i class="fas fa-paper-plane fa-3x mb-3" style="opacity: 0.3;"></i>
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($messages as $msg): 
                                $isSent = $msg['sender_id'] == $userId;
                            ?>
                                <div style="display: flex; <?php echo $isSent ? 'justify-content: flex-end;' : 'justify-content: flex-start;'; ?>">
                                    <div style="max-width: 70%; padding: 0.875rem 1rem; border-radius: 12px; 
                                                <?php echo $isSent 
                                                    ? 'background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%); color: white; border-bottom-right-radius: 4px;' 
                                                    : 'background: white; color: #1f2937; border: 1px solid #e5e7eb; border-bottom-left-radius: 4px;'; ?>
                                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 0.25rem; font-size: 0.75rem; <?php echo $isSent ? 'color: rgba(255,255,255,0.7);' : 'color: #9CA3AF;'; ?>">
                                            <span style="font-weight: 600;"><?php echo e($isSent ? 'You' : $msg['sender_name']); ?></span>
                                            <span><?php echo date('g:i A', strtotime($msg['created_at'])); ?></span>
                                        </div>
                                        <p style="margin: 0; line-height: 1.5;"><?php echo nl2br(e($msg['message_text'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer" style="background: white; border-top: 2px solid #F3F4F6; padding: 1rem 1.5rem;">
                    <form id="messageForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="receiver_id" value="<?php echo $otherUser['other_user_id']; ?>">
                        <?php if ($exchangeId): ?>
                            <input type="hidden" name="exchange_id" value="<?php echo $exchangeId; ?>">
                        <?php endif; ?>
                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                            <input type="text" name="message_text" id="messageInput" class="form-control" 
                                   placeholder="Type your message..." required 
                                   style="flex: 1; border-radius: 20px; padding: 0.625rem 1rem; border: 1px solid #e5e7eb;">
                            <button type="submit" class="btn btn-primary" style="border-radius: 50%; width: 42px; height: 42px; padding: 0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card-body" style="display: flex; align-items: center; justify-content: center; height: 100%;">
                    <div style="text-align: center; color: #6B7280;">
                        <i class="fas fa-comments fa-4x mb-3" style="opacity: 0.2;"></i>
                        <h3 style="color: #4B5563; margin-bottom: 0.5rem;">Select a conversation</h3>
                        <p style="margin: 0;">Choose a user from the list to start messaging</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.list-group-item:hover:not(.active) {
    background-color: #f9fafb;
}

.list-group-item.active {
    border-color: transparent;
}

@media (max-width: 768px) {
    .container-fluid > div[style*="grid"] {
        grid-template-columns: 1fr !important;
        height: auto !important;
    }
    
    .container-fluid > div[style*="grid"] > .card:first-child {
        max-height: 300px;
    }
    
    .container-fluid > div[style*="grid"] > .card:last-child {
        min-height: 400px;
    }
}
</style>

<script>
// Auto-scroll to bottom
const messagesContainer = document.getElementById('messagesContainer');
if (messagesContainer) {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Send message
document.getElementById('messageForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const messageText = formData.get('message_text');
    
    if (!messageText.trim()) return;
    
    const response = await fetch('<?php echo BASE_URL; ?>/api/messages.php?mode=send', {
        method: 'POST',
        body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
        // Reload page to show new message
        window.location.reload();
    } else {
        alert('Error: ' + result.message);
    }
});

// Auto-refresh messages every 5 seconds
<?php if ($otherUser): ?>
setInterval(function() {
    const url = new URL(window.location.href);
    const exchangeId = url.searchParams.get('exchange_id');
    const userId = url.searchParams.get('user_id');
    
    if (exchangeId || userId) {
        fetch('<?php echo BASE_URL; ?>/api/messages.php?mode=list<?php echo $exchangeId ? '&exchange_id=' . $exchangeId : ($otherUserId ? '&user_id=' . $otherUserId : ''); ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > <?php echo count($messages); ?>) {
                    window.location.reload();
                }
            })
            .catch(err => console.error('Error refreshing messages:', err));
    }
}, 5000);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

