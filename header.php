<?php
if (!isset($rbac) || !isset($db)) {
    require_once 'config/Database.php';
    require_once 'config/RBAC.php';
    require_once 'config/auth.php';
    
    $database = new Database();
    $db = $database->connect();
    $rbac = new RBAC($db, $_SESSION['user_id']);
}

// Get unread notifications count
try {
    $notifStmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $notifStmt->execute([$_SESSION['user_id']]);
    $unreadCount = $notifStmt->fetch()['count'];
} catch (PDOException $e) {
    $unreadCount = 0;
}

// Get user details
$userStmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
$userStmt->execute([$_SESSION['user_id']]);
$user = $userStmt->fetch();

$currentHour = (int)date('H');
$greeting = $currentHour < 12 ? 'Good Morning' : ($currentHour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASMS | <?php echo $pageTitle ?? 'Dashboard'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #1e293b;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background-color: #ffffff;
            border-right: 1px solid #e2e8f0;
            padding: 2rem 1.5rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 2rem;
            text-align: center;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .sidebar a:hover {
            background-color: #f1f5f9;
            color: #2563eb;
        }

        .sidebar a.active {
            background-color: #2563eb;
            color: #ffffff;
        }

        .sidebar a i {
            width: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 2rem;
            background: #ffffff;
            border-radius: 24px;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .welcome-section {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .greeting-badge {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #2563eb;
            background: rgba(37, 99, 235, 0.1);
            padding: 0.2rem 0.75rem;
            border-radius: 20px;
            display: inline-block;
            width: fit-content;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 500;
        }

        .user-name {
            font-weight: 700;
            color: #2563eb;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .btn-icon:hover {
            background: #f1f5f9;
            border-color: #2563eb;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: #f8fafc;
            border-radius: 40px;
            cursor: pointer;
        }

        .avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name-small {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .user-role {
            font-size: 0.7rem;
            color: #64748b;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }

        .role-badge.admin { background: #fee2e2; color: #b91c1c; }
        .role-badge.finance { background: #dbeafe; color: #1e40af; }
        .role-badge.mechanic { background: #fef3c7; color: #92400e; }
        .role-badge.user { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>CASMS</h2>
            <nav>
                <?php if($_SESSION['user_role'] == 'admin'): ?>
                    <a href="/admin/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="/admin/users.php"><i class="fas fa-users"></i> Users</a>
                    <a href="/admin/vehicles.php"><i class="fas fa-car"></i> Vehicles</a>
                    <a href="/admin/services.php"><i class="fas fa-tools"></i> Services</a>
                    <a href="/admin/bookings.php"><i class="fas fa-calendar"></i> Bookings</a>
                    <a href="/admin/finance.php"><i class="fas fa-chart-line"></i> Finance</a>
                    <a href="/admin/spare-parts.php"><i class="fas fa-box"></i> Spare Parts</a>
                    <a href="/admin/reports.php"><i class="fas fa-file-alt"></i> Reports</a>
                    <a href="/admin/settings.php"><i class="fas fa-cog"></i> Settings</a>
                
                <?php elseif($_SESSION['user_role'] == 'finance'): ?>
                    <a href="/finance/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="/finance/transactions.php"><i class="fas fa-exchange-alt"></i> Transactions</a>
                    <a href="/finance/invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a>
                    <a href="/finance/payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                    <a href="/finance/reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                
                <?php elseif($_SESSION['user_role'] == 'mechanic'): ?>
                    <a href="/mechanic/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="/mechanic/assigned.php"><i class="fas fa-clipboard-list"></i> Assigned Jobs</a>
                    <a href="/mechanic/update-status.php"><i class="fas fa-sync"></i> Update Status</a>
                    <a href="/mechanic/parts-request.php"><i class="fas fa-box"></i> Request Parts</a>
                    <a href="/mechanic/history.php"><i class="fas fa-history"></i> Service History</a>
                
                <?php else: ?>
                    <a href="/user/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="/user/vehicles.php"><i class="fas fa-car"></i> My Vehicles</a>
                    <a href="/user/services.php"><i class="fas fa-tools"></i> Services</a>
                    <a href="/user/book-service.php"><i class="fas fa-calendar-plus"></i> Book Service</a>
                    <a href="/user/history.php"><i class="fas fa-history"></i> Service History</a>
                    <a href="/user/spare-parts.php"><i class="fas fa-box"></i> Spare Parts</a>
                    <a href="/user/emergency.php" style="color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> Emergency</a>
                    <a href="/user/profile.php"><i class="fas fa-user"></i> Profile</a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Navbar -->
            <div class="navbar">
                <div class="welcome-section">
                    <span class="greeting-badge"><?php echo strtoupper($_SESSION['user_role']); ?> DASHBOARD</span>
                    <h1 class="welcome-title">
                        <?php echo $greeting; ?>, 
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <span class="role-badge <?php echo $_SESSION['user_role']; ?>">
                            <?php echo ucfirst($_SESSION['user_role']); ?>
                        </span>
                    </h1>
                </div>

                <div class="navbar-right">
                    <button class="btn-icon" onclick="window.location.href='/notifications.php'">
                        <?php if($unreadCount > 0): ?>
                            <span class="notification-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-bell"></i>
                    </button>

                    <div class="user-profile" onclick="window.location.href='/profile.php'">
                        <div class="avatar">
                            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <span class="user-name-small"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            <span class="user-role"><?php echo $rbac->getRoleDisplayName(); ?></span>
                        </div>
                    </div>
                </div>
            </div>