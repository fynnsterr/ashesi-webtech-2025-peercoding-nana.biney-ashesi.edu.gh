<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    // Check if user is an admin
    if (hasRole('admin')) {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } else {
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
    }
    exit;
}

$pageTitle = 'Login';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = loginUser($email, $password);
        if ($result['success']) {
            // Check if user is an admin
            if (isset($result['user']['role']) && $result['user']['role'] === 'admin') {
                header('Location: ' . BASE_URL . '/admin/index.php');
            } else {
                header('Location: ' . BASE_URL . '/pages/dashboard.php');
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Only include header AFTER all header() operations are complete
require_once __DIR__ . '/../includes/header.php';
?>

<!-- In login.php, update the main content section -->
<main class="auth-page">
    <div class="auth-container">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Login</h1>
            </div>

            <div class="card-body">

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo BASE_URL; ?>/pages/login.php" onsubmit="return handleLogin(event)">
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            required
                        >
                    </div>

                    <div class="form-group" style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px; width: 100%;">
                            <span class="btn-text">Login</span>
                            <span class="btn-loading d-none">
                                <i class="fas fa-spinner fa-spin me-2"></i> Logging in...
                            </span>
                        </button>
                    </div>

                    <div style="text-align: center; margin-top: 1.5rem; color: var(--gray-dark);">
                        Don't have an account?
                        <a href="<?php echo BASE_URL; ?>/pages/register.php" style="color: var(--primary-color); font-weight: 500;">
                            Create an account
                        </a>
                    </div>

                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="<?php echo BASE_URL; ?>/pages/forgot-password.php" style="color: var(--primary-color);">
                            Forgot your password?
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
</main>


<script>
function handleLogin(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('button[type="submit"]');
    const btnText = submitButton.querySelector('.btn-text');
    const btnLoading = submitButton.querySelector('.btn-loading');
    
    // Show loading state
    btnText.classList.add('d-none');
    btnLoading.classList.remove('d-none');
    submitButton.disabled = true;
    
    // Get form data
    const formData = new FormData(form);
    formData.append('action', 'login');
    
    // Make AJAX request
    fetch('<?php echo BASE_URL; ?>/api/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect on success
            window.location.href = data.redirect || '<?php echo BASE_URL; ?>/pages/dashboard.php';
        } else {
            // Show error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.textContent = data.message || 'Login failed. Please try again.';
            form.insertBefore(errorDiv, form.firstChild);
            
            // Reset button state
            btnText.classList.remove('d-none');
            btnLoading.classList.add('d-none');
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-error';
        errorDiv.textContent = 'An error occurred. Please try again.';
        form.insertBefore(errorDiv, form.firstChild);
        
        // Reset button state
        btnText.classList.remove('d-none');
        btnLoading.classList.add('d-none');
        submitButton.disabled = false;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>