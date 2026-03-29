<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=casms", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['unread_count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetchColumn();

echo json_encode(['unread_count' => $unread_count]);
?>