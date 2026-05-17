<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('traveler');

$booking_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT b.*, r.room_type, a.name AS acc_name
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN accommodations a ON a.id = r.accommodation_id
     WHERE b.id = ? AND b.traveler_id = ?'
);
$stmt->execute([$booking_id, (int)$_SESSION['user_id']]);
$b = $stmt->fetch();
if (!$b) { http_response_code(404); die('Booking not found.'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    $method = $_POST['payment_method'] ?? '';
    $allowed = ['card','mobile_money','bank_transfer'];
    if (!in_array($method, $allowed, true)) {
        $errors[] = 'Choose a valid payment method.';
    } elseif ($b['payment_status'] === 'paid') {
        $errors[] = 'This booking is already paid.';
    } else {
        $ref = strtoupper(bin2hex(random_bytes(6)));
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO payments (booking_id, amount, payment_method, transaction_reference, payment_status)
             VALUES (?, ?, ?, ?, "success")'
        )->execute([$booking_id, $b['total_price'], $method, $ref]);
        $pdo->prepare('UPDATE bookings SET payment_status = "paid" WHERE id = ?')
            ->execute([$booking_id]);
        $pdo->commit();
        flash_set('success', 'Payment recorded (ref ' . $ref . '). Thank you!');
        redirect('traveler/my_bookings.php');
    }
}

$pageTitle = 'Payment';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/traveler.css')) ?>?v=estate-traveler">
<section class="section">
  <h1>Complete payment</h1>
  <p class="muted"><?= e($b['acc_name']) ?> — <?= e($b['room_type']) ?></p>
  <p>Stay: <strong><?= e($b['check_in']) ?></strong> → <strong><?= e($b['check_out']) ?></strong> · <?= (int)$b['guests'] ?> guest(s)</p>
  <p class="price">Total due: <strong>$<?= e(number_format((float)$b['total_price'], 2)) ?></strong></p>

  <?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post" class="form auth-card">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="booking_id" value="<?= (int)$booking_id ?>">
    <label>Payment method
      <select name="payment_method" required>
        <option value="card">Card</option>
        <option value="mobile_money">Mobile Money</option>
        <option value="bank_transfer">Bank Transfer</option>
      </select>
    </label>
    <p class="muted small">This is a mock payment for demo purposes — no real charge will occur.</p>
    <button type="submit" class="btn btn-primary btn-block">Pay $<?= e(number_format((float)$b['total_price'], 2)) ?></button>
  </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
