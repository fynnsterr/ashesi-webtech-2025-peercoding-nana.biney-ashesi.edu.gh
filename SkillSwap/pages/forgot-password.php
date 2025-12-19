<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle = 'Forgot Password';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $message = 'If an account exists with this email, you will receive a password reset link shortly.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="auth-page">
    <div class="auth-container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Reset Your Password</h1>
                <p class="card-subtitle">Enter your email to receive a password reset link</p>
            </div>

            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo e($message); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo BASE_URL; ?>/pages/forgot-password.php">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            Send Reset Link
                        </button>
                    </div>

                    <div style="text-align: center; margin-top: 1.5rem; color: var(--gray-dark);">
                        Remember your password?
                        <a href="<?php echo BASE_URL; ?>/pages/login.php" style="color: var(--primary-color); font-weight: 500;">
                            Back to Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
