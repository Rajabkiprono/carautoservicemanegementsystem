<?php
session_start();

if(!isset($_SESSION['user_id'])) {
    header("Location: simple_login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=casms", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

if(isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? '';
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Check if email already exists for another user
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $user_id]);
    if($check->fetch()) {
        header("Location: user_dashboard.php?page=profile&error=Email already exists");
        exit();
    }
    
    // Update basic info
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->execute([$name, $email, $phone, $user_id]);
    
    // Update password if provided
    if(!empty($new_password)) {
        if($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
        } else {
            header("Location: user_dashboard.php?page=profile&error=Passwords do not match");
            exit();
        }
    }
    
    // Update session name
    $_SESSION['user_name'] = $name;
    
    header("Location: user_dashboard.php?msg=Profile updated successfully! ✅");
    exit();
}
?>