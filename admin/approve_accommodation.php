<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/accommodations.php');
}

csrf_verify($_POST['_csrf'] ?? null);

$adminId = (int)$_SESSION['user_id'];
$id      = (int)($_POST['id'] ?? 0);
$action  = $_POST['action'] ?? '';

if (!in_array($action, ['approve', 'reject'], true) || $id <= 0) {
    redirect('admin/accommodations.php');
}

$newStatus = $action === 'approve' ? 'approved' : 'rejected';

$stmt = $pdo->prepare("SELECT name FROM accommodations WHERE id = ?");
$stmt->execute([$id]);
$acc = $stmt->fetch();

if ($acc) {
    $pdo->prepare("UPDATE accommodations SET status = ? WHERE id = ?")
        ->execute([$newStatus, $id]);

    $logMsg = ucfirst($action) . "d accommodation: \"{$acc['name']}\" (ID {$id})";
    $pdo->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)")
        ->execute([$adminId, $logMsg]);

    flash_set('success', "Property \"{$acc['name']}\" has been {$newStatus}.");
} else {
    flash_set('error', 'Accommodation not found.');
}

$back = $_POST['back'] ?? 'admin/accommodations.php';
redirect($back);
