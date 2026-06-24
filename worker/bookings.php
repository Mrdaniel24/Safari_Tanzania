<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('worker');

$workerId = (int)$_SESSION['user_id'];
$accIds   = worker_accommodation_ids($workerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accIds) {
    csrf_verify($_POST['_csrf'] ?? null);

    if (($_POST['action'] ?? '') === 'complete') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $placeholders = implode(',', array_fill(0, count($accIds), '?'));
        $params = array_merge([$bookingId], $accIds);
        $stmt = $pdo->prepare("
            SELECT b.id FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            WHERE b.id = ? AND r.accommodation_id IN ($placeholders) AND b.booking_status = 'confirmed'
        ");
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE bookings SET booking_status = 'completed' WHERE id = ?")
                ->execute([$bookingId]);
            flash_set('success', 'Booking marked as completed.');
        } else {
            flash_set('error', 'Could not update that booking.');
        }
        redirect('worker/bookings.php');
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$allowed = ['all', 'confirmed', 'completed', 'cancelled'];
if (!in_array($statusFilter, $allowed, true)) $statusFilter = 'all';

$bookings = [];
if ($accIds) {
    $placeholders = implode(',', array_fill(0, count($accIds), '?'));
    $sql = "
        SELECT b.*, u.full_name AS traveler_name, u.email AS traveler_email,
               r.room_type, a.name AS acc_name
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN accommodations a ON a.id = r.accommodation_id
        JOIN users u ON u.id = b.traveler_id
        WHERE r.accommodation_id IN ($placeholders)";
    $params = $accIds;
    if ($statusFilter !== 'all') {
        $sql .= " AND b.booking_status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
}

$activePage = 'bookings';
$pageTitle  = 'Bookings';
include __DIR__ . '/../includes/worker_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-zinc-900">Bookings</h2>
            <p class="text-sm text-zinc-500 mt-1"><?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?> found</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php foreach (['all' => 'All', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $val => $label): ?>
                <a href="?status=<?= $val ?>"
                   class="px-4 py-2 rounded-full text-sm font-semibold transition-all <?= $statusFilter === $val ? 'bg-primary text-white shadow' : 'bg-white text-zinc-600 border border-zinc-200 hover:border-primary' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$accIds): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 flex items-start gap-3">
            <span class="material-symbols-outlined mt-0.5" style="font-size:22px;">warning</span>
            <div>
                <p class="font-bold">No properties assigned</p>
                <p class="text-sm mt-1">Your owner has not assigned any properties to your account yet.</p>
            </div>
        </div>
    <?php elseif (!$bookings): ?>
        <div class="bg-white rounded-2xl border border-zinc-200 p-20 text-center">
            <span class="material-symbols-outlined text-zinc-300" style="font-size:64px;">event_busy</span>
            <p class="text-zinc-500 mt-4 text-lg font-medium">No bookings found</p>
            <p class="text-zinc-400 text-sm mt-1">No <?= $statusFilter !== 'all' ? $statusFilter . ' ' : '' ?>bookings for your assigned properties.</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-zinc-50 border-b border-zinc-100">
                            <th class="text-left px-5 py-3 font-bold text-zinc-700">Traveler</th>
                            <th class="text-left px-5 py-3 font-bold text-zinc-700">Property / Room</th>
                            <th class="text-left px-5 py-3 font-bold text-zinc-700">Check-in</th>
                            <th class="text-left px-5 py-3 font-bold text-zinc-700">Check-out</th>
                            <th class="text-center px-5 py-3 font-bold text-zinc-700">Guests</th>
                            <th class="text-right px-5 py-3 font-bold text-zinc-700">Total</th>
                            <th class="text-center px-5 py-3 font-bold text-zinc-700">Booking</th>
                            <th class="text-center px-5 py-3 font-bold text-zinc-700">Payment</th>
                            <th class="text-center px-5 py-3 font-bold text-zinc-700">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($bookings as $b): ?>
                            <tr class="hover:bg-zinc-50 transition-colors">
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-zinc-900"><?= e($b['traveler_name']) ?></p>
                                    <p class="text-xs text-zinc-400 mt-0.5"><?= e($b['traveler_email']) ?></p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-zinc-900"><?= e($b['acc_name']) ?></p>
                                    <p class="text-xs text-zinc-400 mt-0.5"><?= e($b['room_type']) ?></p>
                                </td>
                                <td class="px-5 py-4 text-zinc-700"><?= e($b['check_in']) ?></td>
                                <td class="px-5 py-4 text-zinc-700"><?= e($b['check_out']) ?></td>
                                <td class="px-5 py-4 text-center text-zinc-700"><?= (int)$b['guests'] ?></td>
                                <td class="px-5 py-4 text-right font-bold text-zinc-900">Tsh <?= number_format((float)$b['total_price'], 0) ?></td>
                                <td class="px-5 py-4 text-center">
                                    <?php
                                    $bs = $b['booking_status'];
                                    $bCls = match($bs) {
                                        'confirmed' => 'bg-emerald-100 text-emerald-800',
                                        'cancelled' => 'bg-red-100 text-red-700',
                                        'completed' => 'bg-blue-100 text-blue-800',
                                        default => 'bg-zinc-100 text-zinc-600',
                                    };
                                    ?>
                                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $bCls ?>"><?= e($bs) ?></span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <?php
                                    $ps = $b['payment_status'];
                                    $pCls = match($ps) {
                                        'paid'    => 'bg-emerald-100 text-emerald-800',
                                        'failed'  => 'bg-red-100 text-red-700',
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        default   => 'bg-zinc-100 text-zinc-600',
                                    };
                                    ?>
                                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $pCls ?>"><?= e($ps) ?></span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <?php if ($b['booking_status'] === 'confirmed'): ?>
                                        <form method="post" onsubmit="return confirm('Mark this booking as completed?');">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition">
                                                Mark complete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-zinc-400">—</span>
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
<?php include __DIR__ . '/../includes/worker_footer.php'; ?>
