<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
requireRole('admin');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID';
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];
$pdo = getDB();

try {
    // Get current verification status
    $stmt = $pdo->prepare("SELECT verification_status FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_status = $stmt->fetchColumn();
    
    // Toggle between 'pending' and 'verified'
    $new_status = ($current_status === 'verified') ? 'pending' : 'verified';
    
    // Update the status
    $stmt = $pdo->prepare("UPDATE users SET verification_status = ? WHERE user_id = ?");
    $stmt->execute([$new_status, $user_id]);
    
    // Get user details for notification
    $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['verification_change'] = [
            'email' => $user['email'],
            'name' => $user['full_name'],
            'new_status' => $new_status
        ];
    }
    
    $_SESSION['success'] = "Verification status updated to: " . ucfirst($new_status);
} catch (Exception $e) {
    $_SESSION['error'] = 'Error updating verification status: ' . $e->getMessage();
}

header('Location: users.php');
exit;
?>
