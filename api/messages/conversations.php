<?php
/**
 * Get Conversations API
 * Retrieves all conversations for the current user
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
$limit = min(50, max(10, intval($_GET['limit'] ?? 20)));
$offset = max(0, intval($_GET['offset'] ?? 0));

try {
    $pdo = getDBConnection();
    
    // Get conversations with last message and unread count
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            CASE 
                WHEN c.user1_id = ? THEN c.user2_id 
                ELSE c.user1_id 
            END as other_user_id,
            CASE 
                WHEN c.user1_id = ? THEN u2.username 
                ELSE u1.username 
            END as other_username,
            CASE 
                WHEN c.user1_id = ? THEN u2.profile_picture 
                ELSE u1.profile_picture 
            END as other_avatar,
            CASE 
                WHEN c.user1_id = ? THEN u2.role 
                ELSE u1.role 
            END as other_role,
            CASE 
                WHEN c.user1_id = ? THEN u2.last_active 
                ELSE u1.last_active 
            END as other_last_active,
            lm.message as last_message,
            lm.message_type as last_message_type,
            lm.sender_id as last_sender_id,
            lm.created_at as last_message_time,
            (
                SELECT COUNT(*) 
                FROM messages m 
                WHERE ((m.sender_id = c.user1_id AND m.receiver_id = c.user2_id) 
                       OR (m.sender_id = c.user2_id AND m.receiver_id = c.user1_id))
                  AND m.receiver_id = ?
                  AND m.is_read = FALSE
            ) as unread_count,
            o.id as order_number
        FROM conversations c
        JOIN users u1 ON c.user1_id = u1.id
        JOIN users u2 ON c.user2_id = u2.id
        LEFT JOIN messages lm ON c.last_message_id = lm.id
        LEFT JOIN orders o ON c.order_id = o.id
        WHERE (c.user1_id = ? OR c.user2_id = ?) 
          AND c.is_archived = FALSE
        ORDER BY c.last_message_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([
        $user['id'], $user['id'], $user['id'], $user['id'], $user['id'], // for CASE statements
        $user['id'], // for unread count
        $user['id'], $user['id'], // for WHERE clause
        $limit, $offset
    ]);
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format conversations
    $formattedConversations = array_map(function($conv) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        // Handle avatar path
        $avatar = $conv['other_avatar'];
        if ($avatar) {
            $v = trim($avatar);
            if (strpos($v, 'http') === 0) {
                $avatar = $v;
            } elseif (strpos($v, $base . '/') === 0 || strpos($v, '/FARMLINK/') === 0) {
                $avatar = $v;
            } elseif (strpos($v, 'uploads/') === 0) {
                $avatar = $base . '/' . $v;
            } elseif (strpos($v, '/') === 0) {
                $avatar = $base . $v;
            } else {
                $avatar = $base . '/uploads/profiles/' . basename($v);
            }
        }
        
        // Format last message preview
        $lastMessagePreview = '';
        if ($conv['last_message']) {
            switch ($conv['last_message_type']) {
                case 'image':
                    $lastMessagePreview = '📷 Image';
                    break;
                case 'file':
                    $lastMessagePreview = '📎 File';
                    break;
                default:
                    $lastMessagePreview = strlen($conv['last_message']) > 50 
                        ? substr($conv['last_message'], 0, 50) . '...'
                        : $conv['last_message'];
            }
        }
        
        // Check if other user is online (active within 5 minutes)
        $isOnline = $conv['other_last_active'] && 
                   strtotime($conv['other_last_active']) > (time() - 300);
        
        return [
            'id' => $conv['id'],
            'other_user' => [
                'id' => $conv['other_user_id'],
                'username' => $conv['other_username'],
                'avatar' => $avatar ?: ($base . '/assets/img/default-avatar.png'),
                'role' => $conv['other_role'],
                'is_online' => $isOnline,
                'last_active' => $conv['other_last_active']
            ],
            'last_message' => [
                'preview' => $lastMessagePreview,
                'sender_id' => $conv['last_sender_id'],
                'is_from_me' => $conv['last_sender_id'] == $_SESSION['user']['id'],
                'time' => $conv['last_message_time'],
                'formatted_time' => $conv['last_message_time'] ? 
                    formatTimeAgo($conv['last_message_time']) : null
            ],
            'unread_count' => intval($conv['unread_count']),
            'order_id' => $conv['order_id'],
            'order_number' => $conv['order_number'],
            'created_at' => $conv['created_at'],
            'updated_at' => $conv['last_message_at']
        ];
    }, $conversations);
    
    echo json_encode([
        'success' => true,
        'conversations' => $formattedConversations,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => count($conversations) === $limit
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get conversations error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve conversations']);
}

/**
 * Format time ago helper function
 */
function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j', strtotime($datetime));
}
?>
