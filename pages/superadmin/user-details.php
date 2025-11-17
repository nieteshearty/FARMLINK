<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';
require $basePath . '/includes/ImageHelper.php';

// Require super admin role
$user = SessionManager::requireRole('superadmin');

$pdo = getDBConnection();

// Get user ID from URL
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$userId) {
    header('Location: system-monitoring.php?error=invalid_user');
    exit;
}

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as total_orders,
           COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total ELSE 0 END), 0) as total_spent,
           COUNT(DISTINCT p.id) as total_products
    FROM users u
    LEFT JOIN orders o ON u.id = o.buyer_id
    LEFT JOIN products p ON u.id = p.farmer_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$userId]);
$userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userDetails) {
    header('Location: system-monitoring.php?error=user_not_found');
    exit;
}

// Get recent activity
$stmt = $pdo->prepare("
    SELECT * FROM activity_log 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$userId]);
$recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent orders (for buyers)
$recentOrders = [];
if ($userDetails['role'] === 'buyer') {
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as farmer_name 
        FROM orders o
        LEFT JOIN users u ON o.farmer_id = u.id
        WHERE o.buyer_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent products (for farmers)
$recentProducts = [];
if ($userDetails['role'] === 'farmer') {
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE farmer_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>User Details - <?= htmlspecialchars($userDetails['username']) ?> | FarmLink</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/superadmin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">User Details</span>
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
        <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage-products.php"><i class="fas fa-box"></i> Manage Products</a>
        <a href="system-monitoring.php" class="active"><i class="fas fa-desktop"></i> System Monitoring</a>
        <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <div class="page-header">
            <div class="header-content">
                <h1>User Details</h1>
                <a href="system-monitoring.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Monitoring
                </a>
            </div>
        </div>

        <!-- User Profile Card -->
        <div class="card">
            <div class="card-header">
                <h3>Profile Information</h3>
                <div class="user-status">
                    <span class="status-badge <?= ($userDetails['status'] ?? 'inactive') === 'active' ? 'online' : 'offline' ?>">
                        <?= ucfirst($userDetails['status'] ?? 'inactive') ?>
                    </span>
                </div>
            </div>
            <div class="user-profile">
                <div class="profile-avatar">
                    <?php
                    $userProfilePic = BASE_URL . '/assets/img/default-avatar.png';
                    if (!empty($userDetails['profile_picture'])) {
                        if (strpos($userDetails['profile_picture'], BASE_URL . '/') === 0 || strpos($userDetails['profile_picture'], '/FARMLINK/') === 0) {
                            $userProfilePic = $userDetails['profile_picture'];
                        } elseif (strpos($userDetails['profile_picture'], 'uploads/') === 0) {
                            $userProfilePic = BASE_URL . '/' . $userDetails['profile_picture'];
                        } else {
                            $userProfilePic = BASE_URL . '/uploads/profiles/' . basename($userDetails['profile_picture']);
                        }
                    }
                    ?>
                    <img src="<?= $userProfilePic ?>" alt="Profile Picture" onerror="this.src='<?= BASE_URL ?>/assets/img/default-avatar.png'">
                </div>
                <div class="profile-info">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Username:</label>
                            <span><?= htmlspecialchars($userDetails['username']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span><?= htmlspecialchars($userDetails['email']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Role:</label>
                            <span class="role-badge role-<?= $userDetails['role'] ?>"><?= ucfirst($userDetails['role']) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Full Name:</label>
                            <span><?= htmlspecialchars(($userDetails['first_name'] ?? '') . ' ' . ($userDetails['last_name'] ?? '')) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Phone:</label>
                            <span><?= htmlspecialchars($userDetails['phone'] ?? 'Not provided') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Address:</label>
                            <span><?= htmlspecialchars($userDetails['address'] ?? 'Not provided') ?></span>
                        </div>
                        <?php if ($userDetails['role'] === 'farmer'): ?>
                        <div class="info-item">
                            <label>Farm Name:</label>
                            <span><?= htmlspecialchars($userDetails['farm_name'] ?? 'Not provided') ?></span>
                        </div>
                        <div class="info-item">
                            <label>Farm Location:</label>
                            <span><?= htmlspecialchars($userDetails['farm_location'] ?? 'Not provided') ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label>Member Since:</label>
                            <span><?= date('F j, Y', strtotime($userDetails['created_at'])) ?></span>
                        </div>
                        <div class="info-item">
                            <label>Last Activity:</label>
                            <span><?= date('M j, Y g:i A', strtotime($userDetails['updated_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <?php if ($userDetails['role'] === 'buyer'): ?>
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $userDetails['total_orders'] ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>₱<?= number_format($userDetails['total_spent'], 2) ?></h3>
                    <p>Total Spent</p>
                </div>
            </div>
            <?php elseif ($userDetails['role'] === 'farmer'): ?>
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-seedling"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $userDetails['total_products'] ?></h3>
                    <p>Products Listed</p>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>₱<?= number_format($userDetails['total_spent'], 2) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid">
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                </div>
                <div class="activity-list">
                    <?php if (empty($recentActivity)): ?>
                        <p class="no-activity">No recent activity found.</p>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?= getActivityIcon($activity['message']) ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-message">
                                        <?= htmlspecialchars($activity['message']) ?>
                                    </div>
                                    <div class="activity-meta">
                                        <span class="activity-time"><?= timeAgo($activity['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders/Products -->
            <div class="card">
                <div class="card-header">
                    <h3><?= $userDetails['role'] === 'buyer' ? 'Recent Orders' : 'Recent Products' ?></h3>
                </div>
                <div class="list-content">
                    <?php if ($userDetails['role'] === 'buyer'): ?>
                        <?php if (empty($recentOrders)): ?>
                            <p class="no-activity">No orders found.</p>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="list-item">
                                    <div class="item-info">
                                        <strong>Order #<?= $order['id'] ?></strong>
                                        <small>from <?= htmlspecialchars($order['farmer_name']) ?></small>
                                    </div>
                                    <div class="item-meta">
                                        <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                                        <span class="item-value">₱<?= number_format($order['total'], 2) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (empty($recentProducts)): ?>
                            <p class="no-activity">No products found.</p>
                        <?php else: ?>
                            <?php foreach ($recentProducts as $product): ?>
                                <div class="list-item">
                                    <div class="item-info">
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <small><?= htmlspecialchars($product['category']) ?></small>
                                    </div>
                                    <div class="item-meta">
                                        <span class="status-badge status-<?= $product['status'] ?>"><?= ucfirst($product['status']) ?></span>
                                        <span class="item-value">₱<?= number_format($product['price'], 2) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <style>
        .page-header {
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-profile {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }

        .profile-avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #4CAF50;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }

        .info-item span {
            font-size: 1em;
            color: #333;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }

        .role-buyer { background: #e3f2fd; color: #1976d2; }
        .role-farmer { background: #e8f5e8; color: #388e3c; }
        .role-superadmin { background: #fff3e0; color: #f57c00; }

        .user-status {
            display: flex;
            align-items: center;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .item-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .item-value {
            font-weight: 600;
            color: #4CAF50;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        @media (max-width: 768px) {
            .user-profile {
                flex-direction: column;
                gap: 20px;
            }

            .profile-avatar {
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Ensure DOM is loaded before setting up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            window.toggleSidebar = toggleSidebar;
            
            const menuToggle = document.querySelector('.menu-toggle');
            if (menuToggle) {
                menuToggle.addEventListener('click', toggleSidebar);
            }
        });

        // Add click outside to close sidebar on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (sidebar && sidebar.classList.contains('active')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
    <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>

<?php
function getActivityIcon($message) {
    if (strpos($message, 'login') !== false) return 'sign-in-alt';
    if (strpos($message, 'logout') !== false) return 'sign-out-alt';
    if (strpos($message, 'created') !== false) return 'plus-circle';
    if (strpos($message, 'updated') !== false) return 'edit';
    if (strpos($message, 'deleted') !== false) return 'trash';
    if (strpos($message, 'order') !== false) return 'shopping-cart';
    if (strpos($message, 'product') !== false) return 'box';
    return 'bell';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    return date('M j', strtotime($datetime));
}
?>
