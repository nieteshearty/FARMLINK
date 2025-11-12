<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/DatabaseHelper.php';

// Require super admin role
$user = SessionManager::requireRole('superadmin');

// Get monitoring data
$pdo = getDBConnection();

// Get active users (logged in within last 24 hours)
$activeUsers = $pdo->query("
    SELECT u.*, al.created_at as last_activity 
    FROM users u 
    JOIN activity_log al ON u.id = al.user_id 
    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) 
    GROUP BY u.id 
    ORDER BY al.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get suspicious activities
$suspiciousActivities = $pdo->query("
    SELECT al.*, u.username, u.role 
    FROM activity_log al 
    JOIN users u ON al.user_id = u.id 
    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
    AND (al.message LIKE '%failed%' OR al.message LIKE '%error%' OR al.message LIKE '%unauthorized%')
    ORDER BY al.created_at DESC 
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Get top farmers by activity
$topFarmers = $pdo->query("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as product_count,
           COUNT(DISTINCT o.id) as order_count,
           COALESCE(SUM(o.total), 0) as total_sales
    FROM users u 
    LEFT JOIN products p ON u.id = p.farmer_id 
    LEFT JOIN orders o ON u.id = o.farmer_id AND o.status = 'completed'
    WHERE u.role = 'farmer' 
    GROUP BY u.id 
    ORDER BY total_sales DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get top buyers by activity
$topBuyers = $pdo->query("
    SELECT u.*, 
           COUNT(DISTINCT o.id) as order_count,
           COALESCE(SUM(o.total), 0) as total_spent
    FROM users u 
    LEFT JOIN orders o ON u.id = o.buyer_id AND o.status = 'completed'
    WHERE u.role = 'buyer' 
    GROUP BY u.id 
    ORDER BY total_spent DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get system metrics
$systemMetrics = DatabaseHelper::getSystemMetrics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>FarmLink • System Monitoring</title>
    <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
    <link rel="stylesheet" href="/FARMLINK/style.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/superadmin.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-page="system-monitoring">
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">System Monitoring</span>
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
        <a href="system-monitoring.php" class="active"><i class="fas fa-desktop"></i> System Monitoring</a>
        <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="/FARMLINK/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <h1>System Monitoring</h1>
        <p class="lead">Monitor user activities, system performance, and security.</p>

        <!-- Real-time Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= count($activeUsers) ?></h3>
                <p>Active Users (24h)</p>
            </div>
            <div class="stat-card">
                <h3><?= count($suspiciousActivities) ?></h3>
                <p>Security Alerts (7d)</p>
            </div>
            <div class="stat-card">
                <h3><?= $systemMetrics['orders_by_status']['pending'] ?? 0 ?></h3>
                <p>Pending Orders</p>
            </div>
            <div class="stat-card">
                <h3>₱<?= number_format($systemMetrics['monthly_sales'][0]['revenue'] ?? 0, 2) ?></h3>
                <p>This Month Revenue</p>
            </div>
        </div>

        <!-- Active Users -->
        <section class="card">
            <h3>Active Users (Last 24 Hours)</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Last Activity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeUsers as $activeUser): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <?php if ($activeUser['profile_picture']): ?>
                                            <?php
                                            // Robust profile picture path handling
                                            $profileValue = trim($activeUser['profile_picture']);
                                            $profileUrl = '';
                                            
                                            if (strpos($profileValue, 'http') === 0) {
                                                $profileUrl = $profileValue;
                                            } elseif (strpos($profileValue, '/FARMLINK/') === 0) {
                                                $profileUrl = $profileValue;
                                            } elseif (strpos($profileValue, 'uploads/profiles/') === 0) {
                                                $profileUrl = '/FARMLINK/' . $profileValue;
                                            } elseif (strpos($profileValue, '/') === 0) {
                                                $profileUrl = '/FARMLINK' . $profileValue;
                                            } else {
                                                $profileUrl = '/FARMLINK/uploads/profiles/' . basename($profileValue);
                                            }
                                            ?>
                                            <img src="<?= htmlspecialchars($profileUrl) ?>" 
                                                 alt="Profile" class="user-thumb"
                                                 onerror="this.src='/FARMLINK/assets/img/default-avatar.png';">
                                        <?php else: ?>
                                            <div class="user-thumb-placeholder">
                                                <?= strtoupper(substr($activeUser['username'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($activeUser['username']) ?></strong>
                                            <br><small><?= htmlspecialchars($activeUser['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="role-badge <?= $activeUser['role'] ?>"><?= ucfirst($activeUser['role']) ?></span></td>
                                <td><?= date('M j, g:i A', strtotime($activeUser['last_activity'])) ?></td>
                                <td><span class="status-badge online">Online</span></td>
                                <td>
                                    <a href="user-details.php?id=<?= $activeUser['id'] ?>" class="btn btn-sm">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Top Performers -->
        <div class="monitoring-grid">
            <!-- Top Farmers -->
            <section class="card">
                <h3>Top Performing Farmers</h3>
                <div class="performer-list">
                    <?php foreach ($topFarmers as $farmer): ?>
                        <div class="performer-item">
                            <div class="performer-info">
                                <strong><?= htmlspecialchars($farmer['username']) ?></strong>
                                <small><?= htmlspecialchars($farmer['farm_name'] ?? 'No farm name') ?></small>
                            </div>
                            <div class="performer-stats">
                                <div>₱<?= number_format($farmer['total_sales'], 2) ?></div>
                                <small><?= $farmer['product_count'] ?> products, <?= $farmer['order_count'] ?> orders</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Top Buyers -->
            <section class="card">
                <h3>Top Active Buyers</h3>
                <div class="performer-list">
                    <?php foreach ($topBuyers as $buyer): ?>
                        <div class="performer-item">
                            <div class="performer-info">
                                <strong><?= htmlspecialchars($buyer['username']) ?></strong>
                                <small><?= htmlspecialchars($buyer['company'] ?? 'Individual buyer') ?></small>
                            </div>
                            <div class="performer-stats">
                                <div>₱<?= number_format($buyer['total_spent'], 2) ?></div>
                                <small><?= $buyer['order_count'] ?> orders</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <!-- Security Alerts -->
        <section class="card">
            <h3>Security & System Alerts</h3>
            <div class="alert-list">
                <?php if (empty($suspiciousActivities)): ?>
                    <p class="no-alerts">No security alerts in the past 7 days. System is running smoothly.</p>
                <?php else: ?>
                    <?php foreach ($suspiciousActivities as $alert): ?>
                        <div class="alert-item">
                            <div class="alert-icon">⚠️</div>
                            <div class="alert-content">
                                <div class="alert-message"><?= htmlspecialchars($alert['message']) ?></div>
                                <div class="alert-meta">
                                    <span class="alert-user"><?= htmlspecialchars($alert['username']) ?> (<?= $alert['role'] ?>)</span>
                                    <span class="alert-time"><?= date('M j, g:i A', strtotime($alert['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <style>
        .monitoring-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

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

        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .role-badge.farmer {
            background: #e8f5e8;
            color: #2d5016;
        }

        .role-badge.buyer {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .role-badge.admin, .role-badge.super_admin {
            background: #fff3e0;
            color: #e65100;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .status-badge.online {
            background: #d4edda;
            color: #155724;
        }

        .performer-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .performer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .performer-stats {
            text-align: right;
        }

        .performer-stats div {
            font-weight: bold;
            color: #4CAF50;
        }

        .alert-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .alert-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .alert-icon {
            font-size: 20px;
        }

        .alert-content {
            flex: 1;
        }

        .alert-message {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .alert-meta {
            font-size: 0.85em;
            color: #666;
        }

        .alert-user {
            margin-right: 15px;
        }

        .no-alerts {
            text-align: center;
            color: #28a745;
            padding: 20px;
            font-style: italic;
        }

        .table-container {
            overflow-x: auto;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        @media (max-width: 768px) {
            .monitoring-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
    <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
</body>
</html>
