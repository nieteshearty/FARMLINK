<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require super admin role
$user = SessionManager::requireRole('superadmin');

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    // Handle profile picture upload
    $profilePicture = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/FARMLINK/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            $relativePath = '/FARMLINK/uploads/profiles/' . $fileName;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                if ($user['profile_picture'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $user['profile_picture'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $user['profile_picture']);
                }
                $profilePicture = $relativePath;
            }
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
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, profile_picture = ?, password = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $profilePicture, $hashedPassword, $user['id']]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, profile_picture = ? WHERE id = ?");
                $stmt->execute([$username, $email, $profilePicture, $user['id']]);
            }
            
            if (!isset($_SESSION['error'])) {
                // Update session data
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['profile_picture'] = $profilePicture;
                
                $_SESSION['success'] = "Profile updated successfully!";
                SessionManager::logActivity($user['id'], 'profile', "Updated super admin profile");
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update profile.";
    }
    
    header('Location: superadmin-profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>FarmLink • Super Admin Profile</title>
    <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
    <link rel="stylesheet" href="/FARMLINK/style.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/superadmin.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-page="profile">
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">Profile</span>
        </div>
        <div class="nav-right">
            <div class="user-menu">
                <span class="welcome">Welcome, <?= htmlspecialchars($user['username']) ?></span>
                <?php
                $profilePicPath = '';
                if (!empty($user['profile_picture'])) {
                    if (strpos($user['profile_picture'], '/FARMLINK/') === 0) {
                        $profilePicPath = $user['profile_picture'];
                    } elseif (strpos($user['profile_picture'], 'uploads/') === 0) {
                        $profilePicPath = '/FARMLINK/' . $user['profile_picture'];
                    } else {
                        $profilePicPath = '/FARMLINK/uploads/profiles/' . basename($user['profile_picture']);
                    }
                } else {
                    $profilePicPath = '/FARMLINK/assets/img/default-avatar.png';
                }
                ?>
                <img src="<?= $profilePicPath ?>" alt="Profile" class="avatar" onerror="this.src='/FARMLINK/assets/img/default-avatar.png'">
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage-products.php"><i class="fas fa-box"></i> Manage Products</a>
        <a href="system-monitoring.php"><i class="fas fa-desktop"></i> System Monitoring</a>
        <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
        <a href="/FARMLINK/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <h1>Super Admin Profile</h1>
        <p class="lead">Manage your super administrator account settings.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <section class="form-section">
            <form method="POST" enctype="multipart/form-data">
                <h3>Profile Picture</h3>
                <div class="profile-upload">
                    <div class="current-profile">
                        <?php if ($user['profile_picture']): ?>
                            <?php 
                                $profilePicPath = $user['profile_picture'];
                                if (strpos($profilePicPath, '/') !== 0) {
                                    $profilePicPath = '/FARMLINK/uploads/profiles/' . $profilePicPath;
                                }
                                if (strpos($profilePicPath, '/FARMLINK/') !== 0) {
                                    $profilePicPath = '/FARMLINK' . ltrim($profilePicPath, '/');
                                }
                            ?>
                            <img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile Picture" onerror="this.src='/FARMLINK/assets/img/default-avatar.png';" class="current-pic">
                        <?php else: ?>
                            <div class="profile-pic-default current-pic">
                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <label>Current Profile Picture</label>
                    </div>
                    <input type="file" name="profile_picture" id="profilePic" accept="image/*" />
                    <div class="profile-preview" id="profilePreview" style="display:none;">
                        <img id="previewImg" src="" alt="Profile Preview" />
                        <label>New Profile Picture</label>
                    </div>
                </div>
                
                <h3>Basic Information</h3>
                <input name="username" placeholder="Username" value="<?= htmlspecialchars($user['username']) ?>" required />
                <input name="email" type="email" placeholder="Email" value="<?= htmlspecialchars($user['email']) ?>" required />
                
                <h3>Change Password (Optional)</h3>
                <input name="current_password" type="password" placeholder="Current Password" />
                <input name="new_password" type="password" placeholder="New Password" />
                
                <div style="text-align:right; margin-top: 16px;">
                    <button type="submit" class="btn">Update Profile</button>
                </div>
            </form>
        </section>

        <!-- System Information -->
        <section class="card">
            <h3>System Information</h3>
            <div class="system-info">
                <div class="info-item">
                    <strong>Role:</strong> Super Administrator
                </div>
                <div class="info-item">
                    <strong>Access Level:</strong> Full System Control
                </div>
                <div class="info-item">
                    <strong>Last Login:</strong> <?= date('M j, Y g:i A') ?>
                </div>
                <div class="info-item">
                    <strong>Account Created:</strong> <?= date('M j, Y', strtotime($user['created_at'])) ?>
                </div>
            </div>
        </section>
    </main>

    <style>
        .form-section {
            max-width: 600px;
            margin-bottom: 30px;
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
        
        .profile-upload {
            display: flex;
            gap: 20px;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .current-profile, .profile-preview {
            text-align: center;
        }

        .current-pic, .profile-preview img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #4CAF50;
            object-fit: cover;
            margin-bottom: 8px;
        }

        .profile-pic-default {
            background-color: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 24px;
        }

        .profile-upload label {
            font-size: 12px;
            color: #666;
            display: block;
        }

        .system-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
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
        
        .menu-toggle {
            background-color: transparent;
            border: none;
            cursor: pointer;
            font-size: 24px;
            margin-right: 16px;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background-color: #fff;
            padding: 16px;
            display: none;
        }
        
        .sidebar.active {
            display: block;
        }
        
        .sidebar a {
            display: block;
            padding: 12px;
            color: #333;
            text-decoration: none;
        }
        
        .sidebar a:hover {
            background-color: #f8f9fa;
        }
        
        .sidebar a.active {
            background-color: #4CAF50;
            color: #fff;
        }
    </style>

    <script>
        document.getElementById('profilePic').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('profilePreview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
    <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
</body>
</html>
