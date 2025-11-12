<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Add reset token columns if they don't exist
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL");
                } catch (Exception $e) {
                    // Column already exists, ignore
                }
                try {
                    $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME NULL");
                } catch (Exception $e) {
                    // Column already exists, ignore
                }
                
                // Store reset token in database
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
                $stmt->execute([$resetToken, $resetExpiry, $email]);
                
                // In a real application, you would send an email here
                // For demo purposes, we'll redirect directly to reset page
                $resetLink = "/FARMLINK/pages/auth/reset-password.php?token=" . urlencode($resetToken);
                
                // Set success flag for JavaScript redirect
                $success = true;
                $redirectUrl = $resetLink;
                
                // Debug: Log the token for verification
                error_log("Generated reset token: " . $resetToken);
                error_log("Reset link: " . $resetLink);
            } else {
                // Don't reveal if email exists or not for security
                $message = "If an account with that email exists, password reset instructions have been sent.";
            }
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            $error = "An error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - FARMLINK</title>
    <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
    <link rel="stylesheet" href="/FARMLINK/assets/css/auth.css">
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK" class="logo">
        </div>
        
        <h1>Forgot Password</h1>
        <p class="subtitle">Enter your email address to reset your password</p>
        
        <?php if (isset($success) && $success): ?>
            <div class="success-message">
                <div class="loading-spinner"></div>
                <p>✅ Email verified! Redirecting to password reset...</p>
            </div>
            <script>
                // Auto-redirect after 2 seconds
                console.log('Redirect URL: <?= $redirectUrl ?>');
                setTimeout(function() {
                    console.log('Redirecting to: <?= $redirectUrl ?>');
                    window.location.href = '<?= $redirectUrl ?>';
                }, 2000);
            </script>
        <?php else: ?>
            <?php if ($message): ?>
                <div class="success-message"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" id="forgotPasswordForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="Enter your email address"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn" id="submitBtn">
                    <span class="btn-text">Send Reset Link</span>
                    <span class="btn-loading" style="display: none;">
                        <div class="loading-spinner"></div>
                        Processing...
                    </span>
                </button>
                
                <div class="signup-link">
                    Remember your password? <a href="/FARMLINK/pages/auth/login.php">Back to Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Handle form submission with loading state
        document.getElementById('forgotPasswordForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            
            // Show loading state
            btnText.style.display = 'none';
            btnLoading.style.display = 'flex';
            submitBtn.disabled = true;
            
            // Allow form to submit normally
            // The loading state will be visible until page reloads
        });
        
        // Auto-focus email input
        document.getElementById('email')?.focus();
    </script>
</body>
</html>
