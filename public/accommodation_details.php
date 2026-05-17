<?php
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('public/accommodation_listing.php'); }

$stmt = $pdo->prepare("SELECT a.*, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
                       FROM accommodations a
                       JOIN users u ON u.id = a.owner_id
                       WHERE a.id = ?");
$stmt->execute([$id]);
$acc = $stmt->fetch();

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = $_SESSION['role'] ?? '';
$canPreview = $acc && (
    $acc['status'] === 'approved'
    || $currentRole === 'admin'
    || ($currentRole === 'owner' && $currentUserId === (int)$acc['owner_id'])
);
if (!$canPreview) { http_response_code(404); die('Accommodation not found.'); }
$isPreviewOnly = $acc['status'] !== 'approved';

$rooms = $pdo->prepare('SELECT * FROM rooms WHERE accommodation_id = ? ORDER BY price ASC');
$rooms->execute([$id]);
$rooms = $rooms->fetchAll();
$roomImagesByRoom = [];
try {
    $roomIds = array_map(fn($r) => (int)$r['id'], $rooms);
    if ($roomIds) {
        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
        $imgStmt = $pdo->prepare("SELECT * FROM room_images WHERE room_id IN ($placeholders) ORDER BY is_cover DESC, sort_order ASC, id ASC");
        $imgStmt->execute($roomIds);
        foreach ($imgStmt->fetchAll() as $img) {
            $roomImagesByRoom[(int)$img['room_id']][] = $img;
        }
    }
} catch (Throwable $e) {
    $roomImagesByRoom = [];
}

$services = [];
$serviceImagesByService = [];
try {
    $svcStmt = $pdo->prepare('SELECT * FROM accommodation_services WHERE accommodation_id = ? AND is_visible = 1 ORDER BY created_at DESC');
    $svcStmt->execute([$id]);
    $services = $svcStmt->fetchAll();
    $serviceIds = array_map(fn($s) => (int)$s['id'], $services);
    if ($serviceIds) {
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $svcImgStmt = $pdo->prepare("SELECT * FROM service_images WHERE service_id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
        $svcImgStmt->execute($serviceIds);
        foreach ($svcImgStmt->fetchAll() as $img) {
            $serviceImagesByService[(int)$img['service_id']][] = $img;
        }
    }
} catch (Throwable $e) {
    $services = [];
    $serviceImagesByService = [];
}

$amenities = $pdo->prepare(
    'SELECT am.name FROM amenities am
     JOIN accommodation_amenities aa ON aa.amenity_id = am.id
     WHERE aa.accommodation_id = ?'
);
$amenities->execute([$id]);
$amenities = $amenities->fetchAll(PDO::FETCH_COLUMN);
$galleryImages = [];
try {
    $imageStmt = $pdo->prepare('SELECT image_path FROM accommodation_images WHERE accommodation_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC LIMIT 6');
    $imageStmt->execute([$id]);
    $galleryImages = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $galleryImages = [];
}

// Map amenity names to material icons
$amenityIcons = [
    'wifi' => 'wifi', 'pool' => 'pool', 'restaurant' => 'restaurant',
    'bar' => 'local_bar', 'parking' => 'local_parking', 'ac' => 'ac_unit',
    'air conditioning' => 'ac_unit', 'game drives' => 'directions_car',
    'safari' => 'directions_car', 'breakfast' => 'restaurant',
    'spa' => 'spa', 'gym' => 'fitness_center', 'tv' => 'tv',
    'pet' => 'pets', 'family' => 'family_restroom',
];
$iconFor = function ($name) use ($amenityIcons) {
    $lower = strtolower($name);
    foreach ($amenityIcons as $key => $icon) {
        if (strpos($lower, $key) !== false) return $icon;
    }
    return 'check_circle';
};

$user = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role']    ?? null;
$listingUrl = e(base_url('public/accommodation_listing.php'));
$homeUrl    = e(base_url('public/index.php'));
$loginUrl   = e(base_url('auth/login.php'));
$logoutUrl  = e(base_url('auth/logout.php'));
$dashUrl    = $role === 'owner'
    ? e(base_url('owner/dashboard.php'))
    : ($role === 'admin'
        ? e(base_url('admin/dashboard.php'))
        : e(base_url('traveler/dashboard.php')));

$logoUrl = e(base_url('assets/images/logo-cropped-transparent.png'));
$heroImg = $acc['image_url'] ?: 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1600&q=80';
if (!$galleryImages) $galleryImages = [$heroImg];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?= e($acc['name']) ?> â€” SAFARI TANZANIA</title>
<meta name="description" content="<?= e(mb_substr((string)$acc['description'], 0, 150)) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/premium.css')) ?>">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<?php if (!GOOGLE_MAPS_API_KEY): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<?php endif; ?>
<style>
  body { font-family: 'Plus Jakarta Sans', sans-serif; }
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

/* DETAILS PREMIUM OVERRIDES */
.details-page { background: var(--white) !important; color: var(--navy) !important; font-family: 'Inter', sans-serif !important; }
.details-page header.bg-white { background: rgba(6,14,42,.94) !important; border-color: rgba(30,198,255,.12) !important; backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); box-shadow: 0 4px 30px rgba(0,0,0,.25) !important; }
.details-page header a, .details-page header nav a { color: rgba(255,255,255,.78) !important; }
.details-page header a:hover, .details-page header nav a:hover { color: #fff !important; }
.details-page .text-amber-600, .details-page .text-amber-500 { color: var(--cyan) !important; }
.details-page .bg-amber-500, .details-page a.bg-amber-500, .details-page button.bg-amber-500 { background: var(--gradient-btn) !important; color:#fff !important; box-shadow: var(--shadow-blue) !important; }
.details-hero-section { max-width: none !important; padding: 92px 24px 34px !important; background: linear-gradient(180deg, var(--dark), #0B1E5B) !important; }
.details-hero-shell { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: minmax(320px,.82fr) minmax(0,1.18fr); gap: 26px; align-items: stretch; }
.details-hero-copy { padding: clamp(8px,2vw,18px) 0; color: #fff; }
.details-hero-media { min-width: 0; }
.details-hero-media .details-gallery-grid { margin-top: 0; height: 100%; min-height: 430px; }
.details-hero-media .gallery-panel { border-radius: 24px; }
.details-hero-kicker { color: var(--cyan); font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .14em !important; }
.details-hero-copy h1 { margin-top: 10px; color: #fff !important; }
.details-hero-rating { display:inline-flex; align-items:center; gap:8px; margin-top: 14px; background: rgba(255,255,255,.10); color: #fff; padding: 8px 12px; border-radius: 999px; font-size: .9rem; font-weight: 700; border:1px solid rgba(255,255,255,.16); backdrop-filter: blur(12px); }
.details-hero-location { display:inline-flex; align-items:center; gap:6px; margin-top: 12px; color:rgba(255,255,255,.78); }
.details-hero-copy .location-fact-grid { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 10px; }
.details-hero-copy .location-fact { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.14); box-shadow: none; backdrop-filter: blur(12px); }
.details-hero-copy .location-fact span { color: rgba(255,255,255,.58); }
.details-hero-copy .location-fact strong { color: #fff; }
@media (max-width: 520px) { .details-hero-copy .location-fact-grid { grid-template-columns: 1fr; } }

@media (max-width: 900px) { .details-hero-shell { grid-template-columns: 1fr; } .details-hero-media .details-gallery-grid { min-height: 310px; } }
.details-page h1, .details-page h2, .details-page h3 { color: var(--navy) !important; font-family: 'Poppins', sans-serif !important; }
.details-page .bg-white { border-color: rgba(11,30,91,.08) !important; box-shadow: 0 12px 36px rgba(11,30,91,.08); }
.details-page .rounded-2xl { border-radius: 24px !important; }
.details-page .text-stone-500, .details-page .text-stone-600, .details-page .text-stone-700 { color: #6b7b99 !important; }
.details-page .bg-stone-100 { background: rgba(11,30,91,.06) !important; }
.details-page .border-stone-100, .details-page .border-stone-200, .details-page .border-stone-300 { border-color: rgba(11,30,91,.10) !important; }
.details-page a[href*='book_room'], .details-page button[type='submit'] { background: var(--gradient-btn) !important; color: #fff !important; border: 0 !important; box-shadow: var(--shadow-blue) !important; }
.details-page footer { background: var(--dark) !important; border-top: 1px solid rgba(30,198,255,.08) !important; }

/* LOGO PREMIUM SIZING */
.brand-logo-img {
  display: block;
  height: 44px;
  width: auto;
  max-width: 178px;
  object-fit: contain;
  filter: drop-shadow(0 8px 18px rgba(0,0,0,.2));
}
.footer-logo-img { height: 42px; }
@media (max-width: 640px) {
  .brand-logo-img { height: 40px; }
}

/* Interactive image gallery inspired by the supplied reference */
.details-gallery-grid,
.detail-media-strip {
  display: flex;
  align-items: stretch;
  gap: 10px;
  width: 100%;
  margin-top: 18px;
}
.details-gallery-grid { height: clamp(230px, 33vw, 400px); }
.detail-media-strip { height: 190px; }
.gallery-panel,
.media-panel {
  position: relative;
  flex: 1 1 0;
  min-width: 0;
  overflow: hidden;
  border-radius: 18px;
  background: #eaf1f7;
  box-shadow: 0 14px 36px rgba(11,30,91,.10);
  transition: flex-grow .48s ease, transform .48s ease, box-shadow .48s ease;
}
.gallery-panel:first-child { flex-grow: 2.2; }
.gallery-panel:hover,
.media-panel:hover {
  flex-grow: 3.4;
  transform: translateY(-2px);
  box-shadow: 0 20px 48px rgba(11,30,91,.16);
}
.gallery-panel img,
.media-panel img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
  filter: saturate(1.03) contrast(1.02);
}
.gallery-panel::after,
.media-panel::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, transparent 52%, rgba(6,14,42,.28));
  opacity: .85;
  pointer-events: none;
}
.gallery-count-pill {
  position: absolute;
  left: 14px;
  bottom: 14px;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 999px;
  padding: 8px 12px;
  font-size: .78rem;
  font-weight: 700;
  color: #fff;
  background: rgba(6,14,42,.62);
  backdrop-filter: blur(14px);
  border: 1px solid rgba(255,255,255,.18);
}
@media (max-width: 768px) {
  .details-gallery-grid,
  .detail-media-strip {
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    padding-bottom: 8px;
  }
  .details-gallery-grid { height: 280px; }
  .detail-media-strip { height: 170px; }
  .gallery-panel,
  .media-panel,
  .gallery-panel:first-child {
    flex: 0 0 78%;
    scroll-snap-align: start;
  }
  .gallery-panel:hover,
  .media-panel:hover {
    flex-grow: 0;
    transform: none;
  }
}
.service-card-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:18px; }
.service-detail-card { background:#fff; border:1px solid rgba(11,30,91,.08); border-radius:24px; padding:18px; box-shadow:0 12px 36px rgba(11,30,91,.08); }
.service-price-pill { display:inline-flex; align-items:center; border-radius:999px; padding:6px 10px; font-size:.78rem; font-weight:800; color:#0B1E5B; background:rgba(30,198,255,.12); }
.location-fact-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:12px; margin-top:16px; }
.location-fact { background:#fff; border:1px solid rgba(11,30,91,.08); border-radius:18px; padding:14px; }
.location-fact span { display:block; color:#6b7b99; font-size:.76rem; font-weight:800; text-transform:uppercase; margin-bottom:3px; }
.location-fact strong { color:#0B1E5B; }
@media (max-width: 768px) { .detail-media-strip, .service-card-grid, .location-fact-grid { grid-template-columns:1fr; } }

/* Cleaner open layout: fewer cards, more structure */
.details-page { background: #f7f9fb !important; }
.details-page .bg-white { box-shadow: none !important; }
.details-page h2 { font-weight: 700 !important; }
.details-content-section { border-top: 1px solid rgba(11,30,91,.10); padding-top: 34px; }
.details-lead { max-width: 760px; font-size: 1.02rem; line-height: 1.9; }
.room-list { border-top: 1px solid rgba(11,30,91,.10); }
.room-row {
  padding: 28px 0;
  border-bottom: 1px solid rgba(11,30,91,.10);
  background: transparent !important;
  box-shadow: none !important;
  border-radius: 0 !important;
}
.room-row:hover { background: transparent !important; }
.service-card-grid { gap: 0 !important; border-top: 1px solid rgba(11,30,91,.10); }
.service-detail-card {
  background: transparent !important;
  border: 0 !important;
  border-bottom: 1px solid rgba(11,30,91,.10) !important;
  border-radius: 0 !important;
  padding: 28px 0 !important;
  box-shadow: none !important;
}
@media (min-width: 768px) {
  .service-detail-card:nth-child(odd) { padding-right: 22px !important; border-right: 1px solid rgba(11,30,91,.08) !important; }
  .service-detail-card:nth-child(even) { padding-left: 22px !important; }
}
.contact-panel {
  background: transparent !important;
  border: 0 !important;
  border-left: 1px solid rgba(11,30,91,.10) !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 4px 0 4px 26px !important;
}
.map-frame {
  border-radius: 18px !important;
  box-shadow: none !important;
  border: 1px solid rgba(11,30,91,.10) !important;
}
@media (max-width: 1024px) {
  .contact-panel { border-left: 0 !important; border-top: 1px solid rgba(11,30,91,.10) !important; padding: 28px 0 0 !important; }
}
</style>
<link rel="stylesheet" href="<?= e(base_url('assets/css/premium.css')) ?>?v=estate-connected">
</head>
<body class="bg-stone-50 text-stone-900 details-page">

<!-- HEADER -->
<header class="bg-white border-b border-stone-100 sticky top-0 z-30">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="<?= $homeUrl ?>" class="flex items-center gap-2 text-stone-900">
      <img src="<?= $logoUrl ?>" alt="Safari Tanzania" class="brand-logo-img">
    </a>
    <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-stone-700">
      <a href="<?= $listingUrl ?>" class="hover:text-amber-600">Explore</a>
      <a href="<?= $listingUrl ?>" class="hover:text-amber-600">Destinations</a>
      <?php if ($user): ?>
        <a href="<?= $dashUrl ?>" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-full font-semibold">Dashboard</a>
        <a href="<?= $logoutUrl ?>" class="hover:text-amber-600">Logout</a>
      <?php else: ?>
        <a href="<?= $loginUrl ?>" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-full font-semibold">Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<!-- HERO: DETAILS LEFT, GALLERY RIGHT -->
<section class="details-hero-section">
  <?php if ($isPreviewOnly): ?>
    <div class="max-w-7xl mx-auto mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900 flex items-start gap-3">
      <span class="material-symbols-outlined" style="font-size:22px;">visibility</span>
      <div>
        <strong class="block">Preview mode</strong>
        <span class="text-sm">This accommodation is still <?= e($acc['status']) ?>, so customers will not see it in the public list until admin approves it.</span>
      </div>
    </div>
  <?php endif; ?>

  <div class="details-hero-shell">
    <div class="details-hero-copy">
      <p class="details-hero-kicker"><?= e($acc['location']) ?></p>
      <h1 class="text-3xl md:text-5xl font-extrabold leading-tight"><?= e($acc['name']) ?></h1>
      <div class="details-hero-rating">
        <span class="material-symbols-outlined text-amber-500" style="font-size:18px;">star</span>
        <?= e(number_format((float)$acc['rating'], 1)) ?>
        <span class="text-stone-500 font-normal">(verified reviews)</span>
      </div>
      <p class="details-hero-location">
        <span class="material-symbols-outlined" style="font-size:18px;">location_on</span>
        <?= e($acc['address'] ?: $acc['location']) ?>
      </p>
      <div class="location-fact-grid">
        <div class="location-fact"><span>Type</span><strong><?= e(ucwords(str_replace('_', ' ', $acc['accommodation_type'] ?? 'Accommodation'))) ?></strong></div>
        <div class="location-fact"><span>Region</span><strong><?= e($acc['region'] ?: $acc['location']) ?></strong></div>
        <div class="location-fact"><span>District</span><strong><?= e($acc['district'] ?: 'Not specified') ?></strong></div>
        <div class="location-fact"><span>Area</span><strong><?= e(($acc['ward_area'] === 'Other' ? $acc['area_other'] : $acc['ward_area']) ?: 'Not specified') ?></strong></div>
      </div>
    </div>

    <div class="details-hero-media">
      <div class="details-gallery-grid" aria-label="Accommodation photo gallery">
        <?php foreach ($galleryImages as $idx => $img): ?>
          <figure class="gallery-panel">
            <img src="<?= e($img) ?>" alt="<?= e($acc['name']) ?> photo <?= (int)$idx + 1 ?>">
            <?php if ($idx === 0): ?>
              <figcaption class="gallery-count-pill">
                <span class="material-symbols-outlined" style="font-size:16px;">photo_library</span>
                <?= count($galleryImages) ?> photo<?= count($galleryImages) === 1 ? '' : 's' ?>
              </figcaption>
            <?php endif; ?>
          </figure>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<!-- BODY GRID -->
<section class="max-w-7xl mx-auto px-6 py-10 grid grid-cols-1 lg:grid-cols-3 gap-10">

  <!-- LEFT: description + amenities + rooms -->
  <div class="lg:col-span-2 space-y-12">
    <div class="details-content-section"><p class="details-lead text-stone-700 whitespace-pre-line"><?= e($acc['description']) ?></p>
    </div>

    <?php if ($amenities): ?>
      <div class="details-content-section">
        <h2 class="text-2xl font-extrabold mb-5">What this place offers</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <?php foreach ($amenities as $name): ?>
            <div class="flex items-center gap-3 text-stone-700">
              <span class="material-symbols-outlined text-amber-600"><?= e($iconFor($name)) ?></span>
              <span class="font-medium"><?= e($name) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="details-content-section">
      <h2 class="text-2xl font-extrabold mb-5">Available Room Types</h2>
      <?php if (!$rooms): ?>
        <p class="text-stone-500">This property has no rooms listed yet.</p>
      <?php else: ?>
        <div class="room-list">
          <?php foreach ($rooms as $r): ?>
            <article class="room-row flex flex-col md:flex-row md:items-center gap-6 transition">
              <div class="flex-1">
                <div class="flex flex-wrap items-baseline gap-3">
                  <h3 class="text-xl font-bold"><?= e($r['room_type']) ?></h3>
                  <p class="text-amber-600 font-extrabold">$<?= e(number_format((float)$r['price'], 0)) ?><span class="text-stone-500 font-medium text-sm">/night</span></p>
                </div>
                <div class="flex flex-wrap gap-4 mt-2 text-sm text-stone-600">
                  <span class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:18px;">group</span>
                    <?= (int)$r['capacity'] ?> Guests
                  </span>
                  <span class="inline-flex items-center gap-1">
                    <span class="material-symbols-outlined" style="font-size:18px;">bed</span>
                    <?= (int)$r['total_rooms'] ?> available
                  </span>
                </div>
                <?php if (!empty($r['description'])): ?>
                  <p class="text-stone-600 mt-3"><?= e($r['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($r['room_amenities'])): ?>
                  <p class="text-stone-600 mt-3 inline-flex items-start gap-2">
                    <span class="material-symbols-outlined text-amber-600" style="font-size:18px;">verified</span>
                    <span><?= e($r['room_amenities']) ?></span>
                  </p>
                <?php endif; ?>
                <?php $roomImgs = $roomImagesByRoom[(int)$r['id']] ?? []; ?>
                <?php if ($roomImgs): ?>
                  <div class="detail-media-strip" aria-label="<?= e($r['room_type']) ?> photo gallery">
                    <?php foreach (array_slice($roomImgs, 0, 4) as $idx => $img): ?>
                      <figure class="media-panel">
                        <img src="<?= e($img['image_path']) ?>" alt="<?= e($r['room_type']) ?> photo <?= (int)$idx + 1 ?>">
                      </figure>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
              <a href="<?= e(base_url('traveler/book_room.php?room_id=' . (int)$r['id'])) ?>"
                 class="inline-flex justify-center bg-amber-500 hover:bg-amber-600 text-white font-bold px-6 py-3 rounded-lg transition whitespace-nowrap">
                Book Now
              </a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($services): ?>
      <div class="details-content-section" id="services">
        <h2 class="text-2xl font-extrabold mb-5">Services & Experiences</h2>
        <div class="service-card-grid">
          <?php foreach ($services as $s): ?>
            <article class="service-detail-card">
              <?php $svcImgs = $serviceImagesByService[(int)$s['id']] ?? []; ?>
              <?php if ($svcImgs): ?>
                <div class="detail-media-strip" style="margin-top:0;" aria-label="<?= e($s['name']) ?> service photo gallery">
                  <?php foreach (array_slice($svcImgs, 0, 4) as $idx => $img): ?>
                    <figure class="media-panel">
                      <img src="<?= e($img['image_path']) ?>" alt="<?= e($s['name']) ?> service photo <?= (int)$idx + 1 ?>">
                    </figure>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="flex items-start justify-between gap-3 mt-4">
                <h3 class="text-lg font-extrabold text-stone-900"><?= e($s['name']) ?></h3>
                <span class="service-price-pill">
                  <?= $s['price'] !== null ? '$' . e(number_format((float)$s['price'], 2)) : 'Ask host' ?>
                </span>
              </div>
              <?php if (!empty($s['description'])): ?>
                <p class="text-stone-600 mt-3 leading-relaxed"><?= e($s['description']) ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <!-- MAP -->
    <div class="details-content-section">
      <?php
      $areaLabel = ($acc['ward_area'] ?? '') === 'Other' ? ($acc['area_other'] ?? '') : ($acc['ward_area'] ?? '');
      $mapLabelParts = array_filter([$areaLabel, $acc['district'] ?? '', $acc['region'] ?? $acc['location'] ?? '', 'Tanzania']);
      $mapLabel = $acc['address'] ?: implode(', ', $mapLabelParts);
      $hasCoords = is_numeric($acc['latitude'] ?? null) && is_numeric($acc['longitude'] ?? null);
      $mapQuery = $hasCoords ? ($acc['latitude'] . ',' . $acc['longitude']) : (($acc['address'] ?: $acc['name'] . ', ' . $acc['location']) . ', Tanzania');
      $gmUrl    = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapQuery);
      ?>
      <h2 class="text-2xl font-extrabold mb-2">Where you'll be</h2>
      <p class="text-stone-500 text-sm mb-4 inline-flex items-center gap-1">
        <span class="material-symbols-outlined" style="font-size:16px;">location_on</span>
        <?= e($mapLabel) ?>
      </p>


      <?php if (GOOGLE_MAPS_API_KEY): ?>
        <!-- Google Maps Embed API -->
        <div class="map-frame overflow-hidden" style="height:420px;">
          <iframe
            width="100%" height="100%" style="border:0;" loading="lazy"
            allowfullscreen referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps/embed/v1/place?key=<?= urlencode(GOOGLE_MAPS_API_KEY) ?>&q=<?= urlencode($mapQuery) ?>&zoom=13">
          </iframe>
        </div>
      <?php else: ?>
        <!-- Leaflet + OpenStreetMap (works without any API key) -->
        <div id="acc-map" class="map-frame overflow-hidden"
             style="height:420px; z-index:0;"></div>
      <?php endif; ?>

      <div class="flex flex-wrap gap-3 mt-4">
        <a href="<?= e($gmUrl) ?>" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-2 border border-stone-300 rounded-lg px-5 py-2.5 text-sm font-semibold text-stone-700 hover:border-amber-500 hover:text-amber-600 transition">
          <span class="material-symbols-outlined" style="font-size:20px;">directions</span>
          Get Directions
        </a>
        <a href="<?= e($gmUrl) ?>" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-2 border border-stone-300 rounded-lg px-5 py-2.5 text-sm font-semibold text-stone-700 hover:border-amber-500 hover:text-amber-600 transition">
          <span class="material-symbols-outlined" style="font-size:20px;">open_in_new</span>
          Open in Google Maps
        </a>
      </div>
    </div>

  </div>

  <!-- RIGHT: contact info -->
  <aside class="lg:col-span-1">
    <div class="contact-panel sticky top-24">
      <h3 class="text-lg font-extrabold mb-5">Lodge Contact</h3>
      <ul class="space-y-5 text-sm">
        <li class="flex gap-3">
          <span class="material-symbols-outlined text-amber-600">location_on</span>
          <div>
            <p class="font-semibold"><?= e($acc['location']) ?></p>
            <p class="text-stone-500"><?= e($acc['address'] ?: 'Tanzania') ?></p>
          </div>
        </li>
        <?php if (!empty($acc['owner_phone'])): ?>
        <li class="flex gap-3">
          <span class="material-symbols-outlined text-amber-600">call</span>
          <div>
            <p class="font-semibold">Reservations</p>
            <p class="text-stone-500"><?= e($acc['owner_phone']) ?></p>
          </div>
        </li>
        <?php endif; ?>
        <li class="flex gap-3">
          <span class="material-symbols-outlined text-amber-600">mail</span>
          <div>
            <p class="font-semibold">Email</p>
            <p class="text-stone-500 break-all"><?= e($acc['owner_email']) ?></p>
          </div>
        </li>
      </ul>
      <a href="#" class="block text-center mt-6 border border-stone-300 hover:border-amber-500 text-stone-700 font-semibold py-3 rounded-lg transition">
        Contact Host
      </a>
    </div>
  </aside>
</section>

<!-- FOOTER -->
<footer class="bg-stone-900 text-stone-400 text-sm mt-10">
  <div class="max-w-7xl mx-auto px-6 py-8 flex flex-col md:flex-row items-center justify-between gap-3">
    <div class="flex items-center gap-2 text-white">
      <img src="<?= $logoUrl ?>" alt="Safari Tanzania" class="brand-logo-img footer-logo-img">
    </div>
    <p>&copy; <?= date('Y') ?> SAFARI TANZANIA. Preserving the Wild.</p>
  </div>
</footer>

<?php if (!GOOGLE_MAPS_API_KEY): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
<script>
(function () {
  const el = document.getElementById('acc-map');
  if (!el) return;

  const accName  = <?= json_encode($acc['name']) ?>;
  const accAddr  = <?= json_encode($mapLabel) ?>;
  const geocodeQ = <?= json_encode($mapQuery) ?>;
  const fixedLat = <?= $hasCoords ? json_encode((float)$acc['latitude']) : 'null' ?>;
  const fixedLng = <?= $hasCoords ? json_encode((float)$acc['longitude']) : 'null' ?>;

  if (typeof L === 'undefined') {
    el.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;text-align:center;padding:32px;background:#fff;color:#0B1E5B;font-family:Plus Jakarta Sans,sans-serif;"><div><strong style="font-size:18px;display:block;margin-bottom:8px;">Map temporarily unavailable</strong><span style="color:#6b7b99;">Use the Google Maps button below to open this location.</span></div></div>';
    return;
  }

  // Amber pin icon to match site theme
  const pin = L.divIcon({
    html: '<div style="width:18px;height:18px;background:#f59e0b;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35);"></div>',
    className: '', iconSize: [18, 18], iconAnchor: [9, 9], popupAnchor: [0, -12]
  });

  function buildMap(lat, lon, zoom) {
    const map = L.map('acc-map', { scrollWheelZoom: false }).setView([lat, lon], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    }).addTo(map);
    L.marker([lat, lon], { icon: pin }).addTo(map)
      .bindPopup(
        '<div style="font-family:\'Plus Jakarta Sans\',sans-serif;min-width:160px;line-height:1.4">' +
        '<strong style="font-size:13px;">' + accName + '</strong><br>' +
        '<span style="color:#78716c;font-size:12px;">' + accAddr + '</span></div>'
      )
      .openPopup();
    // Enable scroll zoom on click
    map.once('click', () => map.scrollWheelZoom.enable());
  }

  if (fixedLat !== null && fixedLng !== null) {
    buildMap(fixedLat, fixedLng, 15);
    return;
  }

  // Try Nominatim geocoding first
  fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(geocodeQ), {
    headers: { 'Accept-Language': 'en' }
  })
  .then(r => r.json())
  .then(data => {
    if (data.length > 0) {
      buildMap(parseFloat(data[0].lat), parseFloat(data[0].lon), 13);
    } else {
      buildMap(-6.369, 34.889, 6); // Tanzania centre fallback
    }
  })
  .catch(() => buildMap(-6.369, 34.889, 6));
})();
</script>
<?php endif; ?>
</body>
</html>






















