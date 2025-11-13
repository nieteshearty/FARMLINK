<?php
/**
 * Send Message API
 * Handles sending messages between users
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
$receiverId = $_POST['receiver_id'] ?? null;
$message = trim($_POST['message'] ?? '');
$orderId = $_POST['order_id'] ?? null;
$messageType = $_POST['message_type'] ?? 'text';

// Validate input
if (!$receiverId || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if ($receiverId == $user['id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot send message to yourself']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Verify receiver exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$receiverId]);
    $receiver = $stmt->fetch();
    
    if (!$receiver) {
        throw new Exception('Receiver not found or inactive');
    }
    
    // Handle file upload if present
    $filePath = null;
    if ($messageType !== 'text' && isset($_FILES['file'])) {
        $uploadDir = $basePath . '/uploads/messages/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = [
            'image' => ['jpg', 'jpeg', 'png', 'gif'],
            'file' => ['pdf', 'doc', 'docx', 'txt']
        ];
        
        $fileExtension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        
        if (in_array($fileExtension, $allowedTypes[$messageType] ?? [])) {
            $fileName = uniqid() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
                // Store as relative path for portability; clients should render with BASE_URL
                $filePath = 'uploads/messages/' . $fileName;
            }
        }
    }
    
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, order_id, message, message_type, file_path) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user['id'], $receiverId, $orderId, $message, $messageType, $filePath]);
    $messageId = $pdo->lastInsertId();
    
    // Update or create conversation
    $stmt = $pdo->prepare("
        INSERT INTO conversations (user1_id, user2_id, order_id, last_message_id, last_message_at) 
        VALUES (LEAST(?, ?), GREATEST(?, ?), ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            last_message_id = ?, 
            last_message_at = NOW(),
            is_archived = FALSE
    ");
    $stmt->execute([
        $user['id'], $receiverId, $user['id'], $receiverId, 
        $orderId, $messageId, $messageId
    ]);
    
    // Create notification for receiver
    $notificationTitle = 'New Message';
    $notificationMessage = 'You have a new message from ' . htmlspecialchars($user['username']);
    $notificationData = json_encode([
        'sender_id' => $user['id'],
        'sender_name' => $user['username'],
        'message_preview' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
        'order_id' => $orderId
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, data, action_url) 
        VALUES (?, 'message', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $receiverId, 
        $notificationTitle, 
        $notificationMessage, 
        $notificationData,
        (defined('BASE_URL') ? BASE_URL : '') . '/pages/common/messages.php?conversation=' . $user['id']
    ]);
    
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'timestamp' => date('Y-m-d H:i:s'),
        'receiver' => [
            'id' => $receiver['id'],
            'username' => $receiver['username']
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Message send error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>
