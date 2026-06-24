<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$sql = "
    SELECT u.id, u.role, u.status, u.created_at
    FROM users u
    WHERE u.role = 'owner'
    ORDER BY u.created_at DESC
";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

$ownerCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'owner'")->fetchColumn();

$activePage = 'users';
$pageTitle  = 'User Verification';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">User Verification</h2>
            <p class="text-sm text-slate-500 mt-1"><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-500">Owners: <?= $ownerCount ?></span>
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
                            <th class="px-6 py-4">User ID</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Registered</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-mono text-slate-700">#<?= (int)$u['id'] ?></td>
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
                            <td class="px-6 py-4 text-slate-500 text-xs">
                                <?= date('M d, Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
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
                                    <!-- Only owner management allowed: suspend/activate -->
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
