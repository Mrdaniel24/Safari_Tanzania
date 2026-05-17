<?php
$ap       = $activePage ?? 'dashboard';
$userName = $_SESSION['full_name'] ?? 'Admin';
$avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0c6780&color=fff';
$logoUrl = base_url('assets/images/logo-cropped-transparent.png');
$flashes  = flash_pull();
$cls = fn(string $p) => 'admin-nav-link flex items-center gap-3 px-4 py-2 ' . ($ap === $p ? 'is-active font-bold' : '');
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= e($pageTitle ?? 'Admin') ?> - SAFARI TANZANIA Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/admin.css')) ?>?v=estate-admin">
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    fontFamily: { sans: ["Arial", "Helvetica", "sans-serif"] }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(30,198,255,.42); border-radius: 10px; }
    </style>
</head>
<body class="admin-page bg-slate-50 text-slate-900 font-sans min-h-screen">
<div class="flex min-h-screen">

    <aside class="admin-sidebar hidden md:flex flex-col h-screen w-64 border-r border-slate-200 bg-white fixed left-0 top-0 z-40 overflow-y-auto custom-scrollbar">
        <div class="admin-brand p-6 flex items-center gap-3 border-b border-slate-100">
            <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" class="admin-logo">
        </div>
        <div class="admin-user-panel px-6 py-4 flex items-center gap-3 border-b border-slate-100">
            <img src="<?= e($avatarUrl) ?>" alt="Avatar" class="w-10 h-10 rounded-full border-2 border-sky-400">
            <div>
                <p class="text-sm font-bold text-slate-800 leading-tight"><?= e($userName) ?></p>
                <p class="text-xs text-slate-400">System Administrator</p>
            </div>
        </div>
        <nav class="flex-1 px-3 py-4">
            <p class="admin-section-label text-xs font-bold text-slate-400 uppercase tracking-widest px-4 mb-2">Main</p>
            <div class="space-y-1">
                <a href="<?= base_url('admin/dashboard.php') ?>" class="<?= $cls('dashboard') ?>">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="text-sm">Dashboard</span>
                </a>
                <a href="<?= base_url('admin/accommodations.php') ?>" class="<?= $cls('accommodations') ?>">
                    <span class="material-symbols-outlined">domain</span>
                    <span class="text-sm">Accommodations</span>
                </a>
                <a href="<?= base_url('admin/users.php') ?>" class="<?= $cls('users') ?>">
                    <span class="material-symbols-outlined">group</span>
                    <span class="text-sm">Users</span>
                </a>
                <a href="<?= base_url('admin/bookings.php') ?>" class="<?= $cls('bookings') ?>">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <span class="text-sm">Bookings</span>
                </a>
            </div>
            <p class="admin-section-label text-xs font-bold text-slate-400 uppercase tracking-widest px-4 mt-6 mb-2">System</p>
            <div class="space-y-1">
                <a href="<?= base_url('admin/logs.php') ?>" class="<?= $cls('logs') ?>">
                    <span class="material-symbols-outlined">history</span>
                    <span class="text-sm">Activity Logs</span>
                </a>
            </div>
        </nav>
        <div class="px-3 py-4 border-t border-slate-100" style="border-color:rgba(30,198,255,.10)!important;">
            <a href="<?= base_url('auth/logout.php') ?>" class="admin-nav-link flex items-center gap-3 px-4 py-2">
                <span class="material-symbols-outlined">logout</span>
                <span class="text-sm">Logout</span>
            </a>
        </div>
    </aside>

    <main class="admin-main flex-1 md:ml-64 flex flex-col min-h-screen">
        <header class="admin-topbar sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200 shadow-sm">
            <div class="flex justify-between items-center w-full px-6 py-4 max-w-7xl mx-auto">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[.2em] text-sky-700">Safari Tanzania Control</p>
                    <h1 class="text-xl font-black text-slate-800 tracking-tight uppercase"><?= e($pageTitle ?? '') ?></h1>
                </div>
                <div class="flex items-center gap-3 text-sm text-slate-500">
                    <span class="material-symbols-outlined text-sky-600">admin_panel_settings</span>
                    <span class="font-semibold text-sky-700">Admin</span>
                </div>
            </div>
        </header>

        <?php if ($flashes): ?>
        <div class="px-6 pt-4 max-w-7xl mx-auto w-full space-y-2">
            <?php foreach ($flashes as $f): $isErr = ($f['type'] === 'error'); ?>
                <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm
                    <?= $isErr ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-800' ?>">
                    <span class="material-symbols-outlined" style="font-size:18px;"><?= $isErr ? 'error' : 'check_circle' ?></span>
                    <?= e($f['msg']) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
