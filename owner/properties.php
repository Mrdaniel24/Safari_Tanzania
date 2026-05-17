<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT a.*,
           COUNT(DISTINCT r.id) AS room_count,
           COUNT(DISTINCT b.id) AS booking_count, COUNT(DISTINCT ai.id) AS image_count
    FROM accommodations a
    LEFT JOIN rooms r ON r.accommodation_id = a.id
    LEFT JOIN bookings b ON b.room_id = r.id AND b.booking_status != 'cancelled'
    LEFT JOIN accommodation_images ai ON ai.accommodation_id = a.id
    WHERE a.owner_id = ?
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$stmt->execute([$ownerId]);
$properties = $stmt->fetchAll();

$activePage = 'properties';
$pageTitle  = 'My Properties';
include __DIR__ . '/../includes/header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-zinc-900">All Properties</h2>
            <p class="text-sm text-zinc-500 mt-1"><?= count($properties) ?> propert<?= count($properties) === 1 ? 'y' : 'ies' ?> registered</p>
        </div>
        <a href="<?= e(base_url('owner/add_property.php')) ?>"
           class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-emerald-800 transition-all">
            <span class="material-symbols-outlined" style="font-size:20px;">add</span>Add Property
        </a>
    </div>

    <?php if (!$properties): ?>
        <div class="bg-white rounded-2xl border border-zinc-200 p-20 text-center">
            <span class="material-symbols-outlined text-zinc-300" style="font-size:64px;">domain_disabled</span>
            <p class="text-zinc-500 mt-4 text-lg font-medium">No properties yet</p>
            <p class="text-zinc-400 text-sm mt-1">Add your first accommodation to start receiving bookings.</p>
            <a href="<?= e(base_url('owner/add_property.php')) ?>"
               class="inline-block mt-6 bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-emerald-800 transition">
                Add your first property
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-4">Property</th>
                            <th class="px-6 py-4">Location</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Rooms</th>
                            <th class="px-6 py-4">Bookings</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($properties as $p): ?>
                        <tr class="hover:bg-zinc-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($p['image_url']): ?>
                                        <img src="<?= e($p['image_url']) ?>" alt=""
                                             class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-lg bg-zinc-100 flex items-center justify-center flex-shrink-0">
                                            <span class="material-symbols-outlined text-zinc-400">image</span>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-semibold text-zinc-900"><?= e($p['name']) ?></p>
                                        <p class="text-zinc-400 text-xs mt-0.5">Added <?= date('M d, Y', strtotime($p['created_at'])) ?> · <?= (int)($p['image_count'] ?? 0) ?> image<?= (int)($p['image_count'] ?? 0) === 1 ? '' : 's' ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-zinc-600"><?= e($p['location']) ?></td>
                            <td class="px-6 py-4">
                                <?php $sc = match($p['status']) {
                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                    'pending'  => 'bg-amber-100 text-amber-800',
                                    'rejected' => 'bg-red-100 text-red-700',
                                    default    => 'bg-zinc-100 text-zinc-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $sc ?>">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-semibold text-zinc-900"><?= (int)$p['room_count'] ?></td>
                            <td class="px-6 py-4 font-semibold text-zinc-900"><?= (int)$p['booking_count'] ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?= e(base_url('owner/rooms.php?acc_id=' . (int)$p['id'])) ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:16px;">bed</span>Rooms
                                    </a>                                    <a href="<?= e(base_url('owner/property_images.php?acc_id=' . (int)$p['id'])) ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-sky-700 bg-sky-50 px-3 py-1.5 rounded-lg hover:bg-sky-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:16px;">photo_library</span>Images
                                    </a>                                    <a href="<?= e(base_url('owner/services.php?acc_id=' . (int)$p['id'])) ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-purple-700 bg-purple-50 px-3 py-1.5 rounded-lg hover:bg-purple-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:16px;">room_service</span>Services
                                    </a>
                                    <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$p['id'])) ?>"
                                       target="_blank"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg hover:bg-amber-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:16px;">visibility</span>Preview
                                    </a>
                                    <a href="<?= e(base_url('owner/edit_property.php?id=' . (int)$p['id'])) ?>"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition">
                                        <span class="material-symbols-outlined" style="font-size:16px;">edit</span>Edit
                                    </a>
                                    <form method="post" action="<?= e(base_url('owner/delete_property.php')) ?>"
                                          onsubmit="return confirm('Delete this property? All its rooms and booking records will also be removed.');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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




