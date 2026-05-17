<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('traveler');

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Traveler';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE traveler_id = ? AND booking_status = 'confirmed' AND check_in >= CURDATE()");
$stmt->execute([$userId]);
$upcoming = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE traveler_id = ?');
$stmt->execute([$userId]);
$totalBookings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE traveler_id = ? AND booking_status != 'cancelled'");
$stmt->execute([$userId]);
$totalSpent = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT b.*, r.room_type, a.name AS acc_name, a.location, a.image_url, a.id AS acc_id
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN accommodations a ON a.id = r.accommodation_id
     WHERE b.traveler_id = ?
     ORDER BY b.created_at DESC
     LIMIT 4'
);
$stmt->execute([$userId]);
$recent = $stmt->fetchAll();

$featured = $pdo->query(
    "SELECT a.*, MIN(r.price) AS from_price
     FROM accommodations a
     LEFT JOIN rooms r ON r.accommodation_id = a.id
     WHERE a.status = 'approved'
     GROUP BY a.id
     ORDER BY a.rating DESC, a.id DESC
     LIMIT 4"
)->fetchAll();

$services = [];
try {
    $services = $pdo->query(
        "SELECT s.id, s.name, s.description, s.price, a.id AS acc_id, a.name AS acc_name, a.location,
                COALESCE(
                    (SELECT si.image_path FROM service_images si WHERE si.service_id = s.id ORDER BY si.sort_order ASC, si.id ASC LIMIT 1),
                    a.image_url
                ) AS image_url
         FROM accommodation_services s
         JOIN accommodations a ON a.id = s.accommodation_id
         WHERE s.is_visible = 1 AND a.status = 'approved'
         ORDER BY s.created_at DESC
         LIMIT 6"
    )->fetchAll();
} catch (Throwable $e) {
    $services = [];
}

$homeUrl    = base_url('public/index.php');
$listUrl    = base_url('public/accommodation_listing.php');
$bookingUrl = base_url('traveler/my_bookings.php');
$logoutUrl  = base_url('auth/logout.php');
$logoUrl    = base_url('assets/images/logo-cropped-transparent.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard - SAFARI TANZANIA</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  :root { --navy:#0B1E5B; --blue:#0F7BD9; --cyan:#1EC6FF; --ink:#101d33; --muted:#6b7b99; --line:rgba(11,30,91,.10); }
  * { letter-spacing: 0; }
  body { font-family: Inter, sans-serif; background:#f7f9fb; color:var(--ink); }
  .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 430,'GRAD' 0,'opsz' 24; }
  .traveler-nav { background:rgba(6,14,42,.95); border-bottom:1px solid rgba(30,198,255,.12); backdrop-filter:blur(18px); }
  .traveler-logo { height:46px; width:auto; object-fit:contain; filter:drop-shadow(0 10px 22px rgba(0,0,0,.22)); }
  .dash-hero { background:linear-gradient(135deg,#060E2A 0%,#0B1E5B 58%,#0F7BD9 100%); color:#fff; }
  .dash-metric { border-left:1px solid rgba(255,255,255,.16); padding-left:24px; }
  .dash-metric:first-child { border-left:0; padding-left:0; }
  .section-line { border-top:1px solid var(--line); padding-top:32px; }
  .trip-row { border-bottom:1px solid var(--line); padding:22px 0; }
  .trip-row:first-child { border-top:1px solid var(--line); }
  .trip-img { width:124px; height:92px; object-fit:cover; border-radius:18px; }
  .status-pill { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; font-size:.75rem; font-weight:700; }
  .status-confirmed { background:#dff8ec; color:#047857; }
  .status-cancelled { background:#fee2e2; color:#b91c1c; }
  .status-completed { background:#dbeafe; color:#1d4ed8; }
  .status-default { background:#eef2f7; color:#64748b; }
  .primary-action { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:#fff; box-shadow:0 14px 34px rgba(15,123,217,.24); }
  .discover-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:16px; }
  .discover-card { display:block; background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:10px; overflow:hidden; box-shadow:0 18px 44px rgba(0,0,0,.08); transition:.18s ease; }
  .discover-card:hover { transform:translateY(-3px); box-shadow:0 22px 54px rgba(0,0,0,.12); }
  .discover-img { width:100%; height:190px; object-fit:cover; display:block; }
  .discover-body { padding:16px; }
  .discover-meta { color:#737373; font-size:.82rem; margin-top:4px; }
  .discover-price { color:#b4873d; font-weight:800; margin-top:14px; }
  .service-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:16px; }
  .service-card { display:grid; grid-template-columns: 132px 1fr; gap:14px; align-items:center; background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:10px; padding:12px; box-shadow:0 14px 34px rgba(0,0,0,.07); transition:.18s ease; }
  .service-card:hover { transform:translateY(-2px); box-shadow:0 20px 46px rgba(0,0,0,.12); }
  .service-img { width:132px; height:112px; object-fit:cover; border-radius:8px; }
  .service-tag { display:inline-flex; align-items:center; gap:6px; color:#b4873d; font-size:.78rem; font-weight:800; }
  .service-card p { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  @media (max-width: 1024px) { .discover-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .service-grid { grid-template-columns:1fr; } }
  @media (max-width: 640px) { .discover-grid { grid-template-columns:1fr; } .service-card { grid-template-columns:1fr; } .service-img { width:100%; height:180px; } }
  @media (max-width: 768px) { .dash-metric { border-left:0; padding-left:0; border-top:1px solid rgba(255,255,255,.14); padding-top:16px; } .dash-metric:first-child { border-top:0; padding-top:0; } .trip-img { width:100%; height:170px; } }
</style>
<link rel="stylesheet" href="<?= e(base_url('assets/css/traveler.css')) ?>?v=estate-traveler">
</head>
<body class="min-h-screen">
<header class="traveler-nav sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="<?= e($homeUrl) ?>" class="inline-flex items-center">
      <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" class="traveler-logo">
    </a>
    <nav class="hidden md:flex items-center gap-7 text-sm font-medium text-white/72">
      <a href="<?= e($listUrl) ?>" class="hover:text-white transition">Explore</a>
      <a href="<?= e($bookingUrl) ?>" class="hover:text-white transition">My Bookings</a>
      <a href="<?= e($logoutUrl) ?>" class="hover:text-white transition">Logout</a>
    </nav>
  </div>
</header>

<main>
  <section class="dash-hero">
    <div class="max-w-7xl mx-auto px-6 py-14 md:py-18">
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_.95fr] gap-10 items-end">
        <div>
          <p class="text-cyan-200 text-sm font-semibold uppercase tracking-[.14em]">Traveler dashboard</p>
          <h1 class="mt-4 text-4xl md:text-5xl font-bold leading-tight">Welcome back, <?= e($userName) ?></h1>
          <p class="mt-4 text-white/68 max-w-2xl leading-relaxed">Track your stays, continue payment, and discover verified accommodations across Tanzania.</p>
          <div class="mt-8 flex flex-wrap gap-3">
            <a href="<?= e($listUrl) ?>" class="primary-action inline-flex items-center gap-2 px-6 py-3 rounded-full font-semibold">
              <span class="material-symbols-outlined" style="font-size:18px;">travel_explore</span> Explore stays
            </a>
            <a href="<?= e($bookingUrl) ?>" class="inline-flex items-center gap-2 px-6 py-3 rounded-full font-semibold border border-white/18 text-white hover:bg-white/10 transition">
              <span class="material-symbols-outlined" style="font-size:18px;">luggage</span> View bookings
            </a>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 text-white">
          <div class="dash-metric">
            <p class="text-4xl font-bold"><?= $upcoming ?></p>
            <p class="mt-1 text-sm text-white/58">Upcoming trips</p>
          </div>
          <div class="dash-metric">
            <p class="text-4xl font-bold"><?= $totalBookings ?></p>
            <p class="mt-1 text-sm text-white/58">Total bookings</p>
          </div>
          <div class="dash-metric">
            <p class="text-4xl font-bold">$<?= number_format($totalSpent, 0) ?></p>
            <p class="mt-1 text-sm text-white/58">Total spent</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="max-w-7xl mx-auto px-6 py-10 md:py-12 space-y-12">
    <?php foreach (flash_pull() as $f): $isErr = $f['type'] === 'error'; ?>
      <div class="flex items-center gap-2 px-4 py-3 rounded-2xl text-sm border <?= $isErr ? 'bg-red-50 border-red-200 text-red-700' : 'bg-blue-50 border-blue-200 text-blue-800' ?>">
        <span class="material-symbols-outlined" style="font-size:18px;"><?= $isErr ? 'error' : 'check_circle' ?></span>
        <?= e($f['msg']) ?>
      </div>
    <?php endforeach; ?>

    <?php if ($services): ?>
    <section class="section-line">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
          <p class="text-sm font-semibold text-sky-700 uppercase tracking-[.12em]">Services & experiences</p>
          <h2 class="text-2xl md:text-3xl font-bold mt-1">Add more comfort to your trip</h2>
          <p class="text-slate-500 mt-2 max-w-2xl">Food, pickup, spa, guides, and other extras from verified accommodations.</p>
        </div>
        <a href="<?= e($listUrl) ?>" class="text-sm font-semibold text-sky-700 hover:underline">Browse all stays</a>
      </div>
      <div class="service-grid">
        <?php foreach ($services as $s): ?>
          <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$s['acc_id'] . '#services')) ?>" class="service-card">
            <img class="service-img" src="<?= e($s['image_url'] ?: base_url('assets/images/hero-bg.png')) ?>" alt="<?= e($s['name']) ?>">
            <div class="min-w-0">
              <span class="service-tag"><span class="material-symbols-outlined" style="font-size:17px;">room_service</span><?= e($s['location']) ?></span>
              <h3 class="text-lg font-bold text-slate-900 mt-2"><?= e($s['name']) ?></h3>
              <p class="text-sm text-slate-500 mt-1"><?= e($s['description'] ?: $s['acc_name']) ?></p>
              <div class="discover-price"><?= $s['price'] !== null ? '$' . e(number_format((float)$s['price'], 2)) : 'Ask host' ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($featured): ?>
    <section class="section-line">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
          <p class="text-sm font-semibold text-sky-700 uppercase tracking-[.12em]">Recommended stays</p>
          <h2 class="text-2xl md:text-3xl font-bold mt-1">Places travelers love</h2>
        </div>
        <a href="<?= e($listUrl) ?>" class="text-sm font-semibold text-sky-700 hover:underline">View all accommodations</a>
      </div>
      <div class="discover-grid">
        <?php foreach ($featured as $a): ?>
          <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$a['id'])) ?>" class="discover-card">
            <img class="discover-img" src="<?= e($a['image_url'] ?: base_url('assets/images/hero-bg.png')) ?>" alt="<?= e($a['name']) ?>">
            <div class="discover-body">
              <h3 class="text-lg font-bold text-slate-900"><?= e($a['name']) ?></h3>
              <p class="discover-meta"><?= e($a['location']) ?> · <?= e(number_format((float)$a['rating'], 1)) ?> rating</p>
              <div class="discover-price">
                <?= $a['from_price'] !== null ? 'From $' . e(number_format((float)$a['from_price'], 0)) . ' / night' : 'Price on request' ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="section-line">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div>
          <p class="text-sm font-semibold text-sky-700 uppercase tracking-[.12em]">Recent activity</p>
          <h2 class="text-2xl md:text-3xl font-bold mt-1">Your latest bookings</h2>
        </div>
        <a href="<?= e($bookingUrl) ?>" class="text-sm font-semibold text-sky-700 hover:underline">View all bookings</a>
      </div>

      <?php if (!$recent): ?>
        <div class="py-14 border-y border-slate-200">
          <p class="text-lg font-semibold text-slate-900">No bookings yet</p>
          <p class="text-slate-500 mt-1">Start by browsing verified stays across Tanzania.</p>
          <a href="<?= e($listUrl) ?>" class="primary-action inline-flex items-center gap-2 mt-5 px-6 py-3 rounded-full font-semibold">
            <span class="material-symbols-outlined" style="font-size:18px;">search</span> Browse accommodations
          </a>
        </div>
      <?php else: ?>
        <div>
          <?php foreach ($recent as $b):
            $bc = match($b['booking_status']) {
              'confirmed' => 'status-confirmed',
              'cancelled' => 'status-cancelled',
              'completed' => 'status-completed',
              default => 'status-default',
            };
          ?>
            <article class="trip-row flex flex-col md:flex-row md:items-center gap-5">
              <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$b['acc_id'])) ?>" class="shrink-0">
                <img src="<?= e($b['image_url'] ?: 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=700&q=80') ?>" alt="" class="trip-img">
              </a>
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                  <h3 class="text-lg font-bold text-slate-900"><?= e($b['acc_name']) ?></h3>
                  <span class="status-pill <?= $bc ?>"><?= e($b['booking_status']) ?></span>
                </div>
                <p class="text-sm text-slate-500 mt-1"><?= e($b['room_type']) ?> · <?= e($b['location']) ?></p>
                <p class="text-sm text-slate-600 mt-3 inline-flex items-center gap-2">
                  <span class="material-symbols-outlined text-sky-600" style="font-size:18px;">calendar_month</span>
                  <?= e($b['check_in']) ?> to <?= e($b['check_out']) ?> · <?= (int)$b['guests'] ?> guest<?= (int)$b['guests'] === 1 ? '' : 's' ?>
                </p>
              </div>
              <div class="md:text-right">
                <p class="text-xl font-bold text-slate-900">$<?= number_format((float)$b['total_price'], 0) ?></p>
                <p class="text-xs text-slate-500 mt-1"><?= e($b['payment_status']) ?> payment</p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="section-line grid grid-cols-1 md:grid-cols-[1fr_auto] gap-6 items-center">
      <div>
        <p class="text-sm font-semibold text-sky-700 uppercase tracking-[.12em]">Next journey</p>
        <h2 class="text-2xl font-bold mt-1">Find another place worth remembering</h2>
        <p class="text-slate-500 mt-2 max-w-2xl">Compare lodges, hotels, guest houses, services, and room options from verified hosts.</p>
      </div>
      <a href="<?= e($listUrl) ?>" class="primary-action inline-flex items-center justify-center gap-2 px-7 py-3 rounded-full font-semibold">
        Explore Tanzania
      </a>
    </section>
  </div>
</main>

<footer class="border-t border-slate-200 bg-white/70">
  <div class="max-w-7xl mx-auto px-6 py-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-3 text-sm text-slate-500">
    <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" style="height:38px;width:auto;">
    <p>&copy; <?= date('Y') ?> Safari Tanzania. Preserving the Wild.</p>
  </div>
</footer>
</body>
</html>
