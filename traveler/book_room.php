<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('traveler');

$room_id = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);
if ($room_id <= 0) { redirect('public/accommodation_listing.php'); }

$stmt = $pdo->prepare(
    'SELECT r.*, a.name AS acc_name, a.location, a.address, a.id AS acc_id, a.image_url, a.region, a.district
     FROM rooms r JOIN accommodations a ON a.id = r.accommodation_id
     WHERE r.id = ? AND a.status = "approved"'
);
$stmt->execute([$room_id]);
$room = $stmt->fetch();
if (!$room) { http_response_code(404); die('Room not found.'); }

$roomImages = [];
try {
    $imgs = $pdo->prepare('SELECT image_path FROM room_images WHERE room_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC LIMIT 4');
    $imgs->execute([$room_id]);
    $roomImages = $imgs->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}
$cover = $roomImages[0] ?? ($room['image_url'] ?: 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1200&q=80');

$errors = [];
$check_in = $_POST['check_in'] ?? '';
$check_out = $_POST['check_out'] ?? '';
$guests = (int)($_POST['guests'] ?? 1);
$estimatedNights = 0;
$estimatedTotal = 0.0;

if ($check_in && $check_out) {
    $previewIn = DateTime::createFromFormat('Y-m-d', $check_in);
    $previewOut = DateTime::createFromFormat('Y-m-d', $check_out);
    if ($previewIn && $previewOut && $previewOut > $previewIn) {
        $estimatedNights = (int)$previewIn->diff($previewOut)->days;
        $estimatedTotal = $estimatedNights * (float)$room['price'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $today = (new DateTime('today'))->format('Y-m-d');
    $d_in  = DateTime::createFromFormat('Y-m-d', $check_in);
    $d_out = DateTime::createFromFormat('Y-m-d', $check_out);

    if (!$d_in || !$d_out)            $errors[] = 'Please choose valid dates.';
    elseif ($check_in < $today)       $errors[] = 'Check-in cannot be in the past.';
    elseif ($check_out <= $check_in)  $errors[] = 'Check-out must be after check-in.';

    if ($guests < 1 || $guests > (int)$room['capacity']) {
        $errors[] = 'This room sleeps up to ' . (int)$room['capacity'] . ' guests.';
    }

    if (!$errors) {
        $ovl = $pdo->prepare(
            "SELECT COUNT(*) FROM bookings
              WHERE room_id = ?
                AND booking_status = 'confirmed'
                AND NOT (check_out <= ? OR check_in >= ?)"
        );
        $ovl->execute([$room_id, $check_in, $check_out]);
        $overlapping = (int)$ovl->fetchColumn();

        if ($overlapping >= (int)$room['total_rooms']) {
            $errors[] = 'Sorry, this room is not available for the selected dates.';
        } else {
            $nights = (int)$d_in->diff($d_out)->days;
            $total  = $nights * (float)$room['price'];

            $ins = $pdo->prepare(
                'INSERT INTO bookings
                 (traveler_id, room_id, check_in, check_out, guests, total_price, booking_status, payment_status)
                 VALUES (?, ?, ?, ?, ?, ?, "confirmed", "pending")'
            );
            $ins->execute([(int)$_SESSION['user_id'], $room_id, $check_in, $check_out, $guests, $total]);
            $booking_id = (int)$pdo->lastInsertId();
            flash_set('success', 'Booking confirmed! Please complete payment.');
            redirect('traveler/payment.php?booking_id=' . $booking_id);
        }
    }
}

$homeUrl = base_url('public/index.php');
$listUrl = base_url('public/accommodation_listing.php');
$bookingsUrl = base_url('traveler/my_bookings.php');
$dashboardUrl = base_url('traveler/dashboard.php');
$logoutUrl = base_url('auth/logout.php');
$logoUrl = base_url('assets/images/logo-cropped-transparent.png');
$backUrl = base_url('public/accommodation_details.php?id=' . (int)$room['acc_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book <?= e($room['room_type']) ?> - Safari Tanzania</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  :root { --navy:#0B1E5B; --blue:#0F7BD9; --cyan:#1EC6FF; --ink:#101d33; --muted:#6b7b99; --line:rgba(11,30,91,.10); }
  * { letter-spacing:0; }
  body { font-family:Inter,sans-serif; background:#f7f9fb; color:var(--ink); }
  .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 430,'GRAD' 0,'opsz' 24; }
  .traveler-nav { background:rgba(6,14,42,.95); border-bottom:1px solid rgba(30,198,255,.12); backdrop-filter:blur(18px); }
  .traveler-logo { height:46px; width:auto; object-fit:contain; filter:drop-shadow(0 10px 22px rgba(0,0,0,.22)); }
  .booking-hero { background:linear-gradient(135deg,#060E2A 0%,#0B1E5B 62%,#0F7BD9 100%); color:#fff; }
  .photo-strip { display:flex; gap:10px; height:360px; }
  .photo-panel { flex:1; min-width:0; overflow:hidden; border-radius:24px; transition:flex-grow .42s ease, transform .42s ease; background:#eaf1f7; }
  .photo-panel:first-child { flex-grow:2.2; }
  .photo-panel:hover { flex-grow:3; transform:translateY(-2px); }
  .photo-panel img { width:100%; height:100%; object-fit:cover; display:block; }
  .section-line { border-top:1px solid var(--line); padding-top:30px; }
  .field { width:100%; border:1px solid #d9e3ee; border-radius:16px; padding:13px 14px; background:#fff; outline:none; transition:.18s ease; }
  .field:focus { border-color:var(--blue); box-shadow:0 0 0 4px rgba(15,123,217,.10); }
  .primary-action { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#fff; box-shadow:0 14px 34px rgba(15,123,217,.24); }
  .summary-panel { border-left:1px solid var(--line); padding-left:28px; }
  @media (max-width:900px){ .summary-panel{border-left:0;border-top:1px solid var(--line);padding-left:0;padding-top:28px;} .photo-strip{height:280px;overflow-x:auto;scroll-snap-type:x mandatory;} .photo-panel,.photo-panel:first-child{flex:0 0 78%;scroll-snap-align:start;} }
</style>
<link rel="stylesheet" href="<?= e(base_url('assets/css/traveler.css')) ?>?v=estate-traveler">
</head>
<body class="min-h-screen">
<header class="traveler-nav sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="<?= e($homeUrl) ?>"><img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" class="traveler-logo"></a>
    <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-white/72">
      <a href="<?= e($listUrl) ?>" class="hover:text-white transition">Explore</a>
      <a href="<?= e($dashboardUrl) ?>" class="hover:text-white transition">Dashboard</a>
      <a href="<?= e($bookingsUrl) ?>" class="hover:text-white transition">My Bookings</a>
      <a href="<?= e($logoutUrl) ?>" class="hover:text-white transition">Logout</a>
    </nav>
  </div>
</header>

<main>
  <section class="booking-hero">
    <div class="max-w-7xl mx-auto px-6 py-12">
      <a href="<?= e($backUrl) ?>" class="inline-flex items-center gap-2 text-sm text-white/72 hover:text-white transition mb-8">
        <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span> Back to property
      </a>
      <div class="grid grid-cols-1 lg:grid-cols-[.75fr_1.25fr] gap-9 items-end">
        <div>
          <p class="text-cyan-200 text-sm font-semibold uppercase tracking-[.14em]"><?= e($room['location']) ?></p>
          <h1 class="mt-3 text-4xl md:text-5xl font-bold leading-tight">Reserve <?= e($room['room_type']) ?></h1>
          <p class="mt-4 text-white/70 leading-relaxed"><?= e($room['acc_name']) ?> · <?= e($room['address'] ?: trim(($room['district'] ?: '') . ', ' . ($room['region'] ?: ''), ', ') ?: 'Tanzania') ?></p>
          <div class="mt-7 flex flex-wrap gap-4 text-sm text-white/76">
            <span class="inline-flex items-center gap-2"><span class="material-symbols-outlined text-cyan-200" style="font-size:18px;">group</span> Up to <?= (int)$room['capacity'] ?> guests</span>
            <span class="inline-flex items-center gap-2"><span class="material-symbols-outlined text-cyan-200" style="font-size:18px;">bed</span> <?= (int)$room['total_rooms'] ?> available</span>
            <span class="inline-flex items-center gap-2"><span class="material-symbols-outlined text-cyan-200" style="font-size:18px;">payments</span> $<?= number_format((float)$room['price'], 0) ?>/night</span>
          </div>
        </div>
        <div class="photo-strip" aria-label="Room photos">
          <?php foreach (($roomImages ?: [$cover]) as $idx => $img): ?>
            <figure class="photo-panel"><img src="<?= e($img) ?>" alt="<?= e($room['room_type']) ?> photo <?= (int)$idx + 1 ?>"></figure>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="max-w-7xl mx-auto px-6 py-12 grid grid-cols-1 lg:grid-cols-[1fr_.72fr] gap-10">
    <div class="space-y-9">
      <div class="section-line">
        <h2 class="text-2xl font-bold">Booking details</h2>
        <p class="text-slate-500 mt-2 max-w-2xl">Choose your travel dates and number of guests. Availability is checked before your booking is created.</p>
      </div>

      <?php if ($errors): ?>
        <div class="border border-red-200 bg-red-50 text-red-700 rounded-2xl p-4 space-y-1">
          <?php foreach ($errors as $err): ?><p class="text-sm"><?= e($err) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="section-line space-y-6" id="bookingForm">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="room_id" value="<?= (int)$room_id ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <label class="block text-sm font-semibold text-slate-700">Check-in
            <input class="field mt-2" type="date" name="check_in" id="checkIn" value="<?= e($check_in) ?>" required>
          </label>
          <label class="block text-sm font-semibold text-slate-700">Check-out
            <input class="field mt-2" type="date" name="check_out" id="checkOut" value="<?= e($check_out) ?>" required>
          </label>
        </div>
        <label class="block text-sm font-semibold text-slate-700 max-w-xs">Guests
          <input class="field mt-2" type="number" name="guests" min="1" max="<?= (int)$room['capacity'] ?>" value="<?= (int)$guests ?>" required>
          <span class="block text-xs text-slate-500 mt-2">Maximum <?= (int)$room['capacity'] ?> guests for this room.</span>
        </label>
        <button type="submit" class="primary-action inline-flex items-center justify-center gap-2 px-7 py-3 rounded-full font-semibold">
          <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span> Confirm booking
        </button>
      </form>
    </div>

    <aside class="summary-panel">
      <div class="sticky top-28 space-y-7">
        <div>
          <p class="text-sm font-semibold text-sky-700 uppercase tracking-[.12em]">Stay summary</p>
          <h2 class="text-2xl font-bold mt-1"><?= e($room['acc_name']) ?></h2>
          <p class="text-slate-500 mt-1"><?= e($room['room_type']) ?></p>
        </div>
        <?php if (!empty($room['room_amenities'])): ?>
          <p class="text-sm text-slate-600 leading-relaxed"><?= e($room['room_amenities']) ?></p>
        <?php endif; ?>
        <div class="border-y border-slate-200 py-5 space-y-3 text-sm">
          <div class="flex justify-between gap-4"><span class="text-slate-500">Nightly rate</span><strong>$<?= number_format((float)$room['price'], 2) ?></strong></div>
          <div class="flex justify-between gap-4"><span class="text-slate-500">Nights</span><strong id="nightsText"><?= $estimatedNights ?: '-' ?></strong></div>
          <div class="flex justify-between gap-4"><span class="text-slate-500">Estimated total</span><strong class="text-xl" id="totalText"><?= $estimatedTotal > 0 ? '$' . number_format($estimatedTotal, 2) : '-' ?></strong></div>
        </div>
        <p class="text-xs text-slate-500 leading-relaxed">Payment is completed after confirmation. Your booking remains pending payment until checkout is recorded.</p>
      </div>
    </aside>
  </section>
</main>

<footer class="border-t border-slate-200 bg-white/70">
  <div class="max-w-7xl mx-auto px-6 py-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-sm text-slate-500">
    <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" style="height:38px;width:auto;">
    <p>&copy; <?= date('Y') ?> Safari Tanzania. Preserving the Wild.</p>
  </div>
</footer>
<script>
(function(){
  const price = <?= json_encode((float)$room['price']) ?>;
  const checkIn = document.getElementById('checkIn');
  const checkOut = document.getElementById('checkOut');
  const nightsText = document.getElementById('nightsText');
  const totalText = document.getElementById('totalText');
  const today = new Date().toISOString().slice(0,10);
  if (checkIn) checkIn.min = today;
  if (checkOut) checkOut.min = today;
  function updateEstimate(){
    if (!checkIn.value || !checkOut.value) { nightsText.textContent='-'; totalText.textContent='-'; return; }
    const start = new Date(checkIn.value + 'T00:00:00');
    const end = new Date(checkOut.value + 'T00:00:00');
    const nights = Math.round((end - start) / 86400000);
    if (nights <= 0) { nightsText.textContent='-'; totalText.textContent='-'; return; }
    nightsText.textContent = nights;
    totalText.textContent = '$' + (nights * price).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  checkIn && checkIn.addEventListener('change', () => { if (checkOut) checkOut.min = checkIn.value || today; updateEstimate(); });
  checkOut && checkOut.addEventListener('change', updateEstimate);
  updateEstimate();
})();
</script>
</body>
</html>
