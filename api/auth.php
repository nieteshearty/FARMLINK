<?php
require 'config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

error_log("Incoming request: " . print_r($input, true));
error_log("Username: " . $username);
error_log("Role: " . $role);

if ($input['action'] === 'login') {
    $username = $input['username'];
    $password = $input['password'];
    $role = $input['role'];

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND role = :role");
    $stmt->execute(['username' => $username, 'role' => $role]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        echo json_encode(['ok' => true, 'user' => $user]);
    } else {
        // Invalid credentials
        echo json_encode(['ok' => false, 'msg' => 'Invalid credentials']);
    }
}
?>
