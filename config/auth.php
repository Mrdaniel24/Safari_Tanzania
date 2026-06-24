<?php
// =====================================================================
// SAFARI TANZANIA - Role-based Middleware
// =====================================================================
require_once __DIR__ . '/db.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user !== null) return $user;
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, full_name, email, role, status FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        flash_set('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }

    // Session idle timeout: 2 hours of inactivity
    $maxIdle = 7200; // seconds
    $lastActivity = $_SESSION['_last_activity'] ?? 0;
    if ($lastActivity > 0 && (time() - $lastActivity) > $maxIdle) {
        session_unset();
        session_destroy();
        flash_set('error', 'Session expired due to inactivity. Please login again.');
        redirect('auth/login.php');
    }
    $_SESSION['_last_activity'] = time();

    $u = current_user();
    if (!$u || $u['status'] !== 'active') {
        session_unset();
        session_destroy();
        flash_set('error', 'Your account is inactive.');
        redirect('auth/login.php');
    }
}

function checkRole(string $requiredRole): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== $requiredRole) {
        http_response_code(403);
        flash_set('error', 'Access denied.');
        redirect('public/index.php');
    }
}

function checkAnyRole(array $roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        flash_set('error', 'Access denied.');
        redirect('public/index.php');
    }
}

function worker_accommodation_ids(int $workerId): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT accommodation_id FROM accommodation_workers WHERE worker_id = ?');
    $stmt->execute([$workerId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): void {
    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid CSRF token.');
    }
}

// API-friendly role check: returns JSON 403 and exits when role not allowed
function require_api_role(array $roles): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Insufficient role']);
        exit;
    }
}

function is_role(string $r): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $r;
}
