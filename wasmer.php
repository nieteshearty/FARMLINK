<?php
require_once __DIR__ . '/api/config.php';
header('Content-Type: application/json');
$action = $_GET['action'] ?? '';
if ($action === 'liveconfig') {
    // Prefer configured BASE_URL; fallback to legacy /FARMLINK detection
    $basePath = defined('BASE_URL') ? BASE_URL : '';
    if ($basePath === '') {
        $basePath = (strpos($_SERVER['REQUEST_URI'] ?? '', '/FARMLINK/') !== false || strpos($_SERVER['HTTP_REFERER'] ?? '', '/FARMLINK/') !== false) ? '/FARMLINK' : '';
    }
    echo json_encode([
        'ok' => true,
        'basePath' => $basePath,
        'https' => isHTTPS(),
        'timestamp' => time()
    ]);
    exit;
}

if ($action === 'dbinfo') {
    $maskedPass = strlen(DB_PASS) > 0 ? str_repeat('*', max(4, strlen(DB_PASS))) : '';
    echo json_encode([
        'ok' => true,
        'host' => DB_HOST,
        'port' => DB_PORT,
        'name' => DB_NAME,
        'user' => DB_USER,
        'pass' => $maskedPass,
    ]);
    exit;
}
http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'not_found']);
