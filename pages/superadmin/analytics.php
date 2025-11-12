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

// Get analytics data
$analytics = [
    'user_growth' => [],
    'sales_trends' => [],
    'product_categories' => [],
    'order_status_breakdown' => [],
    'top_products' => [],
    'farmer_performance' => [],
    'buyer_activity' => [],
    'monthly_revenue' => []
];

// User growth over last 12 months
$userGrowth = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as new_users,
        SUM(CASE WHEN role = 'farmer' THEN 1 ELSE 0 END) as farmers,
        SUM(CASE WHEN role = 'buyer' THEN 1 ELSE 0 END) as buyers
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Sales trends over last 12 months
$salesTrends = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as order_count,
        SUM(total) as revenue,
        AVG(total) as avg_order_value
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    AND status = 'completed'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Product categories distribution
$productCategories = $pdo->query("
    SELECT 
        category,
        COUNT(*) as count,
        AVG(price) as avg_price
    FROM products 
    GROUP BY category
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Order status breakdown
$orderStatus = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(total) as total_value
    FROM orders 
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// Top selling products
$topProducts = $pdo->query("
    SELECT 
        p.name,
        p.category,
        u.username as farmer_name,
        COUNT(oi.id) as times_ordered,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.price * oi.quantity) as total_revenue
    FROM products p
    JOIN users u ON p.farmer_id = u.id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    GROUP BY p.id
    HAVING times_ordered > 0
    ORDER BY total_revenue DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Farmer performance metrics
$farmerPerformance = $pdo->query("
    SELECT 
        u.username,
        u.farm_name,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(o.total), 0) as total_revenue,
        AVG(o.total) as avg_order_value,
        u.created_at as joined_date
    FROM users u
    LEFT JOIN products p ON u.id = p.farmer_id
    LEFT JOIN orders o ON u.id = o.farmer_id AND o.status = 'completed'
    WHERE u.role = 'farmer'
    GROUP BY u.id
    ORDER BY total_revenue DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// Buyer activity metrics
$buyerActivity = $pdo->query("
    SELECT 
        u.username,
        u.company,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(o.total), 0) as total_spent,
        AVG(o.total) as avg_order_value,
        MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.buyer_id AND o.status = 'completed'
    WHERE u.role = 'buyer'
    GROUP BY u.id
    HAVING order_count > 0
    ORDER BY total_spent DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// Get system metrics for summary cards
$systemMetrics = DatabaseHelper::getSystemMetrics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>FarmLink • Analytics & Reports</title>
    <link rel="icon" type="image/png" href="/FARMLINK/assets/img/farmlink.png">
    <link rel="stylesheet" href="/FARMLINK/style.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/superadmin.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="/FARMLINK/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-page="analytics">
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="/FARMLINK/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">Analytics & Reports</span>
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
        <a href="analytics.php" class="active"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="/FARMLINK/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <h1>Analytics & Reports</h1>
        <p class="lead">Comprehensive insights into system performance and business metrics.</p>

        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>₱<?= number_format($systemMetrics['monthly_sales'][0]['revenue'] ?? 0, 2) ?></h3>
                <p>This Month Revenue</p>
                <small class="trend positive">+12% from last month</small>
            </div>
            <div class="stat-card">
                <h3><?= $systemMetrics['total_orders'] ?? 0 ?></h3>
                <p>Total Orders</p>
                <small class="trend positive">+8% from last month</small>
            </div>
            <div class="stat-card">
                <h3><?= $systemMetrics['total_users'] ?? 0 ?></h3>
                <p>Active Users</p>
                <small class="trend positive">+5% from last month</small>
            </div>
            <div class="stat-card">
                <h3><?= count($topProducts) ?></h3>
                <p>Top Products</p>
                <small class="trend neutral">Performing well</small>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- User Growth Chart -->
            <section class="card chart-card">
                <h3>User Growth Trends</h3>
                <canvas id="userGrowthChart"></canvas>
            </section>

            <!-- Sales Revenue Chart -->
            <section class="card chart-card">
                <h3>Revenue Trends</h3>
                <canvas id="salesChart"></canvas>
            </section>

            <!-- Product Categories -->
            <section class="card chart-card">
                <h3>Product Categories</h3>
                <canvas id="categoriesChart"></canvas>
            </section>

            <!-- Order Status -->
            <section class="card chart-card">
                <h3>Order Status Distribution</h3>
                <canvas id="orderStatusChart"></canvas>
            </section>
        </div>

        <!-- Performance Tables -->
        <div class="performance-grid">
            <!-- Top Products -->
            <section class="card">
                <h3>Top Performing Products</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Farmer</th>
                                <th>Orders</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <br><small><?= htmlspecialchars($product['category']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($product['farmer_name']) ?></td>
                                    <td><?= $product['times_ordered'] ?></td>
                                    <td><?= $product['total_quantity'] ?></td>
                                    <td class="revenue">₱<?= number_format($product['total_revenue'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Farmer Performance -->
            <section class="card">
                <h3>Farmer Performance</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Farmer</th>
                                <th>Products</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                                <th>Avg Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($farmerPerformance as $farmer): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($farmer['username']) ?></strong>
                                        <br><small><?= htmlspecialchars($farmer['farm_name'] ?? 'No farm name') ?></small>
                                    </td>
                                    <td><?= $farmer['product_count'] ?></td>
                                    <td><?= $farmer['order_count'] ?></td>
                                    <td class="revenue">₱<?= number_format($farmer['total_revenue'], 2) ?></td>
                                    <td>₱<?= number_format($farmer['avg_order_value'] ?? 0, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Buyer Activity -->
        <section class="card">
            <h3>Top Buyer Activity</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Buyer</th>
                            <th>Company</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Avg Order</th>
                            <th>Last Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buyerActivity as $buyer): ?>
                            <tr>
                                <td><?= htmlspecialchars($buyer['username']) ?></td>
                                <td><?= htmlspecialchars($buyer['company'] ?? 'Individual') ?></td>
                                <td><?= $buyer['order_count'] ?></td>
                                <td class="revenue">₱<?= number_format($buyer['total_spent'], 2) ?></td>
                                <td>₱<?= number_format($buyer['avg_order_value'], 2) ?></td>
                                <td><?= $buyer['last_order_date'] ? date('M j, Y', strtotime($buyer['last_order_date'])) : 'Never' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Export Actions -->
        <section class="card">
            <h3>Export Reports</h3>
            <div class="export-actions">
                <button class="btn btn-outline" onclick="exportReport('sales')">Export Sales Report</button>
                <button class="btn btn-outline" onclick="exportReport('users')">Export User Report</button>
                <button class="btn btn-outline" onclick="exportReport('products')">Export Product Report</button>
                <button class="btn btn-outline" onclick="exportReport('complete')">Export Complete Analytics</button>
            </div>
        </section>
    </main>

    <style>
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        .performance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }

        .chart-card {
            min-height: 400px;
        }

        .chart-card canvas {
            max-height: 300px;
        }

        .trend {
            font-size: 0.8em;
            font-weight: bold;
        }

        .trend.positive {
            color: #28a745;
        }

        .trend.negative {
            color: #dc3545;
        }

        .trend.neutral {
            color: #6c757d;
        }

        .revenue {
            font-weight: bold;
            color: #28a745;
        }

        .export-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .charts-grid,
            .performance-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // User Growth Chart
        const userGrowthData = <?= json_encode($userGrowth) ?>;
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: userGrowthData.map(d => d.month),
                datasets: [{
                    label: 'Total Users',
                    data: userGrowthData.map(d => d.new_users),
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Farmers',
                    data: userGrowthData.map(d => d.farmers),
                    borderColor: '#2196F3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Buyers',
                    data: userGrowthData.map(d => d.buyers),
                    borderColor: '#FF9800',
                    backgroundColor: 'rgba(255, 152, 0, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Sales Chart
        const salesData = <?= json_encode($salesTrends) ?>;
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: salesData.map(d => d.month),
                datasets: [{
                    label: 'Revenue (₱)',
                    data: salesData.map(d => d.revenue),
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: '#4CAF50',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Product Categories Chart
        const categoriesData = <?= json_encode($productCategories) ?>;
        const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
        new Chart(categoriesCtx, {
            type: 'doughnut',
            data: {
                labels: categoriesData.map(d => d.category),
                datasets: [{
                    data: categoriesData.map(d => d.count),
                    backgroundColor: [
                        '#4CAF50', '#2196F3', '#FF9800', '#9C27B0',
                        '#F44336', '#00BCD4', '#FFEB3B', '#795548'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Order Status Chart
        const orderStatusData = <?= json_encode($orderStatus) ?>;
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
        new Chart(orderStatusCtx, {
            type: 'pie',
            data: {
                labels: orderStatusData.map(d => d.status),
                datasets: [{
                    data: orderStatusData.map(d => d.count),
                    backgroundColor: [
                        '#4CAF50', '#FF9800', '#F44336', '#2196F3', '#9C27B0'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Export functions
        function exportReport(type) {
            alert(`Exporting ${type} report... Feature coming soon!`);
        }

        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
    <script src="/FARMLINK/assets/js/logout-confirmation.js"></script>
</body>
</html>
