<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Set content type for JSON response
header('Content-Type: application/json');

// Require buyer role
try {
    $user = SessionManager::requireRole('buyer');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0, 'message' => 'Authentication required']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get cart count for the current buyer
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE buyer_id = ?");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => intval($result['count'])
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Failed to get cart count'
    ]);
}
?>
