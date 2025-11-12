<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $pdo = getDBConnection();
        // Check if user exists by username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            SessionManager::login($user);
            
            // Set redirect URL based on user role - using relative URLs from web root
            $redirect = match($user['role']) {
                'super_admin' => BASE_URL . '/pages/superadmin/dashboard.php?login=success',
                'admin' => BASE_URL . '/pages/admin/dashboard.php',
                'farmer' => BASE_URL . '/pages/farmer/farmer-dashboard.php',
                'buyer' => BASE_URL . '/pages/buyer/buyer-dashboard.php',
                default => BASE_URL . '/index.php'
            };
            
            header("Location: $redirect");
            exit;
        } else {
            $error = "Invalid username or password";
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FARMLINK</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css?v=<?= time() ?>">
    <style>
        /* Agricultural Theme Background */
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            
            /* Beautiful agricultural gradient */
            background: linear-gradient(135deg, 
                #1a4d3a 0%,     /* Deep forest green */
                #2d5016 15%,    /* Dark agricultural green */
                #4a7c59 35%,    /* Medium green */
                #6b9080 55%,    /* Sage green */
                #a4c3b2 75%,    /* Light green-gray */
                #cce3de 90%,    /* Very light mint */
                #e8f5e8 100%    /* Almost white green */
            ) !important;
            
            /* Add farm field texture */
            background-image: 
                /* Field rows pattern */
                repeating-linear-gradient(
                    45deg,
                    transparent,
                    transparent 10px,
                    rgba(255, 255, 255, 0.03) 10px,
                    rgba(255, 255, 255, 0.03) 20px
                ),
                /* Organic dots like seeds */
                radial-gradient(circle at 25% 25%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
                radial-gradient(circle at 75% 75%, rgba(255, 255, 255, 0.08) 1px, transparent 1px),
                /* Subtle leaf pattern */
                radial-gradient(ellipse at 50% 20%, rgba(139, 195, 74, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 20% 80%, rgba(76, 175, 80, 0.08) 0%, transparent 50%);
            
            background-size: 
                40px 40px,      /* Field rows */
                60px 60px,      /* Large dots */
                80px 80px,      /* Small dots */
                200px 200px,    /* Leaf pattern 1 */
                150px 150px;    /* Leaf pattern 2 */
            
            background-attachment: fixed;
            
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            
            /* Gentle animation */
            animation: farmBreeze 25s ease-in-out infinite;
        }
        
        @keyframes farmBreeze {
            0%, 100% {
                background-position: 0 0, 0 0, 30px 30px, 0 0, 50px 50px;
            }
            33% {
                background-position: 10px 5px, 15px 15px, 45px 35px, 20px 10px, 70px 60px;
            }
            66% {
                background-position: 5px 10px, 25px 5px, 35px 45px, 10px 20px, 60px 70px;
            }
        }
        
        /* Enhanced login container with farm theme */
        .login-container {
            background: rgba(255, 255, 255, 0.92) !important;
            backdrop-filter: blur(12px) !important;
            border: 2px solid rgba(76, 175, 80, 0.2) !important;
            border-radius: 20px !important;
            box-shadow: 
                0 20px 40px rgba(45, 80, 22, 0.15),
                0 8px 16px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.6) !important;
            position: relative;
            overflow: hidden;
        }
        
        /* Add a subtle farm border */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, 
                #2d5016, #4a7c59, #6b9080, #a4c3b2, #6b9080, #4a7c59, #2d5016
            );
            border-radius: 20px 20px 0 0;
        }
        
        /* Farm-themed decorative elements */
        .login-container::after {
            content: '🌾';
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 20px;
            opacity: 0.3;
            animation: sway 3s ease-in-out infinite;
        }
        
        @keyframes sway {
            0%, 100% { transform: rotate(-2deg); }
            50% { transform: rotate(2deg); }
        }
        
        /* Logo link styling */
        .logo-container a {
            display: inline-block;
            text-decoration: none;
            cursor: pointer;
        }
        
        .logo-container a:hover .logo {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <a href="<?= BASE_URL ?>/index.php">
                <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK" class="logo">
            </a>
        </div>
        <h1>Welcome Back</h1>
        <p class="subtitle">Please sign in to your account</p>
        
        <?php if (isset($_GET['logout'])): ?>
            <div class="success-message">You have been logged out successfully.</div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">Email or Username</label>
                <input type="text" id="username" name="username" class="form-control" required 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label for="password">Password</label>
                    <a href="<?= BASE_URL ?>/pages/auth/forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn">Log in</button>
            
            <p class="signup-link">
                Don't have an account? <a href="<?= BASE_URL ?>/pages/auth/signup.php">Sign up</a>
            </p>
        </form>
    </div>
    
    <script>
        // Add smooth focus states for keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });
        
        // Remove focus styles on mouse click
        document.addEventListener('mousedown', function() {
            document.body.classList.remove('keyboard-navigation');
        });
        
        // Focus the username field on page load
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField) {
                usernameField.focus();
            }
        });
    </script>
</body>
</html>
