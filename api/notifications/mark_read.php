<?php
/**
 * Mark Notifications as Read API
 * Marks one or all notifications as read
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = $_SESSION['user'];
$notificationId = $_POST['notification_id'] ?? null;
$markAll = isset($_POST['mark_all']) ? filter_var($_POST['mark_all'], FILTER_VALIDATE_BOOLEAN) : false;

try {
    $pdo = getDBConnection();
    
    if ($markAll) {
        // Mark all notifications as read for the user
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user['id']]);
        $affectedRows = $stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'message' => "Marked {$affectedRows} notifications as read",
            'marked_count' => $affectedRows
        ]);
        
    } elseif ($notificationId) {
        // Mark specific notification as read
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notificationId, $user['id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false, 
                'error' => 'Notification not found or already read'
            ]);
        }
        
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Either notification_id or mark_all parameter required'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Mark notifications read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as read']);
}
?>
