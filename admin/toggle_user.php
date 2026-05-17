<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/users.php');
}

csrf_verify($_POST['_csrf'] ?? null);

$adminId = (int)$_SESSION['user_id'];
$userId  = (int)($_POST['id'] ?? 0);
$action  = $_POST['action'] ?? '';

if ($userId <= 0 || $userId === $adminId) {
    flash_set('error', 'Invalid operation.');
    redirect('admin/users.php');
}

// Fetch the target user (can't modify another admin)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    flash_set('error', 'User not found or cannot modify another admin.');
    redirect('admin/users.php');
}

$logMsg = '';

switch ($action) {
    case 'toggle_status':
        $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")
            ->execute([$newStatus, $userId]);
        $logMsg = ucfirst($newStatus) . " user: \"{$user['full_name']}\" (ID {$userId})";
        flash_set('success', "\"{$user['full_name']}\" has been {$newStatus}.");
        break;

    case 'promote_owner':
        if ($user['role'] !== 'traveler') {
            flash_set('error', 'User is not a traveler.');
            redirect('admin/users.php');
        }
        $pdo->prepare("UPDATE users SET role = 'owner' WHERE id = ?")->execute([$userId]);
        $logMsg = "Promoted user \"{$user['full_name']}\" (ID {$userId}) to Owner";
        flash_set('success', "\"{$user['full_name']}\" promoted to Owner.");
        break;

    case 'demote_traveler':
        if ($user['role'] !== 'owner') {
            flash_set('error', 'User is not an owner.');
            redirect('admin/users.php');
        }
        $pdo->prepare("UPDATE users SET role = 'traveler' WHERE id = ?")->execute([$userId]);
        $logMsg = "Demoted user \"{$user['full_name']}\" (ID {$userId}) from Owner to Traveler";
        flash_set('success', "\"{$user['full_name']}\" demoted to Traveler.");
        break;

    default:
        flash_set('error', 'Unknown action.');
        redirect('admin/users.php');
}

if ($logMsg) {
    $pdo->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)")
        ->execute([$adminId, $logMsg]);
}

redirect('admin/users.php');
