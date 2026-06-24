<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');
flash_set('error', 'Access denied: Admins are not permitted to view payments.');
redirect('admin/dashboard.php');
?>
