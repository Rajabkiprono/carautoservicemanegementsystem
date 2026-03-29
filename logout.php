<?php
session_start();

// Log activity if user was logged in
if(isset($_SESSION['user_id'])) {
    require_once 'config/Database.php';
    require_once 'config/RBAC.php';
    
    $database = new Database();
    $db = $database->connect();
    $rbac = new RBAC($db, $_SESSION['user_id']);
    $rbac->logActivity('logout', 'User logged out');
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: simple_login.php");
exit();
?>