<?php
session_start();

if(!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 'finance' && $_SESSION['user_role'] != 'admin')) {
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
$user_name = $_SESSION['user_name'] ?? 'Finance Officer';
$user_email = $_SESSION['user_email'] ?? 'finance@casms.com';

// Get finance user profile
$profile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$profile->execute([$user_id]);
$finance_user = $profile->fetch();

// Handle new service booking
if(isset($_POST['book_service'])) {
    $customer_id = $_POST['customer_id'];
    $vehicle_id = $_POST['vehicle_id'];
    $service_type = $_POST['service_type'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $description = $_POST['description'];
    $mechanic_id = !empty($_POST['mechanic_id']) ? $_POST['mechanic_id'] : null;
    
    $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';
    
    if($mechanic_id) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND appointment_date = ? AND status NOT IN ('completed', 'cancelled')");
        $check->execute([$mechanic_id, $appointment_datetime]);
        $existing_bookings = $check->fetchColumn();
        
        if($existing_bookings > 0) {
            $error = "This mechanic already has a booking at this time!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, vehicle_id, service_type, description, appointment_date, mechanic_id, status) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')");
            $stmt->execute([$customer_id, $vehicle_id, $service_type, $description, $appointment_datetime, $mechanic_id]);
            $success = "Service booked successfully!";
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO bookings (user_id, vehicle_id, service_type, description, appointment_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$customer_id, $vehicle_id, $service_type, $description, $appointment_datetime]);
        $success = "Service booked successfully! (Pending mechanic assignment)";
    }
}

// Handle invoice creation
if(isset($_POST['create_invoice'])) {
    $booking_id = $_POST['booking_id'];
    $user_id = $_POST['user_id'];
    $subtotal = $_POST['subtotal'];
    $tax = $_POST['tax'] ?? 0;
    $discount = $_POST['discount'] ?? 0;
    $total = $_POST['total'];
    $due_date = $_POST['due_date'];
    $notes = $_POST['notes'] ?? '';
    
    $invoice_number = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
    
    $stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, booking_id, user_id, subtotal, tax, discount, total, due_date, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
    $stmt->execute([$invoice_number, $booking_id, $user_id, $subtotal, $tax, $discount, $total, $due_date, $notes]);
    
    $success = "Invoice created successfully!";
}

// Handle payment recording
if(isset($_POST['record_payment'])) {
    $invoice_id = $_POST['invoice_id'];
    $payment_method = $_POST['payment_method'];
    $payment_reference = $_POST['payment_reference'];
    
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid', payment_method = ?, payment_reference = ?, paid_date = NOW() WHERE id = ?");
    $stmt->execute([$payment_method, $payment_reference, $invoice_id]);
    
    $success = "Payment recorded successfully!";
}

// Handle invoice update
if(isset($_POST['update_invoice'])) {
    $invoice_id = $_POST['invoice_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    
    $stmt = $pdo->prepare("UPDATE invoices SET status = ?, notes = ? WHERE id = ?");
    $stmt->execute([$status, $notes, $invoice_id]);
    
    $success = "Invoice updated!";
}

// Handle job assignment to mechanic
if(isset($_POST['assign_mechanic'])) {
    $booking_id = $_POST['booking_id'];
    $mechanic_id = $_POST['mechanic_id'];
    
    $check = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ? AND status IN ('assigned', 'in_progress')");
    $check->execute([$mechanic_id]);
    $active_jobs = $check->fetchColumn();
    
    if($active_jobs >= 3) {
        $error = "This mechanic already has $active_jobs active jobs!";
    } else {
        $stmt = $pdo->prepare("UPDATE bookings SET mechanic_id = ?, status = 'assigned' WHERE id = ?");
        $stmt->execute([$mechanic_id, $booking_id]);
        $success = "Job assigned to mechanic successfully!";
    }
}

// Handle job status update
if(isset($_POST['update_job_status'])) {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$status, $booking_id]);
    
    $success = "Job status updated to " . ucfirst(str_replace('_', ' ', $status)) . "!";
}

// Get all customers
$customers = $pdo->query("SELECT id, name, email, phone FROM users WHERE role = 'customer' AND is_active = 1 ORDER BY name")->fetchAll();

// Get all vehicles with customer info
$vehicles = $pdo->query("SELECT v.*, u.name as customer_name, u.id as customer_id FROM vehicles v JOIN users u ON v.user_id = u.id WHERE u.is_active = 1 ORDER BY u.name, v.brand")->fetchAll();

// Get all mechanics with their current workload
$mechanics = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM bookings WHERE mechanic_id = u.id AND status = 'assigned') as assigned_jobs, (SELECT COUNT(*) FROM bookings WHERE mechanic_id = u.id AND status = 'in_progress') as in_progress_jobs, (SELECT COUNT(*) FROM bookings WHERE mechanic_id = u.id AND status = 'completed') as completed_jobs, (SELECT COUNT(*) FROM bookings WHERE mechanic_id = u.id AND status IN ('assigned', 'in_progress')) as active_jobs FROM users u WHERE u.role = 'mechanic' AND u.is_active = 1 ORDER BY u.name")->fetchAll();

// Get all bookings
$bookings = $pdo->query("SELECT b.*, u.name as customer_name, u.email, u.phone, v.brand, v.model, v.license_plate, m.name as mechanic_name FROM bookings b JOIN users u ON b.user_id = u.id JOIN vehicles v ON b.vehicle_id = v.id LEFT JOIN users m ON b.mechanic_id = m.id ORDER BY CASE b.status WHEN 'scheduled' THEN 1 WHEN 'pending' THEN 2 WHEN 'assigned' THEN 3 WHEN 'in_progress' THEN 4 WHEN 'completed' THEN 5 WHEN 'cancelled' THEN 6 END, b.appointment_date DESC")->fetchAll();

// Get all invoices
$invoices = $pdo->query("SELECT i.*, u.name as customer_name FROM invoices i JOIN users u ON i.user_id = u.id ORDER BY i.created_at DESC")->fetchAll();

// Get dashboard stats
$stats = [
    'total_revenue' => $pdo->query("SELECT SUM(total) FROM invoices WHERE status = 'paid'")->fetchColumn() ?: 0,
    'pending_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('draft', 'sent')")->fetchColumn() ?: 0,
    'paid_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'paid'")->fetchColumn() ?: 0,
    'total_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn() ?: 0,
    'scheduled_jobs' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'scheduled'")->fetchColumn() ?: 0,
    'pending_jobs' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn() ?: 0,
    'assigned_jobs' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'assigned'")->fetchColumn() ?: 0,
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'in_progress'")->fetchColumn() ?: 0,
    'completed_jobs' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'completed'")->fetchColumn() ?: 0,
    'total_mechanics' => count($mechanics),
    'available_mechanics' => count(array_filter($mechanics, function($m) { return $m['active_jobs'] < 3; }))
];

$vehicle_options = [];
foreach($vehicles as $vehicle) {
    $vehicle_options[$vehicle['customer_id']][] = $vehicle;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard - CASMS Professional</title>
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

        /* Professional Light Sidebar */
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
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            box-shadow: 0 8px 16px -4px rgba(16, 185, 129, 0.2);
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
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 30px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 20px -5px rgba(16, 185, 129, 0.3);
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
            background: #d1fae5;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 10px;
            color: #059669;
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
            color: #10b981;
        }

        .nav-link:hover i {
            color: #10b981;
        }

        .nav-item.active .nav-link {
            background: linear-gradient(90deg, #ecfdf5, transparent);
            color: #059669;
            border-left: 3px solid #10b981;
        }

        .nav-item.active .nav-link i {
            color: #10b981;
        }

        /* Main Content */
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
            background: linear-gradient(135deg, #10b981, #059669);
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
            background: linear-gradient(135deg, #10b981, #059669);
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
            color: #10b981;
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
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            background: white;
            padding: 12px;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 22px;
            border: none;
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
            font-size: 14px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .tab-btn:hover {
            background: #e9ecef;
            color: #333;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        /* Tab Content */
        .tab-content {
            background: white;
            border-radius: 20px;
            padding: 28px;
            display: none;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .tab-content.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .section-header h3 i {
            color: #10b981;
            margin-right: 10px;
        }

        .badge {
            background: #e8f5e9;
            color: #10b981;
            padding: 6px 14px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
        }

        /* Form Styles */
        .booking-form, .invoice-form {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
        }

        .form-group label i {
            color: #10b981;
            margin-right: 8px;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .service-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .service-option {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .service-option:hover {
            border-color: #10b981;
            background: white;
            transform: translateY(-2px);
        }

        .service-option.selected {
            border-color: #10b981;
            background: #ecfdf5;
        }

        .service-option i {
            font-size: 28px;
            color: #10b981;
            margin-bottom: 8px;
        }

        .service-option h4 {
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .service-option .price {
            font-weight: 700;
            color: #10b981;
            font-size: 16px;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 13px;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 90px;
        }

        .status-scheduled, .status-draft { background: #cce5ff; color: #004085; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-assigned { background: #d1ecf1; color: #0c5460; }
        .status-in_progress { background: #d4edda; color: #155724; }
        .status-completed, .status-paid { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-sent { background: #cce5ff; color: #004085; }

        .amount {
            font-weight: 700;
            color: #10b981;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-pay { background: #10b981; color: white; }
        .btn-pay:hover { background: #059669; transform: translateY(-2px); }
        .btn-assign { background: #ffc107; color: #333; }
        .btn-assign:hover { background: #e0a800; transform: translateY(-2px); }
        .btn-update { background: #17a2b8; color: white; }
        .btn-update:hover { background: #138496; transform: translateY(-2px); }
        .btn-invoice { background: #6610f2; color: white; }
        .btn-invoice:hover { background: #520dc2; transform: translateY(-2px); }
        .btn-view { background: #6c757d; color: white; }
        .btn-view:hover { background: #5a6268; transform: translateY(-2px); }

        /* Mechanics Grid */
        .mechanics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }

        .mechanic-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            gap: 16px;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }

        .mechanic-card:hover {
            border-color: #10b981;
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -12px rgba(0, 0, 0, 0.1);
        }

        .mechanic-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
        }

        .mechanic-info {
            flex: 1;
        }

        .mechanic-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .mechanic-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .availability {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .available { background: #d4edda; color: #155724; }
        .busy { background: #f8d7da; color: #721c24; }

        .mechanic-contact {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .mechanic-contact i {
            width: 18px;
            color: #10b981;
        }

        .workload-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin: 12px 0;
            overflow: hidden;
        }

        .workload-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 3px;
            transition: width 0.3s;
        }

        .mechanic-stats {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
            padding: 6px;
            background: white;
            border-radius: 8px;
        }

        .stat-value {
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
        }

        .stat-label {
            color: #64748b;
            font-size: 10px;
        }

        /* Jobs Grid */
        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }

        .job-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }

        .job-card:hover {
            border-color: #ffc107;
            background: white;
            transform: translateY(-3px);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .job-id {
            font-weight: 700;
            color: #0f172a;
            font-size: 14px;
        }

        .job-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            color: #475569;
            font-size: 13px;
        }

        .job-detail i {
            width: 18px;
            color: #10b981;
        }

        .assign-form {
            margin-top: 16px;
            display: flex;
            gap: 10px;
        }

        .assign-form select {
            flex: 2;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
        }

        .assign-form button {
            flex: 1;
            padding: 10px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .assign-form button:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .booking-details {
            background: #f8fafc;
            padding: 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: none;
            border: 1px solid #e2e8f0;
        }

        .booking-details.active {
            display: block;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #999;
        }

        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .message, .error {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 13px;
        }

        .message {
            background: #ecfdf5;
            color: #047857;
            border-left: 4px solid #10b981;
        }

        .error {
            background: #fef2f2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        @media (max-width: 1200px) {
            .main-content { margin-left: 280px; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .form-row { grid-template-columns: 1fr; }
            .mechanics-grid, .jobs-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
            .tab-btn { width: 100%; justify-content: center; }
            .top-bar { flex-direction: column; gap: 15px; }
            .service-options { grid-template-columns: 1fr; }
            .mechanic-card { flex-direction: column; text-align: center; }
            .mechanic-avatar { margin: 0 auto; }
            .assign-form { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- Professional Light Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-coins"></i></div>
                <div class="logo-text"><h3>CASMS</h3><p>Finance Portal</p></div>
            </div>
        </div>

        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($finance_user['name'] ?? $user_name, 0, 1)); ?></div>
            <h4><?php echo htmlspecialchars($finance_user['name'] ?? $user_name); ?></h4>
            <p>Finance Officer</p>
            <div class="user-badge"><i class="fas fa-shield-alt"></i> Finance Access</div>
        </div>

        <div class="nav-menu">
            <div class="nav-section-title">FINANCE</div>
            <div class="nav-item active" data-nav="invoices"><a href="#" class="nav-link" onclick="showTabContent('invoices'); return false;"><i class="fas fa-file-invoice"></i><span>Invoices</span><span class="nav-badge"><?php echo count($invoices); ?></span></a></div>
            <div class="nav-item" data-nav="bookings"><a href="#" class="nav-link" onclick="showTabContent('bookings'); return false;"><i class="fas fa-calendar-check"></i><span>All Bookings</span><span class="nav-badge"><?php echo $stats['total_bookings']; ?></span></a></div>
            
            <div class="nav-section-title">OPERATIONS</div>
            <div class="nav-item" data-nav="mechanics"><a href="#" class="nav-link" onclick="showTabContent('mechanics'); return false;"><i class="fas fa-users-cog"></i><span>Mechanics</span><span class="nav-badge"><?php echo $stats['available_mechanics']; ?>/<?php echo $stats['total_mechanics']; ?></span></a></div>
            <div class="nav-item" data-nav="assign"><a href="#" class="nav-link" onclick="showTabContent('assign'); return false;"><i class="fas fa-tasks"></i><span>Assign Jobs</span><span class="nav-badge"><?php echo $stats['pending_jobs'] + $stats['scheduled_jobs']; ?></span></a></div>
            
            <div class="nav-section-title">CREATE</div>
            <div class="nav-item" data-nav="create"><a href="#" class="nav-link" onclick="showTabContent('create'); return false;"><i class="fas fa-plus-circle"></i><span>Create Invoice</span></a></div>
            <div class="nav-item" data-nav="book-service"><a href="#" class="nav-link" onclick="showTabContent('book-service'); return false;"><i class="fas fa-calendar-plus"></i><span>Book Service</span></a></div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-info"><h1 id="pageTitle">Invoices</h1><p id="pageSubtitle">Manage all financial transactions and invoices</p></div>
            <div class="header-actions">
                <div class="clock"><i class="far fa-clock"></i><span id="realtimeClock">--:--:--</span></div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-btn" onclick="toggleDropdown()">
                        <div class="profile-avatar"><?php echo strtoupper(substr($finance_user['name'] ?? $user_name, 0, 1)); ?></div>
                        <div class="profile-info"><div class="profile-name"><?php echo htmlspecialchars($finance_user['name'] ?? $user_name); ?></div><div class="profile-role">Finance Officer</div></div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-header"><div class="dropdown-avatar"><?php echo strtoupper(substr($finance_user['name'] ?? $user_name, 0, 1)); ?></div><div class="dropdown-info"><h4><?php echo htmlspecialchars($finance_user['name'] ?? $user_name); ?></h4><p><?php echo htmlspecialchars($user_email); ?></p></div></div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
                        <a href="settings.php" class="dropdown-item"><i class="fas fa-cog"></i><span>Settings</span></a>
                        <div class="dropdown-divider"></div>
                        <a href="simple_logout.php" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if(isset($success)): ?>
            <div class="message"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-left"><h3>Ksh <?php echo number_format($stats['total_revenue']); ?></h3><p>Total Revenue</p></div><div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div></div>
            <div class="stat-card"><div class="stat-left"><h3><?php echo $stats['pending_invoices']; ?></h3><p>Pending Invoices</p></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
            <div class="stat-card"><div class="stat-left"><h3><?php echo $stats['assigned_jobs'] + $stats['in_progress']; ?></h3><p>Active Jobs</p></div><div class="stat-icon"><i class="fas fa-tasks"></i></div></div>
            <div class="stat-card"><div class="stat-left"><h3><?php echo $stats['available_mechanics']; ?>/<?php echo $stats['total_mechanics']; ?></h3><p>Available Mechanics</p></div><div class="stat-icon"><i class="fas fa-users"></i></div></div>
        </div>

        <!-- Tabs for quick switching -->
        <div class="tabs">
            <button class="tab-btn active" onclick="showTabContent('invoices')"><i class="fas fa-file-invoice"></i> Invoices</button>
            <button class="tab-btn" onclick="showTabContent('bookings')"><i class="fas fa-calendar-check"></i> Bookings</button>
            <button class="tab-btn" onclick="showTabContent('mechanics')"><i class="fas fa-users-cog"></i> Mechanics</button>
            <button class="tab-btn" onclick="showTabContent('assign')"><i class="fas fa-tasks"></i> Assign Jobs</button>
            <button class="tab-btn" onclick="showTabContent('create')"><i class="fas fa-plus-circle"></i> Create Invoice</button>
            <button class="tab-btn" onclick="showTabContent('book-service')"><i class="fas fa-calendar-plus"></i> Book Service</button>
        </div>

        <!-- Invoices Tab -->
        <div id="invoices" class="tab-content active">
            <div class="section-header"><h3><i class="fas fa-file-invoice"></i> All Invoices</h3><span class="badge"><?php echo count($invoices); ?> Total</span></div>
            <?php if(empty($invoices)): ?>
                <div class="empty-state"><i class="fas fa-file-invoice"></i><p>No invoices found</p></div>
            <?php else: ?>
                <div class="table-responsive"><table><thead><tr><th>Invoice #</th><th>Customer</th><th>Booking ID</th><th>Subtotal</th><th>Total</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody><?php foreach($invoices as $invoice): ?><tr><td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td><td><?php echo htmlspecialchars($invoice['customer_name']); ?></td><td>#<?php echo $invoice['booking_id'] ?: 'N/A'; ?></td><td>Ksh <?php echo number_format($invoice['subtotal']); ?></td><td class="amount">Ksh <?php echo number_format($invoice['total']); ?></td><td><?php echo $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></td><td><span class="status-badge status-<?php echo $invoice['status']; ?>"><?php echo ucfirst($invoice['status']); ?></span></td>
                <td><div class="action-btns"><?php if($invoice['status'] != 'paid'): ?><button class="action-btn btn-pay" onclick="recordPayment(<?php echo $invoice['id']; ?>, '<?php echo $invoice['customer_name']; ?>', <?php echo $invoice['total']; ?>)"><i class="fas fa-money-bill"></i> Pay</button><?php endif; ?><button class="action-btn btn-view" onclick="window.open('view_invoice.php?id=<?php echo $invoice['id']; ?>', '_blank')"><i class="fas fa-eye"></i> View</button></div></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>

        <!-- All Bookings Tab -->
        <div id="bookings" class="tab-content">
            <div class="section-header"><h3><i class="fas fa-calendar-check"></i> All Bookings</h3><span class="badge"><?php echo count($bookings); ?> Total</span></div>
            <?php if(empty($bookings)): ?>
                <div class="empty-state"><i class="fas fa-calendar-times"></i><p>No bookings found</p></div>
            <?php else: ?>
                <div class="table-responsive"><table><thead><tr><th>ID</th><th>Customer</th><th>Vehicle</th><th>Service</th><th>Date & Time</th><th>Status</th><th>Mechanic</th><th>Actions</th></tr></thead>
                <tbody><?php foreach($bookings as $booking): ?><tr><td><strong>#<?php echo $booking['id']; ?></strong></td><td><?php echo htmlspecialchars($booking['customer_name']); ?><br><small style="color:#999;"><?php echo $booking['phone']; ?></small></td><td><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?><br><small style="color:#999;"><?php echo $booking['license_plate']; ?></small></td><td><?php echo htmlspecialchars($booking['service_type'] ?? 'General Service'); ?></td><td><?php echo date('M d, Y', strtotime($booking['appointment_date'] ?? $booking['created_at'])); ?><br><small><?php echo date('h:i A', strtotime($booking['appointment_date'] ?? $booking['created_at'])); ?></small></td><td><span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?></span></td><td><?php echo htmlspecialchars($booking['mechanic_name'] ?? 'Not assigned'); ?></td>
                <td><div class="action-btns"><?php if($booking['status'] == 'pending' || $booking['status'] == 'scheduled'): ?><button class="action-btn btn-assign" onclick="showAssignModal(<?php echo $booking['id']; ?>)"><i class="fas fa-user-cog"></i> Assign</button><?php elseif($booking['status'] == 'assigned' || $booking['status'] == 'in_progress'): ?><button class="action-btn btn-update" onclick="updateJobStatus(<?php echo $booking['id']; ?>, '<?php echo $booking['status']; ?>')"><i class="fas fa-sync"></i> Update</button><?php endif; ?><button class="action-btn btn-invoice" onclick="createInvoice(<?php echo $booking['id']; ?>, <?php echo $booking['user_id']; ?>)"><i class="fas fa-file-invoice"></i> Invoice</button></div></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>

        <!-- Mechanics Tab -->
        <div id="mechanics" class="tab-content">
            <div class="section-header"><h3><i class="fas fa-users-cog"></i> Mechanics & Workload</h3><span class="badge"><?php echo $stats['available_mechanics']; ?> Available</span></div>
            <?php if(empty($mechanics)): ?>
                <div class="empty-state"><i class="fas fa-users"></i><p>No mechanics found</p></div>
            <?php else: ?>
                <div class="mechanics-grid"><?php foreach($mechanics as $mechanic): ?><div class="mechanic-card"><div class="mechanic-avatar"><?php echo strtoupper(substr($mechanic['name'], 0, 1)); ?></div><div class="mechanic-info"><div class="mechanic-header"><span class="mechanic-name"><?php echo htmlspecialchars($mechanic['name']); ?></span><?php if($mechanic['active_jobs'] < 3): ?><span class="availability available">Available (<?php echo 3 - $mechanic['active_jobs']; ?> slots)</span><?php else: ?><span class="availability busy">Busy - Full</span><?php endif; ?></div><div class="mechanic-contact"><i class="fas fa-envelope"></i> <?php echo $mechanic['email']; ?></div><div class="mechanic-contact"><i class="fas fa-phone"></i> <?php echo $mechanic['phone']; ?></div><div class="workload-bar"><div class="workload-fill" style="width: <?php echo min(($mechanic['active_jobs'] / 3) * 100, 100); ?>%"></div></div><div class="mechanic-stats"><div class="stat-item"><div class="stat-value"><?php echo $mechanic['assigned_jobs']; ?></div><div class="stat-label">Assigned</div></div><div class="stat-item"><div class="stat-value"><?php echo $mechanic['in_progress_jobs']; ?></div><div class="stat-label">In Progress</div></div><div class="stat-item"><div class="stat-value"><?php echo $mechanic['completed_jobs']; ?></div><div class="stat-label">Completed</div></div></div></div></div><?php endforeach; ?></div>
            <?php endif; ?>
        </div>

        <!-- Assign Jobs Tab -->
        <div id="assign" class="tab-content">
            <div class="section-header"><h3><i class="fas fa-tasks"></i> Assign Jobs to Mechanics</h3><span class="badge"><?php echo $stats['pending_jobs'] + $stats['scheduled_jobs']; ?> Unassigned</span></div>
            <?php $unassigned_bookings = array_filter($bookings, function($b) { return ($b['status'] == 'pending' || $b['status'] == 'scheduled') && !$b['mechanic_id']; }); ?>
            <?php if(empty($unassigned_bookings)): ?>
                <div class="empty-state"><i class="fas fa-check-circle" style="color:#10b981;"></i><p>No unassigned jobs to assign</p></div>
            <?php else: ?>
                <div class="jobs-grid"><?php foreach($unassigned_bookings as $booking): ?><div class="job-card"><div class="job-header"><span class="job-id">Job #<?php echo $booking['id']; ?></span><span class="status-badge status-<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></div><div class="job-detail"><i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['customer_name']); ?></div><div class="job-detail"><i class="fas fa-phone"></i> <?php echo $booking['phone']; ?></div><div class="job-detail"><i class="fas fa-car"></i> <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model'] . ' - ' . $booking['license_plate']); ?></div><div class="job-detail"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($booking['service_type'] ?? 'General Service'); ?></div><div class="job-detail"><i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($booking['appointment_date'] ?? $booking['created_at'])); ?></div><form method="POST" class="assign-form"><input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>"><select name="mechanic_id" required><option value="">Select Mechanic</option><?php foreach($mechanics as $mechanic): ?><?php $is_available = $mechanic['active_jobs'] < 3; ?><option value="<?php echo $mechanic['id']; ?>" <?php echo !$is_available ? 'disabled' : ''; ?>><?php echo htmlspecialchars($mechanic['name']); ?> - <?php echo $is_available ? 'Available (' . (3 - $mechanic['active_jobs']) . ' slots)' : 'Busy (Full)'; ?></option><?php endforeach; ?></select><button type="submit" name="assign_mechanic">Assign Job</button></form></div><?php endforeach; ?></div>
            <?php endif; ?>
        </div>

        <!-- Create Invoice Tab -->
        <div id="create" class="tab-content">
            <div class="section-header"><h3><i class="fas fa-plus-circle"></i> Create New Invoice</h3></div>
            <form method="POST" class="invoice-form"><div class="form-group"><label><i class="fas fa-calendar-check"></i> Select Completed Booking</label><select name="booking_id" id="booking_select" required onchange="loadBookingDetails()"><option value="">-- Choose a completed booking --</option><?php $completed_bookings = array_filter($bookings, function($b) { return $b['status'] == 'completed'; }); foreach($completed_bookings as $booking): ?><option value="<?php echo $booking['id']; ?>" data-user="<?php echo $booking['user_id']; ?>" data-customer="<?php echo htmlspecialchars($booking['customer_name']); ?>" data-vehicle="<?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?>">#<?php echo $booking['id']; ?> - <?php echo htmlspecialchars($booking['customer_name']); ?> - <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?></option><?php endforeach; ?></select></div><div id="booking_details" class="booking-details"><h4 style="margin-bottom: 12px;">Booking Details</h4><p id="detail_customer" style="margin-bottom: 6px;"></p><p id="detail_vehicle"></p></div><input type="hidden" name="user_id" id="user_id"><div class="form-row"><div class="form-group"><label><i class="fas fa-calculator"></i> Subtotal (Ksh)</label><input type="number" name="subtotal" id="subtotal" required min="1" step="0.01" onchange="calculateTotal()"></div><div class="form-group"><label><i class="fas fa-percent"></i> Tax (Ksh)</label><input type="number" name="tax" id="tax" value="0" step="0.01" onchange="calculateTotal()"></div></div><div class="form-row"><div class="form-group"><label><i class="fas fa-tag"></i> Discount (Ksh)</label><input type="number" name="discount" id="discount" value="0" step="0.01" onchange="calculateTotal()"></div><div class="form-group"><label><i class="fas fa-money-bill"></i> Total (Ksh)</label><input type="number" name="total" id="total" readonly required style="background:#f8fafc; font-weight:700;"></div></div><div class="form-group"><label><i class="fas fa-calendar-alt"></i> Due Date</label><input type="date" name="due_date" required value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"></div><div class="form-group"><label><i class="fas fa-sticky-note"></i> Notes</label><textarea name="notes" rows="3" placeholder="Additional notes..."></textarea></div><button type="submit" name="create_invoice" class="btn-primary"><i class="fas fa-file-invoice"></i> Create Invoice</button></form>
        </div>

        <!-- Book Service Tab -->
        <div id="book-service" class="tab-content">
            <div class="section-header"><h3><i class="fas fa-calendar-plus"></i> Book New Service</h3></div>
            <form method="POST" class="booking-form"><div class="form-row"><div class="form-group"><label><i class="fas fa-user"></i> Select Customer</label><select name="customer_id" id="customer_select" required onchange="loadCustomerVehicles()"><option value="">-- Choose Customer --</option><?php foreach($customers as $customer): ?><option value="<?php echo $customer['id']; ?>" data-email="<?php echo $customer['email']; ?>" data-phone="<?php echo $customer['phone']; ?>"><?php echo htmlspecialchars($customer['name']); ?> (<?php echo $customer['phone']; ?>)</option><?php endforeach; ?></select></div><div class="form-group"><label><i class="fas fa-car"></i> Select Vehicle</label><select name="vehicle_id" id="vehicle_select" required><option value="">-- First select a customer --</option></select></div></div><div class="form-group"><label><i class="fas fa-wrench"></i> Service Type</label><div class="service-options"><div class="service-option" onclick="selectService(this, 'General Service', 1500)"><i class="fas fa-oil-can"></i><h4>General Service</h4><div class="price">Ksh 1,500</div></div><div class="service-option" onclick="selectService(this, 'Oil Change', 800)"><i class="fas fa-tint"></i><h4>Oil Change</h4><div class="price">Ksh 800</div></div><div class="service-option" onclick="selectService(this, 'Brake Repair', 2000)"><i class="fas fa-car-battery"></i><h4>Brake Repair</h4><div class="price">Ksh 2,000</div></div><div class="service-option" onclick="selectService(this, 'Engine Diagnostic', 1000)"><i class="fas fa-microchip"></i><h4>Engine Diagnostic</h4><div class="price">Ksh 1,000</div></div><div class="service-option" onclick="selectService(this, 'Tire Rotation', 600)"><i class="fas fa-circle"></i><h4>Tire Rotation</h4><div class="price">Ksh 600</div></div><div class="service-option" onclick="selectService(this, 'AC Service', 1800)"><i class="fas fa-wind"></i><h4>AC Service</h4><div class="price">Ksh 1,800</div></div></div><input type="hidden" name="service_type" id="service_type" required></div><div class="form-row"><div class="form-group"><label><i class="fas fa-calendar"></i> Appointment Date</label><input type="date" name="appointment_date" id="appointment_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"></div><div class="form-group"><label><i class="fas fa-clock"></i> Appointment Time</label><select name="appointment_time" id="appointment_time" required><option value="09:00">9:00 AM</option><option value="10:00">10:00 AM</option><option value="11:00">11:00 AM</option><option value="12:00">12:00 PM</option><option value="13:00">1:00 PM</option><option value="14:00">2:00 PM</option><option value="15:00">3:00 PM</option><option value="16:00">4:00 PM</option></select></div></div><div class="form-group"><label><i class="fas fa-user-cog"></i> Preferred Mechanic (Optional)</label><select name="mechanic_id" id="mechanic_select"><option value="">-- Auto-assign later --</option><?php foreach($mechanics as $mechanic): ?><?php $is_available = $mechanic['active_jobs'] < 3; ?><option value="<?php echo $mechanic['id']; ?>" <?php echo !$is_available ? 'disabled' : ''; ?>><?php echo htmlspecialchars($mechanic['name']); ?> - <?php echo $is_available ? 'Available (' . (3 - $mechanic['active_jobs']) . ' slots)' : 'Busy'; ?></option><?php endforeach; ?></select></div><div class="form-group"><label><i class="fas fa-align-left"></i> Service Description</label><textarea name="description" rows="3" placeholder="Describe any specific issues or requirements..."></textarea></div><button type="submit" name="book_service" class="btn-primary"><i class="fas fa-calendar-check"></i> Book Service</button></form>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="paymentModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-money-bill"></i> Record Payment</h3><button class="modal-close" onclick="closeModal()">&times;</button></div><form method="POST"><input type="hidden" name="invoice_id" id="payment_invoice_id"><div class="form-group"><label>Customer</label><input type="text" id="payment_customer" readonly style="background:#f8fafc;"></div><div class="form-group"><label>Amount (Ksh)</label><input type="number" id="payment_amount" readonly style="background:#f8fafc;"></div><div class="form-group"><label>Payment Method</label><select name="payment_method" required><option value="cash">Cash</option><option value="mpesa">M-PESA</option><option value="card">Card</option><option value="bank">Bank Transfer</option></select></div><div class="form-group"><label>Payment Reference</label><input type="text" name="payment_reference" placeholder="e.g., MPESA transaction ID"></div><div style="display:flex; gap:10px;"><button type="submit" name="record_payment" class="btn-primary" style="flex:2;">Record Payment</button><button type="button" class="btn-primary" style="flex:1; background:#6c757d;" onclick="closeModal()">Cancel</button></div></form></div></div>

<div id="assignModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-user-cog"></i> Assign Job to Mechanic</h3><button class="modal-close" onclick="closeAssignModal()">&times;</button></div><form method="POST"><input type="hidden" name="booking_id" id="assign_booking_id"><div class="form-group"><label>Select Mechanic</label><select name="mechanic_id" required><option value="">-- Choose Mechanic --</option><?php foreach($mechanics as $mechanic): ?><?php $is_available = $mechanic['active_jobs'] < 3; ?><option value="<?php echo $mechanic['id']; ?>" <?php echo !$is_available ? 'disabled' : ''; ?>><?php echo htmlspecialchars($mechanic['name']); ?> - <?php echo $is_available ? 'Available (' . (3 - $mechanic['active_jobs']) . ' slots)' : 'Busy'; ?></option><?php endforeach; ?></select></div><div style="display:flex; gap:10px;"><button type="submit" name="assign_mechanic" class="btn-primary" style="flex:2;">Assign Job</button><button type="button" class="btn-primary" style="flex:1; background:#6c757d;" onclick="closeAssignModal()">Cancel</button></div></form></div></div>

<div id="updateStatusModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-sync"></i> Update Job Status</h3><button class="modal-close" onclick="closeStatusModal()">&times;</button></div><form method="POST"><input type="hidden" name="booking_id" id="status_booking_id"><div class="form-group"><label>New Status</label><select name="status" id="status_select" required><option value="assigned">Assigned</option><option value="in_progress">In Progress</option><option value="completed">Completed</option></select></div><div style="display:flex; gap:10px;"><button type="submit" name="update_job_status" class="btn-primary" style="flex:2;">Update Status</button><button type="button" class="btn-primary" style="flex:1; background:#6c757d;" onclick="closeStatusModal()">Cancel</button></div></form></div></div>

<script>
    function updateClock() { const now = new Date(); document.getElementById('realtimeClock').innerHTML = now.toLocaleString('en-US', { weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true }); }
    updateClock(); setInterval(updateClock, 1000);

    function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('active'); }
    document.addEventListener('click', function(event) { const dropdown = document.getElementById('profileDropdown'); if (!dropdown.contains(event.target)) dropdown.classList.remove('active'); });

    function showTabContent(tabName) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        document.querySelector(`.tab-btn[onclick="showTabContent('${tabName}')"]`).classList.add('active');
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        document.querySelector(`.nav-item[data-nav="${tabName}"]`)?.classList.add('active');
        const titles = { invoices:'Invoices', bookings:'All Bookings', mechanics:'Mechanics', assign:'Assign Jobs', create:'Create Invoice', 'book-service':'Book Service' };
        document.getElementById('pageTitle').innerText = titles[tabName] || 'Dashboard';
    }

    function loadCustomerVehicles() {
        const customerId = document.getElementById('customer_select').value;
        const vehicleSelect = document.getElementById('vehicle_select');
        const vehicles = <?php echo json_encode($vehicle_options); ?>;
        if(!customerId) { vehicleSelect.innerHTML = '<option value="">-- First select a customer --</option>'; return; }
        let options = '<option value="">-- Select Vehicle --</option>';
        if(vehicles[customerId]) { vehicles[customerId].forEach(v => { options += `<option value="${v.id}">${v.brand} ${v.model} - ${v.license_plate}</option>`; }); }
        vehicleSelect.innerHTML = options;
    }

    function selectService(element, serviceType, price) {
        document.querySelectorAll('.service-option').forEach(opt => opt.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('service_type').value = serviceType;
        if(document.getElementById('subtotal')) { document.getElementById('subtotal').value = price; calculateTotal(); }
    }

    function loadBookingDetails() {
        const select = document.getElementById('booking_select');
        const option = select.options[select.selectedIndex];
        if(option.value) {
            document.getElementById('booking_details').classList.add('active');
            document.getElementById('detail_customer').innerHTML = '<i class="fas fa-user" style="color:#10b981; margin-right:8px;"></i> <strong>Customer:</strong> ' + option.dataset.customer;
            document.getElementById('detail_vehicle').innerHTML = '<i class="fas fa-car" style="color:#10b981; margin-right:8px;"></i> <strong>Vehicle:</strong> ' + option.dataset.vehicle;
            document.getElementById('user_id').value = option.dataset.user;
        } else { document.getElementById('booking_details').classList.remove('active'); }
    }

    function calculateTotal() {
        const subtotal = parseFloat(document.getElementById('subtotal')?.value) || 0;
        const tax = parseFloat(document.getElementById('tax')?.value) || 0;
        const discount = parseFloat(document.getElementById('discount')?.value) || 0;
        const total = subtotal + tax - discount;
        if(document.getElementById('total')) document.getElementById('total').value = total.toFixed(2);
    }

    function createInvoice(bookingId, userId) {
        const select = document.getElementById('booking_select');
        for(let i=0; i<select.options.length; i++) { if(select.options[i].value == bookingId) { select.selectedIndex = i; break; } }
        loadBookingDetails();
        showTabContent('create');
    }

    function showAssignModal(bookingId) { document.getElementById('assign_booking_id').value = bookingId; document.getElementById('assignModal').classList.add('active'); }
    function closeAssignModal() { document.getElementById('assignModal').classList.remove('active'); }
    function updateJobStatus(bookingId, currentStatus) { document.getElementById('status_booking_id').value = bookingId; const select = document.getElementById('status_select'); select.value = currentStatus == 'assigned' ? 'in_progress' : 'completed'; document.getElementById('updateStatusModal').classList.add('active'); }
    function closeStatusModal() { document.getElementById('updateStatusModal').classList.remove('active'); }
    function recordPayment(invoiceId, customer, amount) { document.getElementById('payment_invoice_id').value = invoiceId; document.getElementById('payment_customer').value = customer; document.getElementById('payment_amount').value = amount; document.getElementById('paymentModal').classList.add('active'); }
    function closeModal() { document.getElementById('paymentModal').classList.remove('active'); }
    setTimeout(() => { document.querySelectorAll('.message, .error').forEach(el => { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }); }, 5000);
</script>
</body>
</html>