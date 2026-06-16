<?php
require_once __DIR__ . '/../config/db.php';

// --- Filters (preserve existing logic) ---
$q       = trim($_GET['q']       ?? '');
$region  = trim($_GET['region']  ?? '');
$price   = trim($_GET['price']   ?? '');
$rating  = (float)($_GET['rating'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset  = ($page - 1) * $perPage;

$where  = ["a.status = 'approved'"];
$params = [];

if ($q !== '') {
    $where[] = "(a.name LIKE :q_name OR a.location LIKE :q_location OR a.description LIKE :q_description)";
    $params[':q_name'] = "%$q%";
    $params[':q_location'] = "%$q%";
    $params[':q_description'] = "%$q%";
}
if ($region !== '') {
    $where[] = "a.location LIKE :region";
    $params[':region'] = "%$region%";
}
if ($rating > 0) {
    $where[] = "a.rating >= :rating";
    $params[':rating'] = $rating;
}

$priceJoinFilter = '';
if ($price !== '') {
    [$min, $max] = match ($price) {
        '200-500'  => [200, 500],
        '500-1000' => [500, 1000],
        '1000+'    => [1000, 999999],
        default    => [0, 999999],
    };
    $priceJoinFilter = " AND r2.price BETWEEN :pmin AND :pmax";
    $params[':pmin'] = $min;
    $params[':pmax'] = $max;
}

$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(DISTINCT a.id) FROM accommodations a
             LEFT JOIN rooms r2 ON r2.accommodation_id = a.id $priceJoinFilter
             WHERE $whereSql";
$cs = $pdo->prepare($countSql);
$cs->execute($params);
$total = (int)$cs->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$sql = "SELECT a.*, MIN(r.price) AS from_price
        FROM accommodations a
        LEFT JOIN rooms r ON r.accommodation_id = a.id
        LEFT JOIN rooms r2 ON r2.accommodation_id = a.id $priceJoinFilter
        WHERE $whereSql
        GROUP BY a.id
        ORDER BY a.rating DESC, a.id DESC
        LIMIT $perPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// Only offer regions that actually have at least one approved accommodation.
$regionOptions = $pdo->query("SELECT DISTINCT location FROM accommodations WHERE status = 'approved' AND location IS NOT NULL AND location <> '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);

$view = ($_GET['view'] ?? 'grid') === 'map' ? 'map' : 'grid';

// Build property data for the JS map
$mapProps = array_map(fn($a) => [
    'id'       => (int)$a['id'],
    'name'     => $a['name'],
    'location' => $a['location'],
    'address'  => $a['address'] ?? '',
    'price'    => $a['from_price'] !== null ? (float)$a['from_price'] : null,
    'rating'   => (float)$a['rating'],
    'image'    => $a['image_url'] ?: '',
    'url'      => base_url('public/accommodation_details.php?id=' . (int)$a['id']),
], $rows);

$user = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role']    ?? null;
$logoUrl = e(base_url('assets/images/logo-cropped-transparent.png'));
$dashUrl = $role === 'owner'
    ? base_url('owner/dashboard.php')
    : ($role === 'admin' ? base_url('admin/dashboard.php') : base_url('traveler/dashboard.php'));
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exclusive Accommodations â€” SAFARI TANZANIA</title>
<meta name="description" content="Browse luxury safari lodges, beachfront villas and eco-camps across Tanzania. Filter by region, price and rating.">
<link rel="stylesheet" href="<?= e(base_url('assets/css/premium.css')) ?>">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<style>
  body { font-family: 'Plus Jakarta Sans', sans-serif; }
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  .leaflet-popup-content { font-family: 'Plus Jakarta Sans', sans-serif; }

/* LISTING PREMIUM OVERRIDES */
.listing-page { background: var(--white) !important; color: var(--navy) !important; font-family: 'Inter', sans-serif !important; }
.listing-page header.bg-white { background: rgba(6,14,42,.94) !important; border-color: rgba(30,198,255,.12) !important; backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%); box-shadow: 0 4px 30px rgba(0,0,0,.25) !important; }
.listing-page header a:not(.pill-nav__link), .listing-page header nav a:not(.pill-nav__link) { color: rgba(255,255,255,.78) !important; }
.listing-page header a:hover:not(.pill-nav__link), .listing-page header nav a:hover:not(.pill-nav__link) { color: #fff !important; }
.listing-page header .text-amber-600 { color: var(--cyan) !important; }
.listing-page header .bg-amber-500, .listing-page .bg-amber-500 { background: var(--gradient-btn) !important; color: #fff !important; box-shadow: var(--shadow-blue) !important; }
.listing-page header .bg-stone-900 { background: var(--gradient-btn) !important; }
.listing-page > section.bg-white:first-of-type { position: relative; overflow: hidden; min-height: 430px; display: flex; align-items: center; padding-top: 78px; background: linear-gradient(135deg, rgba(6,14,42,.93), rgba(11,30,91,.68)), url('https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=1920&q=85') center/cover no-repeat !important; border: 0 !important; }
.listing-page > section.bg-white:first-of-type h1 { color: #fff !important; font-family: 'Poppins', sans-serif !important; font-size: clamp(2.7rem, 6vw, 5rem) !important; letter-spacing: -1.5px; line-height: 1.05; }
.listing-page > section.bg-white:first-of-type h1::after { content: 'Safari Stays'; display: block; background: var(--gradient-brand); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
.listing-page > section.bg-white:first-of-type p { color: rgba(245,247,250,.68) !important; font-size: 1.08rem !important; }
.listing-page section.max-w-7xl.-mt-8 { margin-top: 0 !important; padding-top: 28px !important; }
.listing-page form.bg-white { border-radius: 24px !important; border: 1px solid rgba(30,198,255,.12) !important; box-shadow: 0 24px 70px rgba(0,0,0,.18) !important; }
.listing-page form label span, .listing-page .text-stone-700 { color: rgba(11,30,91,.76) !important; }
.listing-page select, .listing-page input { border-color: rgba(11,30,91,.12) !important; border-radius: 14px !important; }
.listing-page select:focus, .listing-page input:focus { border-color: var(--cyan) !important; box-shadow: 0 0 0 4px rgba(30,198,255,.12) !important; }
.listing-page article.group, .listing-page article.bg-white { border-radius: 20px !important; border: 1px solid rgba(11,30,91,.07) !important; box-shadow: 0 4px 20px rgba(11,30,91,.06) !important; overflow: hidden; }
.listing-page article.group:hover, .listing-page article.bg-white:hover { transform: translateY(-8px); box-shadow: 0 24px 60px rgba(11,30,91,.15) !important; }
.listing-page article h3 { color: var(--navy) !important; font-family: 'Poppins', sans-serif !important; }
.listing-page .text-stone-500, .listing-page .text-stone-600 { color: #6b7b99 !important; }
.listing-page .text-amber-500 { color: var(--cyan) !important; }
.listing-page .bg-stone-900, .listing-page a.bg-stone-900 { background: var(--gradient-btn) !important; box-shadow: var(--shadow-blue) !important; }
.listing-page .bg-stone-100 { background: rgba(11,30,91,.06) !important; }
.listing-page .rounded-2xl { border-radius: 24px !important; }
.listing-page footer.bg-stone-900 { background: var(--dark) !important; border-top: 1px solid rgba(30,198,255,.08) !important; }

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
</style>
<link rel="stylesheet" href="<?= e(base_url('assets/css/premium.css')) ?>?v=estate-connected">
<link rel="stylesheet" href="<?= e(base_url('assets/css/pill-nav.css')) ?>">
</head>
<body class="bg-stone-50 text-stone-900 listing-page">

<!-- HEADER -->
<header class="bg-white border-b border-stone-100 sticky top-0 z-30">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="<?= e(base_url('public/index.php')) ?>" class="flex items-center gap-2 text-stone-900">
      <img src="<?= $logoUrl ?>" alt="Safari Tanzania" class="brand-logo-img">
    </a>
    <div class="hidden md:flex items-center gap-4">
      <ul class="pill-nav">
        <li class="pill-nav__cursor" aria-hidden="true"></li>
        <li class="pill-nav__item"><a href="<?= e(base_url('public/index.php')) ?>" class="pill-nav__link">Home</a></li>
        <li class="pill-nav__item"><a href="<?= e(base_url('public/accommodation_listing.php')) ?>" class="pill-nav__link">Properties</a></li>
        <?php if ($user): ?>
          <li class="pill-nav__item"><a href="<?= e($dashUrl) ?>" class="pill-nav__link">Dashboard</a></li>
          <li class="pill-nav__item"><a href="<?= e(base_url('traveler/my_bookings.php')) ?>" class="pill-nav__link">My Bookings</a></li>
        <?php endif; ?>
      </ul>
      <?php if ($user): ?>
        <a href="<?= e(base_url('auth/logout.php')) ?>" class="bg-stone-900 text-white px-4 py-2 rounded-full hover:bg-stone-800 text-sm font-semibold">Logout</a>
      <?php else: ?>
        <a href="<?= e(base_url('auth/login.php')) ?>" class="bg-amber-500 text-white px-4 py-2 rounded-full font-semibold hover:bg-amber-600 text-sm">Login</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- PAGE HEAD -->
<section class="bg-white border-b border-stone-100">
  <div class="max-w-7xl mx-auto px-6 py-12 text-center">
    <h1 class="text-4xl md:text-5xl font-extrabold text-stone-900">Exclusive Accommodations</h1>
    <p class="text-stone-600 mt-3 max-w-2xl mx-auto">
      Discover the height of luxury in the heart of the Tanzanian wilderness. From savanna lodges to private coastal villas.
    </p>
  </div>
</section>

<!-- FILTERS -->
<section class="max-w-7xl mx-auto px-6 -mt-8">
  <form method="get" class="bg-white rounded-2xl shadow-lg border border-stone-100 p-5 grid grid-cols-1 md:grid-cols-[1fr_1fr_1fr_auto] gap-4 items-end">
    <input type="hidden" name="q" value="<?= e($q) ?>">

    <label class="block">
      <span class="block text-xs font-bold uppercase tracking-wider text-stone-700 mb-1">Region</span>
      <select name="region" class="w-full rounded-lg border-stone-200 text-sm focus:border-amber-500 focus:ring-amber-500">
        <option value="">All regions</option>
        <?php foreach ($regionOptions as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $region === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="block">
      <span class="block text-xs font-bold uppercase tracking-wider text-stone-700 mb-1">Price Range</span>
      <select name="price" class="w-full rounded-lg border-stone-200 text-sm focus:border-amber-500 focus:ring-amber-500">
        <option value="">Any price</option>
        <option value="200-500"  <?= $price==='200-500'  ? 'selected':'' ?>>Tsh 200 - Tsh 500 / night</option>
        <option value="500-1000" <?= $price==='500-1000' ? 'selected':'' ?>>Tsh 500 - Tsh 1,000 / night</option>
        <option value="1000+"    <?= $price==='1000+'    ? 'selected':'' ?>>Tsh 1,000+ / night</option>
      </select>
    </label>

    <label class="block">
      <span class="block text-xs font-bold uppercase tracking-wider text-stone-700 mb-1">Minimum Rating</span>
      <select name="rating" class="w-full rounded-lg border-stone-200 text-sm focus:border-amber-500 focus:ring-amber-500">
        <option value="0">Any rating</option>
        <option value="4.0" <?= $rating==4.0 ? 'selected':'' ?>>4.0+ Stars</option>
        <option value="4.5" <?= $rating==4.5 ? 'selected':'' ?>>4.5+ Stars</option>
        <option value="5.0" <?= $rating==5.0 ? 'selected':'' ?>>5.0 Stars</option>
      </select>
    </label>

    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg px-6 py-3 inline-flex items-center justify-center gap-2">
      <span class="material-symbols-outlined" style="font-size:20px;">search</span>
      Apply Filters
    </button>
  </form>

  <!-- Active filter chips -->
  <?php
  $chips = [];
  if ($region !== '') $chips[] = ['region', $region];
  if ($price  !== '') $chips[] = ['price', $price];
  if ($rating  > 0)   $chips[] = ['rating', $rating.'+ Stars'];
  if ($q      !== '') $chips[] = ['q', '"'.$q.'"'];
  ?>
  <?php if ($chips): ?>
    <div class="flex flex-wrap gap-2 mt-4">
      <?php foreach ($chips as [$key, $label]):
        $params = $_GET; unset($params[$key]);
        $href = '?' . http_build_query($params);
      ?>
        <a href="<?= e($href) ?>"
           class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1.5 rounded-full text-xs font-semibold hover:bg-amber-100">
          <?= e($label) ?>
          <span class="material-symbols-outlined" style="font-size:16px;">close</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- RESULTS -->
<section class="max-w-7xl mx-auto px-6 py-12">
  <!-- Results header with view toggle -->
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <p class="text-sm text-stone-500"><?= (int)$total ?> stay<?= $total !== 1 ? 's' : '' ?> found</p>
    <div class="flex items-center gap-1 bg-stone-100 p-1 rounded-xl">
      <?php
      $qs = $_GET;
      $qs['view'] = 'grid';
      $gridUrl = '?' . http_build_query($qs);
      $qs['view'] = 'map';
      $mapUrl  = '?' . http_build_query($qs);
      ?>
      <a href="<?= e($gridUrl) ?>"
         class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition
                <?= $view === 'grid' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700' ?>">
        <span class="material-symbols-outlined" style="font-size:18px;">grid_view</span>Grid
      </a>
      <a href="<?= e($mapUrl) ?>"
         class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition
                <?= $view === 'map' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700' ?>">
        <span class="material-symbols-outlined" style="font-size:18px;">map</span>Map
      </a>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="bg-white border border-stone-100 rounded-2xl p-12 text-center">
      <span class="material-symbols-outlined text-5xl text-stone-300">travel_explore</span>
      <h3 class="mt-3 font-bold text-lg">No accommodations match your filters</h3>
      <p class="text-stone-500 text-sm mt-1">Try widening your search criteria.</p>
    </div>
  <?php elseif ($view === 'map'): ?>
    <!-- MAP VIEW -->
    <div class="rounded-2xl overflow-hidden border border-stone-200 shadow-sm mb-4" style="height:600px; z-index:0;">
      <div id="listing-map" style="width:100%;height:100%;"></div>
    </div>
    <p class="text-xs text-stone-400 text-center">
      Map data Â© <a href="https://www.openstreetmap.org/copyright" target="_blank" class="hover:underline">OpenStreetMap</a> contributors.
      Click a marker to view property details.
    </p>
  <?php else: ?>
    <!-- GRID VIEW -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      <?php foreach ($rows as $a): ?>
        <article class="group bg-white rounded-2xl overflow-hidden border border-stone-100 hover:shadow-xl transition">
          <div class="relative aspect-[4/3] overflow-hidden">
            <img src="<?= e($a['image_url'] ?: 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?w=1200') ?>"
                 alt="<?= e($a['name']) ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
            <span class="absolute top-3 left-3 bg-white/95 text-stone-900 text-xs font-bold px-2.5 py-1.5 rounded-full inline-flex items-center gap-1">
              <span class="material-symbols-outlined text-amber-500" style="font-size:16px;">star</span>
              <?= e(number_format((float)$a['rating'], 1)) ?>
            </span>
          </div>
          <div class="p-5">
            <h3 class="font-bold text-lg text-stone-900"><?= e($a['name']) ?></h3>
            <p class="text-sm text-stone-500 mt-1 inline-flex items-center gap-1">
              <span class="material-symbols-outlined" style="font-size:16px;">location_on</span>
              <?= e($a['location']) ?>
            </p>
            <p class="text-sm text-stone-600 mt-3 line-clamp-2">
              <?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 120, 'â€¦')) ?>
            </p>
            <div class="flex items-center justify-between mt-5 pt-4 border-t border-stone-100">
              <p class="text-stone-900">
                <?php if (!is_null($a['from_price'])): ?>
                  <span class="font-extrabold text-xl">Tsh <?= e(number_format((float)$a['from_price'], 0)) ?></span>
                  <span class="text-stone-500 text-sm">/ night</span>
                <?php else: ?>
                  <span class="text-stone-500 text-sm">Price on request</span>
                <?php endif; ?>
              </p>
              <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$a['id'])) ?>"
                 class="bg-stone-900 text-white text-sm font-semibold px-4 py-2 rounded-full hover:bg-amber-600">
                View Details
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Pagination (grid view only) -->
    <?php if ($totalPages > 1): ?>
      <?php
      $qs = $_GET;
      $prev = max(1, $page - 1);
      $next = min($totalPages, $page + 1);
      $qs['page'] = $prev; $prevUrl = '?' . http_build_query($qs);
      $qs['page'] = $next; $nextUrl = '?' . http_build_query($qs);
      ?>
      <div class="mt-12 flex items-center justify-center gap-3">
        <a href="<?= e($prevUrl) ?>"
           class="w-10 h-10 inline-flex items-center justify-center rounded-full border border-stone-200 hover:border-amber-500 hover:text-amber-600 <?= $page<=1?'opacity-40 pointer-events-none':'' ?>">
          <span class="material-symbols-outlined">chevron_left</span>
        </a>
        <span class="text-sm text-stone-600 font-medium">Page <?= (int)$page ?> of <?= (int)$totalPages ?></span>
        <a href="<?= e($nextUrl) ?>"
           class="w-10 h-10 inline-flex items-center justify-center rounded-full border border-stone-200 hover:border-amber-500 hover:text-amber-600 <?= $page>=$totalPages?'opacity-40 pointer-events-none':'' ?>">
          <span class="material-symbols-outlined">chevron_right</span>
        </a>
      </div>
    <?php endif; ?>
  <?php endif; /* end grid view */ ?>
</section>

<!-- FOOTER -->
<footer class="bg-stone-900 text-stone-300 pt-16 pb-8 mt-10">
  <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-10">
    <div class="col-span-2 md:col-span-1">
      <div class="flex items-center gap-2 text-white">
        <img src="<?= $logoUrl ?>" alt="Safari Tanzania" class="brand-logo-img footer-logo-img">
      </div>
      <p class="text-sm mt-4 text-stone-400">
        Crafting unforgettable luxury expeditions across the heart of East Africa since 2012.
      </p>
    </div>
    <div>
      <h5 class="font-bold text-white mb-4">Explore</h5>
      <ul class="space-y-2 text-sm">
        <li><a href="<?= e(base_url('public/index.php')) ?>" class="hover:text-white">Home</a></li>
        <li><a href="<?= e(base_url('public/accommodation_listing.php')) ?>" class="hover:text-white">Explore</a></li>
        <li><a href="#" class="hover:text-white">About Us</a></li>
        <li><a href="#" class="hover:text-white">Contact</a></li>
      </ul>
    </div>
    <div>
      <h5 class="font-bold text-white mb-4">Legal</h5>
      <ul class="space-y-2 text-sm">
        <li><a href="#" class="hover:text-white">Privacy Policy</a></li>
        <li><a href="#" class="hover:text-white">Terms of Service</a></li>
      </ul>
    </div>
    <div>
      <h5 class="font-bold text-white mb-4">Newsletter</h5>
      <form class="flex">
        <input type="email" placeholder="Email address"
               class="flex-1 rounded-l-md border-0 bg-stone-800 text-white text-sm px-3 py-2 focus:ring-amber-500">
        <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-4 rounded-r-md font-semibold text-sm">
          Subscribe
        </button>
      </form>
    </div>
  </div>
  <div class="max-w-7xl mx-auto px-6 mt-12 pt-6 border-t border-stone-800 text-center text-sm text-stone-500">
    &copy; <?= date('Y') ?> SAFARI TANZANIA. Preserving the Wild.
  </div>
</footer>

<?php if ($view === 'map' && !empty($rows)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
<script>
(function () {
  // Known Tanzania region coordinates for fast lookup (no API call needed)
  const TZ = {
    'serengeti':    [-2.333, 34.833],
    'zanzibar':     [-6.166, 39.202],
    'ngorongoro':   [-3.256, 35.588],
    'moshi':        [-3.387, 37.344],
    'kilimanjaro':  [-3.067, 37.356],
    'arusha':       [-3.387, 36.683],
    'dar es salaam':[-6.792, 39.208],
    'tarangire':    [-3.836, 36.011],
    'manyara':      [-3.550, 35.830],
    'selous':       [-8.650, 37.900],
    'ruaha':        [-7.850, 34.950],
    'nungwi':       [-5.723, 39.309],
    'pemba':        [-5.200, 39.750],
    'bagamoyo':     [-6.440, 38.900],
    'mikumi':       [-7.390, 36.900],
  };

  function coordsFor(location) {
    const loc = (location || '').toLowerCase();
    for (const [key, coords] of Object.entries(TZ)) {
      if (loc.includes(key) || key.includes(loc)) return coords;
    }
    return [-6.369, 34.889]; // Tanzania centre
  }

  const properties = <?= json_encode($mapProps, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const mapEl = document.getElementById('listing-map');
  if (typeof L === 'undefined') {
    mapEl.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;text-align:center;padding:32px;background:#fff;color:#0B1E5B;font-family:Plus Jakarta Sans,sans-serif;"><div><strong style="font-size:18px;display:block;margin-bottom:8px;">Map temporarily unavailable</strong><span style="color:#6b7b99;">The accommodations are still available in Grid view.</span></div></div>';
    return;
  }

  const map = L.map('listing-map').setView([-6.369, 34.889], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
  }).addTo(map);

  const pin = L.divIcon({
    html: '<div style="width:16px;height:16px;background:#f59e0b;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.35);"></div>',
    className: '', iconSize: [16, 16], iconAnchor: [8, 8], popupAnchor: [0, -12]
  });

  const bounds = [];
  properties.forEach(p => {
    const [lat, lon] = coordsFor(p.location);
    bounds.push([lat, lon]);

    const price  = p.price ? 'Tsh ' + Math.round(p.price) + '<span style="color:#78716c;font-weight:400">/night</span>' : 'Price on request';
    const img    = p.image ? '<img src="' + p.image + '" style="width:100%;height:80px;object-fit:cover;border-radius:8px;margin-bottom:8px;">' : '';
    const stars  = 'â˜…'.repeat(Math.round(p.rating));
    const popup  =
      '<div style="font-family:\'Plus Jakarta Sans\',sans-serif;min-width:180px;max-width:200px;line-height:1.4">' +
      img +
      '<strong style="font-size:13px;">' + p.name + '</strong>' +
      '<p style="color:#78716c;font-size:11px;margin:2px 0;">' + p.location + '</p>' +
      '<p style="color:#f59e0b;font-size:12px;margin:4px 0;">' + stars + ' ' + p.rating.toFixed(1) + '</p>' +
      '<p style="font-size:13px;font-weight:700;margin:4px 0;">' + price + '</p>' +
      '<a href="' + p.url + '" style="display:inline-block;margin-top:6px;background:#f59e0b;color:#fff;font-size:12px;font-weight:700;padding:4px 12px;border-radius:6px;text-decoration:none;">View Details</a>' +
      '</div>';

    L.marker([lat, lon], { icon: pin }).addTo(map).bindPopup(popup);
  });

  if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [40, 40] });
  } else if (bounds.length === 1) {
    map.setView(bounds[0], 12);
  }
})();
</script>
<?php endif; ?>
<script src="<?= e(base_url('assets/js/pill-nav.js')) ?>" defer></script>
</body>
</html>







