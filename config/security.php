<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------
// CSRF helpers
// ---------------------------------------------------------------------

function get_csrf_token(): string {
    if (empty($_SESSION['_csrf_token']) || empty($_SESSION['_csrf_token_ts']) || time() - $_SESSION['_csrf_token_ts'] > 3600) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(24));
        $_SESSION['_csrf_token_ts'] = time();
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    $t = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES) . '">';
}

function validate_csrf(?string $token): bool {
    if (empty($token) || empty($_SESSION['_csrf_token'])) return false;
    if (!is_string($token)) return false;
    // Timing-safe compare
    return hash_equals((string)$_SESSION['_csrf_token'], $token);
}

// Backwards-compatible wrappers for existing helpers used across the app
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (!empty($_SESSION['_csrf'])) return (string)$_SESSION['_csrf'];
        // fall back to our token
        return get_csrf_token();
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token): void {
        $ok = false;
        if (!empty($_SESSION['_csrf']) && is_string($token) && hash_equals((string)$_SESSION['_csrf'], $token)) $ok = true;
        if (!$ok && validate_csrf($token)) $ok = true;
        if (!$ok) {
            http_response_code(400);
            die('Invalid CSRF token.');
        }
    }
}

// ---------------------------------------------------------------------
// Input validators
// ---------------------------------------------------------------------

function validate_date(string $d): bool {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function validate_positive_int($v): bool {
    return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
}

// ---------------------------------------------------------------------
// Rate limiting (simple IP-based, using session counters)
// ---------------------------------------------------------------------

/**
 * Check if the current request is rate-limited for a given action.
 *
 * Limits per action:
 *   login   – max 5 attempts per 60 seconds
 *   booking – max 5 creates per 30 seconds
 *
 * Returns the number of remaining attempts (int).
 * When 0, the caller should reject the request.
 */
function rate_limit_check(string $action): int {
    $maxAttempts = match ($action) {
        'login'   => 5,
        'booking' => 5,
        default   => 10,
    };
    $window = match ($action) {
        'login'   => 60,
        'booking' => 30,
        default   => 60,
    };

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = '_rate_' . $action . '_' . str_replace('.', '_', $ip);

    $record = $_SESSION[$key] ?? ['t' => 0, 'c' => 0];

    // Reset if window expired
    if (time() - $record['t'] > $window) {
        $record = ['t' => time(), 'c' => 0];
    }

    $remaining = $maxAttempts - $record['c'];
    if ($remaining < 0) $remaining = 0;

    return $remaining;
}

/**
 * Tick (increment) the rate counter for the given action.
 * Call this AFTER a failed attempt (e.g. failed login, failed booking).
 */
function rate_limit_tick(string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = '_rate_' . $action . '_' . str_replace('.', '_', $ip);

    $record = $_SESSION[$key] ?? ['t' => time(), 'c' => 0];

    // Reset if window expired
    $window = match ($action) {
        'login'   => 60,
        'booking' => 30,
        default   => 60,
    };
    if (time() - $record['t'] > $window) {
        $record = ['t' => time(), 'c' => 0];
    }

    $record['c']++;
    $_SESSION[$key] = $record;
}

/**
 * Tick on success to clear the rate counter.
 */
function rate_limit_clear(string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = '_rate_' . $action . '_' . str_replace('.', '_', $ip);
    unset($_SESSION[$key]);
}
