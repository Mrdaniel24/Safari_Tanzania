<?php
// =====================================================================
// SAFARI TANZANIA - Database Connection (PDO)
// =====================================================================
declare(strict_types=1);

// --- Database credentials (edit for your XAMPP setup) ---
const DB_HOST = '127.0.0.1';
const DB_NAME = 'safari_tanzania';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// --- Session bootstrap (single source of truth) ---
if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie params (works on localhost; tighten in production)
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// --- Error handling: log, never display in production ---
ini_set('display_errors', '1'); // change to '0' on production
error_reporting(E_ALL);

// --- PDO connection ---
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Please check config/db.php');
}

// --- Helper: safe HTML output ---
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- Helper: base URL of the app (works in subfolder /SAFARI TANZANIA) ---
function base_url(string $path = ''): string {
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // strip current sub-folder so we always resolve from project root
    $segments = explode('/', trim($script, '/'));
    // if we are inside /auth, /traveler, etc, drop the last segment
    $known = ['auth','traveler','owner','admin','public','config','includes'];
    if (!empty($segments) && in_array(end($segments), $known, true)) {
        array_pop($segments);
    }
    $root = '/' . implode('/', array_filter($segments));
    if ($root === '/') $root = '';
    return $root . '/' . ltrim($path, '/');
}

// --- Helper: redirect ---
function redirect(string $path): void {
    header('Location: ' . base_url($path));
    exit;
}

// --- Helper: flash messages ---
function flash_set(string $type, string $msg): void {
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}
function flash_pull(): array {
    $msgs = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $msgs;
}

// Google Maps Embed API key.
// Get one free at https://console.cloud.google.com/ → Maps Embed API.
// If empty, Leaflet + OpenStreetMap is used instead (no key required).
const GOOGLE_MAPS_API_KEY = '';
