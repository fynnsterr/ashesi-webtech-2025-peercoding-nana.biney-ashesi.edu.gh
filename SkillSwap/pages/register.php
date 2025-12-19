<?php
// Start session and include authentication first
require_once __DIR__ . '/../includes/auth.php';
$currentUser = getCurrentUser();

// Check if user is already logged in and redirect BEFORE any output
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle = 'Join SkillSwap';

// Ghana cities
$ghanaCities = ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Ashaiman', 'Sunyani', 'Cape Coast', 'Obuasi', 'Teshie', 'Tema', 'Koforidua', 'Sekondi', 'Techiman', 'Ho', 'Wa', 'Bolgatanga', 'Bawku', 'Nkawkaw', 'Aflao', 'Hohoe'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $data = [
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'full_name' => $_POST['full_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'location' => $_POST['location'] ?? ''
        ];
        
        if ($data['password'] !== $data['confirm_password']) {
            $error = 'Passwords do not match';
        } elseif (strlen($data['password']) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            unset($data['confirm_password']);
            $result = registerUser($data);
            if ($result['success']) {
                header('Location: ' . BASE_URL . '/pages/dashboard.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Now include the header which outputs HTML
require_once __DIR__ . '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>SkillSwap - Trade Skills, Not Money</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <main>
        <script>
            // Mobile menu toggle
            document.getElementById('hamburger').addEventListener('click', function() {
                document.getElementById('navLinks').classList.toggle('active');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                const navLinks = document.getElementById('navLinks');
                const hamburger = document.getElementById('hamburger');
                
                if (!navLinks.contains(event.target) && !hamburger.contains(event.target)) {
                    navLinks.classList.remove('active');
                }
            });
        </script>

        <!-- Inside register.php -->
        <div class="auth-container">
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">Create an Account</h1>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo BASE_URL; ?>/api/auth.php" onsubmit="return handleRegistration(event)">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" required 
                                   pattern="[0-9]{10,15}" 
                                   title="Please enter a valid phone number (10-15 digits)"
                                   placeholder="e.g., 0244123456">
                        </div>

                        <div class="form-group">
                            <label for="location">Location</label>
                            <select id="location" name="location" class="form-control" required>
                                <option value="" disabled selected>Select your city</option>
                                <?php foreach ($ghanaCities as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required minlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px; width: 100%;">Create Account</button>
                        </div>
                        
                        <div style="text-align: center; margin-top: 1.5rem; color: var(--gray-dark);">
                            Already have an account? <a href="<?php echo BASE_URL; ?>/pages/login.php" style="color: var(--primary-color); font-weight: 500;">Log in</a>
                            <div style="margin-top: 1rem; font-size: 0.9em;">
                                By creating an account, you agree to our
                                <a href="<?php echo BASE_URL; ?>/pages/terms.php" style="color: var(--primary-color);">Terms of Service</a> and
                                <a href="<?php echo BASE_URL; ?>/pages/privacy.php" style="color: var(--primary-color;">Privacy Policy</a>.
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add this script at the bottom of the file, before </body> -->
        <script>
        function handleRegistration(event) {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Validate form fields
            const phone = document.getElementById('phone').value.trim();
            const location = document.getElementById('location').value;
            
            if (!location) {
                alert('Please select your location');
                return false;
            }
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validate phone number (10-15 digits)
            const phoneRegex = /^[0-9]{10,15}$/;
            if (!phoneRegex.test(phone)) {
                alert('Please enter a valid phone number (10-15 digits)');
                return false;
            }
            
            // Validate passwords match
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                return false;
            }
            
            // Disable button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
            
            // Get form data
            const formData = new FormData(form);
            formData.append('action', 'register');
            
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
                    errorDiv.textContent = data.message || 'Registration failed. Please try again.';
                    form.insertBefore(errorDiv, form.firstChild);
                    
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-error';
                errorDiv.textContent = 'An error occurred. Please try again.';
                form.insertBefore(errorDiv, form.firstChild);
                
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
            
            return false;
        }
        </script>

</body>
</html>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>