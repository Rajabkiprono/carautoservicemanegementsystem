<?php
// config/RBAC.php - Advanced Role-Based Access Control

class RBAC {
    private $db;
    private $userId;
    private $userRole;
    private $permissions = [];
    private $cache = [];
    
    public function __construct($db, $userId = null) {
        $this->db = $db;
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        
        if ($this->userId) {
            $this->loadUserRole();
            $this->loadPermissions();
        }
    }
    
    public function can($permission, $resource = null) {
        // Admin has all permissions
        if ($this->userRole === 'admin') {
            return true;
        }
        
        // Check specific permission
        if (in_array($permission, $this->permissions)) {
            // If resource is provided, check resource-specific permissions
            if ($resource) {
                return $this->checkResourcePermission($permission, $resource);
            }
            return true;
        }
        
        return false;
    }
    
    public function requirePermission($permission, $resource = null) {
        if (!$this->can($permission, $resource)) {
            $this->logUnauthorized($permission, $resource);
            $this->showForbidden();
        }
    }
    
    public function canAccessUser($targetUserId) {
        // Admin can access any user
        if ($this->userRole === 'admin') {
            return true;
        }
        
        // Users can access their own data
        return $this->userId == $targetUserId;
    }
    
    public function canAccessVehicle($vehicleId) {
        // Admin can access any vehicle
        if ($this->userRole === 'admin') {
            return true;
        }
        
        // Check cache
        $cacheKey = "vehicle_$vehicleId";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        // Check if vehicle belongs to user
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM vehicles WHERE id = ?");
            $stmt->execute([$vehicleId]);
            $vehicle = $stmt->fetch();
            
            $result = $vehicle && $vehicle['user_id'] == $this->userId;
            $this->cache[$cacheKey] = $result;
            
            return $result;
        } catch (Exception $e) {
            error_log("Vehicle access check error: " . $e->getMessage());
            return false;
        }
    }
    
    public function canAccessBooking($bookingId) {
        // Admin can access any booking
        if ($this->userRole === 'admin') {
            return true;
        }
        
        // Finance can access all bookings
        if ($this->userRole === 'finance') {
            return true;
        }
        
        // Mechanics can access assigned bookings
        if ($this->userRole === 'mechanic') {
            $stmt = $this->db->prepare("SELECT id FROM bookings WHERE id = ? AND mechanic_id = ?");
            $stmt->execute([$bookingId, $this->userId]);
            return (bool)$stmt->fetch();
        }
        
        // Users can access their own bookings
        $stmt = $this->db->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
        $stmt->execute([$bookingId, $this->userId]);
        return (bool)$stmt->fetch();
    }
    
    public function getAccessibleVehicles() {
        if ($this->userRole === 'admin' || $this->userRole === 'finance') {
            // Return all vehicles
            $stmt = $this->db->query("
                SELECT v.*, u.name as owner_name 
                FROM vehicles v 
                JOIN users u ON v.user_id = u.id 
                ORDER BY v.created_at DESC
            ");
            return $stmt->fetchAll();
        }
        
        // Return user's vehicles only
        $stmt = $this->db->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll();
    }
    
    public function getAccessibleBookings($filters = []) {
        $sql = "SELECT b.*, u.name as customer_name, v.brand, v.model, v.license_plate 
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                JOIN vehicles v ON b.vehicle_id = v.id";
        
        $where = [];
        $params = [];
        
        // Apply role-based filtering
        if ($this->userRole === 'user') {
            $where[] = "b.user_id = ?";
            $params[] = $this->userId;
        } elseif ($this->userRole === 'mechanic') {
            $where[] = "b.mechanic_id = ?";
            $params[] = $this->userId;
        }
        
        // Apply additional filters
        if (!empty($filters['status'])) {
            $where[] = "b.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['from_date'])) {
            $where[] = "b.appointment_date >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "b.appointment_date <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY b.appointment_date DESC, b.appointment_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getDashboardUrl() {
        $dashboards = [
            'admin' => '/admin/dashboard.php',
            'finance' => '/finance/dashboard.php',
            'mechanic' => '/mechanic/dashboard.php',
            'user' => '/user/dashboard.php'
        ];
        
        return $dashboards[$this->userRole] ?? '/user/dashboard.php';
    }
    
    public function getSidebarMenu() {
        $menu = [];
        
        // Common menu items for all users
        $menu[] = ['url' => $this->getDashboardUrl(), 'icon' => 'dashboard', 'label' => 'Dashboard'];
        
        switch ($this->userRole) {
            case 'admin':
                $menu[] = ['url' => '/admin/users.php', 'icon' => 'users', 'label' => 'User Management'];
                $menu[] = ['url' => '/admin/vehicles.php', 'icon' => 'car', 'label' => 'All Vehicles'];
                $menu[] = ['url' => '/admin/services.php', 'icon' => 'tools', 'label' => 'Services'];
                $menu[] = ['url' => '/admin/bookings.php', 'icon' => 'calendar', 'label' => 'All Bookings'];
                $menu[] = ['url' => '/admin/finance.php', 'icon' => 'money', 'label' => 'Finance'];
                $menu[] = ['url' => '/admin/reports.php', 'icon' => 'chart', 'label' => 'Reports'];
                $menu[] = ['url' => '/admin/settings.php', 'icon' => 'cog', 'label' => 'Settings'];
                break;
                
            case 'finance':
                $menu[] = ['url' => '/finance/transactions.php', 'icon' => 'exchange', 'label' => 'Transactions'];
                $menu[] = ['url' => '/finance/invoices.php', 'icon' => 'file-invoice', 'label' => 'Invoices'];
                $menu[] = ['url' => '/finance/payments.php', 'icon' => 'credit-card', 'label' => 'Payments'];
                $menu[] = ['url' => '/finance/reports.php', 'icon' => 'chart-bar', 'label' => 'Reports'];
                break;
                
            case 'mechanic':
                $menu[] = ['url' => '/mechanic/assigned.php', 'icon' => 'clipboard', 'label' => 'Assigned Jobs'];
                $menu[] = ['url' => '/mechanic/history.php', 'icon' => 'history', 'label' => 'Work History'];
                $menu[] = ['url' => '/mechanic/parts-request.php', 'icon' => 'box', 'label' => 'Request Parts'];
                break;
                
            case 'user':
                $menu[] = ['url' => '/user/vehicles.php', 'icon' => 'car', 'label' => 'My Vehicles'];
                $menu[] = ['url' => '/user/services.php', 'icon' => 'tools', 'label' => 'Services'];
                $menu[] = ['url' => '/user/book-service.php', 'icon' => 'calendar-plus', 'label' => 'Book Service'];
                $menu[] = ['url' => '/user/history.php', 'icon' => 'history', 'label' => 'Service History'];
                $menu[] = ['url' => '/user/invoices.php', 'icon' => 'file-invoice', 'label' => 'My Invoices'];
                break;
        }
        
        // Profile menu for all users
        $menu[] = ['url' => '/profile.php', 'icon' => 'user', 'label' => 'Profile'];
        
        return $menu;
    }
    
    private function loadUserRole() {
        try {
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$this->userId]);
            $user = $stmt->fetch();
            $this->userRole = $user ? $user['role'] : 'guest';
        } catch (Exception $e) {
            error_log("Load user role error: " . $e->getMessage());
            $this->userRole = 'guest';
        }
    }
    
    private function loadPermissions() {
        try {
            $stmt = $this->db->prepare("SELECT permission FROM permissions WHERE role = ?");
            $stmt->execute([$this->userRole]);
            $this->permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Load permissions error: " . $e->getMessage());
            $this->permissions = [];
        }
    }
    
    private function checkResourcePermission($permission, $resource) {
        // Implement resource-specific permission checks
        // This could check ownership, team membership, etc.
        return true;
    }
    
    private function logUnauthorized($permission, $resource) {
        try {
            $details = json_encode([
                'permission' => $permission,
                'resource' => $resource,
                'url' => $_SERVER['REQUEST_URI'],
                'method' => $_SERVER['REQUEST_METHOD']
            ]);
            
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address) 
                VALUES (?, 'unauthorized_access', ?, ?)
            ");
            $stmt->execute([$this->userId, $details, $_SERVER['REMOTE_ADDR']]);
        } catch (Exception $e) {
            error_log("Log unauthorized error: " . $e->getMessage());
        }
    }
    
    private function showForbidden() {
        header('HTTP/1.0 403 Forbidden');
        include __DIR__ . '/../views/403.php';
        exit();
    }
}
?>