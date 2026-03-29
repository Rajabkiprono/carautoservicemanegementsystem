<?php
// config/Auth.php - Professional authentication handler

class Auth {
    private $db;
    private $user;
    
    public function __construct($db) {
        $this->db = $db;
        if ($this->isLoggedIn()) {
            $this->loadUser();
        }
    }
    
    public function login($email, $password, $remember = false) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Update login info
                $this->updateLoginInfo($user['id']);
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Set remember me cookie if requested
                if ($remember) {
                    $this->setRememberMeToken($user['id']);
                }
                
                // Log activity
                $this->logActivity($user['id'], 'login', 'User logged in successfully');
                
                return ['success' => true, 'role' => $user['role']];
            }
            
            return ['success' => false, 'message' => 'Invalid email or password'];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    public function register($data) {
        try {
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }
            
            // Check if email exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Validate password
            if (strlen($data['password']) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters'];
            }
            
            if (!preg_match('/[A-Z]/', $data['password'])) {
                return ['success' => false, 'message' => 'Password must contain at least one uppercase letter'];
            }
            
            if (!preg_match('/[0-9]/', $data['password'])) {
                return ['success' => false, 'message' => 'Password must contain at least one number'];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, password, phone, role, verification_token, created_at) 
                VALUES (?, ?, ?, ?, 'user', ?, NOW())
            ");
            
            $stmt->execute([
                $data['name'],
                $data['email'],
                $hashedPassword,
                $data['phone'] ?? null,
                $verificationToken
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Create notification settings
            $this->db->prepare("INSERT INTO notification_settings (user_id) VALUES (?)")->execute([$userId]);
            
            // Send verification email
            $this->sendVerificationEmail($data['email'], $verificationToken);
            
            return ['success' => true, 'message' => 'Registration successful! Please check your email to verify your account.'];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
            
            // Clear remember me token
            $this->clearRememberMeToken($_SESSION['user_id']);
        }
        
        // Clear session
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        // Clear remember me cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            $this->redirectToLogin();
        }
    }
    
    public function hasRole($roles) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $roles);
    }
    
    public function requireRole($roles) {
        if (!$this->hasRole($roles)) {
            header('HTTP/1.0 403 Forbidden');
            include __DIR__ . '/../views/403.php';
            exit();
        }
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        if (!$this->user) {
            $this->loadUser();
        }
        
        return $this->user;
    }
    
    private function loadUser() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $this->user = $stmt->fetch();
        } catch (Exception $e) {
            error_log("Load user error: " . $e->getMessage());
        }
    }
    
    private function updateLoginInfo($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET last_login = NOW(), 
                    login_count = login_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Update login info error: " . $e->getMessage());
        }
    }
    
    private function setRememberMeToken($userId) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->db->prepare("
                UPDATE users SET remember_token = ? WHERE id = ?
            ");
            $stmt->execute([$hashedToken, $userId]);
            
            setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
        } catch (Exception $e) {
            error_log("Set remember token error: " . $e->getMessage());
        }
    }
    
    private function clearRememberMeToken($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users SET remember_token = NULL WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Clear remember token error: " . $e->getMessage());
        }
    }
    
    private function logActivity($userId, $action, $details = null) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $browser = $this->getBrowser($userAgent);
            $platform = $this->getPlatform($userAgent);
            
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, browser, platform) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $details, $ip, $userAgent, $browser, $platform]);
        } catch (Exception $e) {
            error_log("Log activity error: " . $e->getMessage());
        }
    }
    
    private function getBrowser($userAgent) {
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        if (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) return 'IE';
        return 'Unknown';
    }
    
    private function getPlatform($userAgent) {
        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac') !== false) return 'MacOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) return 'iOS';
        return 'Unknown';
    }
    
    private function sendVerificationEmail($email, $token) {
        // Implement email sending here (use PHPMailer or similar)
        // For now, we'll just log it
        error_log("Verification email for $email with token: $token");
    }
    
    private function redirectToLogin() {
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
}
?>