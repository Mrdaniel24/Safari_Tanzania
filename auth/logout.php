<?php
require_once __DIR__ . '/../config/db.php';
// Ensure security helpers are loaded so logout events are recorded
require_once __DIR__ . '/../config/security.php';

$uid = $_SESSION['user_id'] ?? null;
if (function_exists('log_security_event')) {
	try { log_security_event($pdo, $uid, 'logout', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]); } catch (Throwable $e) {}
}
session_unset();
session_destroy();
redirect('public/index.php');
