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

$user        = $_SESSION['user_id'] ?? null;
$role        = $_SESSION['role']    ?? null;
$listingUrl  = e(base_url('public/accommodation_listing.php'));
$loginUrl    = e(base_url('auth/login.php'));
$registerUrl = e(base_url('auth/register.php'));
$logoutUrl   = e(base_url('auth/logout.php'));
$dashUrl     = $role === 'owner'
    ? e(base_url('owner/dashboard.php'))
    : ($role === 'admin'
        ? e(base_url('admin/dashboard.php'))
        : e(base_url('traveler/dashboard.php')));
$logoUrl     = e(base_url('assets/images/logo-cropped-transparent.png'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Safari Tanzania â€” Premium Luxury Safari Experiences</title>
<meta name="description" content="Discover Tanzania's finest luxury safari experiences. Book premium lodges, wildlife tours, and exclusive adventures across the Serengeti, Zanzibar, and beyond.">
<link rel="stylesheet" href="<?= e(base_url('assets/css/premium.css')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/pill-nav.css')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body class="home-page">

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• NAVBAR â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<nav class="st-nav" id="mainNav">
  <div class="container nav-inner">
    <a href="<?= e(base_url('public/index.php')) ?>" class="nav-logo">
      <img src="<?= $logoUrl ?>" alt="Safari Tanzania" style="height:64px;">
    </a>
    <ul class="pill-nav" id="navLinks">
      <li class="pill-nav__cursor" aria-hidden="true"></li>
      <li class="pill-nav__item"><a href="<?= e(base_url('public/index.php')) ?>" class="pill-nav__link">Home</a></li>
      <li class="pill-nav__item"><a href="<?= $listingUrl ?>" class="pill-nav__link">Explore</a></li>
      <li class="pill-nav__item"><a href="#destinations" class="pill-nav__link">Destinations</a></li>
      <li class="pill-nav__item"><a href="#packages" class="pill-nav__link">Packages</a></li>
      <li class="pill-nav__item"><a href="#about" class="pill-nav__link">About</a></li>
      <li class="pill-nav__item"><a href="#contact" class="pill-nav__link">Contact</a></li>
    </ul>
    <div class="nav-actions">
      <?php if ($user): ?>
        <a href="<?= $dashUrl ?>" class="btn btn-outline">
          <span class="material-symbols-outlined" style="font-size:18px;">dashboard</span> Dashboard
        </a>
        <a href="<?= $logoutUrl ?>" class="nav-link">Logout</a>
      <?php else: ?>
        <a href="<?= $loginUrl ?>" class="nav-link" style="display:none" id="signInLink">Sign In</a>
        <a href="<?= $registerUrl ?>" class="btn btn-primary">Get Started</a>
      <?php endif; ?>
      <button class="mobile-toggle" id="mobileToggle" aria-label="Menu">
        <span class="material-symbols-outlined">menu</span>
      </button>
    </div>
  </div>
</nav>

<main class="estate-page">
  <section class="estate-hero" id="home">
    <div class="estate-hero-media">
      <img src="<?= e(base_url('assets/images/hero-bg.png')) ?>" alt="Luxury safari lodge living room">
      <div class="estate-hero-copy">
        <h1>Find Your Dream<br>Safari Stay Today</h1>
        <p>Discover verified lodges, villas, and camps across Tanzania with comfort, service, and unforgettable views.</p>
        <div class="estate-hero-actions">
          <a href="<?= $listingUrl ?>" class="estate-btn estate-btn-light">Show</a>
          <a href="#showcase" class="estate-btn estate-btn-ghost">Learn More</a>
        </div>
      </div>
    </div>
    <aside class="estate-intro-card">
      <h2>Who We Are?</h2>
      <p>Safari Tanzania helps travelers find premium stays and trusted hosts in the country's most beautiful places.</p>
      <div class="estate-stats">
        <div><strong>80+</strong><span>Premium Stays</span></div>
        <div><strong>500+</strong><span>Guest Reviews</span></div>
        <div><strong>2K+</strong><span>Happy Clients</span></div>
      </div>
    </aside>
  </section>

  <section class="estate-match" id="packages">
    <div class="estate-section-head">
      <h2>Discover Your Perfect<br><span>Safari Match</span></h2>
      <p>Browse selected safari homes, beach retreats, and wilderness lodges. Every stay is presented with clear details so travelers can compare options quickly and book with confidence.</p>
    </div>

    <?php
    $defaultCards = [
      ['Serengeti Migration Camp','Serengeti National Park',base_url('assets/images/hero-bg.png'),'930000','2,235 sq ft','5 Beds','2 Baths'],
      ['Zanzibar Ocean Villa','Zanzibar Coast','https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=900&q=85','780000','1,850 sq ft','4 Beds','3 Baths'],
      ['Ngorongoro Rim Lodge','Ngorongoro Crater','https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=900&q=85','860000','2,020 sq ft','6 Beds','4 Baths'],
      ['Arusha Garden Retreat','Arusha','https://images.unsplash.com/photo-1493246507139-91e8fad9978e?auto=format&fit=crop&w=900&q=85','520000','1,420 sq ft','3 Beds','2 Baths'],
    ];
    $cards = [];
    foreach ($featured as $a) {
      $cards[] = [
        $a['name'],
        $a['location'],
        $a['image_url'] ?: base_url('assets/images/hero-bg.png'),
        $a['from_price'] !== null ? number_format((float)$a['from_price'] * 1000, 0, '.', '') : '930000',
        'Premium suite',
        number_format((float)$a['rating'], 1),
        'Safari stay',
        (int)$a['id'],
      ];
    }
    while (count($cards) < 4) {
      $fallback = $defaultCards[count($cards)];
      $cards[] = [$fallback[0], $fallback[1], $fallback[2], $fallback[3], $fallback[4], $fallback[5], $fallback[6], 0];
    }
    ?>

    <div class="estate-property-grid">
      <?php foreach ($cards as $i => $card):
        [$name, $place, $img, $priceValue, $factOne, $factTwo, $factThree, $id] = $card;
        $url = $id > 0 ? e(base_url('public/accommodation_details.php?id=' . $id)) : $listingUrl;
      ?>
      <a href="<?= $url ?>" class="estate-property-card <?= $i === 0 ? 'estate-property-card-large' : '' ?>">
        <img src="<?= e($img) ?>" alt="<?= e($name) ?>">
        <span class="estate-save"><span class="material-symbols-outlined">favorite</span></span>
        <div class="estate-price-panel">
          <div>
            <strong>Tsh <?= e(number_format((float)$priceValue, 0)) ?></strong>
            <span><?= e($place) ?></span>
          </div>
          <span class="estate-arrow"><span class="material-symbols-outlined">arrow_forward</span></span>
          <ul>
            <li><?= e($factOne) ?></li>
            <li><?= e($factTwo) ?></li>
            <li><?= e($factThree) ?></li>
          </ul>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="estate-about" id="about">
    <div class="estate-about-copy">
      <h2>About <span>Us</span></h2>
      <p>We help guests discover Tanzania's most memorable stays, from safari camps near wildlife routes to calm coastal homes made for slow mornings.</p>
      <p>Our platform highlights trusted properties, useful location details, and clear booking paths so every trip feels easier to plan.</p>
    </div>
    <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=900&q=85" alt="Traveler consultation">
  </section>

  <section class="estate-showcase" id="showcase">
    <h2>Property Showcase</h2>
    <div class="estate-tabs">
      <a href="<?= $listingUrl ?>" class="active">Buy</a>
      <a href="<?= $listingUrl ?>?region=Serengeti+National+Park">Rent</a>
      <a href="<?= $listingUrl ?>?rating=4.5">Sale</a>
      <form action="<?= $listingUrl ?>" method="get">
        <input name="q" type="search" placeholder="Search by city or stay">
        <button type="submit"><span class="material-symbols-outlined">search</span></button>
      </form>
    </div>
    <div class="estate-showcase-row">
      <?php
      $showcase = [
        ['Arusha, TZ','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=700&q=85','1,128','3','2'],
        ['Serengeti, TZ','https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=700&q=85','2,226','4','3'],
        ['Zanzibar, TZ','https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=700&q=85','2,324','5','4'],
        ['Ngorongoro, TZ','https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=700&q=85','3,118','5','4'],
      ];
      foreach ($showcase as [$city,$image,$sqft,$beds,$baths]):
      ?>
      <a href="<?= $listingUrl ?>?q=<?= urlencode(strtok($city, ',')) ?>" class="estate-show-card">
        <img src="<?= e($image) ?>" alt="<?= e($city) ?>">
        <div>
          <h3><?= e($city) ?></h3>
          <p><span><?= e($sqft) ?> Sq. Ft</span><span><?= e($beds) ?> Beds</span><span><?= e($baths) ?> Baths</span></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="estate-dots"><span></span><span></span><span></span></div>
  </section>
</main>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• FOOTER â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<footer class="site-footer" id="contact">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="<?= e(base_url('public/index.php')) ?>" class="nav-logo" style="margin-bottom:8px;">
          <img src="<?= $logoUrl ?>" alt="Safari Tanzania" style="height:48px;">
        </a>
        <p>Dedicated to authentic and sustainable luxury travel experiences across the United Republic of Tanzania.</p>
        <div class="footer-social">
          <?php foreach (['public','alternate_email','photo_camera'] as $ico): ?>
          <a href="#"><span class="material-symbols-outlined" style="font-size:16px;"><?= $ico ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <div class="footer-title">Quick Links</div>
        <ul class="footer-links">
          <li><a href="<?= e(base_url('public/index.php')) ?>">Home</a></li>
          <li><a href="<?= $listingUrl ?>">Explore Stays</a></li>
          <li><a href="#about">About Us</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-title">Legal</div>
        <ul class="footer-links">
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">Booking Policy</a></li>
          <li><a href="#">Cookie Policy</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-title">Stay in the Loop</div>
        <p style="font-size:0.85rem;color:rgba(255,255,255,0.4);margin-bottom:16px;">Exclusive safari deals and travel inspiration.</p>
        <div class="footer-newsletter">
          <input type="email" placeholder="your@email.com">
          <button class="btn btn-primary" style="width:100%;justify-content:center;">
            <span class="material-symbols-outlined" style="font-size:16px;">send</span> Subscribe
          </button>
        </div>
        <p style="font-size:0.75rem;color:rgba(255,255,255,0.2);margin-top:8px;">No spam. Unsubscribe anytime.</p>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> Safari Tanzania. Preserving the Wild.</span>
      <span>Crafted with â¤ for Tanzania's wilderness</span>
    </div>
  </div>
</footer>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• SCRIPTS â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<script>
// Navbar scroll
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => {
  nav.classList.toggle('scrolled', window.scrollY > 60);
}, { passive: true });

// Reveal handled by animations.js

// Floating pills
setTimeout(() => {
  ['floatPill1','floatPill2'].forEach((id, i) => {
    const el = document.getElementById(id);
    if (el && window.innerWidth > 1024) {
      el.style.display = 'block';
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.8s ease';
      setTimeout(() => el.style.opacity = '1', 300 * i);
    }
  });
}, 1200);

// Parallax hero
const heroBg = document.querySelector('.hero-bg');
if (heroBg) {
  window.addEventListener('scroll', () => {
    heroBg.style.transform = `translateY(${window.scrollY * 0.35}px)`;
  }, { passive: true });
}

// Mobile menu
const toggle = document.getElementById('mobileToggle');
const links = document.getElementById('navLinks');
if (toggle) {
  toggle.addEventListener('click', () => {
    const open = links.style.display === 'flex';
    links.style.display = open ? 'none' : 'flex';
    links.style.flexDirection = 'column';
    links.style.position = 'absolute';
    links.style.top = '100%';
    links.style.left = '0';
    links.style.right = '0';
    links.style.background = 'rgba(255,255,255,0.98)';
    links.style.backdropFilter = 'blur(20px)';
    links.style.padding = '24px';
    links.style.gap = '16px';
    links.style.borderTop = '1px solid rgba(11,30,91,0.08)';
    links.style.borderRadius = '0 0 16px 16px';
    links.style.boxShadow = '0 16px 32px rgba(11,30,91,0.12)';
    toggle.querySelector('.material-symbols-outlined').textContent = open ? 'menu' : 'close';
  });
}
</script>
<script>
window.process = { env: { NODE_ENV: 'production' } };
</script>
<script src="<?= e(base_url('assets/js/animations.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/pill-nav.js')) ?>" defer></script>
</body>
</html>

