<?php
require_once 'config/auth.php';
requireLogin();

// If user already has vehicles, redirect to dashboard
require_once 'config/Database.php';
$database = new Database();
$db = $database->connect();

$stmt = $db->prepare("SELECT COUNT(*) as count FROM vehicles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hasVehicles = $stmt->fetch()['count'] > 0;

if($hasVehicles) {
    redirectToDashboard();
    exit();
}

$error = '';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = intval($_POST['year'] ?? 0);
    $license_plate = strtoupper(trim($_POST['license_plate'] ?? ''));
    $color = trim($_POST['color'] ?? '');

    if(empty($brand) || empty($model) || empty($year) || empty($license_plate)) {
        $error = "Please fill in all required fields";
    } else {
        try {
            $insertStmt = $db->prepare("
                INSERT INTO vehicles (user_id, brand, model, year, license_plate, color) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$_SESSION['user_id'], $brand, $model, $year, $license_plate, $color]);
            
            // Redirect to user dashboard
            header("Location: /casmsystem/user/dashboard.php");
            exit();
        } catch (PDOException $e) {
            $error = "Failed to add vehicle. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Your First Vehicle</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #1877f2; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; }
        .error { color: red; margin-bottom: 15px; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to CASMS! 👋</h1>
        
        <div class="info">
            <strong>First time here?</strong>
            <p>Add your first vehicle to get started with our services.</p>
        </div>

        <?php if($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Brand *</label>
                <input type="text" name="brand" required placeholder="e.g., Toyota">
            </div>
            
            <div class="form-group">
                <label>Model *</label>
                <input type="text" name="model" required placeholder="e.g., Camry">
            </div>
            
            <div class="form-group">
                <label>Year *</label>
                <input type="number" name="year" required min="1900" max="<?php echo date('Y') + 1; ?>">
            </div>
            
            <div class="form-group">
                <label>License Plate *</label>
                <input type="text" name="license_plate" required style="text-transform: uppercase;" placeholder="e.g., KAA 123A">
            </div>
            
            <div class="form-group">
                <label>Color (Optional)</label>
                <input type="text" name="color" placeholder="e.g., Red">
            </div>
            
            <button type="submit">Add Vehicle & Continue →</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="user/dashboard.php" style="color: #666;">Skip for now →</a>
        </p>
    </div>
</body>
</html>