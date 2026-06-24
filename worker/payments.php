<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('worker');

$workerId = (int)$_SESSION['user_id'];
$accIds   = worker_accommodation_ids($workerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accIds) {
    csrf_verify($_POST['_csrf'] ?? null);

    if (($_POST['action'] ?? '') === 'verify') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $method    = $_POST['payment_method'] ?? '';
        $ref       = trim($_POST['transaction_reference'] ?? '');
        $allowed   = ['mobile_money', 'card', 'bank_transfer'];

        if (!in_array($method, $allowed, true)) {
            flash_set('error', 'Select a valid payment method.');
            redirect('worker/payments.php');
        }
        if ($ref === '') {
            flash_set('error', 'Transaction reference is required.');
            redirect('worker/payments.php');
        }

        $placeholders = implode(',', array_fill(0, count($accIds), '?'));
        $params = array_merge([$bookingId], $accIds);
        $stmt = $pdo->prepare("
            SELECT b.id, b.total_price FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            WHERE b.id = ? AND r.accommodation_id IN ($placeholders) AND b.payment_status = 'pending'
        ");
        $stmt->execute($params);
        $booking = $stmt->fetch();

        if (!$booking) {
            flash_set('error', 'Booking not found or already verified.');
            redirect('worker/payments.php');
        }

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_reference, payment_status) VALUES (?, ?, ?, ?, 'success')")
            ->execute([$booking['id'], $booking['total_price'], $method, $ref]);
        $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?")
            ->execute([$booking['id']]);
        $pdo->commit();

        flash_set('success', 'Payment verified and recorded successfully.');
        redirect('worker/payments.php');
    }
}

$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'all'], true)) $tab = 'pending';

$pendingBookings = [];
$allPayments     = [];

if ($accIds) {
    $placeholders = implode(',', array_fill(0, count($accIds), '?'));

    $stmt = $pdo->prepare("
        SELECT b.*, u.full_name AS traveler_name, u.email AS traveler_email,
               r.room_type, a.name AS acc_name
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN accommodations a ON a.id = r.accommodation_id
        JOIN users u ON u.id = b.traveler_id
        WHERE r.accommodation_id IN ($placeholders) AND b.payment_status = 'pending'
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($accIds);
    $pendingBookings = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT p.*, b.booking_status, b.payment_status,
               u.full_name AS traveler_name, r.room_type, a.name AS acc_name
        FROM payments p
        JOIN bookings b ON b.id = p.booking_id
        JOIN rooms r ON r.id = b.room_id
        JOIN accommodations a ON a.id = r.accommodation_id
        JOIN users u ON u.id = b.traveler_id
        WHERE r.accommodation_id IN ($placeholders)
        ORDER BY p.paid_at DESC
    ");
    $stmt->execute($accIds);
    $allPayments = $stmt->fetchAll();
}

$activePage = 'payments';
$pageTitle  = 'Payments';
include __DIR__ . '/../includes/worker_header.php';
?>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-zinc-900">Payments</h2>
            <p class="text-sm text-zinc-500 mt-1">Verify traveler payments for your assigned properties</p>
        </div>
        <div class="flex gap-2">
            <a href="?tab=pending"
               class="px-4 py-2 rounded-full text-sm font-semibold transition-all <?= $tab === 'pending' ? 'bg-primary text-white shadow' : 'bg-white text-zinc-600 border border-zinc-200 hover:border-primary' ?>">
                Pending <?php if ($pendingBookings): ?><span class="ml-1 bg-amber-400 text-white text-xs rounded-full px-1.5"><?= count($pendingBookings) ?></span><?php endif; ?>
            </a>
            <a href="?tab=all"
               class="px-4 py-2 rounded-full text-sm font-semibold transition-all <?= $tab === 'all' ? 'bg-primary text-white shadow' : 'bg-white text-zinc-600 border border-zinc-200 hover:border-primary' ?>">
                All payments
            </a>
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
    <?php elseif ($tab === 'pending'): ?>

        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-2xl p-4 flex items-start gap-3 text-sm">
            <span class="material-symbols-outlined mt-0.5" style="font-size:20px;">info</span>
            <div>
                <span class="font-bold">Manual verification: </span>Confirm the payment with the traveler (M-Pesa SMS, Tigo Pesa code, bank slip), then enter the transaction reference below to record it.
            </div>
        </div>

        <?php if (!$pendingBookings): ?>
            <div class="bg-white rounded-2xl border border-zinc-200 p-20 text-center">
                <span class="material-symbols-outlined text-emerald-300" style="font-size:64px;">check_circle</span>
                <p class="text-zinc-500 mt-4 text-lg font-medium">All payments verified</p>
                <p class="text-zinc-400 text-sm mt-1">No bookings are waiting for payment verification.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($pendingBookings as $b): ?>
                    <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm p-5">
                        <div class="flex items-start justify-between flex-wrap gap-4 mb-4">
                            <div>
                                <p class="font-bold text-zinc-900"><?= e($b['traveler_name']) ?></p>
                                <p class="text-xs text-zinc-500 mt-0.5"><?= e($b['traveler_email']) ?></p>
                                <p class="text-sm text-zinc-600 mt-1"><?= e($b['acc_name']) ?> · <?= e($b['room_type']) ?></p>
                                <p class="text-xs text-zinc-400 mt-0.5"><?= e($b['check_in']) ?> → <?= e($b['check_out']) ?> · <?= (int)$b['guests'] ?> guest<?= (int)$b['guests'] !== 1 ? 's' : '' ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-black text-zinc-900">Tsh <?= number_format((float)$b['total_price'], 0) ?></p>
                                <span class="inline-block mt-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">payment pending</span>
                            </div>
                        </div>
                        <form method="post" class="border-t border-zinc-100 pt-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="verify">
                            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                            <label class="block">
                                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Payment method</span>
                                <select name="payment_method" required class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
                                    <option value="">Select method…</option>
                                    <option value="mobile_money">Mobile Money (M-Pesa / Tigo)</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="card">Card</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-xs font-bold uppercase tracking-wider text-zinc-700 mb-1">Transaction reference</span>
                                <input type="text" name="transaction_reference" required placeholder="e.g. QHG7X1ABC2" class="w-full rounded-lg border-zinc-300 text-sm focus:border-primary focus:ring-primary">
                            </label>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 bg-emerald-600 text-white px-5 py-2.5 rounded-lg font-semibold hover:bg-emerald-700 transition-all">
                                <span class="material-symbols-outlined" style="font-size:20px;">verified</span>Verify payment
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <?php if (!$allPayments): ?>
            <div class="bg-white rounded-2xl border border-zinc-200 p-20 text-center">
                <span class="material-symbols-outlined text-zinc-300" style="font-size:64px;">receipt_long</span>
                <p class="text-zinc-500 mt-4 text-lg font-medium">No payment records</p>
                <p class="text-zinc-400 text-sm mt-1">Verified payments will appear here once processed.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-zinc-50 border-b border-zinc-100">
                                <th class="text-left px-5 py-3 font-bold text-zinc-700">Traveler</th>
                                <th class="text-left px-5 py-3 font-bold text-zinc-700">Property / Room</th>
                                <th class="text-left px-5 py-3 font-bold text-zinc-700">Method</th>
                                <th class="text-left px-5 py-3 font-bold text-zinc-700">Reference</th>
                                <th class="text-right px-5 py-3 font-bold text-zinc-700">Amount</th>
                                <th class="text-center px-5 py-3 font-bold text-zinc-700">Status</th>
                                <th class="text-left px-5 py-3 font-bold text-zinc-700">Verified at</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php foreach ($allPayments as $p): ?>
                                <tr class="hover:bg-zinc-50 transition-colors">
                                    <td class="px-5 py-4 font-semibold text-zinc-900"><?= e($p['traveler_name']) ?></td>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-zinc-900"><?= e($p['acc_name']) ?></p>
                                        <p class="text-xs text-zinc-400 mt-0.5"><?= e($p['room_type']) ?></p>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-700"><?= e(str_replace('_', ' ', $p['payment_method'])) ?></td>
                                    <td class="px-5 py-4 font-mono text-xs text-zinc-600"><?= e($p['transaction_reference'] ?? '—') ?></td>
                                    <td class="px-5 py-4 text-right font-bold text-zinc-900">Tsh <?= number_format((float)$p['amount'], 0) ?></td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold <?= $p['payment_status'] === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' ?>">
                                            <?= e($p['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-zinc-500 text-xs"><?= e($p['paid_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/worker_footer.php'; ?>
