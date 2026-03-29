<?php
session_start();

if(!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 'mechanic' && $_SESSION['user_role'] != 'admin')) {
    header("Location: simple_login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=casms", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Mechanic';
$user_email = $_SESSION['user_email'] ?? 'mechanic@casms.com';

// Get mechanic profile
$profile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$profile->execute([$user_id]);
$mechanic = $profile->fetch();

// Update profile
if(isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $specialization = $_POST['specialization'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, specialization = ? WHERE id = ?");
    if($stmt->execute([$name, $email, $phone, $specialization, $user_id])) {
        $_SESSION['user_name'] = $name;
        $success = "Profile updated successfully!";
    } else {
        $error = "Failed to update profile";
    }
}

// Update availability status
if(isset($_POST['update_availability'])) {
    $status = $_POST['availability_status'];
    
    $stmt = $pdo->prepare("UPDATE users SET availability_status = ? WHERE id = ?");
    if($stmt->execute([$status, $user_id])) {
        $success = "Availability status updated!";
    }
}

// Accept job from available queue
if(isset($_POST['accept_job'])) {
    $booking_id = $_POST['booking_id'];
    
    $check = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND status = 'pending' AND (mechanic_id IS NULL OR mechanic_id = 0 OR mechanic_id = '')");
    $check->execute([$booking_id]);
    
    if($check->rowCount() > 0) {
        $stmt = $pdo->prepare("UPDATE bookings SET mechanic_id = ?, status = 'in_progress', started_at = NOW() WHERE id = ?");
        if($stmt->execute([$user_id, $booking_id])) {
            $success = "Job #$booking_id accepted successfully!";
        } else {
            $error = "Failed to accept job";
        }
    } else {
        $error = "This job is no longer available";
    }
}

// Start job (for assigned jobs from finance)
if(isset($_POST['start_job'])) {
    $booking_id = $_POST['booking_id'];
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'in_progress', started_at = NOW() WHERE id = ? AND mechanic_id = ? AND status IN ('pending', 'assigned')");
    if($stmt->execute([$booking_id, $user_id])) {
        $success = "Job #$booking_id started!";
    } else {
        $error = "Failed to start job";
    }
}

// Update job status
if(isset($_POST['update_status'])) {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    $parts_used = $_POST['parts_used'] ?? '';
    $hours_worked = $_POST['hours_worked'] ?? 0;
    
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("
            UPDATE bookings SET 
                status = ?, 
                notes = CONCAT(IFNULL(notes, ''), '\n[WORK LOG] ', ?),
                parts_used = CONCAT(IFNULL(parts_used, ''), '\n', ?),
                hours_worked = hours_worked + ?,
                updated_at = NOW() 
            WHERE id = ? AND mechanic_id = ?
        ");
        $stmt->execute([$status, $notes, $parts_used, $hours_worked, $booking_id, $user_id]);
        
        if($status == 'completed') {
            $stmt2 = $pdo->prepare("UPDATE bookings SET completed_at = NOW() WHERE id = ?");
            $stmt2->execute([$booking_id]);
        }
        
        $pdo->commit();
        $success = "Job #$booking_id updated to " . ucfirst(str_replace('_', ' ', $status));
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update job: " . $e->getMessage();
    }
}

// Add job note
if(isset($_POST['add_note'])) {
    $booking_id = $_POST['booking_id'];
    $note = $_POST['note'];
    
    $stmt = $pdo->prepare("
        UPDATE bookings SET 
            notes = CONCAT(IFNULL(notes, ''), '\n[NOTE] ', ?),
            updated_at = NOW() 
        WHERE id = ? AND mechanic_id = ?
    ");
    
    if($stmt->execute([$note, $booking_id, $user_id])) {
        $success = "Note added successfully!";
    } else {
        $error = "Failed to add note";
    }
}

// Request parts
if(isset($_POST['request_parts'])) {
    $booking_id = $_POST['booking_id'] ?? null;
    $parts = $_POST['parts'];
    $urgency = $_POST['urgency'];
    
    if($booking_id === '' || $booking_id === null) {
        $booking_id = null;
    } else {
        $check = $pdo->prepare("SELECT id FROM bookings WHERE id = ?");
        $check->execute([$booking_id]);
        if($check->rowCount() == 0) {
            $booking_id = null;
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO parts_requests (booking_id, mechanic_id, parts, urgency, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    
    if($stmt->execute([$booking_id, $user_id, $parts, $urgency])) {
        $success = "Parts request submitted successfully!";
    } else {
        $error = "Failed to submit parts request";
    }
}

// Get ALL pending jobs that need a mechanic
$available_jobs = $pdo->prepare("
    SELECT b.*, 
           u.name as customer, 
           u.phone, 
           u.email,
           v.brand, 
           v.model, 
           v.license_plate,
           v.color,
           v.year
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.status = 'pending' 
      AND (b.mechanic_id IS NULL OR b.mechanic_id = 0 OR b.mechanic_id = '')
    ORDER BY b.appointment_date ASC, b.created_at ASC
");
$available_jobs->execute();
$available_jobs = $available_jobs->fetchAll();

// Get jobs assigned to this mechanic
$assigned_jobs = $pdo->prepare("
    SELECT b.*, 
           u.name as customer, 
           u.phone, 
           u.email,
           v.brand, 
           v.model, 
           v.license_plate,
           v.color,
           v.year
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.mechanic_id = ? 
      AND b.status IN ('pending', 'assigned', 'in_progress')
    ORDER BY 
        CASE b.status
            WHEN 'in_progress' THEN 1
            WHEN 'assigned' THEN 2
            WHEN 'pending' THEN 3
            ELSE 4
        END,
        b.appointment_date ASC
");
$assigned_jobs->execute([$user_id]);
$assigned_jobs = $assigned_jobs->fetchAll();

// Get completed jobs
$completed_jobs = $pdo->prepare("
    SELECT b.*, 
           u.name as customer, 
           v.brand, 
           v.model,
           v.license_plate,
           DATEDIFF(NOW(), b.completed_at) as days_ago
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.status = 'completed' AND b.mechanic_id = ?
    ORDER BY b.updated_at DESC
");
$completed_jobs->execute([$user_id]);
$completed_jobs = $completed_jobs->fetchAll();

// Get parts requests
$parts_requests = $pdo->prepare("
    SELECT pr.*, b.id as booking_number
    FROM parts_requests pr
    LEFT JOIN bookings b ON pr.booking_id = b.id
    WHERE pr.mechanic_id = ? 
    ORDER BY pr.created_at DESC 
    LIMIT 10
");
$parts_requests->execute([$user_id]);
$parts_requests = $parts_requests->fetchAll();

// Get mechanic performance stats
$total_jobs_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE mechanic_id = ?");
$total_jobs_stmt->execute([$user_id]);
$total_jobs = $total_jobs_stmt->fetchColumn();

$total_hours_stmt = $pdo->prepare("SELECT COALESCE(SUM(hours_worked), 0) FROM bookings WHERE mechanic_id = ? AND status = 'completed'");
$total_hours_stmt->execute([$user_id]);
$total_hours = $total_hours_stmt->fetchColumn();

$avg_time_stmt = $pdo->prepare("
    SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR, started_at, completed_at)), 0) 
    FROM bookings 
    WHERE mechanic_id = ? AND status = 'completed' AND started_at IS NOT NULL
");
$avg_time_stmt->execute([$user_id]);
$avg_completion_time = round($avg_time_stmt->fetchColumn());

$assigned_count = 0;
$in_progress_count = 0;

foreach($assigned_jobs as $job) {
    if($job['status'] == 'pending' || $job['status'] == 'assigned') $assigned_count++;
    if($job['status'] == 'in_progress') $in_progress_count++;
}

$stats = [
    'total_jobs' => $total_jobs,
    'completed_jobs' => count($completed_jobs),
    'in_progress' => $in_progress_count,
    'assigned_jobs' => $assigned_count,
    'total_hours' => $total_hours,
    'avg_completion_time' => $avg_completion_time,
    'rating' => 4.8,
];

$message = $_GET['msg'] ?? '';
$active_page = $_GET['page'] ?? 'dashboard';
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mechanic Dashboard - CASMS Professional</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            box-shadow: 0 8px 16px -4px rgba(139, 92, 246, 0.2);
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 30px;
            font-weight: 700;
            color: white;
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.3);
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
            background: #ede9fe;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 10px;
            color: #7c3aed;
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
            color: #8b5cf6;
        }

        .nav-link:hover i {
            color: #8b5cf6;
        }

        .nav-item.active .nav-link {
            background: linear-gradient(90deg, #f5f3ff, transparent);
            color: #7c3aed;
            border-left: 3px solid #8b5cf6;
        }

        .nav-item.active .nav-link i {
            color: #8b5cf6;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 28px 36px;
            transition: all 0.3s ease;
        }

        /* Top Bar */
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

        .profile-avatar-sm {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
            color: #8b5cf6;
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            margin-bottom: 28px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .badge {
            background: #f1f5f9;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        /* Job Cards Grid */
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
            position: relative;
            overflow: hidden;
        }

        .job-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #8b5cf6;
        }

        .job-card.available::before { background: #10b981; }
        .job-card.in-progress::before { background: #f59e0b; }
        .job-card.assigned::before { background: #3b82f6; }

        .job-card:hover {
            border-color: #8b5cf6;
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -12px rgba(139, 92, 246, 0.15);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .job-id {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-assigned { background: #cce5ff; color: #004085; }

        .job-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #475569;
        }

        .detail-item i {
            width: 16px;
            color: #8b5cf6;
        }

        .job-notes {
            background: white;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 12px;
            color: #64748b;
            border-left: 3px solid #8b5cf6;
        }

        .job-actions {
            display: flex;
            gap: 10px;
        }

        .btn-accept, .btn-update {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-accept {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-update {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        .btn-notes {
            padding: 10px 15px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .btn-notes:hover {
            border-color: #8b5cf6;
            color: #8b5cf6;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #8b5cf6;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
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
            border-radius: 24px;
            padding: 30px;
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
            color: #94a3b8;
        }

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

        .message, .error-message {
            padding: 14px 20px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }

        .message {
            background: #ecfdf5;
            color: #047857;
            border-left: 4px solid #10b981;
        }

        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #334155;
        }

        @media (max-width: 1200px) {
            .main-content { margin-left: 280px; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .jobs-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
            .tab-btn { width: 100%; justify-content: center; }
            .top-bar { flex-direction: column; gap: 15px; text-align: center; }
            .header-actions { justify-content: center; }
            .job-details { grid-template-columns: 1fr; }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <!-- Professional Light Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-wrench"></i></div>
                <div class="logo-text"><h3>CASMS</h3><p>Mechanic Portal</p></div>
            </div>
        </div>

        <div class="user-card">
            <div class="user-avatar"><?php echo strtoupper(substr($mechanic['name'] ?? $user_name, 0, 1)); ?></div>
            <h4><?php echo htmlspecialchars($mechanic['name'] ?? $user_name); ?></h4>
            <p><?php echo htmlspecialchars($mechanic['email'] ?? $user_email); ?></p>
            <div class="user-badge"><i class="fas fa-tools"></i> <?php echo $mechanic['specialization'] ?? 'General Mechanic'; ?></div>
        </div>

        <div class="nav-menu">
            <div class="nav-section-title">WORK</div>
            <div class="nav-item <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>" data-nav="dashboard">
                <a href="?page=dashboard" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            </div>
            <div class="nav-item <?php echo $active_page == 'available' ? 'active' : ''; ?>" data-nav="available">
                <a href="?page=available" class="nav-link"><i class="fas fa-bell"></i><span>Available Jobs</span><span class="nav-badge"><?php echo count($available_jobs); ?></span></a>
            </div>
            <div class="nav-item <?php echo $active_page == 'myjobs' ? 'active' : ''; ?>" data-nav="myjobs">
                <a href="?page=myjobs" class="nav-link"><i class="fas fa-tasks"></i><span>My Jobs</span><span class="nav-badge"><?php echo count($assigned_jobs); ?></span></a>
            </div>
            
            <div class="nav-section-title">RECORDS</div>
            <div class="nav-item <?php echo $active_page == 'history' ? 'active' : ''; ?>" data-nav="history">
                <a href="?page=history" class="nav-link"><i class="fas fa-history"></i><span>History</span></a>
            </div>
            
            <div class="nav-section-title">ACCOUNT</div>
            <div class="nav-item <?php echo $active_page == 'profile' ? 'active' : ''; ?>" data-nav="profile">
                <a href="?page=profile" class="nav-link"><i class="fas fa-user-cog"></i><span>Profile</span></a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-info">
                <h1 id="pageTitle">
                    <?php 
                    switch($active_page) {
                        case 'dashboard': echo 'Dashboard'; break;
                        case 'available': echo 'Available Jobs'; break;
                        case 'myjobs': echo 'My Jobs'; break;
                        case 'history': echo 'Job History'; break;
                        case 'profile': echo 'My Profile'; break;
                        default: echo 'Dashboard';
                    }
                    ?>
                </h1>
                <p id="pageSubtitle">Welcome back, <?php echo htmlspecialchars($mechanic['name'] ?? $user_name); ?>! Ready to fix some cars?</p>
            </div>
            <div class="header-actions">
                <div class="clock"><i class="far fa-clock"></i><span id="realtimeClock">--:--:--</span></div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-btn" onclick="toggleDropdown()">
                        <div class="profile-avatar-sm"><?php echo strtoupper(substr($mechanic['name'] ?? $user_name, 0, 1)); ?></div>
                        <div class="profile-info"><div class="profile-name"><?php echo htmlspecialchars($mechanic['name'] ?? $user_name); ?></div><div class="profile-role">Mechanic</div></div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-header"><div class="dropdown-avatar"><?php echo strtoupper(substr($mechanic['name'] ?? $user_name, 0, 1)); ?></div><div class="dropdown-info"><h4><?php echo htmlspecialchars($mechanic['name'] ?? $user_name); ?></h4><p><?php echo htmlspecialchars($mechanic['email'] ?? $user_email); ?></p></div></div>
                        <div class="dropdown-divider"></div>
                        <a href="?page=profile" class="dropdown-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
                        <a href="?page=myjobs" class="dropdown-item"><i class="fas fa-tasks"></i><span>My Jobs</span></a>
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
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- DASHBOARD TAB -->
        <div id="dashboard-tab" class="tab-content <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-left"><h3><?php echo count($available_jobs); ?></h3><p>Available Jobs</p></div><div class="stat-icon"><i class="fas fa-bell"></i></div></div>
                <div class="stat-card"><div class="stat-left"><h3><?php echo $stats['assigned_jobs']; ?></h3><p>Assigned to Me</p></div><div class="stat-icon"><i class="fas fa-clock"></i></div></div>
                <div class="stat-card"><div class="stat-left"><h3><?php echo $stats['in_progress']; ?></h3><p>In Progress</p></div><div class="stat-icon"><i class="fas fa-spinner"></i></div></div>
                <div class="stat-card"><div class="stat-left"><h3><?php echo $stats['completed_jobs']; ?></h3><p>Completed</p></div><div class="stat-icon"><i class="fas fa-check-circle"></i></div></div>
            </div>

            <?php if(!empty($available_jobs)): ?>
            <div class="section-card">
                <div class="section-header"><h3><i class="fas fa-bell" style="color:#10b981;"></i> Available Jobs</h3><a href="?page=available" class="badge" style="text-decoration:none;">View All →</a></div>
                <div class="jobs-grid">
                    <?php foreach(array_slice($available_jobs, 0, 2) as $job): ?>
                    <div class="job-card available"><div class="job-header"><span class="job-id"><i class="fas fa-hashtag"></i> Job #<?php echo $job['id']; ?></span><span class="status-badge status-pending">Available</span></div>
                    <div class="job-details"><div class="detail-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($job['customer']); ?></div><div class="detail-item"><i class="fas fa-car"></i> <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?></div><div class="detail-item"><i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($job['license_plate']); ?></div><div class="detail-item"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($job['service_type']); ?></div></div>
                    <form method="POST"><input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>"><button type="submit" name="accept_job" class="btn-accept"><i class="fas fa-hand-paper"></i> Accept Job</button></form></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(!empty($assigned_jobs)): ?>
            <div class="section-card">
                <div class="section-header"><h3><i class="fas fa-tasks" style="color:#8b5cf6;"></i> My Jobs</h3><a href="?page=myjobs" class="badge" style="text-decoration:none;">View All →</a></div>
                <div class="jobs-grid">
                    <?php foreach(array_slice($assigned_jobs, 0, 2) as $job): ?>
                    <div class="job-card <?php echo $job['status'] == 'in_progress' ? 'in-progress' : 'assigned'; ?>"><div class="job-header"><span class="job-id"><i class="fas fa-hashtag"></i> Job #<?php echo $job['id']; ?></span><span class="status-badge status-<?php echo $job['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></span></div>
                    <div class="job-details"><div class="detail-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($job['customer']); ?></div><div class="detail-item"><i class="fas fa-car"></i> <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?></div><div class="detail-item"><i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($job['license_plate']); ?></div><div class="detail-item"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($job['service_type']); ?></div></div>
                    <div class="job-actions"><?php if($job['status'] == 'pending' || $job['status'] == 'assigned'): ?><form method="POST" style="flex:1;"><input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>"><button type="submit" name="start_job" class="btn-update"><i class="fas fa-play"></i> Start</button></form><?php else: ?><button class="btn-update" onclick="openUpdateModal(<?php echo $job['id']; ?>)"><i class="fas fa-sync-alt"></i> Update</button><?php endif; ?><button class="btn-notes" onclick="openNoteModal(<?php echo $job['id']; ?>)"><i class="fas fa-plus"></i></button><button class="btn-notes" onclick="openPartsRequestModal(<?php echo $job['id']; ?>)"><i class="fas fa-box"></i></button></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- AVAILABLE JOBS TAB -->
        <div id="available-tab" class="tab-content <?php echo $active_page == 'available' ? 'active' : ''; ?>">
            <div class="section-card"><div class="section-header"><h3><i class="fas fa-bell" style="color:#10b981;"></i> Available Jobs</h3><span class="badge"><?php echo count($available_jobs); ?> Available</span></div>
            <?php if(empty($available_jobs)): ?><div class="empty-state"><i class="fas fa-check-circle" style="color:#10b981;"></i><p>All caught up! No pending jobs at the moment.</p></div>
            <?php else: ?><div class="jobs-grid"><?php foreach($available_jobs as $job): ?><div class="job-card available"><div class="job-header"><span class="job-id"><i class="fas fa-hashtag"></i> Job #<?php echo $job['id']; ?></span><span class="status-badge status-pending">Available</span></div>
            <div class="job-details"><div class="detail-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($job['customer']); ?></div><div class="detail-item"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($job['phone']); ?></div><div class="detail-item"><i class="fas fa-car"></i> <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?></div><div class="detail-item"><i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($job['license_plate']); ?></div><div class="detail-item"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($job['service_type']); ?></div><div class="detail-item"><i class="fas fa-calendar"></i> <?php echo date('M d, h:i A', strtotime($job['appointment_date'])); ?></div></div>
            <?php if(!empty($job['notes'])): ?><div class="job-notes"><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(substr($job['notes'], 0, 80)); ?></div><?php endif; ?>
            <form method="POST"><input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>"><button type="submit" name="accept_job" class="btn-accept"><i class="fas fa-hand-paper"></i> Accept Job</button></form></div><?php endforeach; ?></div><?php endif; ?></div>
        </div>

        <!-- MY JOBS TAB -->
        <div id="myjobs-tab" class="tab-content <?php echo $active_page == 'myjobs' ? 'active' : ''; ?>">
            <div class="section-card"><div class="section-header"><h3><i class="fas fa-tasks" style="color:#8b5cf6;"></i> My Jobs</h3><span class="badge"><?php echo count($assigned_jobs); ?> Total</span></div>
            <?php if(empty($assigned_jobs)): ?><div class="empty-state"><i class="fas fa-tasks"></i><p>No jobs assigned to you yet</p><a href="?page=available" class="btn-accept" style="display:inline-block; width:auto; padding:10px 30px; margin-top:15px;"><i class="fas fa-search"></i> Find Jobs</a></div>
            <?php else: ?><div class="jobs-grid"><?php foreach($assigned_jobs as $job): ?><div class="job-card <?php echo $job['status'] == 'in_progress' ? 'in-progress' : 'assigned'; ?>"><div class="job-header"><span class="job-id"><i class="fas fa-hashtag"></i> Job #<?php echo $job['id']; ?></span><span class="status-badge status-<?php echo $job['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></span></div>
            <div class="job-details"><div class="detail-item"><i class="fas fa-user"></i> <?php echo htmlspecialchars($job['customer']); ?></div><div class="detail-item"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($job['phone']); ?></div><div class="detail-item"><i class="fas fa-car"></i> <?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?></div><div class="detail-item"><i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($job['license_plate']); ?></div><div class="detail-item"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($job['service_type']); ?></div><div class="detail-item"><i class="fas fa-calendar"></i> <?php echo date('M d, h:i A', strtotime($job['appointment_date'])); ?></div><?php if($job['started_at']): ?><div class="detail-item"><i class="fas fa-play-circle"></i> Started: <?php echo date('h:i A', strtotime($job['started_at'])); ?></div><?php endif; ?></div>
            <?php if(!empty($job['notes'])): ?><div class="job-notes"><i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars(substr($job['notes'], -100)); ?></div><?php endif; ?>
            <div class="job-actions"><?php if($job['status'] == 'pending' || $job['status'] == 'assigned'): ?><form method="POST" style="flex:1;"><input type="hidden" name="booking_id" value="<?php echo $job['id']; ?>"><button type="submit" name="start_job" class="btn-update"><i class="fas fa-play"></i> Start Job</button></form><?php else: ?><button class="btn-update" onclick="openUpdateModal(<?php echo $job['id']; ?>)"><i class="fas fa-sync-alt"></i> Update</button><?php endif; ?><button class="btn-notes" onclick="openNoteModal(<?php echo $job['id']; ?>)"><i class="fas fa-plus"></i></button><button class="btn-notes" onclick="openPartsRequestModal(<?php echo $job['id']; ?>)"><i class="fas fa-box"></i></button></div></div><?php endforeach; ?></div><?php endif; ?></div>
        </div>

        <!-- HISTORY TAB -->
        <div id="history-tab" class="tab-content <?php echo $active_page == 'history' ? 'active' : ''; ?>">
            <div class="section-card"><div class="section-header"><h3><i class="fas fa-history" style="color:#8b5cf6;"></i> Completed Jobs</h3><span class="badge"><?php echo count($completed_jobs); ?> Total</span></div>
            <?php if(empty($completed_jobs)): ?><div class="empty-state"><i class="fas fa-history"></i><p>No completed jobs yet</p></div>
            <?php else: ?><div class="table-responsive"><table><thead><tr><th>Job ID</th><th>Customer</th><th>Vehicle</th><th>Service</th><th>Completed</th><th>Hours</th></tr></thead><tbody><?php foreach($completed_jobs as $job): ?><tr><td><strong>#<?php echo $job['id']; ?></strong></td><td><?php echo htmlspecialchars($job['customer']); ?></td><td><?php echo htmlspecialchars($job['brand'] . ' ' . $job['model']); ?><br><small><?php echo $job['license_plate']; ?></small></td><td><?php echo htmlspecialchars($job['service_type']); ?></td><td><?php echo date('M d, Y', strtotime($job['updated_at'])); ?><br><small><?php echo $job['days_ago'] == 0 ? 'Today' : $job['days_ago'] . ' days ago'; ?></small></td><td><?php echo $job['hours_worked'] ?? '—'; ?> hrs</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
        </div>

        <!-- PROFILE TAB -->
        <div id="profile-tab" class="tab-content <?php echo $active_page == 'profile' ? 'active' : ''; ?>">
            <div class="section-card" style="max-width: 600px; margin: 0 auto;"><div class="section-header"><h3><i class="fas fa-user-cog" style="color:#8b5cf6;"></i> Profile Settings</h3></div>
            <form method="POST"><div class="form-group"><label><i class="fas fa-user"></i> Full Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($mechanic['name'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fas fa-envelope"></i> Email Address</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($mechanic['email'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fas fa-phone"></i> Phone Number</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($mechanic['phone'] ?? ''); ?>" required></div>
            <div class="form-group"><label><i class="fas fa-cog"></i> Specialization</label><select name="specialization" class="form-control"><option value="General" <?php echo ($mechanic['specialization'] ?? '') == 'General' ? 'selected' : ''; ?>>General Mechanic</option><option value="Engine" <?php echo ($mechanic['specialization'] ?? '') == 'Engine' ? 'selected' : ''; ?>>Engine Specialist</option><option value="Electrical" <?php echo ($mechanic['specialization'] ?? '') == 'Electrical' ? 'selected' : ''; ?>>Electrical Systems</option><option value="Transmission" <?php echo ($mechanic['specialization'] ?? '') == 'Transmission' ? 'selected' : ''; ?>>Transmission Specialist</option><option value="Brakes" <?php echo ($mechanic['specialization'] ?? '') == 'Brakes' ? 'selected' : ''; ?>>Brake Specialist</option><option value="AC" <?php echo ($mechanic['specialization'] ?? '') == 'AC' ? 'selected' : ''; ?>>AC & Cooling</option></select></div>
            <div style="background:#f8fafc; padding:16px; border-radius:12px; margin-bottom:20px;"><p><i class="fas fa-id-card" style="color:#8b5cf6; width:25px;"></i> <strong>Member since:</strong> <?php echo date('F j, Y', strtotime($mechanic['created_at'] ?? 'now')); ?></p><p><i class="fas fa-briefcase" style="color:#8b5cf6; width:25px;"></i> <strong>Jobs completed:</strong> <?php echo $stats['completed_jobs']; ?></p><p><i class="fas fa-clock" style="color:#8b5cf6; width:25px;"></i> <strong>Total hours worked:</strong> <?php echo $stats['total_hours']; ?> hrs</p><p><i class="fas fa-chart-line" style="color:#8b5cf6; width:25px;"></i> <strong>Avg completion time:</strong> <?php echo $stats['avg_completion_time']; ?> hours</p><p><i class="fas fa-star" style="color:#8b5cf6; width:25px;"></i> <strong>Rating:</strong> ⭐ <?php echo $stats['rating']; ?>/5.0</p></div>
            <button type="submit" name="update_profile" class="btn-accept" style="width:100%;"><i class="fas fa-save"></i> Update Profile</button></form></div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="updateModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-sync-alt"></i> Update Job Status</h3><button class="modal-close" onclick="closeUpdateModal()">&times;</button></div>
<form method="POST"><input type="hidden" name="booking_id" id="update_booking_id"><div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="in_progress">In Progress</option><option value="completed">Completed</option></select></div>
<div class="form-group"><label>Hours Worked</label><input type="number" name="hours_worked" class="form-control" step="0.5" min="0" placeholder="e.g., 2.5"></div>
<div class="form-group"><label>Parts Used</label><textarea name="parts_used" class="form-control" rows="2" placeholder="List any parts used..."></textarea></div>
<div class="form-group"><label>Work Notes</label><textarea name="notes" class="form-control" rows="3" placeholder="Describe what was done..."></textarea></div>
<button type="submit" name="update_status" class="btn-update" style="width:100%;"><i class="fas fa-check-circle"></i> Update Job</button></form></div></div>

<div id="noteModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-sticky-note"></i> Add Work Note</h3><button class="modal-close" onclick="closeNoteModal()">&times;</button></div>
<form method="POST"><input type="hidden" name="booking_id" id="note_booking_id"><div class="form-group"><label>Note</label><textarea name="note" class="form-control" rows="4" placeholder="Enter your note..." required></textarea></div>
<button type="submit" name="add_note" class="btn-update" style="width:100%;"><i class="fas fa-plus-circle"></i> Add Note</button></form></div></div>

<div id="partsModal" class="modal"><div class="modal-content"><div class="modal-header"><h3><i class="fas fa-box"></i> Request Parts</h3><button class="modal-close" onclick="closePartsModal()">&times;</button></div>
<form method="POST"><input type="hidden" name="booking_id" id="parts_booking_id"><div class="form-group"><label>Job ID (Optional)</label><input type="text" id="parts_booking_display" class="form-control" readonly><small style="color:#666;">Leave empty for general parts request</small></div>
<div class="form-group"><label>Parts Needed</label><textarea name="parts" class="form-control" rows="4" placeholder="List parts with quantities..." required></textarea></div>
<div class="form-group"><label>Urgency</label><select name="urgency" class="form-control"><option value="low">Low - Can wait</option><option value="medium">Medium - Needed soon</option><option value="high">High - Urgent, job on hold</option></select></div>
<button type="submit" name="request_parts" class="btn-update" style="width:100%;"><i class="fas fa-paper-plane"></i> Submit Request</button></form></div></div>

<script>
    function updateClock() { const now = new Date(); document.getElementById('realtimeClock').innerHTML = now.toLocaleString('en-US', { weekday:'short', year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true }); }
    updateClock(); setInterval(updateClock, 1000);

    function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('active'); }
    document.addEventListener('click', function(event) { const dropdown = document.getElementById('profileDropdown'); if (!dropdown.contains(event.target)) dropdown.classList.remove('active'); });

    function openUpdateModal(id) { document.getElementById('update_booking_id').value = id; document.getElementById('updateModal').classList.add('active'); }
    function closeUpdateModal() { document.getElementById('updateModal').classList.remove('active'); }
    function openNoteModal(id) { document.getElementById('note_booking_id').value = id; document.getElementById('noteModal').classList.add('active'); }
    function closeNoteModal() { document.getElementById('noteModal').classList.remove('active'); }
    function openPartsRequestModal(id) { document.getElementById('parts_booking_id').value = id || ''; document.getElementById('parts_booking_display').value = id ? 'Job #' + id : 'General Request'; document.getElementById('partsModal').classList.add('active'); }
    function closePartsModal() { document.getElementById('partsModal').classList.remove('active'); }

    window.addEventListener('click', function(event) { if (event.target.classList.contains('modal')) { event.target.classList.remove('active'); } });

    setTimeout(() => { document.querySelectorAll('.message, .error-message').forEach(el => { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }); }, 5000);
</script>
</body>
</html>