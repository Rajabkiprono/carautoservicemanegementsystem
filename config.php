<?php
// config/config.php - Master configuration file

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (turn off in production)
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'casmss');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'CASMS');
define('APP_VERSION', '2.0.0');
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/casmsystem');
define('APP_ENV', 'development'); // development, staging, production

// Security keys
define('SECRET_KEY', 'your-secret-key-here-change-this');
define('CSRF_KEY', 'csrf_token');

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Include required files
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/RBAC.php';
require_once __DIR__ . '/functions.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->connect();
} catch(Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Initialize auth if user is logged in
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/Auth.php';
    $auth = new Auth($db);
}
?>