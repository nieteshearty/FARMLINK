<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require super admin role
$user = SessionManager::requireRole('superadmin');

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle user actions (delete, change role, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $action = $_POST['action'];
        $userId = (int)$_POST['user_id'];
        
        try {
            switch ($action) {
                case 'delete':
                    if ($userId !== $user['id']) {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $message = "User deleted successfully";
                        
                        // Log activity
                        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)");
                        $stmt->execute([$user['id'], "Deleted user with ID: $userId"]);
                    }
                    break;
                    
                case 'change_role':
                    $newRole = $_POST['new_role'] ?? '';
                    if (in_array($newRole, ['admin', 'farmer', 'buyer', 'super_admin'])) {
                        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->execute([$newRole, $userId]);
                        $message = "User role updated successfully";
                        
                        // Log activity
                        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)");
                        $stmt->execute([$user['id'], "Changed user role to $newRole for user ID: $userId"]);
                    }
                    break;
                    
                case 'toggle_status':
                    $stmt = $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
                    $stmt->execute([$userId]);
                    $message = "User status updated successfully";
                    
                    // Log activity
                    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)");
                    $stmt->execute([$user['id'], "Toggled status for user ID: $userId"]);
                    break;
            }
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
            
        } catch (PDOException $e) {
            error_log("User management error: " . $e->getMessage());
            $error = "An error occurred while processing your request";
        }
    }
}

// Get all users with statistics
$users = DatabaseHelper::getAllUsers();

// Get user statistics
$userStats = [
    'total' => count($users),
    'farmers' => count(array_filter($users, fn($u) => $u['role'] === 'farmer')),
    'buyers' => count(array_filter($users, fn($u) => $u['role'] === 'buyer')),
    'admins' => count(array_filter($users, fn($u) => in_array($u['role'], ['admin', 'super_admin']))),
    'active' => count(array_filter($users, fn($u) => ($u['status'] ?? 'active') === 'active'))
];

if (isset($_GET['success'])) {
    $message = "Operation completed successfully";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>FarmLink • Manage Users</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/superadmin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-page="manage-users">
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">Manage Users</span>
        </div>
        <div class="nav-right">
            <div class="user-menu">
                <span class="welcome">Welcome, <?= htmlspecialchars($user['username']) ?></span>
                <?php
                $profilePicPath = '';
                if (!empty($user['profile_picture'])) {
                    if (strpos($user['profile_picture'], BASE_URL . '/') === 0 || strpos($user['profile_picture'], '/FARMLINK/') === 0) {
                        $profilePicPath = $user['profile_picture'];
                    } elseif (strpos($user['profile_picture'], 'uploads/') === 0) {
                        $profilePicPath = BASE_URL . '/' . $user['profile_picture'];
                    } else {
                        $profilePicPath = BASE_URL . '/uploads/profiles/' . basename($user['profile_picture']);
                    }
                } else {
                    $profilePicPath = BASE_URL . '/assets/img/default-avatar.png';
                }
                ?>
                <img src="<?= $profilePicPath ?>" alt="Profile" class="avatar" onerror="this.src='<?= BASE_URL ?>/assets/img/default-avatar.png'">
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-users.php" class="active"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage-products.php"><i class="fas fa-box"></i> Manage Products</a>
        <a href="system-monitoring.php"><i class="fas fa-desktop"></i> System Monitoring</a>
        <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <h1>User Management</h1>
        <p class="lead">Manage all users, roles, and permissions across the platform.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- User Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $userStats['total'] ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?= $userStats['farmers'] ?></h3>
                <p>Farmers</p>
            </div>
            <div class="stat-card">
                <h3><?= $userStats['buyers'] ?></h3>
                <p>Buyers</p>
            </div>
            <div class="stat-card">
                <h3><?= $userStats['admins'] ?></h3>
                <p>Admins</p>
            </div>
            <div class="stat-card">
                <h3><?= $userStats['active'] ?></h3>
                <p>Active Users</p>
            </div>
        </div>

        <!-- Users Table -->
        <section class="card">
            <div class="card-header">
                <h3>All Users</h3>
                <div class="search-box">
                    <input type="text" id="userSearch" placeholder="Search users...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <div class="table-container">
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $userItem): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <?php
                                    $profilePicPath = '';
                                    if (!empty($userItem['profile_picture'])) {
                                        if (strpos($userItem['profile_picture'], BASE_URL . '/') === 0 || strpos($userItem['profile_picture'], '/FARMLINK/') === 0) {
                                            $profilePicPath = $userItem['profile_picture'];
                                        } elseif (strpos($userItem['profile_picture'], 'uploads/') === 0) {
                                            $profilePicPath = BASE_URL . '/' . $userItem['profile_picture'];
                                        } else {
                                            $profilePicPath = BASE_URL . '/uploads/profiles/' . basename($userItem['profile_picture']);
                                        }
                                    }
                                    ?>
                                    <?php if ($profilePicPath): ?>
                                        <img src="<?= $profilePicPath ?>" alt="Profile" class="user-thumb" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="user-thumb-placeholder" style="display: none;">
                                            <?= strtoupper(substr($userItem['username'], 0, 1)) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="user-thumb-placeholder">
                                            <?= strtoupper(substr($userItem['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($userItem['username']) ?></strong>
                                        <br><small>ID: #<?= $userItem['id'] ?></small>
                                        <?php if (isset($userItem['farm_name']) && $userItem['farm_name']): ?>
                                            <br><small><?= htmlspecialchars($userItem['farm_name']) ?></small>
                                        <?php endif; ?>
                                        <?php if (isset($userItem['company']) && $userItem['company']): ?>
                                            <br><small><?= htmlspecialchars($userItem['company']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($userItem['email']) ?></td>
                            <td>
                                <form method="POST" class="role-form" style="display: inline;">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $userItem['id'] ?>">
                                    <select name="new_role" class="role-select" 
                                            onchange="if(confirm('Change user role?')) this.form.submit()" 
                                            <?= $userItem['id'] === $user['id'] ? 'disabled' : '' ?>>
                                        <option value="super_admin" <?= $userItem['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                        <option value="admin" <?= $userItem['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="farmer" <?= $userItem['role'] === 'farmer' ? 'selected' : '' ?>>Farmer</option>
                                        <option value="buyer" <?= $userItem['role'] === 'buyer' ? 'selected' : '' ?>>Buyer</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $userItem['id'] ?>">
                                    <button type="submit" class="status-badge <?= ($userItem['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>" 
                                            onclick="return confirm('Toggle user status?')"
                                            <?= $userItem['id'] === $user['id'] ? 'disabled' : '' ?>>
                                        <?= ucfirst($userItem['status'] ?? 'active') ?>
                                    </button>
                                </form>
                            </td>
                            <td><?= date('M j, Y', strtotime($userItem['created_at'])) ?></td>
                            <td class="actions">
                                <a href="user-details.php?id=<?= $userItem['id'] ?>" class="btn btn-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($userItem['id'] !== $user['id']): ?>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Delete this user? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $userItem['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <style>
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-thumb {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-thumb-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .role-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge:hover {
            opacity: 0.8;
        }

        .status-badge:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-box input {
            padding: 8px 35px 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }

        .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 0.85em;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-container {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .search-box {
                max-width: none;
            }
        }
    </style>

    <script>
        // Search functionality
        document.getElementById('userSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>
