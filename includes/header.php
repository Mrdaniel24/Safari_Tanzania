<?php
$ap       = $activePage ?? 'dashboard';
$userName = $_SESSION['full_name'] ?? 'User';
$logoUrl  = base_url('assets/images/logo-cropped-transparent.png');
$avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=0B1E5B&color=fff';
$flashes  = flash_pull();
$cls = fn(string $p) => $ap === $p
    ? 'owner-nav-link is-active'
    : 'owner-nav-link';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= e($pageTitle ?? 'Safari Tanzania') ?> - Safari Tanzania</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/owner.css')) ?>?v=estate-owner">
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0F7BD9',
                        navy: '#0B1E5B',
                        ink: '#07142F',
                        surface: '#F5F8FC',
                        cyan: '#1EC6FF'
                    },
                    fontFamily: { sans: ['Arial', 'Helvetica', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        :root { --navy:#0B1E5B; --blue:#0F7BD9; --cyan:#1EC6FF; --ink:#07142F; }
        * { letter-spacing: 0; }
        .material-symbols-outlined { font-family: 'Material Symbols Outlined'; font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24; }
        body.owner-shell { background: #f4f8fc; color: var(--ink); }
        .owner-sidebar {
            background: linear-gradient(180deg, #07142F 0%, #0B1E5B 58%, #08244E 100%);
            box-shadow: 18px 0 60px rgba(7,20,47,.18);
        }
        .owner-sidebar::after {
            content: '';
            position: absolute;
            inset: auto 18px 18px 18px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(30,198,255,.36), transparent);
        }
        .owner-logo img { width: 132px; max-height: 58px; object-fit: contain; filter: drop-shadow(0 10px 22px rgba(0,0,0,.24)); }
        .owner-mini-card {
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.08);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
        }
        .owner-nav-link {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px; border-radius: 14px;
            color: rgba(255,255,255,.68); font-weight: 650; font-size: 14px;
            transition: .2s ease;
        }
        .owner-nav-link:hover { color:#fff; background: rgba(255,255,255,.08); transform: translateX(2px); }
        .owner-nav-link.is-active {
            color:#fff;
            background: linear-gradient(135deg, rgba(15,123,217,.95), rgba(30,198,255,.62));
            box-shadow: 0 12px 28px rgba(15,123,217,.28);
        }
        .owner-topbar { background: rgba(255,255,255,.82); backdrop-filter: blur(18px); }
        .owner-page { background: radial-gradient(circle at top left, rgba(30,198,255,.13), transparent 34%), #f4f8fc; }
        .owner-action {
            display: inline-flex; align-items:center; gap: 8px;
            border-radius: 999px; padding: 10px 16px;
            background: linear-gradient(135deg, #0F7BD9, #1EC6FF);
            color: #fff; font-weight: 750; font-size: 14px;
            box-shadow: 0 12px 30px rgba(15,123,217,.24);
            transition: .2s ease;
        }
        .owner-action:hover { transform: translateY(-1px); box-shadow: 0 16px 36px rgba(15,123,217,.30); }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.24); border-radius: 10px; }
        @media (max-width: 768px) {
            .owner-sidebar { display: none; }
        }
    </style>
</head>
<body class="owner-shell font-sans">
<div class="flex min-h-screen owner-page">
    <aside class="owner-sidebar hidden md:flex flex-col h-screen w-72 fixed left-0 top-0 z-40 overflow-y-auto custom-scrollbar text-white px-4 py-5">
        <div class="owner-logo px-2 pb-5">
            <a href="<?= e(base_url('owner/dashboard.php')) ?>" class="inline-flex items-center">
                <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania">
            </a>
        </div>

        <div class="owner-mini-card rounded-2xl p-4 mb-5">
            <div class="flex items-center gap-3">
                <img src="<?= e($avatarUrl) ?>" alt="Avatar" class="w-12 h-12 rounded-2xl border border-white/15 shadow-lg">
                <div class="min-w-0">
                    <div class="text-sm font-bold text-white truncate"><?= e($userName) ?></div>
                    <div class="text-xs text-cyan-100/75">Property partner</div>
                </div>
            </div>
        </div>

        <nav class="flex-1 space-y-1">
            <a href="<?= e(base_url('owner/dashboard.php')) ?>" class="<?= $cls('dashboard') ?>">
                <span class="material-symbols-outlined">dashboard</span><span>Dashboard</span>
            </a>
            <a href="<?= e(base_url('owner/properties.php')) ?>" class="<?= $cls('properties') ?>">
                <span class="material-symbols-outlined">domain</span><span>My Properties</span>
            </a>
            <a href="<?= e(base_url('owner/bookings.php')) ?>" class="<?= $cls('bookings') ?>">
                <span class="material-symbols-outlined">calendar_month</span><span>Bookings</span>
            </a>            <a href="<?= e(base_url('owner/profile_verification.php')) ?>" class="<?= $cls('verification') ?>">
                <span class="material-symbols-outlined">verified_user</span><span>Verification</span>
            </a>
        </nav>

        <div class="pt-4 mt-4 border-t border-white/10">
            <a href="<?= e(base_url('public/index.php')) ?>" class="owner-nav-link">
                <span class="material-symbols-outlined">travel_explore</span><span>View Site</span>
            </a>
            <a href="<?= e(base_url('auth/logout.php')) ?>" class="owner-nav-link">
                <span class="material-symbols-outlined">logout</span><span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 md:ml-72 flex flex-col min-h-screen">
        <header class="owner-topbar sticky top-0 z-30 border-b border-slate-200/70">
            <div class="flex justify-between items-center w-full px-5 md:px-8 py-4 max-w-7xl mx-auto">
                <div>
                    <p class="text-xs font-bold uppercase text-primary">Owner portal</p>
                    <h1 class="text-xl md:text-2xl font-bold text-ink"><?= e($pageTitle ?? '') ?></h1>
                </div>
                <a href="<?= e(base_url('owner/add_property.php')) ?>" class="owner-action">
                    <span class="material-symbols-outlined" style="font-size:19px;">add_home</span>
                    <span>Add Property</span>
                </a>
            </div>
        </header>

        <?php if ($flashes): ?>
        <div class="px-5 md:px-8 pt-4 max-w-7xl mx-auto w-full space-y-2">
            <?php foreach ($flashes as $f): $isErr = ($f['type'] === 'error'); ?>
                <div class="flex items-center gap-2 px-4 py-3 rounded-2xl text-sm border shadow-sm
                    <?= $isErr ? 'bg-red-50 border-red-200 text-red-700' : 'bg-blue-50 border-blue-200 text-blue-800' ?>">
                    <span class="material-symbols-outlined" style="font-size:18px;"><?= $isErr ? 'error' : 'check_circle' ?></span>
                    <?= e($f['msg']) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

