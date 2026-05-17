<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
$pages = (int)ceil($total / $perPage);

$stmt = $pdo->prepare("
    SELECT l.*, u.full_name AS admin_name, u.email AS admin_email
    FROM admin_logs l
    JOIN users u ON u.id = l.admin_id
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$logs = $stmt->fetchAll();

$activePage = 'logs';
$pageTitle  = 'Activity Logs';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-5xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Activity Logs</h2>
            <p class="text-sm text-slate-500 mt-1">
                <?= number_format($total) ?> total log entr<?= $total !== 1 ? 'ies' : 'y' ?>
            </p>
        </div>
    </div>

    <?php if (!$logs): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-20 text-center">
            <span class="material-symbols-outlined text-slate-300" style="font-size:64px;">history</span>
            <p class="text-slate-500 mt-4 text-lg font-medium">No activity logged yet</p>
            <p class="text-slate-400 text-sm mt-1">Admin actions (approvals, user changes) will appear here.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <ul class="divide-y divide-slate-100">
                <?php foreach ($logs as $l): ?>
                <li class="px-6 py-4 flex items-start gap-4 hover:bg-slate-50 transition">
                    <div class="w-8 h-8 rounded-full bg-sky-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-sky-600" style="font-size:18px;">history</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-800 leading-snug"><?= e($l['action']) ?></p>
                        <div class="flex items-center gap-2 mt-1">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($l['admin_name']) ?>&background=e0f2fe&color=0c6780&size=20"
                                 alt="" class="w-5 h-5 rounded-full">
                            <p class="text-xs text-slate-400">
                                <span class="font-semibold text-slate-600"><?= e($l['admin_name']) ?></span>
                                &middot; <?= e($l['admin_email']) ?>
                            </p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 flex-shrink-0 mt-0.5">
                        <?= date('M d, Y', strtotime($l['created_at'])) ?><br>
                        <span class="text-slate-300"><?= date('H:i:s', strtotime($l['created_at'])) ?></span>
                    </p>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="flex items-center justify-center gap-2 pt-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>"
                   class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
                    ← Previous
                </a>
            <?php endif; ?>
            <span class="text-sm text-slate-500 px-3">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a href="?page=<?= $page + 1 ?>"
                   class="px-4 py-2 rounded-lg border border-slate-300 text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Next →
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
