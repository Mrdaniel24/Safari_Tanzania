<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$filter  = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$sql = "
    SELECT a.*, u.full_name AS owner_name, u.email AS owner_email,
           COUNT(DISTINCT r.id) AS room_count,
           COUNT(DISTINCT b.id) AS booking_count,
           COUNT(DISTINCT ai.id) AS image_count,
           COUNT(DISTINCT s.id) AS service_count
    FROM accommodations a
    JOIN users u ON u.id = a.owner_id
    LEFT JOIN rooms r ON r.accommodation_id = a.id
    LEFT JOIN bookings b ON b.room_id = r.id AND b.booking_status != 'cancelled'
    LEFT JOIN accommodation_images ai ON ai.accommodation_id = a.id
    LEFT JOIN accommodation_services s ON s.accommodation_id = a.id
    WHERE 1=1
";
$params = [];
if ($filter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $filter;
}
$sql .= " GROUP BY a.id ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accommodations = $stmt->fetchAll();

// Count per status for tab badges
$counts = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM accommodations GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$activePage = 'accommodations';
$pageTitle  = 'Accommodations';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">All Accommodations</h2>
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
                            <th class="px-6 py-4">Property</th>
                            <th class="px-6 py-4">Owner</th>
                            <th class="px-6 py-4">Location</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Content</th>
                            <th class="px-6 py-4">Bookings</th>
                            <th class="px-6 py-4">Submitted</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($accommodations as $a): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($a['image_url']): ?>
                                        <img src="<?= e($a['image_url']) ?>" alt=""
                                             class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                            <span class="material-symbols-outlined text-slate-400" style="font-size:20px;">image</span>
                                        </div>
                                    <?php endif; ?>
                                    <p class="font-semibold text-slate-900"><?= e($a['name']) ?></p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-800"><?= e($a['owner_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e($a['owner_email']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-slate-600"><?= e($a['location']) ?></td>
                            <td class="px-6 py-4">
                                <?php $sc = match($a['status']) {
                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                    'pending'  => 'bg-amber-100 text-amber-800',
                                    'rejected' => 'bg-red-100 text-red-700',
                                    default    => 'bg-slate-100 text-slate-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $sc ?>">
                                    <?= e($a['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-700">
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 px-2.5 py-1 text-xs font-bold"><span class="material-symbols-outlined" style="font-size:14px;">bed</span><?= (int)$a['room_count'] ?></span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 text-sky-700 px-2.5 py-1 text-xs font-bold"><span class="material-symbols-outlined" style="font-size:14px;">photo_library</span><?= (int)($a['image_count'] ?? 0) ?></span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 text-violet-700 px-2.5 py-1 text-xs font-bold"><span class="material-symbols-outlined" style="font-size:14px;">room_service</span><?= (int)($a['service_count'] ?? 0) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-700"><?= (int)$a['booking_count'] ?></td>
                            <td class="px-6 py-4 text-slate-500 text-xs">
                                <?= date('M d, Y', strtotime($a['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= e(base_url('admin/review_accommodation.php?id=' . (int)$a['id'])) ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-sky-700 bg-sky-50 px-3 py-1.5 rounded-lg hover:bg-sky-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:15px;">fact_check</span> Review
                                    </a>
                                    <?php if ($a['status'] === 'approved'): ?>
                                        <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$a['id'])) ?>"
                                           target="_blank"
                                           class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition">
                                            <span class="material-symbols-outlined" style="font-size:15px;">open_in_new</span> Public
                                        </a>
                                    <?php endif; ?>
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




