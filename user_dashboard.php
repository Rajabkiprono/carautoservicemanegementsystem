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

// Handle AJAX requests FIRST before any HTML output
if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    if(isset($_POST['mark_notification_read'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if(isset($_POST['delete_notification'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if(isset($_POST['mark_all_read'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if(isset($_POST['get_notifications'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_count = $stmt->fetchColumn();
        echo json_encode(['unread_count' => $unread_count]);
        exit();
    }
}

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user's vehicles
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$vehicles = $stmt->fetchAll();

// Get user's bookings
$stmt = $pdo->prepare("
    SELECT b.*, v.brand, v.model, v.license_plate, s.service_name, s.price, s.category, s.duration
    FROM bookings b 
    LEFT JOIN vehicles v ON b.vehicle_id = v.id 
    LEFT JOIN services s ON b.service_id = s.id 
    WHERE b.user_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// Get all services
$services = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY service_name")->fetchAll();

// Get service stats for pie chart
$service_stats = $pdo->query("
    SELECT category, COUNT(*) as count 
    FROM services 
    WHERE is_active = 1 
    GROUP BY category
")->fetchAll();

// Get monthly booking stats
$monthly_bookings = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%b') as month,
        COUNT(*) as count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM bookings 
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY MONTH(created_at), YEAR(created_at)
    ORDER BY created_at ASC
");
$monthly_bookings->execute([$user_id]);
$monthly_stats = $monthly_bookings->fetchAll();

// Handle quick vehicle add
if(isset($_POST['quick_add_vehicle'])) {
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $year = $_POST['year'];
    $license = strtoupper($_POST['license_plate']);
    $color = $_POST['color'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, brand, model, year, license_plate, color) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $brand, $model, $year, $license, $color]);
    
    header("Location: user_dashboard.php?msg=Vehicle added successfully! 🚗");
    exit();
}

// Get notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Get unread count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = $stmt->fetchColumn();

$message = $_GET['msg'] ?? '';
$active_page = $_GET['page'] ?? 'dashboard';
$user_email = $user['email'] ?? 'user@casms.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASMS Dashboard - Car Auto Service Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --gradient-primary: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --gradient-secondary: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-accent: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

        .notification-icon {
            position: relative;
            cursor: pointer;
            width: 45px;
            height: 45px;
            background: #f8fafc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            transition: all 0.25s ease;
        }

        .notification-icon:hover {
            background: #f1f5f9;
            transform: scale(1.05);
        }

        .notification-icon i {
            font-size: 20px;
            color: #64748b;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            border: 2px solid white;
        }

        .notification-panel {
            position: absolute;
            top: 60px;
            right: 0;
            width: 380px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.2);
            border: 1px solid #e2e8f0;
            display: none;
            z-index: 1000;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-panel.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .notification-header span {
            background: #ef4444;
            color: white;
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .mark-all-btn {
            background: none;
            border: none;
            color: #2563eb;
            font-size: 12px;
            cursor: pointer;
            font-weight: 500;
        }

        .mark-all-btn:hover {
            text-decoration: underline;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.25s ease;
            cursor: pointer;
            position: relative;
        }

        .notification-item:hover {
            background: #f8fafc;
        }

        .notification-item.unread {
            background: #eff6ff;
            border-left: 3px solid #2563eb;
        }

        .notification-icon-item {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .notification-icon-item.booking {
            background: #dbeafe;
            color: #2563eb;
        }

        .notification-icon-item.emergency {
            background: #fee2e2;
            color: #ef4444;
        }

        .notification-icon-item.vehicle {
            background: #d1fae5;
            color: #10b981;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .notification-desc {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 10px;
            color: #94a3b8;
        }

        .notification-close {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .notification-item:hover .notification-close {
            opacity: 1;
        }

        .notification-close:hover {
            color: #ef4444;
        }

        .empty-notifications {
            text-align: center;
            padding: 50px;
            color: #94a3b8;
        }

        .empty-notifications i {
            font-size: 40px;
            margin-bottom: 10px;
            color: #cbd5e1;
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

        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
            background: white;
            border-radius: 24px;
            padding: 30px;
            border: 1px solid #e2e8f0;
        }

        .welcome-section h2 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .date-badge {
            background: #f8fafc;
            padding: 12px 25px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 22px;
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
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .stat-left p {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            margin-top: 6px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .charts-container {
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

        .recent-section {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            margin-top: 28px;
        }

        .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .recent-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .booking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.25s ease;
        }

        .booking-item:hover {
            background: #f8fafc;
            border-radius: 12px;
        }

        .booking-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .booking-info p {
            color: #64748b;
            font-size: 12px;
        }

        .booking-status {
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-scheduled { background: #cce5ff; color: #004085; }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.25s ease;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.1);
        }

        .service-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            color: white;
        }

        .service-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .service-price {
            font-size: 24px;
            font-weight: 800;
            color: #2563eb;
            margin-bottom: 8px;
        }

        .service-duration {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .book-btn {
            display: inline-block;
            padding: 10px 24px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.25s ease;
        }

        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -8px rgba(37, 99, 235, 0.4);
        }

        .profile-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            padding: 35px;
            border: 1px solid #e2e8f0;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-avatar-lg {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 42px;
            font-weight: 700;
            color: white;
        }

        .profile-field {
            margin-bottom: 20px;
        }

        .profile-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 6px;
        }

        .profile-field input {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }

        .profile-field input:focus {
            outline: none;
            border-color: #2563eb;
        }

        .save-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px -8px rgba(37, 99, 235, 0.4);
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .charts-container { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .top-bar { flex-direction: column; gap: 15px; text-align: center; }
            .header-actions { justify-content: center; }
            .notification-panel { width: calc(100vw - 40px); right: 20px; left: 20px; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- Sidebar -->
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
            <div class="nav-item <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>">
                <a href="?page=dashboard" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            </div>
            <div class="nav-item <?php echo $active_page == 'vehicles' ? 'active' : ''; ?>">
                <a href="?page=vehicles" class="nav-link"><i class="fas fa-car"></i><span>My Vehicles</span><span class="nav-badge"><?php echo count($vehicles); ?></span></a>
            </div>
            <div class="nav-item <?php echo $active_page == 'bookings' ? 'active' : ''; ?>">
                <a href="?page=bookings" class="nav-link"><i class="fas fa-calendar-check"></i><span>My Bookings</span><span class="nav-badge"><?php echo count($bookings); ?></span></a>
            </div>
            
            <div class="nav-section-title">SERVICES</div>
            <div class="nav-item <?php echo $active_page == 'services' ? 'active' : ''; ?>">
                <a href="?page=services" class="nav-link"><i class="fas fa-wrench"></i><span>Services</span></a>
            </div>
            <div class="nav-item">
                <a href="emergency.php" class="nav-link" style="color: #ef4444;"><i class="fas fa-phone-alt"></i><span>Emergency SOS</span></a>
            </div>
            
            <div class="nav-section-title">ACCOUNT</div>
            <div class="nav-item <?php echo $active_page == 'profile' ? 'active' : ''; ?>">
                <a href="?page=profile" class="nav-link"><i class="fas fa-user"></i><span>Profile</span></a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-info">
                <h1>
                    <?php 
                    switch($active_page) {
                        case 'dashboard': echo 'Dashboard'; break;
                        case 'vehicles': echo 'My Vehicles'; break;
                        case 'bookings': echo 'My Bookings'; break;
                        case 'services': echo 'Services'; break;
                        case 'profile': echo 'My Profile'; break;
                        default: echo 'Dashboard';
                    }
                    ?>
                </h1>
                <p>Welcome back, <?php echo htmlspecialchars($user['name']); ?>! Good to see you again</p>
            </div>
            <div class="header-actions">
                <div class="clock"><i class="far fa-clock"></i><span id="realtimeClock">--:--:--</span></div>
                
                <!-- Notification Icon -->
                <div class="notification-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if($unread_count > 0): ?>
                        <span class="notification-badge" id="notificationBadge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </div>

                <!-- Notification Panel -->
                <div class="notification-panel" id="notificationPanel">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <div>
                            <button class="mark-all-btn" onclick="markAllRead()">Mark all as read</button>
                            <span id="unreadCountSpan"><?php echo $unread_count; ?> unread</span>
                        </div>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <?php if(empty($notifications)): ?>
                            <div class="empty-notifications">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($notifications as $notif): ?>
                                <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['id']; ?>">
                                    <div style="display: flex; align-items: flex-start;">
                                        <div class="notification-icon-item <?php echo $notif['type']; ?>">
                                            <i class="fas <?php echo $notif['type'] == 'booking' ? 'fa-calendar-check' : ($notif['type'] == 'emergency' ? 'fa-exclamation-triangle' : 'fa-car'); ?>"></i>
                                        </div>
                                        <div class="notification-content" onclick="markAsRead(<?php echo $notif['id']; ?>)">
                                            <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                            <div class="notification-desc"><?php echo htmlspecialchars($notif['message']); ?></div>
                                            <div class="notification-time">
                                                <?php 
                                                    $time = strtotime($notif['created_at']);
                                                    $now = time();
                                                    $diff = $now - $time;
                                                    if($diff < 60) echo 'Just now';
                                                    elseif($diff < 3600) echo floor($diff/60) . ' minutes ago';
                                                    elseif($diff < 86400) echo floor($diff/3600) . ' hours ago';
                                                    else echo date('M d, Y', $time);
                                                ?>
                                            </div>
                                        </div>
                                        <button class="notification-close" onclick="event.stopPropagation(); deleteNotification(<?php echo $notif['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-btn" onclick="toggleDropdown()">
                        <div class="profile-avatar-sm"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                        <div class="profile-info"><div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div><div class="profile-role">Customer</div></div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-header"><div class="dropdown-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div><div class="dropdown-info"><h4><?php echo htmlspecialchars($user['name']); ?></h4><p><?php echo htmlspecialchars($user_email); ?></p></div></div>
                        <div class="dropdown-divider"></div>
                        <a href="?page=profile" class="dropdown-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
                        <a href="?page=bookings" class="dropdown-item"><i class="fas fa-calendar-check"></i><span>My Bookings</span></a>
                        <div class="dropdown-divider"></div>
                        <a href="simple_logout.php" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- DASHBOARD SECTION -->
        <div id="dashboard-section" style="display: <?php echo $active_page == 'dashboard' ? 'block' : 'none'; ?>">
            <div class="welcome-section">
                <h2>Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋</h2>
                <div class="date-badge"><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?> <i class="fas fa-clock"></i> <?php echo date('h:i:s A'); ?></div>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-left"><h3><?php echo count($vehicles); ?></h3><p>My Vehicles</p></div><div class="stat-icon"><i class="fas fa-car"></i></div></div>
                <div class="stat-card"><div class="stat-left"><h3><?php echo count($bookings); ?></h3><p>Total Bookings</p></div><div class="stat-icon"><i class="fas fa-calendar-check"></i></div></div>
                <div class="stat-card"><div class="stat-left"><h3><?php $completed = 0; foreach($bookings as $b) if($b['status'] == 'completed') $completed++; echo $completed; ?></h3><p>Completed</p></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
                <div class="stat-card"><div class="stat-left"><h3><?php $pending = 0; foreach($bookings as $b) if($b['status'] == 'pending') $pending++; echo $pending; ?></h3><p>In Progress</p></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
            </div>

            <div class="charts-container">
                <div class="chart-card"><div class="chart-header"><i class="fas fa-chart-pie"></i><h3>Service Distribution</h3></div><div class="chart-container"><canvas id="servicesPieChart"></canvas></div></div>
                <div class="chart-card"><div class="chart-header"><i class="fas fa-chart-bar"></i><h3>Monthly Booking Analytics</h3></div><div class="chart-container"><canvas id="bookingsBarChart"></canvas></div></div>
            </div>

            <?php if(!empty($bookings)): ?>
            <div class="recent-section">
                <div class="recent-header"><h3><i class="fas fa-history"></i> Recent Bookings</h3><a href="?page=bookings" class="book-btn" style="padding: 6px 16px; font-size: 12px;">View All</a></div>
                <?php foreach(array_slice($bookings, 0, 5) as $booking): ?>
                <div class="booking-item"><div class="booking-info"><h4><?php echo htmlspecialchars($booking['service_name'] ?? 'Service Booking'); ?></h4><p><i class="fas fa-car"></i> <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?> • <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['created_at'])); ?></p></div><span class="booking-status status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- VEHICLES SECTION -->
        <div id="vehicles-section" style="display: <?php echo $active_page == 'vehicles' ? 'block' : 'none'; ?>">
            <div class="recent-section">
                <div class="recent-header"><h3><i class="fas fa-car"></i> My Vehicles</h3><a href="add_vehicle.php" class="book-btn" style="padding: 8px 20px;"><i class="fas fa-plus"></i> Add Vehicle</a></div>
                <?php if(empty($vehicles)): ?>
                    <p style="text-align: center; color: #64748b; padding: 40px;">No vehicles added yet. Click "Add Vehicle" to get started.</p>
                <?php else: ?>
                    <?php foreach($vehicles as $vehicle): ?>
                    <div class="booking-item"><div class="booking-info"><h4><?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']); ?></h4><p><i class="fas fa-calendar"></i> <?php echo $vehicle['year']; ?> • <i class="fas fa-palette"></i> <?php echo htmlspecialchars($vehicle['color'] ?? 'N/A'); ?> • <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($vehicle['license_plate']); ?></p></div><span class="booking-status status-completed">Active</span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- BOOKINGS SECTION -->
        <div id="bookings-section" style="display: <?php echo $active_page == 'bookings' ? 'block' : 'none'; ?>">
            <div class="recent-section"><div class="recent-header"><h3><i class="fas fa-calendar-check"></i> All Bookings</h3></div>
                <?php if(empty($bookings)): ?>
                    <p style="text-align: center; color: #64748b; padding: 40px;">No bookings yet. Browse services to book one.</p>
                <?php else: ?>
                    <?php foreach($bookings as $booking): ?>
                    <div class="booking-item"><div class="booking-info"><h4><?php echo htmlspecialchars($booking['service_name'] ?? 'Service Booking'); ?></h4><p><i class="fas fa-car"></i> <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?> • <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['created_at'])); ?> • <i class="fas fa-tag"></i> Ksh <?php echo number_format($booking['price'] ?? 0); ?></p></div><span class="booking-status status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- SERVICES SECTION -->
        <div id="services-section" style="display: <?php echo $active_page == 'services' ? 'block' : 'none'; ?>">
            <div class="services-grid">
                <?php foreach($services as $service): ?>
                <div class="service-card"><div class="service-icon"><i class="fas fa-wrench"></i></div><h4 class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></h4><div class="service-price">Ksh <?php echo number_format($service['price']); ?></div><div class="service-duration"><i class="fas fa-clock"></i> <?php echo $service['duration']; ?> mins</div><?php if(empty($vehicles)): ?><span class="book-btn" style="opacity:0.5; cursor:not-allowed;">Add Vehicle First</span><?php else: ?><a href="book_service.php?service_id=<?php echo $service['id']; ?>" class="book-btn">Book Now</a><?php endif; ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PROFILE SECTION -->
        <div id="profile-section" style="display: <?php echo $active_page == 'profile' ? 'block' : 'none'; ?>">
            <div class="profile-container"><div class="profile-header"><div class="profile-avatar-lg"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div><h2 style="color:#0f172a;">My Profile</h2><p style="color:#64748b;">Manage your account information</p></div>
            <form method="POST" action="update_profile.php"><div class="profile-field"><label><i class="fas fa-user"></i> Full Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
            <div class="profile-field"><label><i class="fas fa-envelope"></i> Email Address</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
            <div class="profile-field"><label><i class="fas fa-phone"></i> Phone Number</label><input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter your phone number"></div>
            <div class="profile-field"><label><i class="fas fa-lock"></i> New Password (leave blank to keep current)</label><input type="password" name="new_password" placeholder="Enter new password"></div>
            <div class="profile-field"><label><i class="fas fa-lock"></i> Confirm New Password</label><input type="password" name="confirm_password" placeholder="Confirm new password"></div>
            <button type="submit" name="update_profile" class="save-btn"><i class="fas fa-save"></i> Save Changes</button></form></div>
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
        const notifPanel = document.getElementById('notificationPanel');
        const notifIcon = document.querySelector('.notification-icon');
        
        if (!dropdown.contains(event.target)) dropdown.classList.remove('active');
        if (notifIcon && !notifIcon.contains(event.target) && notifPanel && !notifPanel.contains(event.target)) {
            notifPanel.classList.remove('active');
        }
    });

    function toggleNotifications() {
        document.getElementById('notificationPanel').classList.toggle('active');
    }

    function markAsRead(notificationId) {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'mark_notification_read=1&notification_id=' + notificationId
        }).then(response => response.json())
        .then(data => {
            if(data.success) {
                const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    updateUnreadCount();
                }
            }
        }).catch(error => console.error('Error:', error));
    }

    function deleteNotification(notificationId) {
        if (confirm('Remove this notification?')) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'delete_notification=1&notification_id=' + notificationId
            }).then(response => response.json())
            .then(data => {
                if(data.success) {
                    const item = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                    if (item) {
                        item.remove();
                        updateUnreadCount();
                        
                        const list = document.getElementById('notificationList');
                        if (list.children.length === 0) {
                            list.innerHTML = '<div class="empty-notifications"><i class="fas fa-bell-slash"></i><p>No notifications yet</p></div>';
                        }
                    }
                }
            }).catch(error => console.error('Error:', error));
        }
    }

    function markAllRead() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'mark_all_read=1'
        }).then(response => response.json())
        .then(data => {
            if(data.success) {
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('unread');
                });
                updateUnreadCount();
            }
        }).catch(error => console.error('Error:', error));
    }

    function updateUnreadCount() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_notifications=1'
        }).then(response => response.json())
        .then(data => {
            const badge = document.getElementById('notificationBadge');
            const span = document.getElementById('unreadCountSpan');
            
            if (data.unread_count > 0) {
                if (badge) {
                    badge.innerText = data.unread_count;
                } else {
                    const notifIcon = document.querySelector('.notification-icon');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'notification-badge';
                    newBadge.id = 'notificationBadge';
                    newBadge.innerText = data.unread_count;
                    notifIcon.appendChild(newBadge);
                }
                if (span) span.innerText = data.unread_count + ' unread';
            } else {
                if (badge) badge.remove();
                if (span) span.innerText = '0 unread';
            }
        }).catch(error => console.error('Error:', error));
    }

    // Charts
    document.addEventListener('DOMContentLoaded', function() {
        <?php
        $pie_labels = []; $pie_data = []; $pie_colors = ['#2563eb', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'];
        if(!empty($service_stats)) { foreach($service_stats as $stat) { $pie_labels[] = $stat['category']; $pie_data[] = $stat['count']; } }
        else { $pie_labels = ['Maintenance', 'Repairs', 'Inspection', 'Diagnostic', 'Oil Change']; $pie_data = [12, 8, 5, 7, 10]; }
        ?>
        new Chart(document.getElementById('servicesPieChart'), { type: 'pie', data: { labels: <?php echo json_encode($pie_labels); ?>, datasets: [{ data: <?php echo json_encode($pie_data); ?>, backgroundColor: <?php echo json_encode(array_slice($pie_colors, 0, count($pie_data))); ?>, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } } });
        
        <?php $months = []; $completed_data = []; $pending_data = []; foreach($monthly_stats as $stat) { $months[] = $stat['month']; $completed_data[] = $stat['completed']; $pending_data[] = $stat['pending']; } if(empty($months)) { $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']; $completed_data = [3,5,4,7,6,8]; $pending_data = [2,3,2,4,3,5]; } ?>
        new Chart(document.getElementById('bookingsBarChart'), { type: 'bar', data: { labels: <?php echo json_encode($months); ?>, datasets: [{ label: 'Completed', data: <?php echo json_encode($completed_data); ?>, backgroundColor: '#10b981', borderRadius: 6 },{ label: 'Pending', data: <?php echo json_encode($pending_data); ?>, backgroundColor: '#f59e0b', borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } } });
    });

    setInterval(() => {
        updateUnreadCount();
    }, 30000);

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