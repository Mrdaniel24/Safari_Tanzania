<?php
require_once __DIR__ . '/../config/db.php';

$featured = $pdo->query(
    "SELECT a.*, MIN(r.price) AS from_price
     FROM accommodations a
     LEFT JOIN rooms r ON r.accommodation_id = a.id
     WHERE a.status = 'approved'
     GROUP BY a.id
     ORDER BY a.rating DESC
     LIMIT 3"
)->fetchAll();

$user = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role']    ?? null;

$listingUrl = e(base_url('public/accommodation_listing.php'));
$loginUrl   = e(base_url('auth/login.php'));
$logoutUrl  = e(base_url('auth/logout.php'));
$dashUrl    = $role === 'owner'
    ? e(base_url('owner/dashboard.php'))
    : ($role === 'admin'
        ? e(base_url('admin/dashboard.php'))
        : e(base_url('traveler/dashboard.php')));
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>SAFARI TANZANIA — Discover &amp; Book Accommodations</title>
<meta name="description" content="Discover and book accommodations across Tanzania — luxury lodges, beach villas, and eco-camps with verified hosts and secure booking.">
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Plus Jakarta Sans', sans-serif; }
  .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
  .hero-bg {
    background-image: linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.55)),
      url('https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=1920&q=80');
    background-size: cover;
    background-position: center;
  }
</style>
</head>
<body class="bg-stone-50 text-stone-900">

<!-- HEADER -->
<header class="absolute top-0 left-0 right-0 z-20">
  <div class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between">
    <a href="<?= e(base_url('public/index.php')) ?>" class="flex items-center gap-2 text-white">
      <span class="material-symbols-outlined text-3xl">park</span>
      <span class="font-extrabold tracking-wide text-lg">SAFARI TANZANIA</span>
    </a>
    <nav class="hidden md:flex items-center gap-8 text-white/90 text-sm font-medium">
      <a href="<?= e(base_url('public/index.php')) ?>" class="hover:text-white">Home</a>
      <a href="<?= $listingUrl ?>" class="hover:text-white">Explore</a>
      <a href="<?= $listingUrl ?>" class="hover:text-white">Properties</a>
      <a href="#contact" class="hover:text-white">Contact</a>
      <?php if ($user): ?>
        <a href="<?= $dashUrl ?>" class="bg-white text-stone-900 px-4 py-2 rounded-full font-semibold hover:bg-stone-100">Dashboard</a>
        <a href="<?= $logoutUrl ?>" class="hover:text-white">Logout</a>
      <?php else: ?>
        <a href="<?= $loginUrl ?>" class="bg-white text-stone-900 px-4 py-2 rounded-full font-semibold hover:bg-stone-100">Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<!-- HERO -->
<section class="hero-bg min-h-[640px] flex items-center pt-24 pb-16">
  <div class="max-w-5xl mx-auto px-6 text-center text-white">
    <h1 class="text-4xl md:text-6xl font-extrabold leading-tight mb-5">
      Discover and Book Accommodations Across Tanzania
    </h1>
    <p class="text-lg md:text-xl text-white/90 max-w-3xl mx-auto mb-10">
      From luxury lodges in Ngorongoro to beachfront villas in Zanzibar, find your perfect Tanzanian escape with verified hosts and secure booking.
    </p>

    <form action="<?= $listingUrl ?>" method="get"
          class="bg-white rounded-2xl shadow-2xl p-4 md:p-3 max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-[1fr_1fr_1fr_auto] gap-2 md:gap-0 text-left">
      <label class="md:px-5 md:py-2 md:border-r border-stone-200">
        <span class="block text-xs font-bold text-stone-700 uppercase tracking-wider">Location</span>
        <div class="flex items-center gap-2 mt-1 text-stone-600">
          <span class="material-symbols-outlined text-lg">location_on</span>
          <input type="text" name="q" placeholder="Where to?" class="border-0 p-0 w-full focus:ring-0 text-sm text-stone-900 placeholder-stone-400">
        </div>
      </label>
      <label class="md:px-5 md:py-2 md:border-r border-stone-200">
        <span class="block text-xs font-bold text-stone-700 uppercase tracking-wider">Dates</span>
        <div class="flex items-center gap-2 mt-1 text-stone-600">
          <span class="material-symbols-outlined text-lg">calendar_today</span>
          <input type="text" name="dates" placeholder="Add dates" class="border-0 p-0 w-full focus:ring-0 text-sm text-stone-900 placeholder-stone-400">
        </div>
      </label>
      <label class="md:px-5 md:py-2">
        <span class="block text-xs font-bold text-stone-700 uppercase tracking-wider">Guests</span>
        <div class="flex items-center gap-2 mt-1 text-stone-600">
          <span class="material-symbols-outlined text-lg">group</span>
          <input type="number" min="1" name="guests" placeholder="Add guests" class="border-0 p-0 w-full focus:ring-0 text-sm text-stone-900 placeholder-stone-400">
        </div>
      </label>
      <button type="submit"
              class="bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl px-8 py-4 md:my-1 md:mx-1 transition">
        Search
      </button>
    </form>
  </div>
</section>

<!-- FEATURED -->
<section class="max-w-7xl mx-auto px-6 py-20">
  <div class="flex items-end justify-between mb-10 flex-wrap gap-4">
    <div>
      <h2 class="text-3xl md:text-4xl font-extrabold text-stone-900">Featured Accommodations</h2>
      <p class="text-stone-600 mt-2">Hand-picked premium stays for an unforgettable journey.</p>
    </div>
    <a href="<?= $listingUrl ?>" class="inline-flex items-center gap-2 text-amber-600 font-semibold hover:text-amber-700">
      View all stays <span class="material-symbols-outlined">arrow_forward</span>
    </a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <?php if (empty($featured)): ?>
      <p class="text-stone-500 col-span-3">No featured stays yet. Check back soon.</p>
    <?php endif; ?>
    <?php foreach ($featured as $a): ?>
      <article class="group rounded-2xl overflow-hidden bg-white shadow-sm hover:shadow-xl transition">
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
          <div class="flex items-center justify-between mt-4">
            <p class="text-stone-900">
              <?php if (!is_null($a['from_price'])): ?>
                <span class="font-extrabold text-xl">$<?= e(number_format((float)$a['from_price'], 0)) ?></span>
                <span class="text-stone-500 text-sm">/ night</span>
              <?php else: ?>
                <span class="text-stone-500 text-sm">Price on request</span>
              <?php endif; ?>
            </p>
            <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$a['id'])) ?>"
               class="text-sm font-semibold text-amber-600 hover:text-amber-700">View Details →</a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="bg-white py-20">
  <div class="max-w-7xl mx-auto px-6">
    <div class="text-center mb-14">
      <h2 class="text-3xl md:text-4xl font-extrabold">Your Journey Starts Here</h2>
      <p class="text-stone-600 mt-3">Three simple steps to book your dream Tanzanian safari accommodation.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <?php
      $steps = [
        ['search', 'Search', "Browse through hundreds of hand-verified properties across Tanzania's most iconic destinations."],
        ['compare_arrows', 'Compare', "Filter by price, amenities, and proximity to wildlife hotspots to find your perfect match."],
        ['task_alt', 'Book', "Complete your booking with our secure payment gateway and get instant confirmation."],
      ];
      foreach ($steps as $s): ?>
        <div class="text-center p-6 rounded-2xl">
          <div class="mx-auto w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-5">
            <span class="material-symbols-outlined" style="font-size:32px;"><?= e($s[0]) ?></span>
          </div>
          <h3 class="font-bold text-xl mb-2"><?= e($s[1]) ?></h3>
          <p class="text-stone-600"><?= e($s[2]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- TRUST -->
<section class="max-w-7xl mx-auto px-6 py-20">
  <div class="text-center mb-14">
    <h2 class="text-3xl md:text-4xl font-extrabold">Why Travellers Trust Safari Tanzania</h2>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
    <?php
    $trust = [
      ['verified_user', 'Secure Booking',  'Protected transactions with global security standards.'],
      ['house',         'Verified Listings', 'Every property is personally inspected by our team.'],
      ['map',           'Interactive Map', 'Find stays right next to the park gates or beaches.'],
      ['payments',      'Best Price Promise', 'No hidden fees, just the best rates direct from hosts.'],
    ];
    foreach ($trust as $t): ?>
      <div class="bg-white rounded-2xl p-6 border border-stone-100 hover:border-amber-300 transition">
        <span class="material-symbols-outlined text-amber-600" style="font-size:32px;"><?= e($t[0]) ?></span>
        <h4 class="font-bold mt-3"><?= e($t[1]) ?></h4>
        <p class="text-sm text-stone-600 mt-1"><?= e($t[2]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- TESTIMONIAL BANNER -->
<section class="relative">
  <div class="hero-bg" style="min-height:380px;">
    <div class="max-w-4xl mx-auto px-6 py-24 text-center text-white">
      <p class="text-2xl md:text-3xl font-semibold leading-relaxed">
        "Safari Tanzania made our honeymoon planning effortless. We found a hidden gem in Zanzibar that we couldn't find anywhere else."
      </p>
      <p class="mt-6 font-bold tracking-wide">— SARAH JENKINS</p>
      <a href="<?= $listingUrl ?>" class="inline-block mt-8 bg-amber-500 hover:bg-amber-600 px-8 py-3 rounded-full font-bold">
        Explore Map View
      </a>
    </div>
  </div>
</section>

<!-- STORIES -->
<section class="max-w-7xl mx-auto px-6 py-20">
  <div class="text-center mb-14">
    <h2 class="text-3xl md:text-4xl font-extrabold">Stories from the Wild</h2>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <?php
    $stories = [
      ['"The level of detail in the accommodation listings is unmatched. We knew exactly what to expect at our Ngorongoro camp, from the views to the service."', 'Michael Rossi', 'Frequent Traveller'],
      ['"Booking through Safari Tanzania gave us peace of mind. Their support team helped us modify our dates when our flights changed last minute."', 'Elena Petrova', 'Wildlife Photographer'],
    ];
    foreach ($stories as $st): ?>
      <div class="bg-white rounded-2xl p-8 shadow-sm border border-stone-100">
        <div class="flex text-amber-500 mb-3">
          <?php for ($i=0;$i<5;$i++): ?><span class="material-symbols-outlined" style="font-size:20px;">star</span><?php endfor; ?>
        </div>
        <p class="text-stone-700 italic"><?= e($st[0]) ?></p>
        <div class="mt-5">
          <p class="font-bold"><?= e($st[1]) ?></p>
          <p class="text-sm text-stone-500"><?= e($st[2]) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FOOTER -->
<footer id="contact" class="bg-stone-900 text-stone-300 pt-16 pb-8">
  <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-10">
    <div class="col-span-2 md:col-span-1">
      <div class="flex items-center gap-2 text-white">
        <span class="material-symbols-outlined text-3xl">park</span>
        <span class="font-extrabold tracking-wide">SAFARI TANZANIA</span>
      </div>
      <p class="text-sm mt-4 text-stone-400">
        Dedicated to providing authentic and sustainable travel experiences across the United Republic of Tanzania.
      </p>
      <div class="flex gap-3 mt-5 text-stone-400">
        <span class="material-symbols-outlined">public</span>
        <span class="material-symbols-outlined">alternate_email</span>
      </div>
    </div>

    <div>
      <h5 class="font-bold text-white mb-4">Quick Links</h5>
      <ul class="space-y-2 text-sm">
        <li><a href="<?= e(base_url('public/index.php')) ?>" class="hover:text-white">Home</a></li>
        <li><a href="<?= $listingUrl ?>" class="hover:text-white">Explore</a></li>
        <li><a href="#" class="hover:text-white">About Us</a></li>
        <li><a href="#contact" class="hover:text-white">Contact</a></li>
      </ul>
    </div>

    <div>
      <h5 class="font-bold text-white mb-4">Legal</h5>
      <ul class="space-y-2 text-sm">
        <li><a href="#" class="hover:text-white">Privacy Policy</a></li>
        <li><a href="#" class="hover:text-white">Terms of Service</a></li>
        <li><a href="#" class="hover:text-white">Booking Policy</a></li>
      </ul>
    </div>

    <div>
      <h5 class="font-bold text-white mb-4">Newsletter</h5>
      <p class="text-sm text-stone-400 mb-3">Join our wild list for exclusive deals.</p>
      <form class="flex">
        <input type="email" placeholder="Email address"
               class="flex-1 rounded-l-md border-0 bg-stone-800 text-white text-sm px-3 py-2 focus:ring-amber-500">
        <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-4 rounded-r-md">
          <span class="material-symbols-outlined" style="font-size:20px;">send</span>
        </button>
      </form>
    </div>
  </div>
  <div class="max-w-7xl mx-auto px-6 mt-12 pt-6 border-t border-stone-800 text-center text-sm text-stone-500">
    &copy; <?= date('Y') ?> SAFARI TANZANIA. Preserving the Wild.
  </div>
</footer>

</body>
</html>
