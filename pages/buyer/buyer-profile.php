<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/ImageHelper.php';

// Require buyer role
$user = SessionManager::requireRole('buyer');

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $company = trim($_POST['company']);
    $location = trim($_POST['location']);
    $phoneNumber = trim($_POST['phone_number']);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    // Handle profile picture upload
    $profilePicture = $user['profile_picture'] ?? '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/profiles/';
        
        // Ensure upload directory exists and is writable
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $_SESSION['error'] = 'Failed to create upload directory. Check server permissions.';
                header('Location: buyer-profile.php');
                exit();
            }
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            // Generate unique filename
            $fileName = uniqid('profile_') . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                // Delete old profile picture if it exists and it's not the default
                if (!empty($user['profile_picture'])) {
                    $oldPicturePath = $uploadDir . basename($user['profile_picture']);
                    if (file_exists($oldPicturePath) && !str_contains($oldPicturePath, 'default-avatar')) {
                        @unlink($oldPicturePath);
                    }
                }
                // Store only the filename in the database
                $profilePicture = $fileName;
            } else {
                $_SESSION['error'] = 'Failed to upload file. Please try again.';
                error_log('Failed to move uploaded file to: ' . $uploadPath);
            }
        } else {
            $_SESSION['error'] = 'Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.';
        }
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if username/email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user['id']]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username or email already exists.";
        } else {
            // Update profile
            if (!empty($newPassword)) {
                if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                    $_SESSION['error'] = "Current password is incorrect.";
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, company = ?, location = ?, phone_number = ?, profile_picture = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $company, $location, $phoneNumber, $profilePicture, $hashedPassword, $user['id']]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, company = ?, location = ?, phone_number = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$username, $email, $company, $location, $phoneNumber, $profilePicture, $user['id']]);
            }
            
            if (!isset($_SESSION['error']) && !isset($_SESSION['upload_error'])) {
                // Update session data
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['company'] = $company;
                $_SESSION['user']['location'] = $location;
                $_SESSION['user']['phone_number'] = $phoneNumber;
                $_SESSION['user']['profile_picture'] = $profilePicture;
                
                $_SESSION['success'] = "Profile updated successfully!";
                SessionManager::logActivity($user['id'], 'profile', "Updated profile information");
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update profile.";
    }
    
    header('Location: buyer-profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>FarmLink • Profile</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/buyer.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
</head>
<body data-page="buyer-profile">
  <nav>
    <div class="nav-left">
      <a href="buyer-dashboard.php"><img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK" class="logo"></a>
      <span class="brand">FARMLINK - BUYER</span>
    </div>
    <span>Profile</span>
  </nav>

  <div class="sidebar">
    <a href="buyer-dashboard.php">Dashboard</a>
    <a href="buyer-market.php">Browse Market</a>
    <a href="buyer-cart.php">Shopping Cart</a>
    <a href="buyer-orders.php">My Orders</a>
    <a href="buyer-profile.php" class="active">Profile</a>
    <a href="<?= BASE_URL ?>/pages/auth/logout.php">Logout</a>
  </div>

  <main class="main">
    <h1>Profile Settings</h1>
    <p class="lead">Update your account information.</p>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="form-section">
      <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
        <h3>Profile Picture</h3>
        <div class="profile-upload">
          <div class="current-profile">
            <?php
              $currentProfilePic = ImageHelper::normalizeImagePath($user['profile_picture'] ?? '', 'profiles');
            ?>
            <?php if (!empty($currentProfilePic)): ?>
              <img src="<?= htmlspecialchars($currentProfilePic) ?>"
                   alt="Profile Picture"
                   onerror="this.onerror=null; this.src='<?= BASE_URL ?>/assets/img/default-avatar.png';"
                   class="current-pic">
            <?php else: ?>
              <div class="profile-pic-default current-pic">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <label>Current Profile Picture</label>
          </div>
          <input type="file" name="profile_picture" id="profilePic" accept="image/jpeg,image/png,image/gif" required />
          <div class="file-info" id="fileInfo">No file selected</div>
          <?php if (isset($_SESSION['upload_error'])): ?>
            <div class="error-message"><?= htmlspecialchars($_SESSION['upload_error']); unset($_SESSION['upload_error']); ?></div>
          <?php endif; ?>
        </div>
        
        <h3>Basic Information</h3>
        <input name="username" placeholder="Username" value="<?= htmlspecialchars($user['username']) ?>" required />
        <input name="email" type="email" placeholder="Email" value="<?= htmlspecialchars($user['email']) ?>" required />
        <input name="company" placeholder="Company" value="<?= htmlspecialchars($user['company'] ?? '') ?>" />
        <input name="location" placeholder="Location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" />
        <input name="phone_number" type="tel" placeholder="Phone Number (e.g., +63 912 345 6789)" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" />
        
        <h3>Change Password (Optional)</h3>
        <input name="current_password" type="password" placeholder="Current Password" />
        <input name="new_password" type="password" placeholder="New Password" />
        
        <div style="text-align:right; margin-top: 16px;">
          <button type="submit" class="btn">Update Profile</button>
        </div>
      </form>
    </section>
  </main>

  <style>
    /* Force agricultural green sidebar background */
    .sidebar {
      background: #2E7D32 !important;
    }
    
    .form-section {
      max-width: 500px;
    }
    
    .form-section input {
      width: 100%;
      margin: 8px 0;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    
    .form-section h3 {
      margin-top: 24px;
      margin-bottom: 12px;
      color: #2d6a4f;
    }
    
    .alert {
      padding: 12px;
      margin: 16px 0;
      border-radius: 4px;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .logo {
      width: 40px;
      height: 40px;
      object-fit: cover;
      transition: all 0.3s ease;
    }
    
    .logo:hover {
      transform: scale(1.1);
      box-shadow: 0 0 10px rgba(45, 106, 79, 0.5);
    }
  </style>
  
  <?php
  $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/uploads/profiles/';
  if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
  }
  ?>

  <div class="profile-upload">
    <style>
    .profile-upload {
      margin: 15px 0;
    }
    .current-profile {
      text-align: center;
      margin-bottom: 15px;
    }
    .current-pic {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #2d6a4f;
      margin-bottom: 10px;
    }
    .profile-pic-default {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      background-color: #2d6a4f;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      font-weight: bold;
      margin: 0 auto 10px;
    }
    .file-info {
      margin: 10px 0;
      padding: 5px;
      font-size: 14px;
    }
    .error-message {
      color: #dc3545;
      margin: 10px 0;
      padding: 10px;
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      border-radius: 4px;
    }
    </style>
  </div>

  <script>
  // Client-side form validation
  function validateForm() {
    const fileInput = document.getElementById('profilePic');
    const fileInfo = document.getElementById('fileInfo');
    
    if (fileInput.files.length > 0) {
      const file = fileInput.files[0];
      const fileType = file.type;
      const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
      
      if (!validTypes.includes(fileType)) {
        fileInfo.textContent = 'Invalid file type. Please select a JPG, PNG, or GIF image.';
        fileInfo.style.color = 'red';
        return false;
      }
      
      if (file.size > 2 * 1024 * 1024) { // 2MB max
        fileInfo.textContent = 'File is too large. Maximum size is 2MB.';
        fileInfo.style.color = 'red';
        return false;
      }
      
      fileInfo.textContent = 'Selected: ' + file.name;
      fileInfo.style.color = 'green';
    }
    
    return true;
  }

  // Show file info when a file is selected
  document.getElementById('profilePic').addEventListener('change', function() {
    const fileInfo = document.getElementById('fileInfo');
    if (this.files.length > 0) {
      fileInfo.textContent = 'Selected: ' + this.files[0].name;
      fileInfo.style.color = 'green';
    } else {
      fileInfo.textContent = 'No file selected';
    }
  });
  </script>

</div>

  <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
