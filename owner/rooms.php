<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$accId   = (int)($_GET['acc_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM accommodations WHERE id = ? AND owner_id = ?");
$stmt->execute([$accId, $ownerId]);
$acc = $stmt->fetch();
if (!$acc) { http_response_code(403); die('Property not found or access denied.'); }

$stmt = $pdo->prepare("
    SELECT r.*, COUNT(b.id) AS active_bookings
    FROM rooms r
    LEFT JOIN bookings b ON b.room_id = r.id AND b.booking_status = 'confirmed'
    WHERE r.accommodation_id = ?
    GROUP BY r.id
    ORDER BY r.price ASC
");
$stmt->execute([$accId]);
$rooms = $stmt->fetchAll();

$activePage = 'properties';
$pageTitle  = 'Manage Rooms';
include __DIR__ . '/../includes/header.php';
?>
<div class="p-6 md:p-10 max-w-5xl mx-auto w-full space-y-6">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <a href="<?= e(base_url('owner/properties.php')) ?>"
               class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900 mb-2">
                <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span> Back to properties
            </a>
            <h2 class="text-2xl font-bold text-zinc-900"><?= e($acc['name']) ?></h2>
            <p class="text-zinc-500 text-sm mt-0.5"><?= e($acc['location']) ?> &mdash; Room Management</p>
        </div>
        <a href="<?= e(base_url('owner/add_room.php?acc_id=' . $accId)) ?>"
           class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-emerald-800 transition-all">
            <span class="material-symbols-outlined" style="font-size:20px;">add</span>Add Room Type
        </a>
    </div>

    <?php if (!$rooms): ?>
        <div class="bg-white rounded-2xl border border-zinc-200 p-20 text-center">
            <span class="material-symbols-outlined text-zinc-300" style="font-size:64px;">bed</span>
            <p class="text-zinc-500 mt-4 text-lg font-medium">No rooms added yet</p>
            <p class="text-zinc-400 text-sm mt-1">Add room types with pricing so travelers can book.</p>
            <a href="<?= e(base_url('owner/add_room.php?acc_id=' . $accId)) ?>"
               class="inline-block mt-6 bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-emerald-800 transition">
                Add first room
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-4">Room Type</th>
                            <th class="px-6 py-4">Price / Night</th>
                            <th class="px-6 py-4">Max Guests</th>
                            <th class="px-6 py-4">Units</th>
                            <th class="px-6 py-4">Active Bookings</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($rooms as $r): ?>
                        <tr class="hover:bg-zinc-50">
                            <td class="px-6 py-4 font-semibold text-zinc-900"><?= e($r['room_type']) ?></td>
                            <td class="px-6 py-4 font-bold text-amber-600">
                                $<?= number_format((float)$r['price'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-zinc-600">
                                <span class="inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined" style="font-size:16px;">group</span>
                                    <?= (int)$r['capacity'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-zinc-600"><?= (int)$r['total_rooms'] ?></td>
                            <td class="px-6 py-4">
                                <?php $ab = (int)$r['active_bookings']; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold
                                    <?= $ab > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-100 text-zinc-500' ?>">
                                    <?= $ab ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= e(base_url('owner/edit_room.php?id=' . (int)$r['id'])) ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:16px;">edit</span>Edit
                                    </a>
                                    <form method="post" action="<?= e(base_url('owner/delete_room.php')) ?>"
                                          onsubmit="return confirm('Delete this room type? Existing bookings will also be removed.');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="acc_id" value="<?= $accId ?>">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 bg-red-50 px-3 py-1.5 rounded-lg hover:bg-red-100 transition">
                                            <span class="material-symbols-outlined" style="font-size:16px;">delete</span>Delete
                                        </button>
                                    </form>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
