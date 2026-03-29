<?php
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: simple_login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=casms", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$message = '';
$error = '';

// Handle Add User
if(isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $password, $role]);
        $message = "User added successfully!";
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Add Vehicle
if(isset($_POST['add_vehicle'])) {
    $user_id = $_POST['user_id'];
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $year = $_POST['year'];
    $license_plate = $_POST['license_plate'];
    $color = $_POST['color'];
    $fuel_type = $_POST['fuel_type'];
    $transmission = $_POST['transmission'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, brand, model, year, license_plate, color, fuel_type, transmission, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$user_id, $brand, $model, $year, $license_plate, $color, $fuel_type, $transmission]);
        $message = "Vehicle added successfully!";
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Add Booking
if(isset($_POST['add_booking'])) {
    $user_id = $_POST['user_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $service_type = $_POST['service_type'];
    $booking_date = $_POST['booking_date'];
    $notes = $_POST['notes'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO bookings (user_id, vehicle_id, service_type, booking_date, notes, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $vehicle_id, $service_type, $booking_date, $notes]);
        $message = "Booking added successfully!";
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all users for dropdowns
$users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll();

// Get all vehicles for dropdown
$vehicles = $pdo->query("SELECT v.*, u.name as owner_name FROM vehicles v LEFT JOIN users u ON v.user_id = u.id ORDER BY v.id DESC")->fetchAll();

// Get all bookings
$bookings = $pdo->query("
    SELECT b.*, u.name as user_name, v.brand, v.model, v.license_plate 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    LEFT JOIN vehicles v ON b.vehicle_id = v.id 
    ORDER BY b.id DESC
")->fetchAll();

// Get stats
$total_users = count($users);
$total_vehicles = count($vehicles);
$total_bookings = count($bookings);

// Get user counts by role for pie chart
$admin_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$mechanic_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'mechanic'")->fetchColumn();
$finance_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'finance'")->fetchColumn();
$user_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// Get monthly vehicle and booking data for bar chart (last 6 months)
$monthly_data = $pdo->query("
    SELECT 
        DATE_FORMAT(date_table.month_date, '%b') as month,
        COALESCE(vehicles.count, 0) as vehicle_count,
        COALESCE(bookings.count, 0) as booking_count
    FROM (
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH), '%Y-%m-01') as month_date UNION
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 4 MONTH), '%Y-%m-01') UNION
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 MONTH), '%Y-%m-01') UNION
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 2 MONTH), '%Y-%m-01') UNION
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') UNION
        SELECT DATE_FORMAT(NOW(), '%Y-%m-01')
    ) date_table
    LEFT JOIN (
        SELECT DATE_FORMAT(created_at, '%Y-%m-01') as month, COUNT(*) as count 
        FROM vehicles WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
    ) vehicles ON date_table.month_date = vehicles.month
    LEFT JOIN (
        SELECT DATE_FORMAT(created_at, '%Y-%m-01') as month, COUNT(*) as count 
        FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
    ) bookings ON date_table.month_date = bookings.month
    ORDER BY date_table.month_date
")->fetchAll();

$months = [];
$vehicle_counts = [];
$booking_counts = [];

foreach($monthly_data as $data) {
    $months[] = $data['month'];
    $vehicle_counts[] = $data['vehicle_count'];
    $booking_counts[] = $data['booking_count'];
}

// Get admin name for welcome message
$admin_name = $_SESSION['user_name'] ?? 'Admin';
$admin_email = $_SESSION['user_email'] ?? 'admin@casms.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASMS Admin Portal | Professional Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f8;
            overflow-x: hidden;
        }

        /* Dashboard Layout */
        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* ========== PROFESSIONAL LIGHT SIDEBAR ========== */
        .sidebar {
            width: 280px;
            background: #ffffff;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #e2e8f0;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        /* Sidebar Header */
        .sidebar-header {
            padding: 28px 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            box-shadow: 0 8px 16px -4px rgba(37, 99, 235, 0.2);
        }

        .logo-text h3 {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        .logo-text p {
            font-size: 10px;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* Navigation Menu */
        .nav-menu {
            padding: 16px 16px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            padding: 12px 18px 8px;
        }

        .nav-item {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 18px;
            color: #475569;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.25s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .nav-link i {
            width: 22px;
            font-size: 18px;
            color: #94a3b8;
            transition: all 0.25s ease;
        }

        .nav-link .nav-badge {
            margin-left: auto;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
        }

        .nav-link:hover {
            background: #f8fafc;
            color: #2563eb;
        }

        .nav-link:hover i {
            color: #2563eb;
        }

        .nav-item.active .nav-link {
            background: linear-gradient(90deg, #eff6ff, transparent);
            color: #2563eb;
            border-left: 3px solid #2563eb;
        }

        .nav-item.active .nav-link i {
            color: #2563eb;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 28px 36px;
            transition: all 0.3s ease;
        }

        /* Top Bar with Profile Dropdown */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            background: white;
            padding: 16px 28px;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
        }

        .page-info h1 {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.3px;
        }

        .page-info p {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .clock {
            background: #f8fafc;
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }

        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 16px 8px 12px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .profile-btn:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .profile-avatar {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .profile-info {
            text-align: left;
        }

        .profile-name {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .profile-role {
            font-size: 10px;
            color: #64748b;
        }

        .profile-btn i {
            color: #94a3b8;
            font-size: 12px;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 12px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.15);
            border: 1px solid #e2e8f0;
            width: 260px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.25s ease;
            z-index: 100;
        }

        .profile-dropdown.active .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dropdown-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }

        .dropdown-info h4 {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .dropdown-info p {
            font-size: 11px;
            color: #64748b;
        }

        .dropdown-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 8px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .dropdown-item i {
            width: 20px;
            font-size: 14px;
            color: #94a3b8;
        }

        .dropdown-item:hover {
            background: #f8fafc;
            color: #2563eb;
        }

        .dropdown-item:hover i {
            color: #2563eb;
        }

        .dropdown-item.text-danger {
            color: #ef4444;
        }

        .dropdown-item.text-danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.25s ease;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.1);
        }

        .stat-left h3 {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .stat-left p {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-top: 6px;
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .chart-header i {
            font-size: 20px;
            color: #2563eb;
        }

        .chart-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .chart-container {
            height: 260px;
            position: relative;
        }

        /* Quick Add Section */
        .quick-add {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 28px;
            border: 1px solid #e2e8f0;
        }

        .quick-add-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .quick-add-header i {
            font-size: 22px;
            color: #2563eb;
        }

        .quick-add-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .forms-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .form-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.25s ease;
            border: 1px solid #e2e8f0;
        }

        .form-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.08);
        }

        .form-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-group {
            margin-bottom: 12px;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            transition: all 0.25s ease;
            background: white;
        }

        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .btn-primary { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn:hover { transform: translateY(-1px); filter: brightness(1.05); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }

        /* Data Tables */
        .data-section {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e2e8f0;
        }

        .section-header {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 14px 12px;
            background: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #334155;
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .role-admin { background: #fee2e2; color: #dc2626; }
        .role-user { background: #dbeafe; color: #2563eb; }
        .role-mechanic { background: #ffedd5; color: #ea580c; }
        .role-finance { background: #d1fae5; color: #059669; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-completed { background: #d1fae5; color: #059669; }

        .alert {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }

        .alert-success { background: #ecfdf5; color: #047857; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #b91c1c; border-left: 4px solid #ef4444; }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-content { margin-left: 280px; padding: 20px; }
            .forms-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .charts-row { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .main-content { margin-left: 0; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            .top-bar { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- PROFESSIONAL LIGHT SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-car-side"></i>
                </div>
                <div class="logo-text">
                    <h3>CASMS</h3>
                    <p>Auto Management System</p>
                </div>
            </div>
        </div>

        <div class="nav-menu">
            <div class="nav-item active" data-nav="dashboard">
                <a href="#" class="nav-link" onclick="showDashboardView(); return false;">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-section-title">MANAGEMENT</div>
            
            <div class="nav-item" data-nav="users">
                <a href="#" class="nav-link" onclick="showUsersView(); return false;">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    <span class="nav-badge"><?php echo $total_users; ?></span>
                </a>
            </div>
            <div class="nav-item" data-nav="vehicles">
                <a href="#" class="nav-link" onclick="showVehiclesView(); return false;">
                    <i class="fas fa-car"></i>
                    <span>Vehicles</span>
                    <span class="nav-badge"><?php echo $total_vehicles; ?></span>
                </a>
            </div>
            <div class="nav-item" data-nav="bookings">
                <a href="#" class="nav-link" onclick="showBookingsView(); return false;">
                    <i class="fas fa-calendar-check"></i>
                    <span>Bookings</span>
                    <span class="nav-badge"><?php echo $total_bookings; ?></span>
                </a>
            </div>
            
            <div class="nav-section-title">SERVICES</div>
            
            <div class="nav-item">
                <a href="services.php" class="nav-link">
                    <i class="fas fa-wrench"></i>
                    <span>Services</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="jobs.php" class="nav-link">
                    <i class="fas fa-briefcase"></i>
                    <span>Jobs</span>
                </a>
            </div>
            
            <div class="nav-section-title">SUPPORT</div>
            
            <div class="nav-item">
                <a href="emergency.php" class="nav-link">
                    <i class="fas fa-phone-alt"></i>
                    <span>Emergency</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="invoices.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-info">
                <h1 id="pageTitle">Dashboard</h1>
                <p id="pageSubtitle">Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Here's your platform overview</p>
            </div>
            <div class="header-actions">
                <div class="clock">
                    <i class="far fa-clock"></i>
                    <span id="realtimeClock">--:--:--</span>
                </div>
                
                <!-- Profile Dropdown -->
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-btn" onclick="toggleDropdown()">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                        </div>
                        <div class="profile-info">
                            <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
                            <div class="profile-role">Administrator</div>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                            </div>
                            <div class="dropdown-info">
                                <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                                <p><?php echo htmlspecialchars($admin_email); ?></p>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <a href="notifications.php" class="dropdown-item">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                            <span style="margin-left: auto; background: #ef4444; color: white; padding: 2px 8px; border-radius: 20px; font-size: 10px;">3</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="simple_logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- DASHBOARD VIEW -->
        <div id="dashboardView">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-left">
                        <h3><?php echo $total_users; ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-left">
                        <h3><?php echo $total_vehicles; ?></h3>
                        <p>Total Vehicles</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-car"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-left">
                        <h3><?php echo $total_bookings; ?></h3>
                        <p>Total Bookings</p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-header">
                        <i class="fas fa-chart-pie"></i>
                        <h3>User Role Distribution</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="rolePieChart"></canvas>
                    </div>
                    <div style="display: flex; justify-content: center; gap: 20px; margin-top: 16px; flex-wrap: wrap;">
                        <span style="font-size: 12px;"><span style="background:#dc2626; display:inline-block; width:10px; height:10px; border-radius:50%;"></span> Admin (<?php echo $admin_count; ?>)</span>
                        <span style="font-size: 12px;"><span style="background:#ea580c; display:inline-block; width:10px; height:10px; border-radius:50%;"></span> Mechanic (<?php echo $mechanic_count; ?>)</span>
                        <span style="font-size: 12px;"><span style="background:#059669; display:inline-block; width:10px; height:10px; border-radius:50%;"></span> Finance (<?php echo $finance_count; ?>)</span>
                        <span style="font-size: 12px;"><span style="background:#2563eb; display:inline-block; width:10px; height:10px; border-radius:50%;"></span> User (<?php echo $user_count; ?>)</span>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <i class="fas fa-chart-line"></i>
                        <h3>Vehicles vs Bookings (6 Months Trend)</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="vehiclesBookingsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="quick-add">
                <div class="quick-add-header">
                    <i class="fas fa-bolt"></i>
                    <h3>Quick Actions</h3>
                </div>
                <div class="forms-grid">
                    <div class="form-card">
                        <div class="form-title"><i class="fas fa-user-plus"></i> Add New User</div>
                        <form method="POST">
                            <div class="input-group"><input type="text" name="name" placeholder="Full Name" required></div>
                            <div class="input-group"><input type="email" name="email" placeholder="Email Address" required></div>
                            <div class="input-group"><input type="text" name="phone" placeholder="Phone Number" required></div>
                            <div class="input-group"><input type="password" name="password" placeholder="Password" required></div>
                            <div class="input-group"><select name="role"><option value="user">User</option><option value="admin">Admin</option><option value="mechanic">Mechanic</option><option value="finance">Finance</option></select></div>
                            <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-save"></i> Create User</button>
                        </form>
                    </div>
                    <div class="form-card">
                        <div class="form-title"><i class="fas fa-car"></i> Register Vehicle</div>
                        <form method="POST">
                            <div class="input-group"><select name="user_id" required><option value="">Select Owner</option><?php foreach($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select></div>
                            <div class="input-group"><input type="text" name="brand" placeholder="Brand" required></div>
                            <div class="input-group"><input type="text" name="model" placeholder="Model" required></div>
                            <div class="input-group"><input type="number" name="year" placeholder="Year" required></div>
                            <div class="input-group"><input type="text" name="license_plate" placeholder="License Plate" required></div>
                            <div class="input-group"><input type="text" name="color" placeholder="Color"></div>
                            <button type="submit" name="add_vehicle" class="btn btn-success"><i class="fas fa-save"></i> Add Vehicle</button>
                        </form>
                    </div>
                    <div class="form-card">
                        <div class="form-title"><i class="fas fa-calendar-plus"></i> Create Booking</div>
                        <form method="POST">
                            <div class="input-group"><select name="user_id" id="user_select" required onchange="loadUserVehicles(this.value)"><option value="">Select User</option><?php foreach($users as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?></select></div>
                            <div class="input-group"><select name="vehicle_id" id="vehicle_select" required><option value="">Select User First</option></select></div>
                            <div class="input-group"><input type="text" name="service_type" placeholder="Service Type" required></div>
                            <div class="input-group"><input type="datetime-local" name="booking_date" required></div>
                            <div class="input-group"><textarea name="notes" rows="2" placeholder="Additional Notes"></textarea></div>
                            <button type="submit" name="add_booking" class="btn btn-warning"><i class="fas fa-save"></i> Book Service</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- USERS VIEW -->
        <div id="usersView" class="data-section" style="display: none;">
            <div class="section-header"><i class="fas fa-users" style="color:#2563eb;"></i> User Management</div>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Joined</th></tr></thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td>#<?php echo str_pad($user['id'],4,'0',STR_PAD_LEFT); ?></td>
                            <td><i class="fas fa-user-circle" style="color:#2563eb; margin-right:8px;"></i><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                            <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- VEHICLES VIEW -->
        <div id="vehiclesView" class="data-section" style="display: none;">
            <div class="section-header"><i class="fas fa-car" style="color:#2563eb;"></i> Vehicle Fleet</div>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>ID</th><th>Owner</th><th>Vehicle</th><th>Year</th><th>License Plate</th><th>Color</th></tr></thead>
                    <tbody>
                        <?php foreach($vehicles as $v): ?>
                        <tr>
                            <td>#<?php echo str_pad($v['id'],4,'0',STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($v['owner_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($v['brand'] . ' ' . $v['model']); ?></td>
                            <td><?php echo $v['year']; ?></td>
                            <td><code><?php echo htmlspecialchars($v['license_plate']); ?></code></td>
                            <td><?php echo htmlspecialchars($v['color'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BOOKINGS VIEW -->
        <div id="bookingsView" class="data-section" style="display: none;">
            <div class="section-header"><i class="fas fa-calendar-check" style="color:#2563eb;"></i> Service Bookings</div>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>ID</th><th>User</th><th>Vehicle</th><th>Service</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($bookings as $b): ?>
                        <tr>
                            <td>#<?php echo str_pad($b['id'],4,'0',STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($b['user_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(($b['brand']??'').' '.($b['model']??'')); ?></td>
                            <td><?php echo htmlspecialchars($b['service_type'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($b['booking_date'] ?? $b['created_at'])); ?></td>
                            <td><span class="status-badge status-<?php echo $b['status'] ?? 'pending'; ?>"><?php echo ucfirst($b['status'] ?? 'pending'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Real-time clock
    function updateClock() {
        const now = new Date();
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        document.getElementById('realtimeClock').innerHTML = now.toLocaleString('en-US', options);
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Profile Dropdown Toggle
    function toggleDropdown() {
        document.getElementById('profileDropdown').classList.toggle('active');
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('profileDropdown');
        const btn = dropdown.querySelector('.profile-btn');
        if (!dropdown.contains(event.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Navigation functions
    function showDashboardView() {
        document.getElementById('dashboardView').style.display = 'block';
        document.getElementById('usersView').style.display = 'none';
        document.getElementById('vehiclesView').style.display = 'none';
        document.getElementById('bookingsView').style.display = 'none';
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.querySelector('.nav-item[data-nav="dashboard"]').classList.add('active');
        document.getElementById('pageTitle').innerText = 'Dashboard';
        document.getElementById('pageSubtitle').innerHTML = 'Welcome back, <?php echo htmlspecialchars($admin_name); ?>! Here\'s your platform overview';
    }
    function showUsersView() {
        document.getElementById('dashboardView').style.display = 'none';
        document.getElementById('usersView').style.display = 'block';
        document.getElementById('vehiclesView').style.display = 'none';
        document.getElementById('bookingsView').style.display = 'none';
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.querySelector('.nav-item[data-nav="users"]').classList.add('active');
        document.getElementById('pageTitle').innerText = 'User Management';
        document.getElementById('pageSubtitle').innerHTML = 'Manage all registered users and their roles';
    }
    function showVehiclesView() {
        document.getElementById('dashboardView').style.display = 'none';
        document.getElementById('usersView').style.display = 'none';
        document.getElementById('vehiclesView').style.display = 'block';
        document.getElementById('bookingsView').style.display = 'none';
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.querySelector('.nav-item[data-nav="vehicles"]').classList.add('active');
        document.getElementById('pageTitle').innerText = 'Vehicle Fleet';
        document.getElementById('pageSubtitle').innerHTML = 'Track and manage all registered vehicles';
    }
    function showBookingsView() {
        document.getElementById('dashboardView').style.display = 'none';
        document.getElementById('usersView').style.display = 'none';
        document.getElementById('vehiclesView').style.display = 'none';
        document.getElementById('bookingsView').style.display = 'block';
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.querySelector('.nav-item[data-nav="bookings"]').classList.add('active');
        document.getElementById('pageTitle').innerText = 'Service Bookings';
        document.getElementById('pageSubtitle').innerHTML = 'View and manage all service appointments';
    }

    // Load vehicles for booking form
    function loadUserVehicles(userId) {
        if(!userId) return;
        var vehiclesByUser = <?php 
            $map = [];
            foreach($vehicles as $v) { $map[$v['user_id']][] = $v; }
            echo json_encode($map); 
        ?>;
        var select = document.getElementById('vehicle_select');
        if(vehiclesByUser[userId] && vehiclesByUser[userId].length > 0) {
            var options = '<option value="">-- Select Vehicle --</option>';
            vehiclesByUser[userId].forEach(function(v) {
                options += '<option value="' + v.id + '">' + v.brand + ' ' + v.model + ' - ' + v.license_plate + '</option>';
            });
            select.innerHTML = options;
        } else {
            select.innerHTML = '<option value="">No vehicles for this user</option>';
        }
    }

    // Initialize Charts
    document.addEventListener('DOMContentLoaded', function() {
        new Chart(document.getElementById('rolePieChart'), {
            type: 'pie',
            data: { labels: ['Admin','Mechanic','Finance','User'], datasets: [{ data: [<?php echo $admin_count; ?>,<?php echo $mechanic_count; ?>,<?php echo $finance_count; ?>,<?php echo $user_count; ?>], backgroundColor: ['#dc2626','#ea580c','#059669','#2563eb'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        new Chart(document.getElementById('vehiclesBookingsChart'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($months); ?>, datasets: [{ label: 'Vehicles Added', data: <?php echo json_encode($vehicle_counts); ?>, backgroundColor: '#10b981', borderRadius: 8 },{ label: 'Bookings Created', data: <?php echo json_encode($booking_counts); ?>, backgroundColor: '#f59e0b', borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    });

    // Auto-hide messages after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(el => el.style.display = 'none');
    }, 5000);
</script>
</body>
</html>