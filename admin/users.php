<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$filter  = $_GET['role'] ?? 'all';
$allowed = ['all', 'traveler', 'owner'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$sql = "
    SELECT u.*,
           COUNT(DISTINCT b.id)  AS booking_count,
           COUNT(DISTINCT a.id)  AS property_count
    FROM users u
    LEFT JOIN bookings b ON b.traveler_id = u.id
    LEFT JOIN accommodations a ON a.owner_id = u.id
    WHERE u.role != 'admin'
";
$params = [];
if ($filter !== 'all') {
    $sql .= " AND u.role = ?";
    $params[] = $filter;
}
$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roleCounts = $pdo->query("
    SELECT role, COUNT(*) AS cnt FROM users WHERE role != 'admin' GROUP BY role
")->fetchAll(PDO::FETCH_KEY_PAIR);

$activePage = 'users';
$pageTitle  = 'Users';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">All Users</h2>
            <p class="text-sm text-slate-500 mt-1"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php foreach (['all' => 'All', 'traveler' => 'Travelers', 'owner' => 'Owners'] as $val => $label):
                $cnt = $val === 'all' ? array_sum($roleCounts) : ($roleCounts[$val] ?? 0);
            ?>
                <a href="?role=<?= $val ?>"
                   class="px-4 py-2 rounded-full text-xs font-bold border transition flex items-center gap-1.5
                       <?= $filter === $val
                           ? 'bg-sky-700 text-white border-sky-700'
                           : 'bg-white text-slate-600 border-slate-300 hover:border-sky-500 hover:text-sky-700' ?>">
                    <?= $label ?>
                    <?php if ($cnt > 0): ?>
                        <span class="<?= $filter === $val ? 'bg-white/20' : 'bg-slate-100' ?> px-1.5 py-0.5 rounded-full text-[10px] font-bold">
                            <?= $cnt ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$users): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-20 text-center">
            <span class="material-symbols-outlined text-slate-300" style="font-size:64px;">person_off</span>
            <p class="text-slate-500 mt-4 text-lg font-medium">No users found</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Bookings</th>
                            <th class="px-6 py-4">Properties</th>
                            <th class="px-6 py-4">Joined</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($u['full_name']) ?>&background=e2e8f0&color=475569&size=36"
                                         alt="" class="w-9 h-9 rounded-full flex-shrink-0">
                                    <div>
                                        <p class="font-semibold text-slate-900"><?= e($u['full_name']) ?></p>
                                        <p class="text-xs text-slate-400"><?= e($u['email']) ?></p>
                                        <?php if ($u['phone']): ?>
                                            <p class="text-xs text-slate-400"><?= e($u['phone']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php $rc = match($u['role']) {
                                    'owner'    => 'bg-violet-100 text-violet-800',
                                    'traveler' => 'bg-blue-100 text-blue-800',
                                    default    => 'bg-slate-100 text-slate-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $rc ?>">
                                    <?= e($u['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php $sc = $u['status'] === 'active'
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-red-100 text-red-700'; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $sc ?>">
                                    <?= e($u['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-700"><?= (int)$u['booking_count'] ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= (int)$u['property_count'] ?></td>
                            <td class="px-6 py-4 text-slate-500 text-xs">
                                <?= date('M d, Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Toggle suspend/activate -->
                                    <form method="post" action="<?= base_url('admin/toggle_user.php') ?>"
                                          onsubmit="return confirm('<?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?> this user?');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <?php if ($u['status'] === 'active'): ?>
                                            <button type="submit"
                                                    class="text-xs font-semibold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg hover:bg-amber-100 transition">
                                                Suspend
                                            </button>
                                        <?php else: ?>
                                            <button type="submit"
                                                    class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition">
                                                Activate
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <!-- Promote/demote role -->
                                    <?php if ($u['role'] === 'traveler'): ?>
                                    <form method="post" action="<?= base_url('admin/toggle_user.php') ?>"
                                          onsubmit="return confirm('Promote this user to Owner?');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="action" value="promote_owner">
                                        <button type="submit"
                                                class="text-xs font-semibold text-violet-700 bg-violet-50 px-3 py-1.5 rounded-lg hover:bg-violet-100 transition">
                                            Make Owner
                                        </button>
                                    </form>
                                    <?php elseif ($u['role'] === 'owner'): ?>
                                    <form method="post" action="<?= base_url('admin/toggle_user.php') ?>"
                                          onsubmit="return confirm('Demote this owner to Traveler?');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="action" value="demote_traveler">
                                        <button type="submit"
                                                class="text-xs font-semibold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg hover:bg-slate-200 transition">
                                            Demote
                                        </button>
                                    </form>
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
