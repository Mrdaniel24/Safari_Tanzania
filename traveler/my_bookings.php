<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('traveler');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_verify($_POST['_csrf'] ?? null);
    $bid = (int)($_POST['booking_id'] ?? 0);
    $upd = $pdo->prepare(
        'UPDATE bookings
         SET booking_status = "cancelled"
         WHERE id = ? AND traveler_id = ? AND booking_status = "confirmed"'
    );
    $upd->execute([$bid, (int)$_SESSION['user_id']]);
    flash_set($upd->rowCount() ? 'success' : 'error', $upd->rowCount() ? 'Booking cancelled.' : 'Could not cancel that booking.');
    redirect('traveler/my_bookings.php');
}

$stmt = $pdo->prepare(
    'SELECT b.*, r.room_type, r.price, a.name AS acc_name, a.location, a.id AS acc_id, a.image_url
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN accommodations a ON a.id = r.accommodation_id
     WHERE b.traveler_id = ?
     ORDER BY b.created_at DESC'
);
$stmt->execute([(int)$_SESSION['user_id']]);
$rows = $stmt->fetchAll();

$totalBookings = count($rows);
$upcoming = 0;
$pendingPayments = 0;
foreach ($rows as $row) {
    if ($row['booking_status'] === 'confirmed' && $row['check_in'] >= date('Y-m-d')) $upcoming++;
    if ($row['booking_status'] === 'confirmed' && $row['payment_status'] === 'pending') $pendingPayments++;
}

$homeUrl = base_url('public/index.php');
$listUrl = base_url('public/accommodation_listing.php');
$dashboardUrl = base_url('traveler/dashboard.php');
$logoutUrl = base_url('auth/logout.php');
$logoUrl = base_url('assets/images/logo-cropped-transparent.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings - Safari Tanzania</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  :root { --navy:#0B1E5B; --blue:#0F7BD9; --cyan:#1EC6FF; --ink:#101d33; --muted:#6b7b99; --line:rgba(11,30,91,.10); }
  * { letter-spacing:0; }
  body { font-family:Inter,sans-serif; background:#f7f9fb; color:var(--ink); }
  .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 430,'GRAD' 0,'opsz' 24; }
  .traveler-nav { background:rgba(6,14,42,.95); border-bottom:1px solid rgba(30,198,255,.12); backdrop-filter:blur(18px); }
  .traveler-logo { height:46px; width:auto; object-fit:contain; filter:drop-shadow(0 10px 22px rgba(0,0,0,.22)); }
  .page-hero { background:linear-gradient(135deg,#060E2A 0%,#0B1E5B 62%,#0F7BD9 100%); color:#fff; }
  .metric { border-left:1px solid rgba(255,255,255,.16); padding-left:24px; }
  .metric:first-child { border-left:0; padding-left:0; }
  .booking-row { border-bottom:1px solid var(--line); padding:24px 0; }
  .booking-row:first-child { border-top:1px solid var(--line); }
  .booking-img { width:132px; height:96px; object-fit:cover; border-radius:18px; }
  .status-pill { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; font-size:.74rem; font-weight:700; text-transform:capitalize; }
  .status-confirmed, .status-paid { background:#dff8ec; color:#047857; }
  .status-cancelled, .status-failed { background:#fee2e2; color:#b91c1c; }
  .status-completed { background:#dbeafe; color:#1d4ed8; }
  .status-pending { background:#fff4d6; color:#a16207; }
  .status-default { background:#eef2f7; color:#64748b; }
  .primary-action { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#fff; box-shadow:0 14px 34px rgba(15,123,217,.24); }
  .soft-action { background:#eef7ff; color:#0f6fb8; }
  @media (max-width:768px){ .metric{border-left:0;border-top:1px solid rgba(255,255,255,.14);padding-left:0;padding-top:16px;} .metric:first-child{border-top:0;padding-top:0;} .booking-img{width:100%;height:180px;} }
</style>
<link rel="stylesheet" href="<?= e(base_url('assets/css/traveler.css')) ?>?v=estate-traveler">
<link rel="stylesheet" href="<?= e(base_url('assets/css/pill-nav.css')) ?>">
</head>
<body class="min-h-screen">
<header class="traveler-nav sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="<?= e($homeUrl) ?>"><img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" class="traveler-logo"></a>
    <ul class="pill-nav hidden md:flex">
      <li class="pill-nav__cursor" aria-hidden="true"></li>
      <li class="pill-nav__item"><a href="<?= e($listUrl) ?>" class="pill-nav__link">Explore</a></li>
      <li class="pill-nav__item"><a href="<?= e($dashboardUrl) ?>" class="pill-nav__link">Dashboard</a></li>
      <li class="pill-nav__item"><a href="<?= e($logoutUrl) ?>" class="pill-nav__link">Logout</a></li>
    </ul>
  </div>
</header>

<main>
  <section class="page-hero">
    <div class="max-w-7xl mx-auto px-6 py-12">
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_.8fr] gap-10 items-end">
        <div>
          <p class="text-cyan-200 text-sm font-semibold uppercase tracking-[.14em]">Trip history</p>
          <h1 class="mt-3 text-4xl md:text-5xl font-bold leading-tight">My Bookings</h1>
          <p class="mt-4 text-white/70 max-w-2xl leading-relaxed">Review upcoming stays, complete pending payments, or revisit your accommodation details.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 text-white">
          <div class="metric"><p class="text-4xl font-bold"><?= $totalBookings ?></p><p class="mt-1 text-sm text-white/58">All bookings</p></div>
          <div class="metric"><p class="text-4xl font-bold"><?= $upcoming ?></p><p class="mt-1 text-sm text-white/58">Upcoming</p></div>
          <div class="metric"><p class="text-4xl font-bold"><?= $pendingPayments ?></p><p class="mt-1 text-sm text-white/58">Need payment</p></div>
        </div>
      </div>
    </div>
  </section>

  <section class="max-w-7xl mx-auto px-6 py-10 md:py-12">
    <?php foreach (flash_pull() as $f): $isErr = $f['type'] === 'error'; ?>
      <div class="mb-6 flex items-center gap-2 px-4 py-3 rounded-2xl text-sm border <?= $isErr ? 'bg-red-50 border-red-200 text-red-700' : 'bg-blue-50 border-blue-200 text-blue-800' ?>">
        <span class="material-symbols-outlined" style="font-size:18px;"><?= $isErr ? 'error' : 'check_circle' ?></span>
        <?= e($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-7">
      <div>
        <p class="text-sm font-semibold text-sky-700 uppercase tracking-[.12em]">Bookings</p>
        <h2 class="text-2xl md:text-3xl font-bold mt-1">Your reservations</h2>
      </div>
      <a href="<?= e($listUrl) ?>" class="primary-action inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full font-semibold">
        <span class="material-symbols-outlined" style="font-size:18px;">travel_explore</span> Explore more
      </a>
    </div>

    <?php if (!$rows): ?>
      <div class="py-16 border-y border-slate-200">
        <p class="text-xl font-bold text-slate-900">You have no bookings yet</p>
        <p class="text-slate-500 mt-2 max-w-2xl">When you reserve a room, your trip details and payment status will appear here.</p>
        <a href="<?= e($listUrl) ?>" class="primary-action inline-flex items-center gap-2 mt-6 px-6 py-3 rounded-full font-semibold">Browse accommodations</a>
      </div>
    <?php else: ?>
      <div>
        <?php foreach ($rows as $b):
          $bookingClass = match($b['booking_status']) {
            'confirmed' => 'status-confirmed',
            'cancelled' => 'status-cancelled',
            'completed' => 'status-completed',
            default => 'status-default',
          };
          $paymentClass = match($b['payment_status']) {
            'paid' => 'status-paid',
            'pending' => 'status-pending',
            'failed' => 'status-failed',
            default => 'status-default',
          };
        ?>
          <article class="booking-row flex flex-col lg:flex-row lg:items-center gap-5">
            <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$b['acc_id'])) ?>" class="shrink-0">
              <img src="<?= e($b['image_url'] ?: 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=700&q=80') ?>" alt="" class="booking-img">
            </a>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-lg font-bold text-slate-900"><?= e($b['acc_name']) ?></h3>
                <span class="status-pill <?= $bookingClass ?>"><?= e($b['booking_status']) ?></span>
                <span class="status-pill <?= $paymentClass ?>"><?= e($b['payment_status']) ?> payment</span>
              </div>
              <p class="text-sm text-slate-500 mt-1"><?= e($b['room_type']) ?> · <?= e($b['location']) ?></p>
              <div class="flex flex-wrap gap-4 mt-3 text-sm text-slate-600">
                <span class="inline-flex items-center gap-2"><span class="material-symbols-outlined text-sky-600" style="font-size:18px;">calendar_month</span><?= e($b['check_in']) ?> to <?= e($b['check_out']) ?></span>
                <span class="inline-flex items-center gap-2"><span class="material-symbols-outlined text-sky-600" style="font-size:18px;">group</span><?= (int)$b['guests'] ?> guest<?= (int)$b['guests'] === 1 ? '' : 's' ?></span>
              </div>
            </div>
            <div class="lg:text-right space-y-3">
              <p class="text-xl font-bold text-slate-900">Tsh <?= number_format((float)$b['total_price'], 2) ?></p>
              <div class="flex flex-wrap lg:justify-end gap-2">
                <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$b['acc_id'])) ?>" class="soft-action inline-flex items-center gap-1 px-4 py-2 rounded-full text-sm font-semibold">View</a>
                <?php if ($b['payment_status'] === 'paid'): ?>
                  <a href="<?= e(base_url('traveler/receipt.php?booking_id=' . (int)$b['id'])) ?>" class="inline-flex items-center gap-1 px-4 py-2 rounded-full text-sm font-semibold" style="background:#F0FDF4; color:#047857;">
                    <span class="material-symbols-outlined" style="font-size:16px;">receipt_long</span>Receipt
                  </a>
                <?php endif; ?>
                <?php if ($b['payment_status'] === 'pending' && $b['booking_status'] === 'confirmed'): ?>
                  <a href="<?= e(base_url('traveler/payment.php?booking_id=' . (int)$b['id'])) ?>" class="primary-action inline-flex items-center gap-1 px-4 py-2 rounded-full text-sm font-semibold">Pay</a>
                <?php endif; ?>
                <?php if ($b['booking_status'] === 'confirmed'): ?>
                  <form method="post" onsubmit="return confirm('Cancel this booking?');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-red-50 text-red-700 hover:bg-red-100 transition">Cancel</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<footer class="border-t border-slate-200 bg-white/70">
  <div class="max-w-7xl mx-auto px-6 py-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-sm text-slate-500">
    <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" style="height:38px;width:auto;">
    <p>&copy; <?= date('Y') ?> Safari Tanzania. Preserving the Wild.</p>
  </div>
</footer>
<script src="<?= e(base_url('assets/js/pill-nav.js')) ?>" defer></script>
</body>
</html>
