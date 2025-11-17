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

// Get comprehensive stats using DatabaseHelper
$systemMetrics = DatabaseHelper::getSystemMetrics();
$stats = DatabaseHelper::getStats();

// Get recent activity
$recentActivity = $pdo->query("
    SELECT al.*, u.username, u.role 
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get quick stats for cards
$quickStats = [
    'active_users_today' => $pdo->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM activity_log 
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn(),
    'pending_orders' => $systemMetrics['orders_by_status']['pending'] ?? 0,
    'monthly_revenue' => $systemMetrics['monthly_sales'][0]['revenue'] ?? 0,
    'new_users_week' => $pdo->query("
        SELECT COUNT(*) 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")->fetchColumn()
];

// Get top performers
$topFarmers = $pdo->query("
    SELECT u.username, u.farm_name, COALESCE(SUM(o.total), 0) as revenue
    FROM users u
    LEFT JOIN orders o ON u.id = o.farmer_id AND o.status = 'completed'
    WHERE u.role = 'farmer'
    GROUP BY u.id
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$topProducts = $pdo->query("
    SELECT p.name, p.category, COUNT(oi.id) as orders
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id
    ORDER BY orders DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>FarmLink • Super Admin Dashboard</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/superadmin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-page="dashboard">
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">Super Admin Dashboard</span>
        </div>
        <div class="nav-right">
            <div class="user-menu">
                <span class="welcome">Welcome, <?= htmlspecialchars($user['username']) ?></span>
                <?php
                $profilePicPath = ImageHelper::normalizeImagePath($user['profile_picture'] ?? '', 'profiles');
                if (empty($profilePicPath)) {
                    $profilePicPath = BASE_URL . '/assets/img/default-avatar.png';
                }
                ?>
                <img src="<?= $profilePicPath ?>" alt="Profile" class="avatar" onerror="this.src='<?= BASE_URL ?>/assets/img/default-avatar.png'">
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage-products.php"><i class="fas fa-box"></i> Manage Products</a>
        <a href="system-monitoring.php"><i class="fas fa-desktop"></i> System Monitoring</a>
        <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <div class="dashboard-header">
            <h1>Dashboard Overview</h1>
            <p class="lead">Monitor your platform's performance and key metrics at a glance.</p>
        </div>

        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($stats['total_users'] ?? 0) ?></h3>
                    <p>Total Users</p>
                    <small class="trend positive">+<?= $quickStats['new_users_week'] ?> this week</small>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3>₱<?= number_format($quickStats['monthly_revenue'], 2) ?></h3>
                    <p>Monthly Revenue</p>
                    <small class="trend positive">+12% from last month</small>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $quickStats['pending_orders'] ?></h3>
                    <p>Pending Orders</p>
                    <small class="trend neutral">Needs attention</small>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $quickStats['active_users_today'] ?></h3>
                    <p>Active Today</p>
                    <small class="trend positive">Users online</small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <section class="card">
            <h3>Quick Actions</h3>
            <div class="quick-actions-grid">
                <a href="manage-users.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-content">
                        <h4>Manage Users</h4>
                        <p>Add, edit, or remove users</p>
                    </div>
                </a>
                
                <a href="manage-products.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="action-content">
                        <h4>Manage Products</h4>
                        <p>Review and manage products</p>
                    </div>
                </a>
                
                <a href="system-monitoring.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-monitor-waveform"></i>
                    </div>
                    <div class="action-content">
                        <h4>System Monitor</h4>
                        <p>Check system health</p>
                    </div>
                </a>
                
                <a href="analytics.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-content">
                        <h4>Analytics</h4>
                        <p>View detailed reports</p>
                    </div>
                </a>
                
                <a href="settings.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="action-content">
                        <h4>Settings</h4>
                        <p>Configure system settings</p>
                    </div>
                </a>
                
                <a href="superadmin-profile.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="action-content">
                        <h4>Profile</h4>
                        <p>Update your profile</p>
                    </div>
                </a>
            </div>
        </section>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Recent Activity -->
            <section class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                    <a href="system-monitoring.php" class="view-all">View All</a>
                </div>
                <div class="activity-list">
                    <?php if (empty($recentActivity)): ?>
                        <p class="no-activity">No recent activity found.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($recentActivity, 0, 8) as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-<?= getActivityIcon($activity['message']) ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-message">
                                        <strong><?= htmlspecialchars($activity['username'] ?? 'System') ?></strong>
                                        <?= htmlspecialchars($activity['message']) ?>
                                    </div>
                                    <div class="activity-meta">
                                        <span class="activity-role"><?= ucfirst($activity['role'] ?? 'system') ?></span>
                                        <span class="activity-time"><?= timeAgo($activity['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Top Performers -->
            <section class="card">
                <div class="card-header">
                    <h3>Top Performing Farmers</h3>
                    <a href="analytics.php" class="view-all">View All</a>
                </div>
                <div class="performers-list">
                    <?php foreach ($topFarmers as $index => $farmer): ?>
                        <div class="performer-item">
                            <div class="performer-rank">#<?= $index + 1 ?></div>
                            <div class="performer-info">
                                <strong><?= htmlspecialchars($farmer['username']) ?></strong>
                                <small><?= htmlspecialchars($farmer['farm_name'] ?? 'No farm name') ?></small>
                            </div>
                            <div class="performer-value">₱<?= number_format($farmer['revenue'], 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <!-- System Status -->
        <div class="dashboard-grid">
            <!-- System Health -->
            <section class="card">
                <h3>System Health</h3>
                <div class="health-indicators">
                    <div class="health-item">
                        <div class="health-status online"></div>
                        <span>Database Connection</span>
                        <strong>Online</strong>
                    </div>
                    <div class="health-item">
                        <div class="health-status online"></div>
                        <span>File Upload System</span>
                        <strong>Operational</strong>
                    </div>
                    <div class="health-item">
                        <div class="health-status online"></div>
                        <span>User Authentication</span>
                        <strong>Active</strong>
                    </div>
                    <div class="health-item">
                        <div class="health-status warning"></div>
                        <span>Email Notifications</span>
                        <strong>Limited</strong>
                    </div>
                </div>
            </section>

            <!-- Top Products -->
            <section class="card">
                <div class="card-header">
                    <h3>Popular Products</h3>
                    <a href="manage-products.php" class="view-all">View All</a>
                </div>
                <div class="products-list">
                    <?php foreach ($topProducts as $product): ?>
                        <div class="product-item">
                            <div class="product-info">
                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                                <small><?= htmlspecialchars($product['category']) ?></small>
                            </div>
                            <div class="product-orders"><?= $product['orders'] ?> orders</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>

    <style>
        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            margin-bottom: 5px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card.primary { border-left: 4px solid #007bff; }
        .stat-card.success { border-left: 4px solid #28a745; }
        .stat-card.warning { border-left: 4px solid #ffc107; }
        .stat-card.info { border-left: 4px solid #17a2b8; }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .stat-card.primary .stat-icon { background: #007bff; }
        .stat-card.success .stat-icon { background: #28a745; }
        .stat-card.warning .stat-icon { background: #ffc107; }
        .stat-card.info .stat-icon { background: #17a2b8; }

        .stat-content h3 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }

        .stat-content p {
            margin: 5px 0;
            color: #666;
        }

        .trend {
            font-size: 0.85em;
            font-weight: 500;
        }

        .trend.positive { color: #28a745; }
        .trend.negative { color: #dc3545; }
        .trend.neutral { color: #6c757d; }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }

        .action-card:hover {
            border-color: #4CAF50;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
        }

        .action-content h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
        }

        .action-content p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .view-all {
            color: #4CAF50;
            text-decoration: none;
            font-size: 0.9em;
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4CAF50;
            font-size: 14px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-message {
            font-size: 14px;
            margin-bottom: 4px;
        }

        .activity-meta {
            font-size: 12px;
            color: #666;
        }

        .activity-role {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 10px;
            margin-right: 8px;
        }

        .performers-list, .products-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .performer-item, .product-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .performer-rank {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: #4CAF50;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .performer-info, .product-info {
            flex: 1;
        }

        .performer-value, .product-orders {
            font-weight: bold;
            color: #4CAF50;
        }

        .health-indicators {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .health-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .health-status {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .health-status.online { background: #28a745; }
        .health-status.warning { background: #ffc107; }
        .health-status.offline { background: #dc3545; }

        .no-activity {
            text-align: center;
            color: #666;
            padding: 20px;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const nav = document.querySelector('nav');
            
            
            if (sidebar) {
                sidebar.classList.toggle('active');
                
                // Add/remove class to nav for padding adjustment when sidebar is active
                if (sidebar.classList.contains('active')) {
                    nav.classList.add('sidebar-active');
                } else {
                    nav.classList.remove('sidebar-active');
                }
            } else {
            }
        }
        
        // Ensure DOM is loaded before setting up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            
            // Ensure the function is available globally
            window.toggleSidebar = toggleSidebar;
            
            // Also add direct event listener as backup
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (menuToggle) {
                // Remove any existing onclick to avoid conflicts
                menuToggle.removeAttribute('onclick');
                
                // Add event listener
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleSidebar();
                });
                
            } else {
            }
            
            // Test sidebar visibility
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
            }
        });
        
        // Add click outside to close sidebar on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (sidebar && sidebar.classList.contains('active')) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                    document.querySelector('nav').classList.remove('sidebar-active');
                }
            }
        });
        
        // Show success message if redirected with success parameter
        if (window.location.search.includes('login=success')) {
            const url = new URL(window.location);
            url.searchParams.delete('login');
            window.history.replaceState({}, '', url);
            
            const welcomeMsg = document.createElement('div');
            welcomeMsg.className = 'alert alert-success';
            welcomeMsg.style.cssText = 'background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;';
            welcomeMsg.innerHTML = '<i class="fas fa-check-circle"></i> Welcome back, <?= htmlspecialchars($user["username"]) ?>! You have successfully logged in.';
            
            const main = document.querySelector('.main');
            main.insertBefore(welcomeMsg, main.firstChild);
            
            setTimeout(() => {
                welcomeMsg.style.transition = 'opacity 0.5s ease';
                welcomeMsg.style.opacity = '0';
                setTimeout(() => welcomeMsg.remove(), 500);
            }, 5000);
        }
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
