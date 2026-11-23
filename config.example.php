<?php
/**
 * FabLine ERP - Configuration Template
 * 
 * Instructions:
 * 1. Copy this file and rename it to `config.php`.
 * 2. Update your database connection credentials below.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout settings (exempt 'tv' display user)
if (isset($_SESSION['expire_time'])) {
    if (!(isset($_SESSION['username']) && strcasecmp($_SESSION['username'], 'tv') === 0)) {
        if (time() > $_SESSION['expire_time']) {
            session_unset();
            session_destroy();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('HTTP/1.1 401 Unauthorized');
                echo 'SESSION_EXPIRED';
                exit();
            } else {
                header('Location: login.php');
                exit();
            }
        }
    }
}

// Auto logout injection for HTML pages (non-AJAX)
$isAjax = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

if (
    !$isAjax &&
    isset($_SERVER['SCRIPT_NAME']) &&
    stripos(basename($_SERVER['SCRIPT_NAME']), 'ajax') === false
) {
    $inject_script = '<script src="auto_logout.js"></script>';

    if (!headers_sent()) {
        ob_start(function ($buffer) use ($inject_script) {
            if (stripos($buffer, '<html') === false) {
                return $buffer;
            }
            $pos = stripos($buffer, '</body>');
            if ($pos !== false) {
                return substr_replace($buffer, $inject_script . '</body>', $pos, 7);
            }
            return $buffer;
        });
    }
}

// Database Connection Credentials
$hostname     = getenv('DB_HOST') ?: "localhost";
$username     = getenv('DB_USER') ?: "root";
$password     = getenv('DB_PASS') ?: "";
$databaseName = getenv('DB_NAME') ?: "fablinedb";

// Connect to MySQL database
$db = mysqli_connect($hostname, $username, $password, $databaseName);

// Check database connection
if (!$db) {
    $error = mysqli_connect_error();
    error_log("Database connection failed: " . $error);
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }
    
    die("Server Error: Unable to connect to database. Please verify your credentials in config.php.");
}

// Set charset to utf8mb4 for unicode compatibility
mysqli_set_charset($db, 'utf8mb4');
