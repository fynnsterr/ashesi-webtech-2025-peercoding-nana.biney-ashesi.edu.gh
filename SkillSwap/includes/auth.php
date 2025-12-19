<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'logout':
            // Clear all session variables
            $_SESSION = [];

            // Delete the session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            // Destroy the session
            session_destroy();
            
            // Return JSON response
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 * @return int|null
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $user = null;
    
    if ($user === null) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([getUserId()]);
        $user = $stmt->fetch();
    }
    
    return $user;
}

/**
 * Get user role
 * @return string|null
 */
function getUserRole() {
    $user = getCurrentUser();
    return $user['role'] ?? null;
}

/**
 * Check if user has specific role
 * @param string|array $roles
 * @return bool
 */
function hasRole($roles) {
    $userRole = getUserRole();
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    return $userRole === $roles;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/pages/login.php');
        exit;
    }
}

/**
 * Require user to have specific role
 * @param string|array $roles
 * @param bool $redirect
 */
function requireRole($roles, $redirect = true) {
    requireLogin();
    
    $roles = is_array($roles) ? $roles : [$roles];
    $userRole = getUserRole();
    
    if (!in_array($userRole, $roles)) {
        if ($redirect) {
            header('Location: ' . BASE_URL . '/pages/dashboard.php');
            exit;
        }
        return false;
    }
    
    return true;
}

/**
 * Login user
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function loginUser($email, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password', 'user' => null];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password', 'user' => null];
    }
    
    // Set session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    
    return ['success' => true, 'message' => 'Login successful', 'user' => $user];
}

/**
 * Register new user
 * @param array $data
 * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
 */
function registerUser($data) {
    $pdo = getDB();
    
    // Validate required fields
    $required = ['email', 'password', 'full_name', 'phone', 'location'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Field {$field} is required", 'user_id' => null];
        }
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format', 'user_id' => null];
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already registered', 'user_id' => null];
    }
    
    // Generate username from email
    $username = strtolower(explode('@', $data['email'])[0]);
    $counter = 1;
    $originalUsername = $username;
    
    // Ensure username is unique
    while (true) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            break;
        }
        $username = $originalUsername . $counter;
        $counter++;
    }
    
    // Hash password
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Insert user
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, email, password_hash, full_name, phone, 
                location, role, verification_status
            ) VALUES (?, ?, ?, ?, ?, ?, 'both', 'pending')
        ");
        
        $stmt->execute([
            $username,
            $data['email'],
            $passwordHash,
            $data['full_name'],
            $data['phone'],
            $data['location']
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Auto-login after registration
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $data['email'];
        $_SESSION['user_role'] = 'both'; // Default role for new users
        
        return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.', 'user_id' => null];
    }
}

function logoutUser() {
    // Clear all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();
    
    // Redirect to home page
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit;
}

/**
 * Sanitize output
 * @param string $string
 * @return string
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}