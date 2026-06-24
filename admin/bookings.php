<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

// Deny access to bookings for admin roles - privacy-by-design enforcement
flash_set('error', 'Access denied: Admins are not permitted to view bookings.');
redirect('admin/dashboard.php');
