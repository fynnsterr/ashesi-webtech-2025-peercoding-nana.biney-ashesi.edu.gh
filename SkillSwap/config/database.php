<?php

// Detect Base URL
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Check if we are in a subdirectory (like /SkillSwap/)
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    // If we are in /SkillSwap/pages/index.php, the base path is /SkillSwap
    // If we are in /pages/index.php, the base path is empty
    
    $base_path = '';
    if (strpos($script_name, '/SkillSwap/') !== false) {
        $base_path = '/SkillSwap';
    }
    
    // Alternatively, a more robust way to handle any subdirectory:
    // This assumes the project root contains 'config/database.php'
    $current_dir = str_replace('\\', '/', __DIR__);
    $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    
    if (!empty($doc_root) && strpos($current_dir, $doc_root) === 0) {
        $relative_path = substr($current_dir, strlen($doc_root));
        // Remove 'config' from the end
        $base_path = preg_replace('/\/config$/', '', $relative_path);
    }

    define('BASE_URL', $base_path);
}

define('DB_HOST', 'sql308.infinityfree.com');
define('DB_NAME', 'if0_40713863_ssc2027');
define('DB_USER', 'if0_40713863');
define('DB_PASS', 'uuY9yuYjl5kU'); 
define('DB_CHARSET', 'utf8mb4');

/**
 * Get database connection
 * @return PDO
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}