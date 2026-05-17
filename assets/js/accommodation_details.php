<?php
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('public/accommodation_listing.php'); }

$stmt = $pdo->prepare("SELECT a.*, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
                       FROM accommodations a
                       JOIN users u ON u.id = a.owner_id
                       WHERE a.id = ? AND a.status = 'approved'");
$stmt->execute([$id]);
$acc = $stmt->fetch();
if (!$acc) { http_response_code(404); die('Accommodation not found.'); }

$rooms = $pdo->prepare('SELECT * FROM rooms WHERE accommodation_id = ? ORDER BY price ASC');
$rooms->execute([$id]);
$rooms = $rooms->fetchAll();

$amenities = $pdo->prepare(
    'SELECT am.name FROM amenities am
     JOIN accommodation_amenities aa ON aa.amenity_id = am.id
     WHERE aa.accommodation_id = ?'
);
$amenities->execute([$id]);
$amenities = $amenities->fetchAll(PDO::FETCH_COLUMN);

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

$heroImg = $acc['image_url'] ?: 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1600&q=80';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?= e($acc['name']) ?> — SAFARI TANZANIA</title>
<meta name="description" content="<?= e(mb_substr((string)$acc['description'], 0, 150)) ?>">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<?php if (!GOOGLE_MAPS_API_KEY): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<?php endif; ?>
<style>
  body { font-family: 'Plus Jakarta Sans', sans-serif; }
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="bg-stone-50 text-stone-900">

<!-- HEADER -->
<header class="bg-white border-b border-stone-100 sticky top-0 z-30">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="<?= $homeUrl ?>" class="flex items-center gap-2 text-stone-900">
      <span class="material-symbols-outlined text-3xl text-amber-600">park</span>
      <span class="font-extrabold tracking-wide">SAFARI TANZANIA</span>
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

<!-- HERO IMAGES -->
<section class="max-w-7xl mx-auto px-6 pt-8">
  <div class="relative rounded-2xl overflow-hidden h-[420px] md:h-[520px]">
    <img src="<?= e($heroImg) ?>" alt="<?= e($acc['name']) ?>" class="w-full h-full object-cover">
    <button class="absolute bottom-4 right-4 bg-white text-stone-900 text-sm font-semibold px-4 py-2 rounded-lg shadow inline-flex items-center gap-2">
      <span class="material-symbols-outlined" style="font-size:18px;">grid_view</span>
      Show all photos
    </button>
  </div>
</section>

<!-- HEAD -->
<section class="max-w-7xl mx-auto px-6 mt-8">
  <p class="text-xs font-bold tracking-widest text-amber-600 uppercase"><?= e($acc['location']) ?></p>
  <div class="flex flex-wrap items-end justify-between gap-3 mt-2">
    <h1 class="text-3xl md:text-4xl font-extrabold"><?= e($acc['name']) ?></h1>
    <span class="inline-flex items-center gap-2 bg-stone-100 text-stone-900 px-3 py-1.5 rounded-full text-sm font-semibold">
      <span class="material-symbols-outlined text-amber-500" style="font-size:18px;">star</span>
      <?= e(number_format((float)$acc['rating'], 1)) ?>
      <span class="text-stone-500 font-normal">(verified reviews)</span>
    </span>
  </div>
  <p class="text-stone-500 mt-1 inline-flex items-center gap-1">
    <span class="material-symbols-outlined" style="font-size:18px;">location_on</span>
    <?= e($acc['address'] ?: $acc['location']) ?>
  </p>
</section>

<!-- BODY GRID -->
<section class="max-w-7xl mx-auto px-6 py-10 grid grid-cols-1 lg:grid-cols-3 gap-10">

  <!-- LEFT: description + amenities + rooms -->
  <div class="lg:col-span-2 space-y-10">
    <div>
      <p class="text-stone-700 leading-relaxed whitespace-pre-line"><?= e($acc['description']) ?></p>
    </div>

    <?php if ($amenities): ?>
      <div>
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

    <div>
      <h2 class="text-2xl font-extrabold mb-5">Available Room Types</h2>
      <?php if (!$rooms): ?>
        <p class="text-stone-500">This property has no rooms listed yet.</p>
      <?php else: ?>
        <div class="space-y-5">
          <?php foreach ($rooms as $r): ?>
            <article class="bg-white border border-stone-100 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row md:items-center gap-6 hover:shadow-md transition">
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

    <!-- MAP -->
    <div>
      <h2 class="text-2xl font-extrabold mb-2">Where you'll be</h2>
      <p class="text-stone-500 text-sm mb-4 inline-flex items-center gap-1">
        <span class="material-symbols-outlined" style="font-size:16px;">location_on</span>
        <?= e($acc['address'] ?: $acc['location'] . ', Tanzania') ?>
      </p>

      <?php
      $mapQuery = ($acc['address'] ?: $acc['name'] . ', ' . $acc['location']) . ', Tanzania';
      $gmUrl    = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapQuery);
      ?>

      <?php if (GOOGLE_MAPS_API_KEY): ?>
        <!-- Google Maps Embed API -->
        <div class="rounded-2xl overflow-hidden border border-stone-200 shadow-sm" style="height:420px;">
          <iframe
            width="100%" height="100%" style="border:0;" loading="lazy"
            allowfullscreen referrerpolicy="no-referrer-when-downgrade"
            src="https://www.google.com/maps/embed/v1/place?key=<?= urlencode(GOOGLE_MAPS_API_KEY) ?>&q=<?= urlencode($mapQuery) ?>&zoom=13">
          </iframe>
        </div>
      <?php else: ?>
        <!-- Leaflet + OpenStreetMap (works without any API key) -->
        <div id="acc-map" class="rounded-2xl overflow-hidden border border-stone-200 shadow-sm"
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

  <!-- RIGHT: contact card -->
  <aside class="lg:col-span-1">
    <div class="bg-white border border-stone-100 rounded-2xl p-6 shadow-sm sticky top-24">
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
      <span class="material-symbols-outlined text-amber-500">park</span>
      <span class="font-bold">SAFARI TANZANIA</span>
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
  const accAddr  = <?= json_encode($acc['address'] ?: $acc['location']) ?>;
  const geocodeQ = <?= json_encode($mapQuery) ?>;

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
