<?php
/**
 * Get Notifications API
 * Retrieves notifications for the current user
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Set base path for includes
$basePath = dirname(dirname(__DIR__));
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = $_SESSION['user'];
$limit = min(50, max(5, intval($_GET['limit'] ?? 20)));
$offset = max(0, intval($_GET['offset'] ?? 0));
$unreadOnly = isset($_GET['unread_only']) ? filter_var($_GET['unread_only'], FILTER_VALIDATE_BOOLEAN) : false;
$type = $_GET['type'] ?? null; // message, order, review, stock_alert, system

try {
    $pdo = getDBConnection();
    
    // Build WHERE clause
    $whereConditions = ["n.user_id = ?"];
    $params = [$user['id']];
    
    if ($unreadOnly) {
        $whereConditions[] = "n.is_read = FALSE";
    }
    
    if ($type) {
        $whereConditions[] = "n.type = ?";
        $params[] = $type;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            CASE 
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'just_now'
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'today'
                WHEN n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'this_week'
                ELSE 'older'
            END as time_group
        FROM notifications n
        WHERE {$whereClause}
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countParams = array_slice($params, 0, -2); // Remove limit and offset
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM notifications n
        WHERE {$whereClause}
    ");
    $stmt->execute($countParams);
    $totalCount = $stmt->fetch()['total'];
    
    // Get unread count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications n
        WHERE n.user_id = ? AND n.is_read = FALSE
    ");
    $stmt->execute([$user['id']]);
    $unreadCount = $stmt->fetch()['unread_count'];
    
    // Format notifications
    $formattedNotifications = array_map(function($notification) {
        // Parse notification data
        $data = [];
        if ($notification['data']) {
            $data = json_decode($notification['data'], true) ?: [];
        }
        
        // Get notification icon based on type
        $icons = [
            'message' => 'fas fa-comment',
            'order' => 'fas fa-shopping-cart',
            'review' => 'fas fa-star',
            'stock_alert' => 'fas fa-exclamation-triangle',
            'system' => 'fas fa-cog',
            'payment' => 'fas fa-credit-card'
        ];
        
        // Get notification color based on type
        $colors = [
            'message' => '#4CAF50',
            'order' => '#2196F3',
            'review' => '#FF9800',
            'stock_alert' => '#F44336',
            'system' => '#9E9E9E',
            'payment' => '#9C27B0'
        ];
        
        return [
            'id' => $notification['id'],
            'type' => $notification['type'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'data' => $data,
            'is_read' => (bool)$notification['is_read'],
            'action_url' => $notification['action_url'],
            'created_at' => $notification['created_at'],
            'time_group' => $notification['time_group'],
            'formatted_time' => formatNotificationTime($notification['created_at']),
            'icon' => $icons[$notification['type']] ?? 'fas fa-bell',
            'color' => $colors[$notification['type']] ?? '#9E9E9E'
        ];
    }, $notifications);
    
    // Group notifications by time
    $groupedNotifications = [];
    foreach ($formattedNotifications as $notification) {
        $group = $notification['time_group'];
        if (!isset($groupedNotifications[$group])) {
            $groupedNotifications[$group] = [];
        }
        $groupedNotifications[$group][] = $notification;
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $formattedNotifications,
        'grouped_notifications' => $groupedNotifications,
        'counts' => [
            'total' => intval($totalCount),
            'unread' => intval($unreadCount)
        ],
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount,
            'total_pages' => ceil($totalCount / $limit),
            'current_page' => floor($offset / $limit) + 1
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get notifications error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve notifications']);
}

/**
 * Format notification time
 */
function formatNotificationTime($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>
