<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$filter  = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$sql = "
    SELECT a.id, a.name, a.region, a.status AS verification_status, a.created_at
    FROM accommodations a
    WHERE 1=1
";
$params = [];
if ($filter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accommodations = $stmt->fetchAll();

// Count per status for tab badges (only accommodations table)
$counts = $pdo->query("SELECT status, COUNT(*) AS cnt FROM accommodations GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$activePage = 'accommodations';
$pageTitle  = 'Property Verification';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Property Verification</h2>
            <p class="text-sm text-slate-500 mt-1"><?= count($accommodations) ?> result<?= count($accommodations) !== 1 ? 's' : '' ?></p>
        </div>
        <!-- Status filter tabs -->
        <div class="flex gap-2 flex-wrap">
            <?php
            $tabs = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
            foreach ($tabs as $val => $label):
                $cnt = $val === 'all' ? array_sum($counts) : ($counts[$val] ?? 0);
            ?>
                <a href="?status=<?= $val ?>"
                   class="px-4 py-2 rounded-full text-xs font-bold border transition flex items-center gap-1.5
                       <?= $filter === $val
                           ? 'bg-sky-700 text-white border-sky-700'
                           : 'bg-white text-slate-600 border-slate-300 hover:border-sky-500 hover:text-sky-700' ?>">
                    <?= $label ?>
                    <?php if ($cnt > 0): ?>
                        <span class="<?= $filter === $val ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?> px-1.5 py-0.5 rounded-full text-[10px] font-bold">
                            <?= $cnt ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$accommodations): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-20 text-center">
            <span class="material-symbols-outlined text-slate-300" style="font-size:64px;">domain_disabled</span>
            <p class="text-slate-500 mt-4 text-lg font-medium">No accommodations found</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-4">Property ID</th>
                            <th class="px-6 py-4">Property Name</th>
                            <th class="px-6 py-4">Region</th>
                            <th class="px-6 py-4">Verification Status</th>
                            <th class="px-6 py-4">Registration Date</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($accommodations as $a): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-mono text-slate-700">#<?= (int)$a['id'] ?></td>
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900"><?= e($a['name']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= e($a['region'] ?? 'Not specified') ?></td>
                            <td class="px-6 py-4">
                                <?php $sc = match($a['verification_status'] ?? '') {
                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                    'pending'  => 'bg-amber-100 text-amber-800',
                                    'rejected' => 'bg-red-100 text-red-700',
                                    default    => 'bg-slate-100 text-slate-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $sc ?>">
                                    <?= e($a['verification_status'] ?? $a['status'] ?? 'unknown') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs">
                                <?= date('M d, Y', strtotime($a['created_at'])) ?>
                            </td>
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
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
