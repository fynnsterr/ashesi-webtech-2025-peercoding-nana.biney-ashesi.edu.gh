<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/contact.php');
    exit;
}

if (!isLoggedIn()) {
    $_SESSION['flash_error'] = 'You must be logged in to send a message.';
    header('Location: ' . BASE_URL . '/pages/login.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Basic validation
if (empty($name) || empty($email) || empty($message)) {
    $_SESSION['flash_error'] = 'Please fill in all required fields.';
    header('Location: ' . BASE_URL . '/pages/contact.php');
    exit;
}

$pdo = getDB();
$currentUser = getCurrentUser();

try {
    // 1. Find an Admin to receive the message
    $stmt = $pdo->prepare("SELECT user_id, email FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($currentUser && $admin) {
        // --- SCENARIO: Logged-in User -> Internal Message to Admin ---
        
        // Construct the message with the subject prefix
        $fullMessage = "Subject: " . $subject . "\n\n" . $message;

        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message_text, is_read, created_at) 
            VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$currentUser['user_id'], $admin['user_id'], $fullMessage]);
        
        $_SESSION['flash_success'] = 'Your message has been sent directly to the administration team.';

    } else {
        // Fallback if no admin is found or unexpected error
        $_SESSION['flash_error'] = 'Unable to send message at this time. Please try again later.';
    }

} catch (PDOException $e) {
    error_log("Database error in process_contact.php: " . $e->getMessage());
    $_SESSION['flash_error'] = 'An error occurred while sending your message. Please try again later.';
}

header('Location: ' . BASE_URL . '/pages/contact.php');
exit;
exit;
