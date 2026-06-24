<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

// Minimal system monitoring page - aggregated security_events if available
try {
    $recent = $pdo->query("SELECT id, user_id, event_type, meta, created_at FROM security_events ORDER BY created_at DESC LIMIT 25")->fetchAll();
    $totalEvents = (int)$pdo->query("SELECT COUNT(*) FROM security_events")->fetchColumn();
} catch (Throwable $e) {
    $recent = [];
    $totalEvents = 0;
}

$activePage = 'monitoring';
$pageTitle  = 'System Monitoring';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">
    <h2 class="text-2xl font-bold">System Monitoring</h2>
    <p class="text-sm text-slate-500">Total security events recorded: <?= $totalEvents ?></p>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-4">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold">Recent Security Events</h3>
        </div>
        <?php if (!$recent): ?>
            <div class="px-6 py-8 text-center text-slate-400">No security events available.</div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($recent as $r): ?>
                <li class="px-6 py-4 text-sm flex items-center justify-between">
                    <div class="text-slate-800"><?= e($r['event_type']) ?></div>
                    <div class="text-xs text-slate-500">ID: <?= (int)$r['id'] ?> · User: <?= $r['user_id'] ? ('#' . (int)$r['user_id']) : 'N/A' ?> · <?= date('M d, Y H:i', strtotime($r['created_at'])) ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
