<?php
// Simple CLI helper to reset a user's password using the project's PDO connection.
require_once __DIR__ . '/../config/db.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$argv = $_SERVER['argv'];
// Usage: php scripts/reset_admin_password.php email@example.com NewP@ssw0rd
if (empty($argv[1]) || empty($argv[2])) {
    echo "Usage: php scripts/reset_admin_password.php email@example.com NewPassword\n";
    exit(1);
}

$email = $argv[1];
$newPassword = $argv[2];

// Use PASSWORD_BCRYPT to produce a PHP-compatible hash for password_verify()
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
    $stmt->execute([$hash, $email]);
    if ($stmt->rowCount() > 0) {
        echo "Password updated for admin user: $email\n";
        exit(0);
    }
    // If no rows updated, try updating any user matching the email as fallback
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hash, $email]);
    if ($stmt->rowCount() > 0) {
        echo "Password updated for user: $email (role not admin)\n";
        exit(0);
    }
    echo "No user found with email: $email\n";
    exit(2);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(3);
}
