<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$filter  = $_GET['status'] ?? 'all';
$allowed = ['all', 'confirmed', 'completed', 'cancelled'];
if (!in_array($filter, $allowed, true)) $filter = 'all';

$sql = "
    SELECT b.*,
           u.full_name AS traveler_name, u.email AS traveler_email,
           r.room_type, r.price AS room_price,
           a.name AS acc_name, a.location,
           o.full_name AS owner_name
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN accommodations a ON a.id = r.accommodation_id
    JOIN users u ON u.id = b.traveler_id
    JOIN users o ON o.id = a.owner_id
";
$params = [];
if ($filter !== 'all') {
    $sql .= " WHERE b.booking_status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Summary stats
$stats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(booking_status = 'confirmed')  AS confirmed,
        SUM(booking_status = 'completed')  AS completed,
        SUM(booking_status = 'cancelled')  AS cancelled,
        SUM(CASE WHEN booking_status != 'cancelled' THEN total_price ELSE 0 END) AS revenue
    FROM bookings
")->fetch();

$activePage = 'bookings';
$pageTitle  = 'Bookings';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <!-- Summary cards -->
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-5">
        <?php
        $cards = [
            ['label' => 'Total Bookings', 'value' => $stats['total'],     'icon' => 'event_note',   'color' => 'text-sky-600',     'bg' => 'bg-sky-100'],
            ['label' => 'Confirmed',      'value' => $stats['confirmed'], 'icon' => 'check_circle', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-100'],
            ['label' => 'Completed',      'value' => $stats['completed'], 'icon' => 'task_alt',     'color' => 'text-blue-600',    'bg' => 'bg-blue-100'],
            ['label' => 'Platform Revenue', 'value' => 'Tsh ' . number_format((float)$stats['revenue'], 0), 'icon' => 'payments', 'color' => 'text-violet-600', 'bg' => 'bg-violet-100'],
        ];
        foreach ($cards as $c):
        ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <span class="material-symbols-outlined <?= $c['color'] ?> text-2xl"><?= $c['icon'] ?></span>
            <p class="text-2xl font-black text-slate-900 mt-2"><?= $c['value'] ?></p>
            <p class="text-xs text-slate-400 mt-1 font-semibold uppercase tracking-wide"><?= $c['label'] ?></p>
        </div>
        <?php endforeach; ?>
    </section>

    <div class="flex items-center justify-between flex-wrap gap-4">
        <h2 class="text-xl font-bold text-slate-900">
            <?= count($bookings) ?> booking<?= count($bookings) !== 1 ? 's' : '' ?>
        </h2>
        <div class="flex gap-2 flex-wrap">
            <?php foreach ($allowed as $s): ?>
                <a href="?status=<?= $s ?>"
                   class="px-4 py-2 rounded-full text-xs font-bold border transition
                       <?= $filter === $s
                           ? 'bg-sky-700 text-white border-sky-700'
                           : 'bg-white text-slate-600 border-slate-300 hover:border-sky-500 hover:text-sky-700' ?>">
                    <?= ucfirst($s) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$bookings): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-20 text-center">
            <span class="material-symbols-outlined text-slate-300" style="font-size:64px;">event_busy</span>
            <p class="text-slate-500 mt-4 text-lg font-medium">No bookings found</p>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
                        <tr>
                            <th class="px-6 py-4">Traveler</th>
                            <th class="px-6 py-4">Property / Room</th>
                            <th class="px-6 py-4">Owner</th>
                            <th class="px-6 py-4">Check-in</th>
                            <th class="px-6 py-4">Check-out</th>
                            <th class="px-6 py-4">Guests</th>
                            <th class="px-6 py-4">Total</th>
                            <th class="px-6 py-4">Booking</th>
                            <th class="px-6 py-4">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($bookings as $b): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900"><?= e($b['traveler_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e($b['traveler_email']) ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900"><?= e($b['acc_name']) ?></p>
                                <p class="text-xs text-slate-500"><?= e($b['room_type']) ?> &middot; <?= e($b['location']) ?></p>
                            </td>
                            <td class="px-6 py-4 text-slate-600 text-xs"><?= e($b['owner_name']) ?></td>
                            <td class="px-6 py-4 text-slate-600"><?= e($b['check_in']) ?></td>
                            <td class="px-6 py-4 text-slate-600"><?= e($b['check_out']) ?></td>
                            <td class="px-6 py-4 text-slate-700"><?= (int)$b['guests'] ?></td>
                            <td class="px-6 py-4 font-bold text-slate-900">
                                Tsh <?= number_format((float)$b['total_price'], 0) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php $bc = match($b['booking_status']) {
                                    'confirmed' => 'bg-emerald-100 text-emerald-800',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    default     => 'bg-slate-100 text-slate-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $bc ?>">
                                    <?= e($b['booking_status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php $pc = match($b['payment_status']) {
                                    'paid'    => 'bg-emerald-100 text-emerald-800',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    'failed'  => 'bg-red-100 text-red-700',
                                    default   => 'bg-slate-100 text-slate-600',
                                }; ?>
                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $pc ?>">
                                    <?= e($b['payment_status']) ?>
                                </span>
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
