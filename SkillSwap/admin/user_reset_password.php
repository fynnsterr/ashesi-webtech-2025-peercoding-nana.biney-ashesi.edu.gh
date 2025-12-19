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
    // Generate a random password
    $new_password = bin2hex(random_bytes(4)); // 8 characters long
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update the user's password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->execute([$hashed_password, $user_id]);
    
    // Get user email for notification (optional)
    $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['reset_password'] = [
            'email' => $user['email'],
            'password' => $new_password,
            'name' => $user['full_name']
        ];
    }
    
    $_SESSION['success'] = 'Password has been reset successfully';
} catch (Exception $e) {
    $_SESSION['error'] = 'Error resetting password: ' . $e->getMessage();
}

header('Location: users.php');
exit;
?>
