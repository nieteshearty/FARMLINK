<?php
/**
 * Get Messages API
 * Retrieves messages for a conversation
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
$otherUserId = $_GET['user_id'] ?? null;
$orderId = $_GET['order_id'] ?? null;
$limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
$offset = max(0, intval($_GET['offset'] ?? 0));

if (!$otherUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing user_id parameter']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get messages between users
    $whereClause = "
        ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    ";
    $params = [$user['id'], $otherUserId, $otherUserId, $user['id']];
    
    if ($orderId) {
        $whereClause .= " AND m.order_id = ?";
        $params[] = $orderId;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            sender.username as sender_username,
            sender.profile_picture as sender_avatar,
            receiver.username as receiver_username,
            receiver.profile_picture as receiver_avatar
        FROM messages m
        JOIN users sender ON m.sender_id = sender.id
        JOIN users receiver ON m.receiver_id = receiver.id
        WHERE {$whereClause}
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark messages as read (where current user is receiver)
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET is_read = TRUE 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$user['id'], $otherUserId]);
    
    // Get other user info
    $stmt = $pdo->prepare("
        SELECT id, username, profile_picture, role, last_active 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$otherUserId]);
    $otherUser = $stmt->fetch();
    
    // Format messages
    $formattedMessages = array_map(function($message) use ($basePath) {
        // Handle profile picture paths with BASE_URL
        $base = defined('BASE_URL') ? BASE_URL : '';
        $normalizeAvatar = function($avatar) use ($base) {
            if (empty($avatar)) return '';
            $v = trim($avatar);
            if (strpos($v, 'http') === 0) return $v;
            if (strpos($v, $base . '/') === 0 || strpos($v, '/FARMLINK/') === 0) return $v;
            if (strpos($v, 'uploads/') === 0) return $base . '/' . $v;
            if (strpos($v, '/') === 0) return $base . $v;
            return $base . '/uploads/profiles/' . basename($v);
        };

        $senderAvatar = $normalizeAvatar($message['sender_avatar'] ?? '');
        $receiverAvatar = $normalizeAvatar($message['receiver_avatar'] ?? '');
        
        return [
            'id' => $message['id'],
            'sender_id' => $message['sender_id'],
            'receiver_id' => $message['receiver_id'],
            'message' => $message['message'],
            'message_type' => $message['message_type'],
            'file_path' => $message['file_path'],
            'is_read' => (bool)$message['is_read'],
            'created_at' => $message['created_at'],
            'sender' => [
                'id' => $message['sender_id'],
                'username' => $message['sender_username'],
                'avatar' => $senderAvatar ?: ($base . '/assets/img/default-avatar.png')
            ],
            'receiver' => [
                'id' => $message['receiver_id'],
                'username' => $message['receiver_username'],
                'avatar' => $receiverAvatar ?: ($base . '/assets/img/default-avatar.png')
            ]
        ];
    }, $messages);
    
    // Reverse to show oldest first
    $formattedMessages = array_reverse($formattedMessages);
    
    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'other_user' => [
            'id' => $otherUser['id'],
            'username' => $otherUser['username'],
            'avatar' => (function() use ($otherUser) {
                $base = defined('BASE_URL') ? BASE_URL : '';
                $v = $otherUser['profile_picture'] ?? '';
                if (empty($v)) return $base . '/assets/img/default-avatar.png';
                if (strpos($v, 'http') === 0) return $v;
                if (strpos($v, $base . '/') === 0 || strpos($v, '/FARMLINK/') === 0) return $v;
                if (strpos($v, 'uploads/') === 0) return $base . '/' . $v;
                if (strpos($v, '/') === 0) return $base . $v;
                return $base . '/uploads/profiles/' . basename($v);
            })(),
            'role' => $otherUser['role'],
            'last_active' => $otherUser['last_active'],
            'is_online' => $otherUser['last_active'] && 
                          strtotime($otherUser['last_active']) > (time() - 300) // 5 minutes
        ],
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => count($messages) === $limit
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get messages error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve messages']);
}
?>
