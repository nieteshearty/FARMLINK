<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Check if any super admin exists
$superAdminExists = false;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
    $stmt->execute();
    $superAdminExists = $stmt->fetchColumn() > 0;
} catch (PDOException $e) {
    error_log("Signup error: " . $e->getMessage());
    $error = "An error occurred. Please try again later.";
}

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Only allow super_admin role if no super admin exists
    $allowed_roles = $superAdminExists ? ['farmer', 'buyer'] : ['super_admin', 'farmer', 'buyer'];
    
    if (!in_array($role, $allowed_roles)) {
        $error = "Invalid role selected";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        try {
            // Check if username or email already exists
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = "Username or email already exists.";
            } else {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role)
                    VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $username,
                    $email,
                    $hashedPassword,
                    $role
                ]);
                
                // Set success message
                $_SESSION['success'] = "Registration successful! You can now log in.";
                
                // Redirect to login page
                header("Location: " . BASE_URL . "/pages/auth/login.php");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Signup error: " . $e->getMessage());
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
    <title>Create Account - FARMLINK</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css?v=<?= time() . rand(1000, 9999) ?>">
    <style>
        /* FORCE OVERRIDE ALL STYLES - HIGHEST PRIORITY */
        html {
            background: linear-gradient(135deg, 
                #1a4d3a 0%,     /* Deep forest green */
                #2d5016 15%,    /* Dark agricultural green */
                #4a7c59 35%,    /* Medium green */
                #6b9080 55%,    /* Sage green */
                #a4c3b2 75%,    /* Light green-gray */
                #cce3de 90%,    /* Very light mint */
                #e8f5e8 100%    /* Almost white green */
            ) !important;
            min-height: 100vh !important;
        }
        
        body {
            background: linear-gradient(135deg, 
                #1a4d3a 0%,     /* Deep forest green */
                #2d5016 15%,    /* Dark agricultural green */
                #4a7c59 35%,    /* Medium green */
                #6b9080 55%,    /* Sage green */
                #a4c3b2 75%,    /* Light green-gray */
                #cce3de 90%,    /* Very light mint */
                #e8f5e8 100%    /* Almost white green */
            ) !important;
            min-height: 100vh !important;
            margin: 0 !important;
            padding: 20px !important;
        }
        
        /* Override any white backgrounds */
        .auth-wrap {
            background: transparent !important;
        }
        
        .auth-box {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px) !important;
            max-width: 400px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        /* Ensure logo uses auth.css styling */
        .logo-container .logo {
            height: 80px !important;
            width: 80px !important;
        }
        
        .auth-header h1 {
            color: #1B5E20;
            font-size: 1.8rem;
            margin: 0;
        }
        
        input[type="text"], 
        input[type="email"], 
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        button[type="submit"] {
            width: 100%;
            background: #4CAF50;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        button[type="submit"]:hover {
            background: #45a049;
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border: 1px solid #F44336;
        }
            
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
            background-repeat: no-repeat;
            
            /* Ensure full coverage */
            width: 100%;
            height: 100%;
            min-height: 100vh;
            
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
        
        /* Enhanced signup container with farm theme */
        .auth-box {
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
        .auth-box::before {
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
        .auth-box::after {
            content: '🌱';
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 20px;
            opacity: 0.3;
            animation: grow 4s ease-in-out infinite;
        }
        
        @keyframes grow {
            0%, 100% { transform: scale(1) rotate(-2deg); }
            50% { transform: scale(1.1) rotate(2deg); }
        }
        .role-hint {
            font-size: 0.85em;
            color: #666;
            margin: 5px 0 15px;
            padding: 8px 12px;
            background: #f5f5f5;
            border-radius: 4px;
            border-left: 3px solid #4CAF50;
        }
        
        .profile-upload-container {
            position: relative;
            width: 100%;
            height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background-color: #f9f9f9;
        }
        
        .profile-image-container {
            position: relative;
            width: 100%;
            height: 100%;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 16px;
            cursor: pointer;
        }
        
        .upload-icon {
            font-size: 24px;
            margin-right: 10px;
        }
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .has-image {
            border: 1px solid #ccc;
        }
        
        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        /* Profile Upload Styling */
        .profile-upload-container {
            text-align: center;
            margin: 15px 0;
        }
        
        .profile-image-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #E0E0E0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-image-container:hover {
            border-color: #4CAF50;
            transform: scale(1.05);
        }
        
        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .profile-image-container:hover .upload-overlay {
            opacity: 1;
        }
        
        .upload-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .file-input {
            display: none;
        }
        
        .file-info {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        
        .file-requirements {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        /* Role Selection */
        .role-hint {
            background: #F9FFF9;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            color: #666;
            margin-top: 8px;
            border: 1px solid #E8F5E8;
        }
        
        /* Role-specific fields */
        #farmerFields, #buyerFields {
            background: #F9FFF9;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #E8F5E8;
        }
        
        /* Switch link */
        .switch {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .text-link {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 600;
        }
        
        .text-link:hover {
            text-decoration: underline;
        }
        
        /* Button styling */
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            width: 100%;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        button[type="submit"]:hover {
            background: #45a049;
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
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
<body class="auth-page" data-page="signup">
  <div class="auth-wrap">
    <div class="auth-box">
      <div class="auth-header">
        <div class="logo-container">
          <a href="<?= BASE_URL ?>/index.php">
            <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK" class="logo">
          </a>
        </div>
        <h1>Create Account</h1>
      </div>
      
      <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      
      <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
        <input name="username" type="text" placeholder="Username" required 
               value="<?= htmlspecialchars($_POST['username'] ?? $_GET['name'] ?? '') ?>">
        
        <input name="email" type="email" placeholder="Email" required
               value="<?= htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? '') ?>">
        
        <input name="password" type="password" id="password" placeholder="Password" required>
        <input name="confirm_password" type="password" placeholder="Confirm Password" required>
        
        <!-- Profile Picture Upload -->
        <div class="form-group">
            <label>Profile Picture (Optional)</label>
            <div class="profile-upload-container">
                <div class="profile-image-container" onclick="document.getElementById('profilePic').click()">
                    <img id="profilePreview" src="<?= BASE_URL ?>/assets/img/default-avatar.png" alt="Profile Preview" class="profile-image">
                    <div class="upload-overlay">
                        <i class="upload-icon">📷</i>
                        <span>Choose Photo</span>
                    </div>
                </div>
                <input type="file" name="profile_picture" id="profilePic" accept="image/*" 
                       onchange="previewImage(this)" class="file-input">
                <div class="file-info" id="fileInfo">No file chosen</div>
                <div class="file-requirements">JPG, PNG or GIF (max 2MB)</div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="roleSelect">I want to register as:</label>
            <select name="role" id="roleSelect" required onchange="toggleRoleFields()">
                <?php if (!$superAdminExists): ?>
                    <option value="super_admin" <?= (isset($_POST['role']) && $_POST['role'] === 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                <?php endif; ?>
                <option value="farmer" <?= (isset($_POST['role']) && $_POST['role'] === 'farmer') ? 'selected' : '' ?>>Farmer</option>
                <option value="buyer" <?= (isset($_POST['role']) && $_POST['role'] === 'buyer') ? 'selected' : '' ?>>Buyer</option>
            </select>
            <div class="role-hint">
                <strong>Farmers</strong> can list and sell their products.<br>
                <strong>Buyers</strong> can browse and purchase products.
            </div>
        </div>
        
        <!-- Farmer-specific fields -->
        <div id="farmerFields" style="display:<?= (isset($_POST['role']) && $_POST['role'] === 'farmer') ? 'block' : 'none' ?>">
          <input name="farm_name" placeholder="Farm Name" 
                 value="<?= htmlspecialchars($_POST['farm_name'] ?? '') ?>">
          <input name="location" placeholder="Location" 
                 value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
        </div>
        
        <!-- Buyer-specific fields -->
        <div id="buyerFields" style="display:<?= (isset($_POST['role']) && $_POST['role'] === 'buyer') ? 'block' : 'none' ?>">
          <input name="company" placeholder="Company (Optional)" 
                 value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
          <input name="location" placeholder="Location" 
                 value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
        </div>
        
        <div class="form-group" style="margin-top: 20px;">
          <button type="submit" class="btn btn-primary">Create Account</button>
        </div>
        
        <p class="switch">
            Already have an account? 
            <a href="<?= BASE_URL ?>/pages/auth/login.php" class="text-link">Log in here</a>
        </p>
      </form>
    </div>
  </div>

  <script>
    function toggleRoleFields() {
        const role = document.getElementById('roleSelect').value;
        document.getElementById('farmerFields').style.display = role === 'farmer' ? 'block' : 'none';
        document.getElementById('buyerFields').style.display = role === 'buyer' ? 'block' : 'none';
    }
    
    function previewImage(input) {
        const preview = document.getElementById('profilePreview');
        const fileInfo = document.getElementById('fileInfo');
        const file = input.files[0];
        
        if (file) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Please select a valid image file (JPEG, PNG, or GIF)');
                input.value = '';
                return false;
            }
            
            // Validate file size (2MB max)
            const maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                alert('Image size must be less than 2MB');
                input.value = '';
                return false;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                fileInfo.textContent = file.name;
                preview.classList.add('has-image');
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.src = '<?= BASE_URL ?>/assets/img/default-avatar.png';
            fileInfo.textContent = 'No file chosen';
            preview.classList.remove('has-image');
        }
    }
    
    // Click on the profile image to trigger file input
    document.querySelector('.profile-image-container').addEventListener('click', function() {
        document.getElementById('profilePic').click();
    });
    
    function validateForm() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementsByName('confirm_password')[0].value;
        
        if (password !== confirmPassword) {
            alert('Passwords do not match!');
            return false;
        }
        
        if (password.length < 8) {
            alert('Password must be at least 8 characters long');
            return false;
        }
        
        return true;
    }
    
    // Initialize the form when page loads
    document.addEventListener('DOMContentLoaded', function() {
        toggleRoleFields();
    });
  </script>
</body>
</html>
