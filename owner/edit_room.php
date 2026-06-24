<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$roomId  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT r.*, a.name AS acc_name, a.id AS acc_id
    FROM rooms r
    JOIN accommodations a ON a.id = r.accommodation_id
    WHERE r.id = ? AND a.owner_id = ?
");
$stmt->execute([$roomId, $ownerId]);
$room = $stmt->fetch();
if (!$room) { http_response_code(403); die('Room not found or access denied.'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $room_type   = trim($_POST['room_type'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 1);
    $total_rooms = (int)($_POST['total_rooms'] ?? 1);

    $allowedRoomTypes = ['Single Room', 'Double Room', 'VIP Single', 'VIP Double'];
    if (!in_array($room_type, $allowedRoomTypes, true)) $errors[] = 'Please select a valid room type.';
    if (!is_numeric($price) || (float)$price <= 0)        $errors[] = 'Price must be a positive number.';
    if ($capacity < 1 || $capacity > 20)                  $errors[] = 'Capacity must be between 1 and 20 guests.';
    if ($total_rooms < 1 || $total_rooms > 500)           $errors[] = 'Number of units must be between 1 and 500.';

    if (!$errors) {
        $pdo->prepare("
            UPDATE rooms SET room_type = ?, price = ?, capacity = ?, total_rooms = ? WHERE id = ?
        ")->execute([$room_type, (float)$price, $capacity, $total_rooms, $roomId]);
        flash_set('success', 'Room updated.');
        redirect('owner/rooms.php?acc_id=' . (int)$room['acc_id']);
    }

    $room = array_merge($room, compact('room_type', 'price', 'capacity', 'total_rooms'));
}

$activePage = 'properties';
$pageTitle  = 'Edit Room';
include __DIR__ . '/../includes/header.php';
?>
<div class="p-6 md:p-10 max-w-2xl mx-auto w-full">
    <a href="<?= e(base_url('owner/rooms.php?acc_id=' . (int)$room['acc_id'])) ?>"
       class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900 mb-6">
        <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span> Back to rooms
    </a>

    <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm p-8">
        <h2 class="text-2xl font-bold text-zinc-900 mb-1">Edit Room Type</h2>
        <p class="text-zinc-500 text-sm mb-6">Property: <strong><?= e($room['acc_name']) ?></strong></p>

        <?php foreach ($errors as $err): ?>
            <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg mb-4">
                <span class="material-symbols-outlined" style="font-size:18px;">error</span><?= e($err) ?>
            </div>
        <?php endforeach; ?>

        <form method="post" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $roomId ?>">

            <div>
                <label class="block text-sm font-semibold text-zinc-700 mb-1">
                    Room Category / Type <span class="text-red-500">*</span>
                </label>
                <?php
                $allowedRoomTypes = ['Single Room', 'Double Room', 'VIP Single', 'VIP Double'];
                $currentType = $room['room_type'] ?? '';
                ?>
                <select name="room_type" required
                        class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                    <option value="">— Select room type —</option>
                    <?php if ($currentType !== '' && !in_array($currentType, $allowedRoomTypes, true)): ?>
                        <option value="<?= e($currentType) ?>" selected>⚠️ <?= e($currentType) ?> (legacy — reselect below)</option>
                    <?php endif; ?>
                    <option value="Single Room"  <?= $currentType === 'Single Room'  ? 'selected' : '' ?>>Single Room</option>
                    <option value="Double Room"  <?= $currentType === 'Double Room'  ? 'selected' : '' ?>>Double Room</option>
                    <option value="VIP Single"   <?= $currentType === 'VIP Single'   ? 'selected' : '' ?>>VIP Single</option>
                    <option value="VIP Double"   <?= $currentType === 'VIP Double'   ? 'selected' : '' ?>>VIP Double</option>
                </select>
                <?php if ($currentType !== '' && !in_array($currentType, $allowedRoomTypes, true)): ?>
                    <p class="text-xs text-amber-600 mt-1">Existing type "<?= e($currentType) ?>" is not a standard option. Please select one of the standard room types above.</p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-zinc-700 mb-1">
                        Price / Night (Tsh) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 font-bold">Tsh</span>
                        <input type="number" name="price" min="1" step="0.01" required value="<?= e($room['price']) ?>"
                               class="w-full pl-12 rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-zinc-700 mb-1">
                        Max Guests <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="capacity" min="1" max="20" required value="<?= e($room['capacity']) ?>"
                           class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-zinc-700 mb-1">
                        Number of Units <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="total_rooms" min="1" max="500" required value="<?= e($room['total_rooms']) ?>"
                           class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 bg-primary text-white py-3 rounded-lg font-bold hover:bg-emerald-800 transition">
                    Save Changes
                </button>
                <a href="<?= e(base_url('owner/rooms.php?acc_id=' . (int)$room['acc_id'])) ?>"
                   class="flex-1 text-center border border-zinc-300 py-3 rounded-lg font-semibold text-zinc-700 hover:bg-zinc-50 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
