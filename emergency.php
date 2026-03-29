<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: simple_login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=casms", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user's vehicles
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$vehicles = $stmt->fetchAll();

// Get emergency bookings - FIXED: Ensure all columns are selected
$stmt = $pdo->prepare("
    SELECT eb.*, v.brand, v.model, v.license_plate 
    FROM emergency_bookings eb 
    LEFT JOIN vehicles v ON eb.vehicle_id = v.id 
    WHERE eb.user_id = ? 
    ORDER BY eb.created_at DESC
");
$stmt->execute([$user_id]);
$emergency_bookings = $stmt->fetchAll();

// Handle emergency booking
if(isset($_POST['emergency_booking'])) {
    $vehicle_id = $_POST['vehicle_id'];
    $emergency_type = $_POST['emergency_type'];
    $description = $_POST['description'];
    $location_url = $_POST['location_url'] ?? '';
    $photo_path = '';
    
    // Handle photo upload
    if(isset($_FILES['emergency_photo']) && $_FILES['emergency_photo']['error'] == 0) {
        $target_dir = "uploads/emergency/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['emergency_photo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $file_name;
        
        if(move_uploaded_file($_FILES['emergency_photo']['tmp_name'], $target_file)) {
            $photo_path = $target_file;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO emergency_bookings (user_id, vehicle_id, emergency_type, description, location_url, photo_path, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $vehicle_id, $emergency_type, $description, $location_url, $photo_path]);
    
    header("Location: emergency.php?msg=Emergency assistance requested! 🆘 We'll contact you immediately.");
    exit();
}

$message = $_GET['msg'] ?? '';
$user_email = $user['email'] ?? 'user@casms.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency SOS - CASMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --accent: #f59e0b;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --emergency: #ef4444;
            --emergency-dark: #dc2626;
            --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --gradient-secondary: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-emergency: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            --box-shadow-lg: 0 30px 60px rgba(0,0,0,0.15);
            --border-radius: 12px;
            --border-radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 0.4);
        }

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

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

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

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: #e2e8f0; }
        .sidebar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

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

        .user-card {
            margin: 24px 20px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 20px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .user-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 30px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.3);
        }

        .user-card h4 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .user-card p {
            font-size: 11px;
            color: #64748b;
        }

        .user-badge {
            display: inline-block;
            background: #dbeafe;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 10px;
            color: #2563eb;
            font-weight: 600;
            margin-top: 10px;
        }

        .nav-menu {
            padding: 8px 16px;
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

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 28px 36px;
            transition: all 0.3s ease;
        }

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

        .profile-avatar-sm {
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

        .dropdown-item.text-danger {
            color: #ef4444;
        }

        .dropdown-item.text-danger:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .emergency-hero {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .emergency-hero h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .emergency-hero p {
            font-size: 16px;
            opacity: 0.95;
        }

        .emergency-phone-large {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            background: white;
            color: #dc2626;
            padding: 15px 35px;
            border-radius: 60px;
            font-size: 28px;
            font-weight: 800;
            text-decoration: none;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .emergency-phone-large:hover {
            transform: scale(1.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            color: #b91c1c;
        }

        .emergency-form-card {
            background: white;
            border-radius: 24px;
            padding: 35px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }

        .form-group label i {
            color: #ef4444;
            margin-right: 8px;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.25s ease;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .emergency-type-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .emergency-type-btn {
            padding: 20px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .emergency-type-btn i {
            font-size: 32px;
            color: #ef4444;
            margin-bottom: 10px;
            display: block;
        }

        .emergency-type-btn span {
            font-size: 13px;
            font-weight: 600;
            display: block;
            color: #475569;
        }

        .emergency-type-btn:hover {
            border-color: #ef4444;
            transform: translateY(-3px);
            background: white;
        }

        .emergency-type-btn.selected {
            background: #fef2f2;
            border-color: #ef4444;
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.2);
        }

        .location-picker {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .location-picker iframe {
            width: 100%;
            height: 250px;
            border: none;
        }

        .photo-upload {
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .photo-upload:hover {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .photo-upload i {
            font-size: 40px;
            color: #ef4444;
            margin-bottom: 10px;
        }

        .photo-preview {
            display: none;
            margin-top: 15px;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
        }

        .photo-preview img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .remove-photo {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .submit-emergency {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .submit-emergency:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(239, 68, 68, 0.3);
        }

        .emergency-history {
            background: white;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #e2e8f0;
        }

        .emergency-history h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .emergency-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.25s ease;
            background: #fef2f2;
            border-radius: 16px;
            margin-bottom: 10px;
        }

        .emergency-item:hover {
            background: #fee2e2;
            transform: translateX(5px);
        }

        .emergency-info h4 {
            font-size: 16px;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 5px;
        }

        .emergency-info p {
            font-size: 12px;
            color: #64748b;
        }

        .emergency-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #2563eb; }
        .status-resolved { background: #d1fae5; color: #059669; }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }

        .message {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            background: #ecfdf5;
            color: #047857;
            border-left: 4px solid #10b981;
        }

        @media (max-width: 1200px) {
            .main-content { margin-left: 280px; padding: 20px; }
            .emergency-type-buttons { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .main-content { margin-left: 0; }
            .top-bar { flex-direction: column; gap: 15px; text-align: center; }
            .header-actions { justify-content: center; }
            .emergency-type-buttons { grid-template-columns: 1fr; }
            .emergency-hero h1 { font-size: 24px; }
            .emergency-phone-large { font-size: 18px; padding: 12px 20px; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-car-side"></i></div>
                <div class="logo-text"><h3>CASMS</h3><p>Auto Management</p></div>
            </div>
        </div>

        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
            <div class="user-badge"><i class="fas fa-user-check"></i> Customer</div>
        </div>

        <div class="nav-menu">
            <div class="nav-section-title">MAIN</div>
            <div class="nav-item">
                <a href="user_dashboard.php?page=dashboard" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            </div>
            <div class="nav-item">
                <a href="user_dashboard.php?page=vehicles" class="nav-link"><i class="fas fa-car"></i><span>My Vehicles</span></a>
            </div>
            <div class="nav-item">
                <a href="user_dashboard.php?page=bookings" class="nav-link"><i class="fas fa-calendar-check"></i><span>My Bookings</span></a>
            </div>
            
            <div class="nav-section-title">SERVICES</div>
            <div class="nav-item">
                <a href="user_dashboard.php?page=services" class="nav-link"><i class="fas fa-wrench"></i><span>Services</span></a>
            </div>
            <div class="nav-item active">
                <a href="emergency.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-phone-alt"></i><span>Emergency SOS</span></a>
            </div>
            
            <div class="nav-section-title">ACCOUNT</div>
            <div class="nav-item">
                <a href="user_dashboard.php?page=profile" class="nav-link"><i class="fas fa-user"></i><span>Profile</span></a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="page-info">
                <h1>Emergency SOS</h1>
                <p>Get immediate roadside assistance 24/7</p>
            </div>
            <div class="header-actions">
                <div class="clock"><i class="far fa-clock"></i><span id="realtimeClock">--:--:--</span></div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-btn" onclick="toggleDropdown()">
                        <div class="profile-avatar-sm"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                        <div class="profile-info"><div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div><div class="profile-role">Customer</div></div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-header"><div class="dropdown-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div><div class="dropdown-info"><h4><?php echo htmlspecialchars($user['name']); ?></h4><p><?php echo htmlspecialchars($user_email); ?></p></div></div>
                        <div class="dropdown-divider"></div>
                        <a href="user_dashboard.php?page=profile" class="dropdown-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
                        <a href="user_dashboard.php?page=bookings" class="dropdown-item"><i class="fas fa-calendar-check"></i><span>My Bookings</span></a>
                        <div class="dropdown-divider"></div>
                        <a href="simple_logout.php" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="emergency-hero">
            <i class="fas fa-exclamation-triangle" style="font-size: 50px; margin-bottom: 15px;"></i>
            <h1>🚨 24/7 Emergency Assistance</h1>
            <p>Stuck on the road? Don't panic! Our emergency response team is ready to help you anytime, anywhere.</p>
            <a href="tel:+254700999999" class="emergency-phone-large">
                <i class="fas fa-phone-alt"></i>
                <span>+254 700 999 999</span>
            </a>
        </div>

        <div class="emergency-form-card">
            <h3 style="margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-clipboard-list" style="color: #ef4444;"></i>
                Request Emergency Assistance
            </h3>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-car"></i> Select Vehicle *</label>
                    <select name="vehicle_id" required>
                        <option value="">Choose your vehicle</option>
                        <?php foreach($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['id']; ?>">
                                <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' - ' . $vehicle['license_plate']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(empty($vehicles)): ?>
                        <small style="color: #ef4444; display: block; margin-top: 5px;">
                            <i class="fas fa-exclamation-circle"></i> You need to add a vehicle first. 
                            <a href="user_dashboard.php?page=vehicles" style="color: #2563eb;">Add Vehicle</a>
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-exclamation-circle"></i> Emergency Type *</label>
                    <div class="emergency-type-buttons">
                        <div class="emergency-type-btn" onclick="selectEmergencyType(this, 'accident')">
                            <i class="fas fa-car-crash"></i>
                            <span>Accident</span>
                        </div>
                        <div class="emergency-type-btn" onclick="selectEmergencyType(this, 'breakdown')">
                            <i class="fas fa-tools"></i>
                            <span>Breakdown</span>
                        </div>
                        <div class="emergency-type-btn" onclick="selectEmergencyType(this, 'flat_tire')">
                            <i class="fas fa-tire"></i>
                            <span>Flat Tire</span>
                        </div>
                        <div class="emergency-type-btn" onclick="selectEmergencyType(this, 'battery')">
                            <i class="fas fa-car-battery"></i>
                            <span>Dead Battery</span>
                        </div>
                        <div class="emergency-type-btn" onclick="selectEmergencyType(this, 'lockout')">
                            <i class="fas fa-lock"></i>
                            <span>Locked Out</span>
                        </div>
                        <div class="emergency-type-btn" onclick="selectEmergencyType(this, 'other')">
                            <i class="fas fa-question-circle"></i>
                            <span>Other</span>
                        </div>
                    </div>
                    <input type="hidden" name="emergency_type" id="emergency_type" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Your Location *</label>
                    <div class="location-picker">
                        <iframe id="mapIframe" width="100%" height="250" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" 
                            src="https://www.openstreetmap.org/export/embed.html?bbox=-0.004017949104309083%2C51.47612752641776%2C0.00030577182769775396%2C51.478569861898606&layer=mapnik&marker=51.47734856672978%2C-0.001855890086233557">
                        </iframe>
                    </div>
                    <input type="url" name="location_url" id="location_url" placeholder="Or paste Google Maps link" required style="margin-top: 10px;">
                    <small style="color: #64748b; display: block; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Click "Get My Location" to auto-fill your current location
                    </small>
                    <button type="button" onclick="getUserLocation()" style="margin-top: 10px; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 12px; cursor: pointer;">
                        <i class="fas fa-location-dot"></i> Get My Location
                    </button>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-camera"></i> Take Photo (Optional)</label>
                    <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
                        <i class="fas fa-camera"></i>
                        <p>Click to take a photo of the situation</p>
                        <small style="color: #64748b;">Helps us assess the emergency faster</small>
                    </div>
                    <input type="file" id="photoInput" name="emergency_photo" accept="image/*" capture="environment" style="display: none;" onchange="previewPhoto(this)">
                    <div class="photo-preview" id="photoPreview">
                        <img id="previewImg" src="" alt="Preview">
                        <button type="button" class="remove-photo" onclick="removePhoto()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-pencil-alt"></i> Description *</label>
                    <textarea name="description" rows="4" placeholder="Describe the emergency situation in detail..." required></textarea>
                </div>

                <button type="submit" name="emergency_booking" class="submit-emergency" <?php echo empty($vehicles) ? 'disabled' : ''; ?>>
                    <i class="fas fa-exclamation-triangle"></i>
                    Request Emergency Assistance
                </button>
            </form>
        </div>

        <div class="emergency-history">
            <h3><i class="fas fa-history" style="color: #ef4444;"></i> My Emergency Requests</h3>
            
            <?php if(empty($emergency_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <p>No emergency requests yet</p>
                </div>
            <?php else: ?>
                <?php foreach($emergency_bookings as $emergency): ?>
                    <div class="emergency-item">
                        <div class="emergency-info">
                            <h4>
                                <?php 
                                switch($emergency['emergency_type']) {
                                    case 'accident': echo '🚨 Accident Emergency'; break;
                                    case 'breakdown': echo '🔧 Vehicle Breakdown'; break;
                                    case 'flat_tire': echo '🛞 Flat Tire'; break;
                                    case 'battery': echo '⚡ Battery Dead'; break;
                                    case 'lockout': echo '🔒 Locked Out'; break;
                                    default: echo '⚠️ ' . ucfirst($emergency['emergency_type'] ?? 'Emergency');
                                }
                                ?>
                            </h4>
                            <p>
                                <i class="fas fa-car"></i> <?php echo htmlspecialchars(($emergency['brand'] ?? '') . ' ' . ($emergency['model'] ?? '')); ?> • 
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($emergency['created_at'] ?? 'now')); ?>
                            </p>
                            <?php if(!empty($emergency['location_url'])): ?>
                                <a href="<?php echo htmlspecialchars($emergency['location_url']); ?>" target="_blank" style="color: #2563eb; text-decoration: none; font-size: 12px;">
                                    <i class="fas fa-map-marker-alt"></i> View Location
                                </a>
                            <?php endif; ?>
                        </div>
                        <span class="emergency-status status-<?php echo $emergency['status'] ?? 'pending'; ?>">
                            <?php echo ucfirst($emergency['status'] ?? 'Pending'); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function updateClock() { 
        const now = new Date(); 
        document.getElementById('realtimeClock').innerHTML = now.toLocaleString('en-US', { weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true }); 
    }
    updateClock(); 
    setInterval(updateClock, 1000);

    function toggleDropdown() { 
        document.getElementById('profileDropdown').classList.toggle('active'); 
    }
    
    document.addEventListener('click', function(event) { 
        const dropdown = document.getElementById('profileDropdown'); 
        if (!dropdown.contains(event.target)) dropdown.classList.remove('active'); 
    });

    function selectEmergencyType(element, type) {
        document.querySelectorAll('.emergency-type-btn').forEach(btn => btn.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('emergency_type').value = type;
    }

    function getUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
                document.getElementById('location_url').value = mapsUrl;
                
                document.getElementById('mapIframe').src = `https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01}%2C${lat-0.01}%2C${lng+0.01}%2C${lat+0.01}&layer=mapnik&marker=${lat}%2C${lng}`;
                
                alert('Location captured! Your coordinates have been added.');
            }, function() {
                alert('Unable to get your location. Please enter your location manually.');
            });
        } else {
            alert('Geolocation is not supported by this browser.');
        }
    }

    function previewPhoto(input) {
        const preview = document.getElementById('photoPreview');
        const previewImg = document.getElementById('previewImg');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removePhoto() {
        document.getElementById('photoPreview').style.display = 'none';
        document.getElementById('photoInput').value = '';
    }

    setTimeout(() => { 
        const msg = document.querySelector('.message'); 
        if(msg) { 
            msg.style.transition = 'opacity 0.5s'; 
            msg.style.opacity = '0'; 
            setTimeout(() => msg.remove(), 500); 
        } 
    }, 5000);
</script>
</body>
</html>