<?php
session_start();

// CSRF Protection
if(empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=casms", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

$error = '';
$success = '';
$form_data = [];

// Handle OAuth registration data
if(isset($_SESSION['oauth_data'])) {
    $form_data = $_SESSION['oauth_data'];
}

// Handle registration
if(isset($_POST['register'])) {
    // CSRF check
    if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid request origin");
    }
    
    // Sanitize inputs
    $name = trim(filter_var($_POST['name'], FILTER_SANITIZE_STRING));
    $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
    $phone = trim(filter_var($_POST['phone'], FILTER_SANITIZE_STRING));
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validation
    $errors = [];
    
    if(empty($name)) {
        $errors[] = "Full name is required";
    } elseif(strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters";
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if(empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif(!preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
        $errors[] = "Valid phone number is required";
    }
    
    if(strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    } elseif(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
    }
    
    if($password != $confirm) {
        $errors[] = "Passwords do not match";
    }
    
    if(!$terms) {
        $errors[] = "You must agree to the Terms of Service";
    }
    
    if(empty($errors)) {
        // Check if email exists
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        
        if($check->fetch()) {
            $error = "Email already registered. <a href='login.php'>Login instead?</a>";
        } else {
            // Check if phone exists
            $check_phone = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $check_phone->execute([$phone]);
            
            if($check_phone->fetch()) {
                $error = "Phone number already registered";
            } else {
                // Generate email verification token
                $verification_token = bin2hex(random_bytes(32));
                
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, verification_token, created_at) VALUES (?, ?, ?, ?, 'user', ?, NOW())");
                
                if($stmt->execute([$name, $email, $phone, $hashed, $verification_token])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Send verification email
                    sendVerificationEmail($email, $name, $verification_token);
                    
                    // Clear OAuth session data
                    unset($_SESSION['oauth_data']);
                    
                    $success = "Registration successful! Please check your email to verify your account.";
                    
                    // Auto-login option (commented for security)
                    // $_SESSION['user_id'] = $user_id;
                    // header("Location: user_dashboard.php");
                    // exit();
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
    
    // Preserve form data
    $form_data = $_POST;
}

// Function to send verification email
function sendVerificationEmail($email, $name, $token) {
    $subject = "Verify Your CASMS Account";
    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=" . $token;
    
    $message = "
    <html>
    <head>
        <title>Email Verification</title>
    </head>
    <body>
        <h2>Welcome to CASMS, $name!</h2>
        <p>Please click the link below to verify your email address:</p>
        <p><a href='$verification_link'>Verify Email Address</a></p>
        <p>If you didn't create an account, you can ignore this email.</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@casms.com" . "\r\n";
    
    mail($email, $subject, $message, $headers);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - CASMS Professional</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            padding: 20px;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
            animation: shine 15s ease-in-out infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        .register-container {
            width: 100%;
            max-width: 500px;
            position: relative;
            z-index: 1;
        }

        .register-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .register-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.4);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 50px;
            color: #667eea;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo h2 {
            font-size: 28px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-top: 10px;
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }

        .progress-bar::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #ddd;
            transform: translateY(-50%);
            z-index: 1;
        }

        .progress-step {
            width: 30px;
            height: 30px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #999;
            position: relative;
            z-index: 2;
            background: white;
        }

        .progress-step.active {
            border-color: #667eea;
            color: #667eea;
        }

        .progress-step.completed {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
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
            transition: color 0.3s ease;
        }

        .input-wrapper input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #eee;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .input-wrapper input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .input-wrapper input:hover {
            border-color: #764ba2;
        }

        .input-wrapper:focus-within i {
            color: #667eea;
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #eee;
            border-radius: 2px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .password-strength-text {
            font-size: 12px;
            margin-top: 5px;
            text-align: right;
        }

        .checkbox-group {
            margin-bottom: 20px;
        }

        .checkbox-label {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
            color: #555;
            font-size: 14px;
        }

        .checkbox-label input {
            width: auto;
            margin-right: 10px;
            margin-top: 3px;
            cursor: pointer;
        }

        .checkbox-label a {
            color: #667eea;
            text-decoration: none;
        }

        .checkbox-label a:hover {
            text-decoration: underline;
        }

        .register-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .register-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .register-btn:hover::after {
            width: 300px;
            height: 300px;
        }

        /* Back to Home Button - Inside Form Style */
        .back-home-btn {
            width: 100%;
            padding: 15px;
            background: transparent;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
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
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .social-register {
            margin-top: 30px;
            text-align: center;
        }

        .social-register p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            position: relative;
        }

        .social-register p::before,
        .social-register p::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: #ddd;
        }

        .social-register p::before {
            left: 0;
        }

        .social-register p::after {
            right: 0;
        }

        .social-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
        }

        .social-btn {
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
            gap: 8px;
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
            box-shadow: 0 5px 15px rgba(219, 68, 55, 0.3);
        }

        .social-btn.facebook {
            background: #4267B2;
        }

        .social-btn.facebook:hover {
            background: #365899;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(66, 103, 178, 0.3);
        }

        .social-btn.github {
            background: #333;
        }

        .social-btn.github:hover {
            background: #24292e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(51, 51, 51, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 600px) {
            .register-box {
                padding: 30px 20px;
            }

            .social-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="logo">
                <i class="fas fa-user-plus"></i>
                <h2>Create Account</h2>
            </div>

            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-step completed">1</div>
                <div class="progress-step">2</div>
                <div class="progress-step">3</div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="input-group">
                    <label for="name">Full Name</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="name" name="name" 
                               placeholder="Enter your full name" required
                               value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" 
                               placeholder="Enter your email" required
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" 
                               placeholder="Enter your phone number" required
                               value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Create a password" required>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="password-strength-text" id="strengthText"></div>
                </div>

                <div class="input-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm your password" required>
                    </div>
                </div>

                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span>I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a></span>
                    </label>
                </div>

                <button type="submit" name="register" class="register-btn" id="registerBtn">
                    <span>Create Account</span>
                </button>

                <!-- Back to Home Button - Inside the Form -->
                <a href="index.php" class="back-home-btn">
                    <i class="fas fa-home"></i>
                    <span>Back to Home</span>
                </a>
            </form>

            <div class="social-register">
                <p>Or sign up with</p>
                <div class="social-buttons">
                    <a href="oauth/google.php" class="social-btn google">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </a>
                    <a href="oauth/facebook.php" class="social-btn facebook">
                        <i class="fab fa-facebook-f"></i>
                        <span>Facebook</span>
                    </a>
                    <a href="oauth/github.php" class="social-btn github">
                        <i class="fab fa-github"></i>
                        <span>GitHub</span>
                    </a>
                </div>
            </div>

            <div class="login-link">
                Already have an account? <a href="simple_login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        // Password strength meter
        const password = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        password.addEventListener('input', function() {
            const val = this.value;
            let strength = 0;
            
            if(val.length >= 8) strength++;
            if(val.match(/[a-z]+/)) strength++;
            if(val.match(/[A-Z]+/)) strength++;
            if(val.match(/[0-9]+/)) strength++;
            if(val.match(/[$@#&!]+/)) strength++;
            
            const width = (strength / 5) * 100;
            strengthBar.style.width = width + '%';
            
            let color, text;
            switch(strength) {
                case 0:
                case 1:
                    color = '#ff4444';
                    text = 'Weak';
                    break;
                case 2:
                case 3:
                    color = '#ffbb33';
                    text = 'Medium';
                    break;
                case 4:
                case 5:
                    color = '#00C851';
                    text = 'Strong';
                    break;
            }
            
            strengthBar.style.background = color;
            strengthText.innerHTML = text;
            strengthText.style.color = color;
        });

        // Password match validation
        const confirmPassword = document.getElementById('confirm_password');
        
        function checkPasswordMatch() {
            if(confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        password.addEventListener('change', checkPasswordMatch);
        confirmPassword.addEventListener('keyup', checkPasswordMatch);

        // Form submission with loading
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('registerBtn');
            btn.innerHTML = '<span class="loading"></span> Creating Account...';
            btn.disabled = true;
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>