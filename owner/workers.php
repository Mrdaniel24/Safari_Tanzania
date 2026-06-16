<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];

$propsStmt = $pdo->prepare("SELECT id, name, location FROM accommodations WHERE owner_id = ? ORDER BY name ASC");
$propsStmt->execute([$ownerId]);
$properties = $propsStmt->fetchAll();
$propIds = array_map('intval', array_column($properties, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim(strtolower($_POST['email'] ?? ''));
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $selected = array_values(array_intersect(array_map('intval', $_POST['accommodation_ids'] ?? []), $propIds));

        $errors = [];
        if ($fullName === '') $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

        if (!$errors) {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) $errors[] = 'That email is already registered.';
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role, owner_id, status) VALUES (?, ?, ?, ?, 'worker', ?, 'active')")
                ->execute([$fullName, $email, $phone ?: null, $hash, $ownerId]);
            $workerId = (int)$pdo->lastInsertId();
            if ($selected) {
                $awStmt = $pdo->prepare('INSERT INTO accommodation_workers (accommodation_id, worker_id) VALUES (?, ?)');
                foreach ($selected as $accId) $awStmt->execute([$accId, $workerId]);
            }
            $pdo->commit();
            flash_set('success', 'Worker account created.');
        } else {
            foreach ($errors as $err) flash_set('error', $err);
        }
        redirect('owner/workers.php');
    }

    if ($action === 'update') {
        $workerId = (int)($_POST['worker_id'] ?? 0);
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND owner_id = ? AND role = 'worker'");
        $chk->execute([$workerId, $ownerId]);
        if (!$chk->fetch()) {
            flash_set('error', 'Worker not found.');
            redirect('owner/workers.php');
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $status   = ($_POST['status'] ?? 'active') === 'suspended' ? 'suspended' : 'active';
        $password = $_POST['password'] ?? '';
        $selected = array_values(array_intersect(array_map('intval', $_POST['accommodation_ids'] ?? []), $propIds));

        $errors = [];
        if ($fullName === '') $errors[] = 'Full name is required.';
        if ($password !== '' && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

        if (!$errors) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, status = ?, password = ? WHERE id = ?')
                    ->execute([$fullName, $phone ?: null, $status, $hash, $workerId]);
            } else {
                $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, status = ? WHERE id = ?')
                    ->execute([$fullName, $phone ?: null, $status, $workerId]);
            }

            $pdo->prepare("DELETE aw FROM accommodation_workers aw
                            JOIN accommodations a ON a.id = aw.accommodation_id
                            WHERE aw.worker_id = ? AND a.owner_id = ?")
                ->execute([$workerId, $ownerId]);
            if ($selected) {
                $awStmt = $pdo->prepare('INSERT INTO accommodation_workers (accommodation_id, worker_id) VALUES (?, ?)');
                foreach ($selected as $accId) $awStmt->execute([$accId, $workerId]);
            }
            flash_set('success', 'Worker updated.');
        } else {
            foreach ($errors as $err) flash_set('error', $err);
        }
        redirect('owner/workers.php');
    }

    if ($action === 'delete') {
        $workerId = (int)($_POST['worker_id'] ?? 0);
        $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND owner_id = ? AND role = 'worker'");
        $del->execute([$workerId, $ownerId]);
        flash_set($del->rowCount() ? 'success' : 'error', $del->rowCount() ? 'Worker removed.' : 'Could not remove that worker.');
        redirect('owner/workers.php');
    }
}

$stmt = $pdo->prepare("SELECT id, full_name, email, phone, status, created_at FROM users WHERE owner_id = ? AND role = 'worker' ORDER BY created_at DESC");
$stmt->execute([$ownerId]);
$workers = $stmt->fetchAll();

$assignStmt = $pdo->prepare("SELECT a.id, a.name FROM accommodation_workers aw JOIN accommodations a ON a.id = aw.accommodation_id WHERE aw.worker_id = ? ORDER BY a.name ASC");
foreach ($workers as &$w) {
    $assignStmt->execute([$w['id']]);
    $w['properties'] = $assignStmt->fetchAll();
}
unset($w);

$activePage = 'workers';
$pageTitle  = 'Workers';
include __DIR__ . '/../includes/header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-zinc-900">Workers</h2>
            <p class="text-sm text-zinc-500 mt-1"><?= count($workers) ?> worker<?= count($workers) !== 1 ? 's' : '' ?> on your team</p>
        </div>
    </div>

    <?php if (!$properties): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-5 text-sm">
            You need at least one property before you can assign workers.
            <a href="<?= e(base_url('owner/add_property.php')) ?>" class="underline font-semibold">Add a property</a> first.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-zinc-900 mb-4">Add a worker</h3>
        <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <label class="block">
                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Full name</span>
                <input type="text" name="full_name" required class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
            </label>
            <label class="block">
                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Email</span>
                <input type="email" name="email" required class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
            </label>
            <label class="block">
                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Phone</span>
                <input type="text" name="phone" class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
            </label>
            <label class="block">
                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Password</span>
                <input type="password" name="password" required minlength="6" class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
            </label>

            <?php if ($properties): ?>
            <div class="md:col-span-2">
                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-2">Assign properties</span>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($properties as $p): ?>
                        <label class="inline-flex items-center gap-2 bg-zinc-50 border border-zinc-200 rounded-lg px-3 py-2 text-sm cursor-pointer hover:border-primary">
                            <input type="checkbox" name="accommodation_ids[]" value="<?= (int)$p['id'] ?>" class="rounded text-primary focus:ring-primary">
                            <span><?= e($p['name']) ?> <span class="text-zinc-400">- <?= e($p['location']) ?></span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="md:col-span-2">
                <button type="submit" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-all">
                    <span class="material-symbols-outlined" style="font-size:20px;">person_add</span>Create worker account
                </button>
            </div>
        </form>
    </div>

    <?php if (!$workers): ?>
        <div class="bg-white rounded-2xl border border-zinc-200 p-20 text-center">
            <span class="material-symbols-outlined text-zinc-300" style="font-size:64px;">badge</span>
            <p class="text-zinc-500 mt-4 text-lg font-medium">No workers yet</p>
            <p class="text-zinc-400 text-sm mt-1">Add staff accounts to help manage bookings and payments for your properties.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($workers as $w):
                $assignedIds = array_map('intval', array_column($w['properties'], 'id'));
            ?>
            <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm p-5">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <p class="font-bold text-zinc-900"><?= e($w['full_name']) ?></p>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            <?= e($w['email']) ?><?= $w['phone'] ? ' · ' . e($w['phone']) : '' ?>
                        </p>
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <?php if ($w['properties']): foreach ($w['properties'] as $p): ?>
                                <span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-full font-semibold"><?= e($p['name']) ?></span>
                            <?php endforeach; else: ?>
                                <span class="text-xs text-zinc-400">No properties assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $w['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' ?>">
                            <?= e($w['status']) ?>
                        </span>
                        <form method="post" onsubmit="return confirm('Remove this worker? This cannot be undone.');">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="worker_id" value="<?= (int)$w['id'] ?>">
                            <button type="submit" class="text-xs font-semibold text-red-700 bg-red-50 px-3 py-1.5 rounded-lg hover:bg-red-100 transition">Remove</button>
                        </form>
                    </div>
                </div>

                <details class="mt-4">
                    <summary class="cursor-pointer text-xs font-semibold text-zinc-500 hover:text-zinc-700">Edit details &amp; property assignments</summary>
                    <form method="post" class="mt-4 pt-4 border-t border-zinc-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="worker_id" value="<?= (int)$w['id'] ?>">

                        <label class="block">
                            <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Full name</span>
                            <input type="text" name="full_name" value="<?= e($w['full_name']) ?>" required class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Phone</span>
                            <input type="text" name="phone" value="<?= e($w['phone'] ?? '') ?>" class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Status</span>
                            <select name="status" class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
                                <option value="active" <?= $w['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="suspended" <?= $w['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">New password</span>
                            <input type="password" name="password" minlength="6" placeholder="Leave blank to keep current" class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
                        </label>

                        <?php if ($properties): ?>
                        <div class="md:col-span-2">
                            <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-2">Assigned properties</span>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($properties as $p): ?>
                                    <label class="inline-flex items-center gap-2 bg-zinc-50 border border-zinc-200 rounded-lg px-3 py-2 text-sm cursor-pointer hover:border-primary">
                                        <input type="checkbox" name="accommodation_ids[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $assignedIds, true) ? 'checked' : '' ?> class="rounded text-primary focus:ring-primary">
                                        <span><?= e($p['name']) ?> <span class="text-zinc-400">- <?= e($p['location']) ?></span></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="md:col-span-2">
                            <button type="submit" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-all">
                                <span class="material-symbols-outlined" style="font-size:20px;">save</span>Save changes
                            </button>
                        </div>
                    </form>
                </details>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
