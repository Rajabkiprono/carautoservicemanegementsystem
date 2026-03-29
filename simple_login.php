<?php
session_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$db_host = 'localhost';
$db_name = 'casms';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = '';

// Handle login
if(isset($_POST['login'])) {
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if($user && password_verify($password, $user['password'])) {
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            // IMMEDIATE REDIRECT - NO QUESTIONS ASKED
            if($user['role'] == 'admin') {
                header("Location: admin_dashboard.php");
                exit();
            } elseif($user['role'] == 'finance') {
                header("Location: finance_dashboard.php");
                exit();
            } elseif($user['role'] == 'mechanic') {
                header("Location: mechanic_dashboard.php");
                exit();
            } else {
                // Check if user has vehicles
                $check = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE user_id = ?");
                $check->execute([$user['id']]);
                $hasVehicles = $check->fetchColumn();
                
                if($hasVehicles == 0) {
                    header("Location: add_vehicle.php");
                    exit();
                } else {
                    header("Location: user_dashboard.php");
                    exit();
                }
            }
            
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CASMS - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .login-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 60px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .logo h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #666;
            font-size: 14px;
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c33;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group label i {
            margin-right: 8px;
            color: #667eea;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .input-wrapper input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .input-wrapper input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        /* Password toggle button */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            font-size: 18px;
            z-index: 2;
            background: transparent;
            border: none;
            padding: 5px;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            cursor: pointer;
        }

        .remember input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .forgot {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .forgot:hover {
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }

        .login-btn i {
            font-size: 18px;
        }

        /* Back to Home Button - Inside Form Style */
        .back-home-btn {
            width: 100%;
            padding: 15px;
            background: transparent;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: #666;
            margin-top: 15px;
        }

        .back-home-btn i {
            font-size: 18px;
        }

        .back-home-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.1);
        }

        .social-login {
            margin-top: 30px;
            text-align: center;
        }

        .social-login p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            position: relative;
        }

        .social-login p::before,
        .social-login p::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: #ddd;
        }

        .social-login p::before {
            left: 0;
        }

        .social-login p::after {
            right: 0;
        }

        .social-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .social-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: white;
        }

        .social-btn i {
            font-size: 18px;
        }

        .social-btn.google {
            background: #DB4437;
        }

        .social-btn.google:hover {
            background: #c53929;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(219,68,55,0.3);
        }

        .social-btn.facebook {
            background: #4267B2;
        }

        .social-btn.facebook:hover {
            background: #365899;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(66,103,178,0.3);
        }

        .social-btn.github {
            background: #333;
        }

        .social-btn.github:hover {
            background: #24292e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(51,51,51,0.3);
        }

        .register {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }

        .register a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .register a:hover {
            text-decoration: underline;
        }

        .terms {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }

        .terms a {
            color: #667eea;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        @media (max-width: 550px) {
            .login-box {
                padding: 30px 20px;
            }
            .logo i {
                font-size: 45px;
            }
            .logo h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <div class="logo">
                <i class="fas fa-car"></i>
                <h2>CASMS</h2>
                <p>Car & Auto Management System</p>
            </div>
            
            <?php if($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="email" placeholder="Enter your email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>

                <div class="options">
                    <label class="remember">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot">Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="login-btn">
                    <span>Login</span>
                    <i class="fas fa-arrow-right"></i>
                </button>

                <!-- Back to Home Button - Inside the Form -->
                <a href="index.php" class="back-home-btn">
                    <i class="fas fa-home"></i>
                    <span>Back to Home</span>
                </a>
            </form>

            <div class="social-login">
                <p>Or login with</p>
                <div class="social-buttons">
                    <a href="#" class="social-btn google" onclick="alert('Google login coming soon!')">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </a>
                    <a href="#" class="social-btn facebook" onclick="alert('Facebook login coming soon!')">
                        <i class="fab fa-facebook-f"></i>
                        <span>Facebook</span>
                    </a>
                    <a href="#" class="social-btn github" onclick="alert('GitHub login coming soon!')">
                        <i class="fab fa-github"></i>
                        <span>GitHub</span>
                    </a>
                </div>
            </div>

            <div class="register">
                Don't have an account? <a href="simple_register.php">Sign up</a>
            </div>

            <div class="terms">
                By logging in, you agree to our <a href="#">Terms</a> and <a href="#">Privacy Policy</a>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle the eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.querySelector('.login-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
        });

        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>