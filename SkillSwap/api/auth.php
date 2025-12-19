<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Include the auth functions
require_once __DIR__ . '/../includes/auth.php';

// Get the action from POST or GET
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Handle different actions
switch ($action) {
    case 'login':
        // Get POST data
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Login the user
        $result = loginUser($email, $password);
        
        // Return JSON response
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'redirect' => isset($result['user']['role']) && $result['user']['role'] === 'admin' 
                    ? BASE_URL . '/admin/index.php' 
                    : BASE_URL . '/pages/dashboard.php'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?? 'Login failed. Please check your credentials.'
            ]);
        }
        break;
        
    case 'register':
        // Get POST data
        $data = [
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'full_name' => $_POST['full_name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'location' => $_POST['location'] ?? ''
        ];
        
        // Register the user
        $result = registerUser($data);
        
        // Get return URL from POST or GET, default to dashboard
        $returnTo = $_POST['return_to'] ?? $_GET['return_to'] ?? BASE_URL . '/pages/dashboard.php';
        
        // Return JSON response
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'redirect' => $returnTo
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message'] ?? 'Registration failed'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}