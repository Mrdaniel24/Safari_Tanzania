<?php
// credentials.php
// Loads admin credentials from environment variables. DO NOT commit real secrets into the repo.

if (session_status() === PHP_SESSION_NONE) session_start();

$ADMIN_USERNAME = getenv('ADMIN_USERNAME') ?: null;
$ADMIN_PASSWORD = getenv('ADMIN_PASSWORD') ?: null;

function get_admin_credentials(): array {
    global $ADMIN_USERNAME, $ADMIN_PASSWORD;
    return [
        'username' => $ADMIN_USERNAME,
        'password' => $ADMIN_PASSWORD,
    ];
}

// Helper: check an attempted login against env credentials (best-effort)
function check_admin_env_login(string $username, string $password): bool {
    $creds = get_admin_credentials();
    if (empty($creds['username']) || empty($creds['password'])) return false;
    // Use hash_compare for timing-safe comparison
    return hash_equals($creds['username'], $username) && hash_equals($creds['password'], $password);
}
