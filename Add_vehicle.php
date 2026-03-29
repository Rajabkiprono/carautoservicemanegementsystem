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

$error = '';

if(isset($_POST['add_vehicle'])) {
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $year = $_POST['year'];
    $license = strtoupper(trim($_POST['license_plate']));
    $color = trim($_POST['color'] ?? '');
    $fuel_type = $_POST['fuel_type'] ?? 'Petrol';
    $transmission = $_POST['transmission'] ?? 'Manual';
    
    // Basic validation
    if(empty($brand) || empty($model) || empty($year) || empty($license)) {
        $error = "All fields marked with * are required";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, brand, model, year, license_plate, color, fuel_type, transmission) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            if($stmt->execute([$_SESSION['user_id'], $brand, $model, $year, $license, $color, $fuel_type, $transmission])) {
                // Redirect directly without any notification
                header("Location: user_dashboard.php");
                exit();
            } else {
                $error = "Failed to add vehicle. Please try again.";
            }
        } catch(PDOException $e) {
            if(strpos($e->getMessage(), 'Duplicate entry')) {
                $error = "A vehicle with this license plate already exists!";
            } else {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get common vehicle brands
$common_brands = ['Toyota', 'Honda', 'Nissan', 'Mitsubishi', 'Subaru', 'Mazda', 'Suzuki', 'Volkswagen', 'BMW', 'Mercedes', 'Audi', 'Ford', 'Hyundai', 'Kia'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Vehicle - CASMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container { width: 100%; max-width: 600px; }
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h2 { 
            text-align: center; 
            color: #333; 
            margin-bottom: 30px;
            font-size: 28px;
        }
        h2 i { color: #667eea; margin-right: 10px; }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .form-group { margin-bottom: 20px; }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }
        
        label i { color: #667eea; margin-right: 8px; }
        
        .required::after {
            content: '*';
            color: #dc3545;
            margin-left: 4px;
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }
        
        .btn i { margin-right: 8px; }
        
        .skip {
            text-align: center;
            margin-top: 20px;
        }
        
        .skip a {
            color: #666;
            text-decoration: none;
        }
        
        .skip a:hover { color: #667eea; }
        
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid #2196f3;
        }
        
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 480px) {
            .card { padding: 25px; }
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2><i class="fas fa-car"></i> Add Your Vehicle</h2>
            
            <div class="info">
                <i class="fas fa-info-circle"></i> 
                <strong>Quick Tip:</strong> Fill in your vehicle details to start booking services.
            </div>
            
            <?php if($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="required"><i class="fas fa-tag"></i> Brand</label>
                    <input type="text" name="brand" placeholder="e.g., Toyota" required 
                           value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="required"><i class="fas fa-car-side"></i> Model</label>
                    <input type="text" name="model" placeholder="e.g., Camry" required 
                           value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>">
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label class="required"><i class="fas fa-calendar"></i> Year</label>
                        <input type="number" name="year" placeholder="2020" required 
                               min="1900" max="<?php echo date('Y'); ?>"
                               value="<?php echo htmlspecialchars($_POST['year'] ?? date('Y')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="required"><i class="fas fa-qrcode"></i> License Plate</label>
                        <input type="text" name="license_plate" placeholder="KAA123A" required 
                               value="<?php echo htmlspecialchars($_POST['license_plate'] ?? ''); ?>"
                               style="text-transform: uppercase;">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label><i class="fas fa-palette"></i> Color</label>
                        <input type="text" name="color" placeholder="e.g., Red" 
                               value="<?php echo htmlspecialchars($_POST['color'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-gas-pump"></i> Fuel Type</label>
                        <select name="fuel_type">
                            <option value="Petrol">Petrol</option>
                            <option value="Diesel">Diesel</option>
                            <option value="Electric">Electric</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-cog"></i> Transmission</label>
                    <select name="transmission">
                        <option value="Manual">Manual</option>
                        <option value="Automatic">Automatic</option>
                        <option value="CVT">CVT</option>
                    </select>
                </div>
                
                <button type="submit" name="add_vehicle" class="btn">
                    <i class="fas fa-plus-circle"></i> Add Vehicle
                </button>
            </form>
            
            <div class="skip">
                <a href="user_dashboard.php"><i class="fas fa-arrow-right"></i> Skip for now</a>
            </div>
        </div>
    </div>
</body>
</html>