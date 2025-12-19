<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the auth functions
require_once __DIR__ . '/../includes/auth.php';

// Call the logout function (this will handle the redirection)
logoutUser();