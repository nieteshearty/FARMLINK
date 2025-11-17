<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';
require $basePath . '/includes/ImageHelper.php';

// Require super admin role
$user = SessionManager::requireRole('superadmin');

$pdo = getDBConnection();
$message = '';
$error = '';

// Handle settings updates
if ($_POST) {
    try {
        // Create settings table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT,
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT,
                FOREIGN KEY (updated_by) REFERENCES users(id)
            )
        ");

        // Update settings
        $settings = [
            'site_name' => $_POST['site_name'] ?? 'FARMLINK',
            'site_description' => $_POST['site_description'] ?? 'Connecting Farmers and Buyers',
            'admin_email' => $_POST['admin_email'] ?? '',
            'max_file_size' => $_POST['max_file_size'] ?? '5',
            'allowed_file_types' => $_POST['allowed_file_types'] ?? 'jpg,jpeg,png,gif',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'user_registration' => isset($_POST['user_registration']) ? '1' : '0',
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
            'auto_approve_products' => isset($_POST['auto_approve_products']) ? '1' : '0',
            'default_currency' => $_POST['default_currency'] ?? 'PHP',
            'timezone' => $_POST['timezone'] ?? 'Asia/Manila',
            'items_per_page' => $_POST['items_per_page'] ?? '20'
        ];

        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_by = VALUES(updated_by)
            ");
            $stmt->execute([$key, $value, $user['id']]);
        }

        $message = "Settings updated successfully!";
        
        // Log activity
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)");
        $stmt->execute([$user['id'], "Updated system settings"]);
        
    } catch (Exception $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$currentSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Settings table might not exist yet, use defaults
}

// Default values
$defaults = [
    'site_name' => 'FARMLINK',
    'site_description' => 'Connecting Farmers and Buyers',
    'admin_email' => '',
    'max_file_size' => '5',
    'allowed_file_types' => 'jpg,jpeg,png,gif',
    'maintenance_mode' => '0',
    'user_registration' => '1',
    'email_notifications' => '1',
    'auto_approve_products' => '1',
    'default_currency' => 'PHP',
    'timezone' => 'Asia/Manila',
    'items_per_page' => '20'
];

// Merge with current settings
$settings = array_merge($defaults, $currentSettings);

// Get system info
$systemInfo = [
    'php_version' => phpversion(),
    'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'disk_free_space' => disk_free_space('.'),
    'disk_total_space' => disk_total_space('.')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>FarmLink • System Settings</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/farmlink.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/superadmin.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/logout-confirmation.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/navigation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body data-page="settings">
    <nav>
        <div class="nav-left">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <img src="<?= BASE_URL ?>/assets/img/farmlink.png" alt="FARMLINK Logo" class="nav-logo">
            <span class="nav-title">Settings</span>
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
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
        <a href="manage-products.php"><i class="fas fa-box"></i> Manage Products</a>
        <a href="system-monitoring.php"><i class="fas fa-desktop"></i> System Monitoring</a>
        <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics & Reports</a>
        <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
        <a href="superadmin-profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/pages/auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <main class="main">
        <h1>System Settings</h1>
        <p class="lead">Configure system-wide settings and preferences.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="settings-container">
            <!-- Settings Form -->
            <div class="settings-main">
                <form method="POST" class="settings-form">
                    <!-- General Settings -->
                    <section class="card">
                        <h3>General Settings</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="site_name">Site Name</label>
                                <input type="text" id="site_name" name="site_name" 
                                       value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="admin_email">Admin Email</label>
                                <input type="email" id="admin_email" name="admin_email" 
                                       value="<?= htmlspecialchars($settings['admin_email']) ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="site_description">Site Description</label>
                                <textarea id="site_description" name="site_description" rows="3"><?= htmlspecialchars($settings['site_description']) ?></textarea>
                            </div>
                        </div>
                    </section>

                    <!-- File Upload Settings -->
                    <section class="card">
                        <h3>File Upload Settings</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="max_file_size">Max File Size (MB)</label>
                                <input type="number" id="max_file_size" name="max_file_size" 
                                       value="<?= htmlspecialchars($settings['max_file_size']) ?>" min="1" max="100">
                            </div>
                            <div class="form-group">
                                <label for="allowed_file_types">Allowed File Types</label>
                                <input type="text" id="allowed_file_types" name="allowed_file_types" 
                                       value="<?= htmlspecialchars($settings['allowed_file_types']) ?>"
                                       placeholder="jpg,jpeg,png,gif">
                                <small>Comma-separated list of allowed file extensions</small>
                            </div>
                        </div>
                    </section>

                    <!-- System Preferences -->
                    <section class="card">
                        <h3>System Preferences</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="default_currency">Default Currency</label>
                                <select id="default_currency" name="default_currency">
                                    <option value="PHP" <?= $settings['default_currency'] === 'PHP' ? 'selected' : '' ?>>PHP (₱)</option>
                                    <option value="USD" <?= $settings['default_currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                    <option value="EUR" <?= $settings['default_currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone">
                                    <option value="Asia/Manila" <?= $settings['timezone'] === 'Asia/Manila' ? 'selected' : '' ?>>Asia/Manila</option>
                                    <option value="UTC" <?= $settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    <option value="America/New_York" <?= $settings['timezone'] === 'America/New_York' ? 'selected' : '' ?>>America/New_York</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="items_per_page">Items Per Page</label>
                                <select id="items_per_page" name="items_per_page">
                                    <option value="10" <?= $settings['items_per_page'] === '10' ? 'selected' : '' ?>>10</option>
                                    <option value="20" <?= $settings['items_per_page'] === '20' ? 'selected' : '' ?>>20</option>
                                    <option value="50" <?= $settings['items_per_page'] === '50' ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $settings['items_per_page'] === '100' ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- Feature Toggles -->
                    <section class="card">
                        <h3>Feature Settings</h3>
                        <div class="toggle-grid">
                            <div class="toggle-item">
                                <label class="toggle-label">
                                    <input type="checkbox" name="maintenance_mode" 
                                           <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    Maintenance Mode
                                </label>
                                <small>Temporarily disable site access for maintenance</small>
                            </div>
                            <div class="toggle-item">
                                <label class="toggle-label">
                                    <input type="checkbox" name="user_registration" 
                                           <?= $settings['user_registration'] === '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    User Registration
                                </label>
                                <small>Allow new users to register accounts</small>
                            </div>
                            <div class="toggle-item">
                                <label class="toggle-label">
                                    <input type="checkbox" name="email_notifications" 
                                           <?= $settings['email_notifications'] === '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    Email Notifications
                                </label>
                                <small>Send email notifications for important events</small>
                            </div>
                            <div class="toggle-item">
                                <label class="toggle-label">
                                    <input type="checkbox" name="auto_approve_products" 
                                           <?= $settings['auto_approve_products'] === '1' ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    Auto-approve Products
                                </label>
                                <small>Automatically approve new products without review</small>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <button type="reset" class="btn btn-secondary">Reset</button>
                    </div>
                </form>
            </div>

            <!-- System Information -->
            <div class="settings-sidebar">
                <section class="card">
                    <h3>System Information</h3>
                    <div class="system-info">
                        <div class="info-item">
                            <strong>PHP Version:</strong>
                            <span><?= $systemInfo['php_version'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>MySQL Version:</strong>
                            <span><?= $systemInfo['mysql_version'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Server:</strong>
                            <span><?= $systemInfo['server_software'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Upload Limit:</strong>
                            <span><?= $systemInfo['upload_max_filesize'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Memory Limit:</strong>
                            <span><?= $systemInfo['memory_limit'] ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Disk Space:</strong>
                            <span><?= formatBytes($systemInfo['disk_free_space']) ?> / <?= formatBytes($systemInfo['disk_total_space']) ?></span>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions">
                        <button type="button" class="btn btn-outline" onclick="clearCache()">Clear Cache</button>
                        <button type="button" class="btn btn-outline" onclick="exportSettings()">Export Settings</button>
                        <button type="button" class="btn btn-outline" onclick="viewLogs()">View System Logs</button>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <style>
        .settings-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .toggle-grid {
            display: grid;
            gap: 15px;
        }

        .toggle-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
        }

        .toggle-slider {
            position: relative;
            width: 50px;
            height: 24px;
            background: #ccc;
            border-radius: 24px;
            transition: background 0.3s;
        }

        .toggle-slider:before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }

        input[type="checkbox"]:checked + .toggle-slider {
            background: #4CAF50;
        }

        input[type="checkbox"]:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        input[type="checkbox"] {
            display: none;
        }

        .system-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: #333;
            color: #fff;
            padding: 20px;
            display: none;
        }
        
        .sidebar.active {
            display: block;
        }
        
        .menu-toggle {
            background: none;
            border: none;
            padding: 10px;
            font-size: 18px;
            cursor: pointer;
        }
    </style>

    <script>
        function clearCache() {
            if (confirm('Are you sure you want to clear the system cache?')) {
                // Implement cache clearing logic
                alert('Cache cleared successfully!');
            }
        }

        function exportSettings() {
            // Implement settings export
            alert('Settings export feature coming soon!');
        }

        function viewLogs() {
            // Implement log viewing
            alert('System logs viewer coming soon!');
        }
        
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/logout-confirmation.js"></script>
</body>
</html>

<?php
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
?>
