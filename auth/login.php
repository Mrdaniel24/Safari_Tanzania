<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/security.php';

$errors = [];
$email_old = '';
$remember = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    // Rate limiting: max 5 login attempts per 60 seconds per IP
    $remaining = rate_limit_check('login');
    if ($remaining <= 0) {
        $errors[] = 'Too many login attempts. Please wait 60 seconds and try again.';
    }

    if (!$errors) {
        $email     = trim(strtolower($_POST['email'] ?? ''));
        $password  = $_POST['password'] ?? '';
        $remember  = !empty($_POST['remember']);
        $email_old = $email;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $errors[] = 'Enter a valid email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid credentials.';
                rate_limit_tick('login');
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'Your account is suspended.';
                rate_limit_tick('login');
            } else {
                rate_limit_clear('login');
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['_last_activity'] = time();

                if ($remember) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), session_id(), [
                        'expires'  => time() + 60 * 60 * 24 * 30,
                        'path'     => $params['path'],
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }

                switch ($user['role']) {
                    case 'owner':  redirect('owner/dashboard.php');
                    case 'admin':  redirect('admin/dashboard.php');
                    case 'worker': redirect('worker/dashboard.php');
                    default:       redirect('traveler/dashboard.php');
                }
            }
        }
    }
}

$listingUrl = e(base_url('public/accommodation_listing.php'));
$homeUrl    = e(base_url('public/index.php'));
$registerUrl = e(base_url('auth/register.php'));
$logoUrl    = e(base_url('assets/images/logo-cropped-transparent.png'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Login - Safari Tanzania</title>
<link rel="stylesheet" href="<?= e(base_url('assets/css/premium.css')) ?>">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
.auth-page {
  min-height: 100vh;
  background: var(--dark);
  color: var(--white);
  display: flex;
  flex-direction: column;
}
.auth-nav {
  position: relative;
  z-index: 5;
  background: rgba(6,14,42,.94);
  border-bottom: 1px solid rgba(30,198,255,.12);
  box-shadow: 0 4px 30px rgba(0,0,0,.25);
}
.auth-nav .nav-logo img {
  height: 58px;
  width: auto;
  max-width: 190px;
  object-fit: contain;
  filter: drop-shadow(0 8px 18px rgba(0,0,0,.22));
}
.auth-shell {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(420px, .95fr);
  min-height: calc(100vh - 91px);
}
.auth-visual {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  padding: 72px clamp(32px, 6vw, 88px);
  background:
    linear-gradient(135deg, rgba(6,14,42,.92), rgba(11,30,91,.62)),
    url('https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=1800&q=85') center/cover no-repeat;
}
.auth-visual::after {
  content: '';
  position: absolute;
  inset: auto 0 0 0;
  height: 34%;
  background: linear-gradient(0deg, rgba(6,14,42,.95), transparent);
}
.auth-visual-content {
  position: relative;
  z-index: 2;
  max-width: 620px;
}
.auth-kicker {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--cyan);
  font: 800 .78rem/1 'Montserrat', sans-serif;
  letter-spacing: 3px;
  text-transform: uppercase;
  margin-bottom: 20px;
}
.auth-kicker::before {
  content: '';
  width: 34px;
  height: 2px;
  border-radius: 2px;
  background: var(--gradient-btn);
}
.auth-visual h1 {
  font-size: clamp(2.75rem, 6vw, 5.25rem);
  line-height: 1.02;
  letter-spacing: -1.8px;
  font-weight: 900;
  margin-bottom: 24px;
}
.auth-visual p {
  max-width: 540px;
  color: rgba(245,247,250,.72);
  font-size: 1.08rem;
}
.auth-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 34px;
}
.auth-pill {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  padding: 10px 16px;
  border-radius: 999px;
  background: rgba(11,30,91,.48);
  border: 1px solid rgba(30,198,255,.16);
  color: rgba(255,255,255,.88);
  backdrop-filter: blur(14px);
  font-weight: 700;
  font-size: .86rem;
}
.auth-panel-wrap {
  background: var(--white);
  color: var(--navy);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 56px clamp(24px, 5vw, 72px);
}
.auth-card {
  width: 100%;
  max-width: 470px;
}
.auth-card h2 {
  font-size: clamp(2rem, 4vw, 2.8rem);
  font-weight: 900;
  letter-spacing: -.8px;
  margin-bottom: 8px;
}
.auth-sub { color: #6b7b99; }
.auth-alert {
  margin-top: 18px;
  padding: 13px 15px;
  border-radius: 14px;
  display: flex;
  gap: 10px;
  align-items: flex-start;
  font-size: .9rem;
}
.auth-alert-error { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
.auth-alert-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.auth-form { margin-top: 28px; display: grid; gap: 18px; }
.auth-field label {
  display: block;
  font-size: .82rem;
  font-weight: 800;
  color: rgba(11,30,91,.82);
  margin-bottom: 8px;
}
.auth-input-wrap { position: relative; }
.auth-input-wrap .material-symbols-outlined {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7b99;
  font-size: 20px;
}
.auth-input {
  width: 100%;
  border: 1px solid rgba(11,30,91,.13);
  background: #fff;
  color: var(--navy);
  border-radius: 15px;
  padding: 14px 14px 14px 46px;
  outline: none;
  font: 600 .95rem/1.2 'Inter', sans-serif;
}
.auth-input:focus {
  border-color: var(--cyan);
  box-shadow: 0 0 0 4px rgba(30,198,255,.13);
}
.auth-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  color: #6b7b99;
  font-size: .9rem;
}
.auth-row a { color: var(--blue); font-weight: 800; }
.auth-check { display: inline-flex; align-items: center; gap: 9px; }
.auth-check input { width: 17px; height: 17px; accent-color: #0F7BD9; }
.auth-submit {
  width: 100%;
  justify-content: center;
  padding: 15px 24px;
  font-size: .95rem;
}
.auth-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #93a1bd;
  font-size: .76rem;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
}
.auth-divider::before,
.auth-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(11,30,91,.12);
}
.auth-google {
  width: 100%;
  border: 1px solid rgba(11,30,91,.13);
  background: #fff;
  color: #6b7b99;
  border-radius: 15px;
  padding: 14px;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
  font-weight: 800;
  opacity: .7;
}
.auth-switch { text-align: center; color: #6b7b99; font-size: .92rem; }
.auth-switch a { color: var(--blue); font-weight: 900; }
.auth-footer {
  background: var(--dark);
  border-top: 1px solid rgba(30,198,255,.08);
  color: rgba(255,255,255,.35);
  font-size: .85rem;
}
.auth-footer-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.auth-footer img { height: 38px; width: auto; max-width: 150px; object-fit: contain; }
@media (max-width: 980px) {
  .auth-shell { grid-template-columns: 1fr; }
  .auth-visual { min-height: 420px; }
  .auth-panel-wrap { padding-top: 44px; }
}
@media (max-width: 640px) {
  .auth-nav .nav-logo img { height: 46px; }
  .auth-visual { padding: 52px 24px; min-height: 380px; }
  .auth-row { align-items: flex-start; flex-direction: column; gap: 10px; }
  .auth-footer-inner { flex-direction: column; text-align: center; }
}
</style>
<link rel="stylesheet" href="<?= e(base_url('assets/css/premium.css')) ?>?v=estate-connected">
</head>
<body class="auth-page">
<header class="auth-nav">
  <div class="container nav-inner" style="padding-top:14px;padding-bottom:14px;">
    <a href="<?= $homeUrl ?>" class="nav-logo">
      <img src="<?= $logoUrl ?>" alt="Safari Tanzania">
    </a>
    <nav class="nav-links">
      <a href="<?= $listingUrl ?>" class="nav-link">Explore</a>
      <a href="<?= e(base_url('public/index.php#destinations')) ?>" class="nav-link">Destinations</a>
      <a href="<?= $registerUrl ?>" class="btn btn-primary">Sign Up</a>
    </nav>
  </div>
</header>

<main class="auth-shell">
  <section class="auth-visual">
    <div class="auth-visual-content">
      <div class="auth-kicker">Welcome back</div>
      <h1>Return to the <span class="text-gradient">wild beauty</span> of Tanzania</h1>
      <p>Log in to manage bookings, explore verified stays, and continue planning your next safari experience.</p>
      <div class="auth-pills">
        <span class="auth-pill"><span class="material-symbols-outlined" style="font-size:18px;color:var(--cyan);">verified</span> Verified stays</span>
        <span class="auth-pill"><span class="material-symbols-outlined" style="font-size:18px;color:var(--gold);">star</span> Premium support</span>
      </div>
    </div>
  </section>

  <section class="auth-panel-wrap">
    <div class="auth-card">
      <h2>Sign In</h2>
      <p class="auth-sub">Enter your details to continue your journey.</p>

      <?php foreach ($errors as $err): ?>
        <div class="auth-alert auth-alert-error">
          <span class="material-symbols-outlined" style="font-size:18px;">error</span>
          <span><?= e($err) ?></span>
        </div>
      <?php endforeach; ?>

      <?php foreach (flash_pull() as $f): ?>
        <div class="auth-alert auth-alert-success"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>

      <form method="post" class="auth-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <div class="auth-field">
          <label for="email">Email Address</label>
          <div class="auth-input-wrap">
            <span class="material-symbols-outlined">mail</span>
            <input id="email" class="auth-input" type="email" name="email" required value="<?= e($email_old) ?>" placeholder="mail@example.com">
          </div>
        </div>

        <div class="auth-field">
          <label for="password">Password</label>
          <div class="auth-input-wrap">
            <span class="material-symbols-outlined">lock</span>
            <input id="password" class="auth-input" type="password" name="password" required>
          </div>
        </div>

        <div class="auth-row">
          <label class="auth-check">
            <input type="checkbox" name="remember" <?= $remember ? 'checked' : '' ?>>
            Remember me for 30 days
          </label>
          <a href="#">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary auth-submit">Login to Safari Tanzania</button>

        <div class="auth-divider">or</div>

        <button type="button" disabled class="auth-google">
          <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.6l6.2 5.2C40.9 35.5 44 30.2 44 24c0-1.3-.1-2.3-.4-3.5z"/></svg>
          Continue with Google
        </button>

        <p class="auth-switch">Don't have an account? <a href="<?= $registerUrl ?>">Create Account</a></p>
      </form>
    </div>
  </section>
</main>

<footer class="auth-footer">
  <div class="auth-footer-inner">
    <img src="<?= $logoUrl ?>" alt="Safari Tanzania">
    <p>&copy; <?= date('Y') ?> Safari Tanzania. Preserving the Wild.</p>
  </div>
</footer>
</body>
</html>


