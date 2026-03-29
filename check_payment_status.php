<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=casms", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error']);
    exit();
}

$booking_number = $_GET['booking'] ?? '';

if (!$booking_number) {
    echo json_encode(['error' => 'No booking number']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT b.payment_status, m.status as mpesa_status 
    FROM bookings b 
    LEFT JOIN mpesa_transactions m ON b.id = m.booking_id 
    WHERE b.booking_number = ?
    ORDER BY m.created_at DESC LIMIT 1
");
$stmt->execute([$booking_number]);
$booking = $stmt->fetch();

if ($booking) {
    if ($booking['payment_status'] == 'paid' || $booking['mpesa_status'] == 'Completed') {
        echo json_encode(['status' => 'completed']);
    } elseif ($booking['payment_status'] == 'failed' || $booking['mpesa_status'] == 'Failed') {
        echo json_encode(['status' => 'failed']);
    } else {
        echo json_encode(['status' => 'pending']);
    }
} else {
    echo json_encode(['status' => 'not_found']);
}
?>