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

// Get user's vehicles
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$vehicles = $stmt->fetchAll();

// Get active services
$services = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY service_name")->fetchAll();

// Handle booking
if(isset($_POST['book_service'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $service_id = $_POST['service_id'];
    $appointment_datetime = $_POST['appointment_datetime'];
    $notes = $_POST['notes'] ?? '';
    
    // Split datetime
    $datetime = new DateTime($appointment_datetime);
    $appointment_date = $datetime->format('Y-m-d');
    $appointment_time = $datetime->format('H:i:s');
    
    // Generate booking number
    $booking_number = 'BK-' . date('Ymd') . '-' . rand(1000, 9999);
    
    // Get service details
    $stmt = $pdo->prepare("SELECT service_name, price, duration FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    $amount = $service['price'];
    $service_name = $service['service_name'];
    
    // Get vehicle details
    $stmt = $pdo->prepare("SELECT brand, model, license_plate FROM vehicles WHERE id = ?");
    $stmt->execute([$vehicle_id]);
    $vehicle = $stmt->fetch();
    $vehicle_name = $vehicle['brand'] . ' ' . $vehicle['model'];
    
    try {
        // Insert booking
        $stmt = $pdo->prepare("INSERT INTO bookings (
            booking_number, user_id, vehicle_id, service_id, 
            appointment_date, appointment_time, notes, status, 
            estimated_cost, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
        
        $stmt->execute([
            $booking_number, $user_id, $vehicle_id, $service_id,
            $appointment_date, $appointment_time, $notes, $amount
        ]);
        
        $booking_id = $pdo->lastInsertId();
        
        // ========== CREATE NOTIFICATION FOR BOOKING ==========
        $title = "New Booking Confirmed";
        $message = "Your booking #$booking_number for $service_name on $vehicle_name has been confirmed. We'll notify you when a mechanic is assigned.";
        $type = "booking";
        
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $notif_stmt->execute([$user_id, $title, $message, $type]);
        
        // Also create a notification for admins (optional)
        $admin_stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        $admin_stmt->execute();
        $admins = $admin_stmt->fetchAll();
        
        $admin_title = "New Booking Received";
        $admin_message = "User has booked a new service: $service_name for $vehicle_name. Booking #$booking_number";
        
        foreach($admins as $admin) {
            $admin_notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'booking', 0, NOW())");
            $admin_notif_stmt->execute([$admin['id'], $admin_title, $admin_message]);
        }
        
        $message = "Booking created successfully! Your booking number is: " . $booking_number;
        
    } catch (Exception $e) {
        $error = "Booking failed: " . $e->getMessage();
    }
}

$message = $_GET['msg'] ?? '';
$error = $error ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Book Service - CASMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        .header h1 i {
            color: #667eea;
            margin-right: 10px;
        }
        .back-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .back-btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        .back-btn i { margin-right: 5px; }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group label i {
            color: #667eea;
            margin-right: 8px;
        }
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        .service-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        .service-info.active { display: block; }
        .service-detail {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        .service-price {
            font-size: 24px;
            font-weight: 700;
            color: #28a745;
        }
        .service-duration {
            color: #666;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover { 
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        .btn i { margin-right: 8px; }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .amount-display {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #667eea;
        }
        @media (max-width: 600px) {
            .header { flex-direction: column; gap: 15px; text-align: center; }
            .card { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-plus"></i> Book Service</h1>
            <a href="user_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if($message): ?>
            <div class="message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <?php if(empty($vehicles)): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-car"></i>
                    <h3>No Vehicles Added</h3>
                    <p>You need to add a vehicle before booking a service.</p>
                    <a href="user_dashboard.php?page=vehicles" class="btn" style="display: inline-block; width: auto; padding: 10px 30px; margin-top: 15px; text-decoration: none;">
                        <i class="fas fa-plus"></i> Add Vehicle
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <form method="POST" id="bookingForm">
                    <div class="form-group">
                        <label><i class="fas fa-car"></i> Select Vehicle</label>
                        <select name="vehicle_id" required>
                            <option value="">-- Choose your vehicle --</option>
                            <?php foreach($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['id']; ?>">
                                <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' - ' . $vehicle['license_plate']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tools"></i> Select Service</label>
                        <select name="service_id" id="serviceSelect" required onchange="showServiceInfo()">
                            <option value="">-- Choose a service --</option>
                            <?php foreach($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>" 
                                    data-price="<?php echo $service['price']; ?>"
                                    data-duration="<?php echo $service['duration']; ?>"
                                    data-name="<?php echo htmlspecialchars($service['service_name']); ?>">
                                <?php echo htmlspecialchars($service['service_name']); ?> - Ksh <?php echo number_format($service['price']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="serviceInfo" class="service-info">
                        <h4 style="margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Service Details</h4>
                        <div class="service-detail">
                            <span class="service-price" id="selectedPrice">Ksh 0</span>
                            <span class="service-duration" id="selectedDuration">
                                <i class="fas fa-clock"></i> <span id="duration">0</span> mins
                            </span>
                        </div>
                    </div>

                    <div class="amount-display" id="amountDisplay">
                        <i class="fas fa-coins"></i> Estimated Cost: Ksh 0
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Appointment Date & Time</label>
                        <input type="datetime-local" name="appointment_datetime" id="appointment_datetime" required 
                               min="<?php echo date('Y-m-d\TH:i', strtotime('+1 day')); ?>">
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> Please select a date and time for your service
                        </small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Additional Notes (Optional)</label>
                        <textarea name="notes" rows="4" placeholder="Any special requests or additional information..."></textarea>
                    </div>

                    <button type="submit" name="book_service" class="btn" id="submitBtn">
                        <i class="fas fa-calendar-check"></i> Confirm Booking
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function showServiceInfo() {
            const select = document.getElementById('serviceSelect');
            const option = select.options[select.selectedIndex];
            const serviceInfo = document.getElementById('serviceInfo');
            const amountDisplay = document.getElementById('amountDisplay');
            
            if(option.value) {
                const price = option.dataset.price;
                const duration = option.dataset.duration;
                
                document.getElementById('selectedPrice').textContent = 'Ksh ' + Number(price).toLocaleString();
                document.getElementById('duration').textContent = duration;
                amountDisplay.innerHTML = '<i class="fas fa-coins"></i> Estimated Cost: Ksh ' + Number(price).toLocaleString();
                serviceInfo.classList.add('active');
            } else {
                serviceInfo.classList.remove('active');
                amountDisplay.innerHTML = '<i class="fas fa-coins"></i> Estimated Cost: Ksh 0';
            }
        }

        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const year = tomorrow.getFullYear();
        const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const day = String(tomorrow.getDate()).padStart(2, '0');
        const hours = '09';
        const minutes = '00';
        document.getElementById('appointment_datetime').min = `${year}-${month}-${day}T${hours}:${minutes}`;
        
        // Set default value to tomorrow 9 AM
        document.getElementById('appointment_datetime').value = `${year}-${month}-${day}T${hours}:${minutes}`;

        // Prevent double submission
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    </script>
</body>
</html>