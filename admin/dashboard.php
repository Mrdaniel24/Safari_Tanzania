<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

// Platform stats
$totalUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$totalOwners  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();
$totalTravelers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'traveler'")->fetchColumn();

$totalAcc     = (int)$pdo->query("SELECT COUNT(*) FROM accommodations")->fetchColumn();
$pendingAcc   = (int)$pdo->query("SELECT COUNT(*) FROM accommodations WHERE status = 'pending'")->fetchColumn();
$approvedAcc  = (int)$pdo->query("SELECT COUNT(*) FROM accommodations WHERE status = 'approved'")->fetchColumn();

$totalBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$confirmedBook  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed'")->fetchColumn();

$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE booking_status != 'cancelled'")->fetchColumn();

// Properties pending approval
$pending = $pdo->query("
    SELECT a.*, u.full_name AS owner_name, u.email AS owner_email,
           COUNT(r.id) AS room_count
    FROM accommodations a
    JOIN users u ON u.id = a.owner_id
    LEFT JOIN rooms r ON r.accommodation_id = a.id
    WHERE a.status = 'pending'
    GROUP BY a.id
    ORDER BY a.created_at ASC
    LIMIT 10
")->fetchAll();

// Recent admin logs
$logs = $pdo->query("
    SELECT l.*, u.full_name AS admin_name
    FROM admin_logs l
    JOIN users u ON u.id = l.admin_id
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll();

$activePage = 'dashboard';
$pageTitle  = 'Admin Dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-8">

    <!-- Stats row 1 -->
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-sky-600 text-3xl">group</span>
                <span class="text-xs font-bold bg-sky-100 text-sky-700 px-2.5 py-1 rounded-full">Users</span>
            </div>
            <p class="text-3xl font-black text-slate-900"><?= $totalUsers ?></p>
            <p class="text-xs text-slate-400 mt-1 font-semibold uppercase tracking-wide">
                <?= $totalOwners ?> owners Â· <?= $totalTravelers ?> travelers
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-violet-600 text-3xl">domain</span>
                <?php if ($pendingAcc > 0): ?>
                    <span class="text-xs font-bold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">
                        <?= $pendingAcc ?> pending
                    </span>
                <?php else: ?>
                    <span class="text-xs font-bold bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full">All clear</span>
                <?php endif; ?>
            </div>
            <p class="text-3xl font-black text-slate-900"><?= $totalAcc ?></p>
            <p class="text-xs text-slate-400 mt-1 font-semibold uppercase tracking-wide">
                <?= $approvedAcc ?> approved
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-blue-600 text-3xl">calendar_month</span>
                <span class="text-xs font-bold bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full">Bookings</span>
            </div>
            <p class="text-3xl font-black text-slate-900"><?= $totalBookings ?></p>
            <p class="text-xs text-slate-400 mt-1 font-semibold uppercase tracking-wide">
                <?= $confirmedBook ?> confirmed
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="material-symbols-outlined text-emerald-600 text-3xl">payments</span>
                <span class="text-xs font-bold bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full">Revenue</span>
            </div>
            <p class="text-3xl font-black text-slate-900">$<?= number_format($totalRevenue, 0) ?></p>
            <p class="text-xs text-slate-400 mt-1 font-semibold uppercase tracking-wide">Platform total</p>
        </div>
    </section>

    <!-- Pending approvals -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-bold text-slate-900">Pending Approvals</h2>
                <?php if ($pendingAcc > 0): ?>
                    <span class="text-xs font-bold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full">
                        <?= $pendingAcc ?> waiting
                    </span>
                <?php endif; ?>
            </div>
            <a href="<?= base_url('admin/accommodations.php?status=pending') ?>"
               class="text-sm font-semibold text-sky-700 hover:underline">View all â†’</a>
        </div>
        <?php if (!$pending): ?>
            <div class="px-6 py-12 text-center text-slate-400">
                <span class="material-symbols-outlined text-emerald-400" style="font-size:40px;">check_circle</span>
                <p class="mt-2 font-medium">No pending approvals. All caught up!</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-3">Property</th>
                            <th class="px-6 py-3">Owner</th>
                            <th class="px-6 py-3">Location</th>
                            <th class="px-6 py-3">Rooms</th>
                            <th class="px-6 py-3">Submitted</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($pending as $a): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-semibold text-slate-900"><?= e($a['name']) ?></td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-800"><?= e($a['owner_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e($a['owner_email']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= e($a['location']) ?></td>
                            <td class="px-6 py-4 text-slate-600"><?= (int)$a['room_count'] ?></td>
                            <td class="px-6 py-4 text-slate-500 text-xs"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= e(base_url('admin/review_accommodation.php?id=' . (int)$a['id'])) ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-sky-700 bg-sky-50 px-3 py-1.5 rounded-lg hover:bg-sky-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:15px;">fact_check</span> Review
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Recent activity logs -->
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Recent Activity</h2>
            <a href="<?= base_url('admin/logs.php') ?>" class="text-sm font-semibold text-sky-700 hover:underline">View all â†’</a>
        </div>
        <?php if (!$logs): ?>
            <div class="px-6 py-10 text-center text-slate-400 italic">No activity logged yet.</div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($logs as $l): ?>
                <li class="px-6 py-4 flex items-start gap-3">
                    <span class="material-symbols-outlined text-sky-400 mt-0.5" style="font-size:20px;">history</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-800"><?= e($l['action']) ?></p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            by <strong><?= e($l['admin_name']) ?></strong> &middot;
                            <?= date('M d, Y H:i', strtotime($l['created_at'])) ?>
                        </p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>



