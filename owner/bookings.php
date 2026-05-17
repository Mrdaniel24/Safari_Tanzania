<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];

// Mark booking as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    csrf_verify($_POST['_csrf'] ?? null);
    $bid = (int)($_POST['booking_id'] ?? 0);

    $chk = $pdo->prepare("
        SELECT b.id FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN accommodations a ON a.id = r.accommodation_id
        WHERE b.id = ? AND a.owner_id = ? AND b.booking_status = 'confirmed'
    ");
    $chk->execute([$bid, $ownerId]);

    if ($chk->fetch()) {
        $pdo->prepare("UPDATE bookings SET booking_status = 'completed' WHERE id = ?")
            ->execute([$bid]);
        flash_set('success', 'Booking marked as completed.');
    } else {
        flash_set('error', 'Could not update that booking.');
    }
    redirect('owner/bookings.php');
}

$filter = $_GET['status'] ?? 'all';
$allowed = ['all', 'confirmed', 'completed', 'cancelled'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$sql = "
    SELECT b.*, u.full_name AS traveler_name, u.email AS traveler_email,
           r.room_type, a.name AS acc_name, a.location
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN accommodations a ON a.id = r.accommodation_id
    JOIN users u ON u.id = b.traveler_id
    WHERE a.owner_id = ?
";
$params = [$ownerId];
if ($filter !== 'all') {
    $sql .= " AND b.booking_status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$activePage = 'bookings';
$pageTitle  = 'Bookings';
include __DIR__ . '/../includes/header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-zinc-900">All Bookings</h2>
            <p class="text-sm text-zinc-500 mt-1"><?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?> found</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php foreach ($allowed as $s): ?>
                <a href="?status=<?= $s ?>"
                   class="px-4 py-2 rounded-full text-xs font-bold border transition
                       <?= $filter === $s
                           ? 'bg-primary text-white border-primary'
                           : 'bg-white text-zinc-600 border-zinc-300 hover:border-emerald-600 hover:text-emerald-700' ?>">
                    <?= ucfirst($s) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$bookings): ?>
        <div class="bg-white rounded-2xl border border-zinc-200 p-20 text-center">
            <span class="material-symbols-outlined text-zinc-300" style="font-size:64px;">event_busy</span>
            <p class="text-zinc-500 mt-4 text-lg font-medium">No bookings found</p>
            <?php if ($filter !== 'all'): ?>
                <p class="text-zinc-400 text-sm mt-1">No <?= e($filter) ?> bookings yet.</p>
                <a href="?" class="inline-block mt-4 text-sm font-semibold text-emerald-700 hover:underline">View all bookings</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-zinc-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-4">Traveler</th>
                            <th class="px-6 py-4">Property / Room</th>
                            <th class="px-6 py-4">Check-in</th>
                            <th class="px-6 py-4">Check-out</th>
                            <th class="px-6 py-4">Guests</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Booking</th>
                            <th class="px-6 py-4">Payment</th>
                            <th class="px-6 py-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($bookings as $b): ?>
                        <tr class="hover:bg-zinc-50">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-zinc-900"><?= e($b['traveler_name']) ?></p>
                                <p class="text-xs text-zinc-400 mt-0.5"><?= e($b['traveler_email']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-zinc-900"><?= e($b['acc_name']) ?></p>
                                <p class="text-xs text-zinc-500"><?= e($b['room_type']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-zinc-600"><?= e($b['check_in']) ?></td>
                            <td class="px-6 py-4 text-zinc-600"><?= e($b['check_out']) ?></td>
                            <td class="px-6 py-4 text-zinc-600"><?= (int)$b['guests'] ?></td>
                            <td class="px-6 py-4 font-bold text-zinc-900">
                                $<?= number_format((float)$b['total_price'], 0) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php $bc = match($b['booking_status']) {
                                    'confirmed' => 'bg-emerald-100 text-emerald-800',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    default     => 'bg-zinc-100 text-zinc-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $bc ?>">
                                    <?= e($b['booking_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php $pc = match($b['payment_status']) {
                                    'paid'    => 'bg-emerald-100 text-emerald-800',
                                    'failed'  => 'bg-red-100 text-red-700',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    default   => 'bg-zinc-100 text-zinc-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $pc ?>">
                                    <?= e($b['payment_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($b['booking_status'] === 'confirmed'): ?>
                                    <form method="post"
                                          onsubmit="return confirm('Mark this booking as completed?');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit"
                                                class="text-xs font-semibold text-blue-700 bg-blue-50 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition">
                                            Mark Complete
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-zinc-300">—</span>
                                <?php endif; ?>
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
