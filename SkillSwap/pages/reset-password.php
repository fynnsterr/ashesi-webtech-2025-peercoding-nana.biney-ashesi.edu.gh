<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle = 'Reset Password';
$message = '';
$error = '';
$validToken = false;
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!empty($token) && !empty($email)) {
    $validToken = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $message = 'Your password has been reset successfully. You can now login with your new password.';
        $validToken = false;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<main class="auth-page">
    <div class="auth-container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Reset Your Password</h1>
                <?php if ($validToken): ?>
                    <p class="card-subtitle">Create a new password for your account</p>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <?php echo e($message); ?>
                        <div style="margin-top: 1rem;">
                            <a href="/pages/login.php" class="btn btn-outline-secondary btn-sm">
                                Back to Login
                            </a>
                        </div>
                    </div>
                <?php elseif (!$validToken): ?>
                    <div class="alert alert-error">
                        Invalid or expired password reset link. Please request a new one.
                        <div style="margin-top: 1rem;">
                            <a href="/pages/forgot-password.php" class="btn btn-outline-secondary btn-sm">
                                Request New Reset Link
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="/pages/reset-password.php?token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($email); ?>">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                            <small class="form-text text-muted">Password must be at least 8 characters long</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-control" 
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="form-group" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
