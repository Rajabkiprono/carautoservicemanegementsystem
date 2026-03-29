<?php
// Save as hash_generator.php and run once

$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br><br>";

echo "Copy this SQL: <br><br>";

echo "TRUNCATE TABLE users;<br><br>";

echo "INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `is_active`, `created_at`) VALUES<br>";
echo "(1, 'System Administrator', 'admin@casms.com', '" . $hash . "', '0712345678', 'admin', 1, NOW()),<br>";
echo "(2, 'Finance Manager', 'finance@casms.com', '" . $hash . "', '0723456789', 'finance', 1, NOW()),<br>";
echo "(3, 'Senior Mechanic', 'mechanic@casms.com', '" . $hash . "', '0734567890', 'mechanic', 1, NOW()),<br>";
echo "(4, 'John Doe', 'john@example.com', '" . $hash . "', '0745678901', 'user', 1, NOW()),<br>";
echo "(5, 'Jane Smith', 'jane@example.com', '" . $hash . "', '0756789012', 'user', 1, NOW()),<br>";
echo "(6, 'Bob Johnson', 'bob@example.com', '" . $hash . "', '0767890123', 'user', 1, NOW());";
?>