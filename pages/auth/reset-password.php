<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Validate token
if (empty($token)) {
    $error = "Invalid reset link";
} else {
    try {
        $pdo = getDBConnection();
        
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
        
        // Check if token exists first
        $stmt = $pdo->prepare("SELECT id, username, email, reset_token_expiry FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "Invalid reset link";
        } else {
            // Check if token is expired
            $now = new DateTime();
            $expiry = new DateTime($user['reset_token_expiry']);
            
            if ($now > $expiry) {
                $error = "Reset link has expired. Please request a new one.";
            }
        }
    } catch (Exception $e) {
        error_log("Reset password token validation error: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword)) {
        $error = "Please enter a new password";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match";
    } else {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
            $stmt->execute([$hashedPassword, $token]);
            
            $success = "Password has been reset successfully. You can now log in with your new password.";
        } catch (Exception $e) {
            error_log("Reset password update error: " . $e->getMessage());
            $error = "An error occurred while updating your password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - FARMLINK</title>
    <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
    <link rel="stylesheet" href="/FARMLINK/assets/css/auth.css">
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK" class="logo">
        </div>
        
        <h1>Reset Password</h1>
        <p class="subtitle">Enter your new password</p>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?= htmlspecialchars($success) ?>
                <br><br>
                <a href="/FARMLINK/pages/auth/login.php" class="btn">Go to Login</a>
            </div>
        <?php elseif ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php if (strpos($error, 'Invalid') === 0): ?>
                <div class="signup-link">
                    <a href="/FARMLINK/pages/auth/forgot-password.php">Request a new reset link</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST" id="resetPasswordForm">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required minlength="6"
                           placeholder="Enter new password (min 6 characters)">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                           placeholder="Confirm your new password">
                </div>
                
                <button type="submit" class="btn">Reset Password</button>
                
                <div class="signup-link">
                    Remember your password? <a href="/FARMLINK/pages/auth/login.php">Back to Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
            }
        });
    </script>
</body>
</html>
