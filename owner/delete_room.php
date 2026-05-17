<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('owner/properties.php');
}

csrf_verify($_POST['_csrf'] ?? null);

$ownerId = (int)$_SESSION['user_id'];
$roomId  = (int)($_POST['id'] ?? 0);
$accId   = (int)($_POST['acc_id'] ?? 0);

// Verify ownership through the accommodation
$stmt = $pdo->prepare("
    SELECT r.id FROM rooms r
    JOIN accommodations a ON a.id = r.accommodation_id
    WHERE r.id = ? AND a.owner_id = ?
");
$stmt->execute([$roomId, $ownerId]);
if (!$stmt->fetch()) {
    flash_set('error', 'Room not found or access denied.');
    redirect('owner/properties.php');
}

$pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$roomId]);

flash_set('success', 'Room type deleted.');
redirect('owner/rooms.php?acc_id=' . $accId);
