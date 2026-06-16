<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('traveler');

$booking_id = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT b.*, r.room_type, r.price AS room_price,
            a.name AS acc_name, a.location, a.image_url, a.id AS acc_id
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
    $method  = $_POST['payment_method'] ?? '';
    $allowed = ['card', 'mobile_money', 'bank_transfer'];
    if (!in_array($method, $allowed, true)) {
        $errors[] = 'Choose a valid payment method.';
    } elseif ($b['payment_status'] === 'paid') {
        $errors[] = 'This booking is already paid.';
    } else {
        $ref = strtoupper(bin2hex(random_bytes(6)));
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO payments (booking_id, amount, payment_method, transaction_reference, payment_status) VALUES (?, ?, ?, ?, "success")')
            ->execute([$booking_id, $b['total_price'], $method, $ref]);
        $pdo->prepare('UPDATE bookings SET payment_status = "paid" WHERE id = ?')
            ->execute([$booking_id]);
        $pdo->commit();
        flash_set('success', 'Payment confirmed (ref ' . $ref . '). Your receipt is ready.');
        redirect('traveler/receipt.php?booking_id=' . $booking_id);
    }
}

/* Compute nights & per-guest breakdown */
$nights    = 0;
$perNight  = (float)$b['room_price'];
$guests    = (int)$b['guests'];
try {
    $dIn  = new DateTime($b['check_in']);
    $dOut = new DateTime($b['check_out']);
    $nights = (int)$dIn->diff($dOut)->days;
} catch (Throwable $e) {}

$coverImg = $b['image_url'] ?: 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=900&q=80';

$homeUrl      = base_url('public/index.php');
$bookingsUrl  = base_url('traveler/my_bookings.php');
$dashboardUrl = base_url('traveler/dashboard.php');
$logoutUrl    = base_url('auth/logout.php');
$logoUrl      = base_url('assets/images/logo-cropped-transparent.png');

$flash = flash_pull();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Complete Payment - Safari Tanzania</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  :root { --navy:#0B1E5B; --blue:#0F7BD9; --cyan:#1EC6FF; --ink:#101d33; }
  * { letter-spacing:0; }
  body { font-family:Inter,sans-serif; background:#f7f9fb; color:var(--ink); }
  .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 430,'GRAD' 0,'opsz' 24; }
  .tnav { background:rgba(6,14,42,.96); border-bottom:1px solid rgba(30,198,255,.12); backdrop-filter:blur(18px); }
  .tnav-logo { height:44px; width:auto; object-fit:contain; filter:drop-shadow(0 8px 18px rgba(0,0,0,.22)); }
  .tlink { color:rgba(255,255,255,.68); font-size:.87rem; font-weight:600; transition:.18s; }
  .tlink:hover { color:#fff; }
  .pay-hero { background:linear-gradient(135deg,#060E2A 0%,#0B1E5B 55%,#0F7BD9 100%); }
  .pay-card { background:#fff; border-radius:24px; border:1px solid rgba(15,36,82,.08); box-shadow:0 20px 56px rgba(7,20,47,.10); }
  .pay-divider { border-top:1px solid #EEF2F7; }
  .pay-method { display:flex; align-items:center; gap:14px; padding:16px 20px; border-radius:18px; border:2px solid #e2e8f0; cursor:pointer; transition:.18s ease; }
  .pay-method:has(input:checked) { border-color:var(--blue); background:#EEF6FF; }
  .pay-method:hover { border-color:#93c5fd; background:#f8faff; }
  .pay-method input { accent-color:var(--blue); width:18px; height:18px; cursor:pointer; flex-shrink:0; }
  .pay-method-icon { width:42px; height:42px; border-radius:12px; display:grid; place-items:center; background:#f1f5f9; flex-shrink:0; }
  .pay-btn { display:inline-flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:16px 24px; border-radius:999px; background:linear-gradient(135deg,#059669,#34D399); color:#fff; font-weight:800; font-size:1.05rem; border:none; cursor:pointer; font-family:inherit; box-shadow:0 14px 34px rgba(5,150,105,.24); transition:.2s ease; }
  .pay-btn:hover { transform:translateY(-1px); box-shadow:0 18px 42px rgba(5,150,105,.30); }
  .summary-row { display:flex; align-items:center; justify-content:space-between; gap:16px; }
  .summary-row + .summary-row { border-top:1px solid #f1f5f9; padding-top:10px; margin-top:10px; }
  .flash-ok { background:#F0FDF4; color:#047857; border:1px solid #A7F3D0; border-radius:16px; padding:12px 16px; font-size:.9rem; }
  .flash-err { background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; border-radius:16px; padding:12px 16px; font-size:.9rem; }
</style>
</head>
<body class="min-h-screen">

<!-- Top nav -->
<header class="tnav sticky top-0 z-40">
  <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="<?= e($homeUrl) ?>"><img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" class="tnav-logo"></a>
    <div class="hidden md:flex items-center gap-7">
      <a href="<?= e($dashboardUrl) ?>" class="tlink">Dashboard</a>
      <a href="<?= e($bookingsUrl) ?>" class="tlink">My Bookings</a>
      <a href="<?= e($logoutUrl) ?>" class="tlink">Logout</a>
    </div>
  </div>
</header>

<!-- Hero -->
<section class="pay-hero text-white">
  <div class="max-w-6xl mx-auto px-6 py-10">
    <a href="<?= e($bookingsUrl) ?>" class="inline-flex items-center gap-2 text-sm text-white/65 hover:text-white transition mb-6">
      <span class="material-symbols-outlined" style="font-size:17px;">arrow_back</span>Back to bookings
    </a>
    <p class="text-cyan-300 text-xs font-bold uppercase tracking-widest mb-2">Step 2 of 2</p>
    <h1 class="text-3xl md:text-4xl font-bold">Complete Payment</h1>
    <p class="text-white/65 mt-2 text-sm">Your room is reserved — confirm payment to complete your booking.</p>
  </div>
</section>

<!-- Main content -->
<main class="max-w-6xl mx-auto px-6 py-10">

  <!-- Flash messages -->
  <?php foreach ($flash as $f): ?>
    <div class="mb-6 flex items-center gap-2 <?= $f['type'] === 'error' ? 'flash-err' : 'flash-ok' ?>">
      <span class="material-symbols-outlined" style="font-size:18px;"><?= $f['type'] === 'error' ? 'error' : 'check_circle' ?></span>
      <?= e($f['msg']) ?>
    </div>
  <?php endforeach; ?>
  <?php foreach ($errors as $err): ?>
    <div class="mb-4 flex items-center gap-2 flash-err">
      <span class="material-symbols-outlined" style="font-size:18px;">error</span><?= e($err) ?>
    </div>
  <?php endforeach; ?>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_.8fr] gap-8">

    <!-- Payment form -->
    <div class="space-y-6">
      <div class="pay-card p-7">
        <h2 class="text-lg font-bold text-slate-900 mb-1">Select Payment Method</h2>
        <p class="text-sm text-slate-500 mb-6">Choose how you'd like to pay for your stay.</p>

        <form method="post" id="pay-form" class="space-y-3">
          <input type="hidden" name="_csrf"       value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="booking_id"  value="<?= (int)$booking_id ?>">

          <label class="pay-method">
            <input type="radio" name="payment_method" value="card" required checked>
            <div class="pay-method-icon" style="background:#EEF6FF;">
              <span class="material-symbols-outlined" style="color:#0F7BD9;font-size:22px;">credit_card</span>
            </div>
            <div>
              <p class="font-bold text-slate-900 text-sm">Credit / Debit Card</p>
              <p class="text-xs text-slate-500 mt-0.5">Visa, Mastercard, etc.</p>
            </div>
          </label>

          <label class="pay-method">
            <input type="radio" name="payment_method" value="mobile_money">
            <div class="pay-method-icon" style="background:#F0FDF4;">
              <span class="material-symbols-outlined" style="color:#059669;font-size:22px;">smartphone</span>
            </div>
            <div>
              <p class="font-bold text-slate-900 text-sm">Mobile Money</p>
              <p class="text-xs text-slate-500 mt-0.5">M-Pesa, Tigo Pesa, Airtel Money</p>
            </div>
          </label>

          <label class="pay-method">
            <input type="radio" name="payment_method" value="bank_transfer">
            <div class="pay-method-icon" style="background:#FFF7ED;">
              <span class="material-symbols-outlined" style="color:#D97706;font-size:22px;">account_balance</span>
            </div>
            <div>
              <p class="font-bold text-slate-900 text-sm">Bank Transfer</p>
              <p class="text-xs text-slate-500 mt-0.5">Direct wire to Safari Tanzania account</p>
            </div>
          </label>

          <div class="pay-divider pt-5 mt-2">
            <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-5">
              <span class="material-symbols-outlined text-amber-600 flex-shrink-0" style="font-size:20px;">info</span>
              <p class="text-sm text-amber-800 leading-relaxed">This is a <strong>demo payment</strong> — no real charge will occur. A receipt will be generated after confirmation.</p>
            </div>
            <button type="submit" class="pay-btn">
              <span class="material-symbols-outlined" style="font-size:20px;">lock</span>
              Pay Tsh <?= e(number_format((float)$b['total_price'], 2)) ?>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Booking summary -->
    <aside class="space-y-5">
      <div class="pay-card overflow-hidden">
        <img src="<?= e($coverImg) ?>" alt="<?= e($b['acc_name']) ?>" class="w-full h-44 object-cover">
        <div class="p-6">
          <p class="text-xs font-bold uppercase tracking-widest text-sky-600 mb-1"><?= e($b['location'] ?? '') ?></p>
          <h3 class="text-xl font-bold text-slate-900"><?= e($b['acc_name']) ?></h3>
          <p class="text-sm text-slate-500 mt-0.5"><?= e($b['room_type']) ?></p>

          <div class="mt-5 space-y-3">
            <div class="summary-row text-sm">
              <span class="text-slate-500 flex items-center gap-1.5"><span class="material-symbols-outlined text-sky-500" style="font-size:16px;">calendar_month</span>Check-in</span>
              <strong class="text-slate-900"><?= e($b['check_in']) ?></strong>
            </div>
            <div class="summary-row text-sm">
              <span class="text-slate-500 flex items-center gap-1.5"><span class="material-symbols-outlined text-sky-500" style="font-size:16px;">calendar_month</span>Check-out</span>
              <strong class="text-slate-900"><?= e($b['check_out']) ?></strong>
            </div>
            <div class="summary-row text-sm">
              <span class="text-slate-500 flex items-center gap-1.5"><span class="material-symbols-outlined text-sky-500" style="font-size:16px;">group</span>Guests</span>
              <strong class="text-slate-900"><?= $guests ?></strong>
            </div>
            <div class="summary-row text-sm">
              <span class="text-slate-500">Nights</span>
              <strong class="text-slate-900"><?= $nights ?></strong>
            </div>
          </div>

          <div class="pay-divider mt-4 pt-4 space-y-2">
            <div class="summary-row text-xs text-slate-400">
              <span>Rate × guests × nights</span>
              <span>Tsh <?= number_format($perNight, 2) ?> × <?= $guests ?> × <?= $nights ?></span>
            </div>
            <div class="summary-row">
              <span class="font-bold text-slate-900">Total due</span>
              <strong class="text-2xl font-black text-sky-700">Tsh <?= number_format((float)$b['total_price'], 2) ?></strong>
            </div>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2 text-xs text-slate-500">
        <span class="material-symbols-outlined" style="font-size:16px;color:#059669;">verified_user</span>
        Secured by Safari Tanzania · demo environment
      </div>
    </aside>

  </div>
</main>

<footer class="border-t border-slate-200 bg-white/70 mt-12">
  <div class="max-w-6xl mx-auto px-6 py-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-sm text-slate-500">
    <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" style="height:36px;width:auto;">
    <p>&copy; <?= date('Y') ?> Safari Tanzania. Preserving the Wild.</p>
  </div>
</footer>
</body>
</html>
