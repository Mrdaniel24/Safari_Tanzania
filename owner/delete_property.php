<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('owner/properties.php');
}

csrf_verify($_POST['_csrf'] ?? null);

$ownerId = (int)$_SESSION['user_id'];
$id      = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare("DELETE FROM accommodations WHERE id = ? AND owner_id = ?");
$stmt->execute([$id, $ownerId]);

flash_set(
    $stmt->rowCount() ? 'success' : 'error',
    $stmt->rowCount() ? 'Property deleted.' : 'Could not delete that property.'
);
redirect('owner/properties.php');
