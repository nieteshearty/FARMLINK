<?php
// Set base path for includes
$basePath = dirname(dirname(__DIR__));  // Go up two levels to reach FARMLINK directory

// Include required files
require $basePath . '/api/config.php';
require $basePath . '/includes/session.php';

// Perform logout
SessionManager::logout();

// Redirect to login page with logout message
header("Location: /FARMLINK/pages/auth/login.php?logout=1");
exit;
?>
