<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Verify admin authentication (you'll need to implement proper session/token auth)
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

// For now, we'll assume admin is authenticated - in production, implement proper JWT/session validation

if ($method === 'GET') {
    // Get all users
    $stmt = $pdo->query("SELECT id, username, email, role, farm_name, location, company, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['ok' => true, 'users' => $users]);
    
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['email']) || !isset($input['password']) || !isset($input['role'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, email, password, and role required']);
        exit;
    }
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$input['username'], $input['email']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'msg' => 'Username or email already exists']);
        exit;
    }
    
    // Hash password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    // Prepare user data
    $userData = [
        'username' => $input['username'],
        'email' => $input['email'],
        'password' => $hashedPassword,
        'role' => $input['role']
    ];
    
    // Add role-specific fields
    if ($input['role'] === 'farmer') {
        $userData['farm_name'] = $input['farm_name'] ?? '';
        $userData['location'] = $input['location'] ?? '';
    } elseif ($input['role'] === 'buyer') {
        $userData['company'] = $input['company'] ?? '';
        $userData['location'] = $input['location'] ?? '';
    }
    
    // Insert user
    $columns = implode(', ', array_keys($userData));
    $placeholders = implode(', ', array_fill(0, count($userData), '?'));
    $values = array_values($userData);
    
    $stmt = $pdo->prepare("INSERT INTO users ($columns) VALUES ($placeholders)");
    if ($stmt->execute($values)) {
        $userId = $pdo->lastInsertId();
        
        // Get created user (without password)
        $stmt = $pdo->prepare("SELECT id, username, email, role, farm_name, location, company, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $newUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['ok' => true, 'msg' => 'User created successfully', 'user' => $newUser]);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Failed to create user']);
    }
    
} elseif ($method === 'DELETE') {
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        exit;
    }
    
    // Prevent deleting own account
    // In production, you'd get this from the authenticated user's session
    $currentUserId = 1; // Example - replace with actual auth
    
    if ($userId == $currentUserId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Cannot delete your own account']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$userId])) {
        echo json_encode(['ok' => true, 'msg' => 'User deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Failed to delete user']);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
