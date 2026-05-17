<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'role' => 'traveler', 'property_type' => '', 'government_recognized' => '', 'legal_owner' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim(strtolower($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm']  ?? '';
    $role      = ($_POST['role'] ?? 'traveler') === 'owner' ? 'owner' : 'traveler';
    $terms     = !empty($_POST['terms']);
    $property_type = $_POST['property_type'] ?? '';
    $government_recognized = $_POST['government_recognized'] ?? '';
    $legal_owner = $_POST['legal_owner'] ?? '';
    $old = compact('full_name', 'email', 'phone', 'role', 'property_type', 'government_recognized', 'legal_owner');

    if ($full_name === '' || mb_strlen($full_name) > 100) $errors[] = 'Full name is required (max 100 chars).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))       $errors[] = 'Please enter a valid email.';
    if ($phone !== '' && !preg_match('/^[0-9 +\-]{6,20}$/', $phone)) $errors[] = 'Phone format looks invalid.';
    if (strlen($password) < 8)                            $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)                           $errors[] = 'Passwords do not match.';
    if (!$terms)                                          $errors[] = 'You must agree to the Terms of Service.';

    if ($role === 'owner') {
        $validPropertyTypes = ['guest_house', 'lodge', 'hotel'];
        if (!in_array($property_type, $validPropertyTypes, true)) {
            $errors[] = 'Please choose the type of accommodation you own.';
        }
        if ($government_recognized !== 'yes') {
            $errors[] = 'Owner accounts require a government-recognized accommodation.';
        }
        if ($legal_owner !== 'yes') {
            $errors[] = 'Owner accounts require legal ownership confirmation.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, phone, password, role, status)
                 VALUES (?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([$full_name, $email, $phone ?: null, $hash, $role]);
            flash_set('success', 'Account created. Please log in.');
            redirect('auth/login.php');
        }
    }
}

$listingUrl = e(base_url('public/accommodation_listing.php'));
$homeUrl    = e(base_url('public/index.php'));
$loginUrl   = e(base_url('auth/login.php'));
$logoUrl    = e(base_url('assets/images/logo-cropped-transparent.png'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Create Account - Safari Tanzania</title>
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
  grid-template-columns: minmax(0, 1.02fr) minmax(460px, .98fr);
  min-height: calc(100vh - 91px);
}
.auth-visual {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  padding: 72px clamp(32px, 6vw, 88px);
  background:
    linear-gradient(135deg, rgba(6,14,42,.90), rgba(11,30,91,.58)),
    url('https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?auto=format&fit=crop&w=1800&q=85') center/cover no-repeat;
}
.auth-visual::after {
  content: '';
  position: absolute;
  inset: auto 0 0 0;
  height: 36%;
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
  font-size: clamp(2.65rem, 6vw, 5rem);
  line-height: 1.03;
  letter-spacing: -1.7px;
  font-weight: 900;
  margin-bottom: 24px;
}
.auth-visual p {
  max-width: 560px;
  color: rgba(245,247,250,.72);
  font-size: 1.08rem;
}
.auth-benefits {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-top: 34px;
  max-width: 520px;
}
.auth-benefit {
  padding: 18px;
  border-radius: 18px;
  background: rgba(11,30,91,.48);
  border: 1px solid rgba(30,198,255,.16);
  backdrop-filter: blur(14px);
  font-weight: 600;
}
.auth-benefit .material-symbols-outlined { color: var(--cyan); margin-bottom: 8px; }
.auth-panel-wrap {
  background: var(--white);
  color: var(--navy);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 44px clamp(24px, 5vw, 72px);
}
.auth-card { width: 100%; max-width: 520px; }
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
  background: #fff1f2;
  color: #9f1239;
  border: 1px solid #fecdd3;
}
.auth-form { margin-top: 24px; display: grid; gap: 16px; }
.auth-field label,
.role-label {
  display: block;
  font-size: .82rem;
  font-weight: 800;
  color: rgba(11,30,91,.82);
  margin-bottom: 8px;
}
.role-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.role-tab input { display: none; }
.role-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: 1px solid rgba(11,30,91,.13);
  border-radius: 16px;
  padding: 13px 16px;
  color: var(--navy);
  background: #fff;
  font-weight: 900;
  cursor: pointer;
  transition: .22s ease;
}
.role-tab input:checked + .role-card {
  background: var(--gradient-btn);
  border-color: transparent;
  color: #fff;
  box-shadow: var(--shadow-blue);
}
.owner-gate {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 22px;
  background: rgba(3, 10, 31, .72);
  backdrop-filter: blur(10px) saturate(120%);
}
.owner-gate.is-open { display: flex; }
.owner-modal {
  width: min(520px, 100%);
  border: 1px solid rgba(30,198,255,.26);
  border-radius: 24px;
  background: linear-gradient(180deg, #ffffff, #f6fbff);
  box-shadow: 0 28px 80px rgba(0,0,0,.38);
  color: var(--navy);
  overflow: hidden;
  transform: translateY(14px) scale(.97);
  opacity: 0;
  animation: ownerModalIn .32s ease forwards;
}
.owner-modal-head {
  padding: 22px 24px 16px;
  border-bottom: 1px solid rgba(11,30,91,.08);
}
.owner-gate-title {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--navy);
  font: 700 .95rem/1.35 'Inter', sans-serif;
  margin-bottom: 12px;
}
.owner-progress {
  height: 9px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(11,30,91,.10);
}
.owner-progress span {
  display: block;
  width: 0%;
  height: 100%;
  border-radius: inherit;
  background: var(--gradient-btn);
  box-shadow: 0 0 18px rgba(30,198,255,.55);
  transition: width .36s ease;
}
.owner-step-count {
  margin-top: 10px;
  color: rgba(11,30,91,.58);
  font-size: .83rem;
  font-weight: 600;
}
.owner-modal-body { padding: 24px; }
.owner-step {
  display: none;
  animation: ownerQuestionIn .28s ease both;
}
.owner-step.is-active { display: block; }
.owner-question > span {
  display: block;
  color: var(--navy);
  font: 700 1.04rem/1.45 'Inter', sans-serif;
  margin-bottom: 16px;
}
.owner-choice-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.owner-choice-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.owner-choice input { display: none; }
.owner-choice span {
  min-height: 54px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(11,30,91,.13);
  border-radius: 16px;
  background: #fff;
  color: var(--navy);
  font-weight: 650;`r`n  letter-spacing: 0;
  text-align: center;
  padding: 12px;
  cursor: pointer;
  transition: .2s ease;
}
.owner-choice span:hover {
  border-color: rgba(15,123,217,.42);
  box-shadow: 0 12px 28px rgba(11,30,91,.10);
}
.owner-choice input:checked + span {
  background: var(--gradient-btn);
  color: #fff;
  border-color: transparent;
  box-shadow: var(--shadow-blue);
}
.owner-note {
  margin: 18px 24px 24px;
  padding: 12px 14px;
  border-radius: 16px;
  background: rgba(30,198,255,.09);
  color: rgba(11,30,91,.68);
  font-size: .84rem;
  line-height: 1.55;
}
body.owner-wizard-lock { overflow: hidden; }
@keyframes ownerModalIn {
  to { transform: translateY(0) scale(1); opacity: 1; }
}
@keyframes ownerQuestionIn {
  from { transform: translateX(18px); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
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
.auth-input.no-icon { padding-left: 14px; }
.auth-input:focus {
  border-color: var(--cyan);
  box-shadow: 0 0 0 4px rgba(30,198,255,.13);
}
.auth-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.auth-check {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  color: #6b7b99;
  font-size: .88rem;
  line-height: 1.55;
}
.auth-check input { margin-top: 4px; width: 17px; height: 17px; accent-color: #0F7BD9; }
.auth-check a { color: var(--blue); font-weight: 900; }
.auth-submit {
  width: 100%;
  justify-content: center;
  padding: 15px 24px;
  font-size: .95rem;
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
  .auth-visual { min-height: 430px; }
}
@media (max-width: 640px) {
  .auth-nav .nav-logo img { height: 46px; }
  .auth-visual { padding: 52px 24px; min-height: 390px; }
  .auth-benefits, .auth-grid-2, .role-grid, .owner-choice-grid, .owner-choice-grid.two { grid-template-columns: 1fr; }
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
      <a href="<?= e(base_url('public/index.php#packages')) ?>" class="nav-link">Packages</a>
      <a href="<?= $loginUrl ?>" class="btn btn-primary">Login</a>
    </nav>
  </div>
</header>

<main class="auth-shell">
  <section class="auth-visual">
    <div class="auth-visual-content">
      <div class="auth-kicker">Join the adventure</div>
      <h1>Begin your journey into <span class="text-gradient">Tanzania's wild</span></h1>
      <p>Create an account to book verified stays, save your safari plans, and manage travel details in one place.</p>
      <div class="auth-benefits">
        <div class="auth-benefit"><span class="material-symbols-outlined">travel_explore</span><br>Curated Experiences</div>
        <div class="auth-benefit"><span class="material-symbols-outlined">support_agent</span><br>Expert Support</div>
      </div>
    </div>
  </section>

  <section class="auth-panel-wrap">
    <div class="auth-card">
      <h2>Create Account</h2>
      <p class="auth-sub">Fill in your details to get started.</p>

      <?php foreach ($errors as $err): ?>
        <div class="auth-alert">
          <span class="material-symbols-outlined" style="font-size:18px;">error</span>
          <span><?= e($err) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="post" class="auth-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <div>
          <span class="role-label">I am a...</span>
          <div class="role-grid">
            <label class="role-tab">
              <input type="radio" name="role" value="traveler" <?= $old['role'] === 'traveler' ? 'checked' : '' ?>>
              <div class="role-card"><span class="material-symbols-outlined">person</span> Traveler</div>
            </label>
            <label class="role-tab">
              <input type="radio" name="role" value="owner" <?= $old['role'] === 'owner' ? 'checked' : '' ?>>
              <div class="role-card"><span class="material-symbols-outlined">domain</span> Owner</div>
            </label>
          </div>
        </div>
        <div class="owner-gate" id="ownerGate" aria-hidden="true">
          <div class="owner-modal" role="dialog" aria-modal="true" aria-labelledby="ownerGateTitle">
            <div class="owner-modal-head">
              <div class="owner-gate-title" id="ownerGateTitle"><span class="material-symbols-outlined">verified_user</span> Owner quick check</div>
              <div class="owner-progress" aria-hidden="true"><span id="ownerProgressBar"></span></div>
              <div class="owner-step-count" id="ownerStepCount">Step 1 of 3</div>
            </div>
            <div class="owner-modal-body">
              <div class="owner-step owner-question is-active" data-step="0">
                <span>Which one do you own?</span>
                <div class="owner-choice-grid">
                  <label class="owner-choice"><input type="radio" name="property_type" value="guest_house" <?= $old['property_type'] === 'guest_house' ? 'checked' : '' ?>><span>Guest House</span></label>
                  <label class="owner-choice"><input type="radio" name="property_type" value="lodge" <?= $old['property_type'] === 'lodge' ? 'checked' : '' ?>><span>Lodge</span></label>
                  <label class="owner-choice"><input type="radio" name="property_type" value="hotel" <?= $old['property_type'] === 'hotel' ? 'checked' : '' ?>><span>Hotel</span></label>
                </div>
              </div>
              <div class="owner-step owner-question" data-step="1">
                <span>Is it recognized by the government?</span>
                <div class="owner-choice-grid two">
                  <label class="owner-choice"><input type="radio" name="government_recognized" value="yes" <?= $old['government_recognized'] === 'yes' ? 'checked' : '' ?>><span>Yes</span></label>
                  <label class="owner-choice"><input type="radio" name="government_recognized" value="no" <?= $old['government_recognized'] === 'no' ? 'checked' : '' ?>><span>No</span></label>
                </div>
              </div>
              <div class="owner-step owner-question" data-step="2">
                <span>Are you the legal owner?</span>
                <div class="owner-choice-grid two">
                  <label class="owner-choice"><input type="radio" name="legal_owner" value="yes" <?= $old['legal_owner'] === 'yes' ? 'checked' : '' ?>><span>Yes</span></label>
                  <label class="owner-choice"><input type="radio" name="legal_owner" value="no" <?= $old['legal_owner'] === 'no' ? 'checked' : '' ?>><span>No</span></label>
                </div>
              </div>
            </div>
            <p class="owner-note">You will complete full business details and document upload later inside profile verification.</p>
          </div>
        </div>

        <div class="auth-field">
          <label for="full_name">Full Name</label>
          <div class="auth-input-wrap">
            <span class="material-symbols-outlined">badge</span>
            <input id="full_name" class="auth-input" type="text" name="full_name" maxlength="100" required value="<?= e($old['full_name']) ?>">
          </div>
        </div>

        <div class="auth-field">
          <label for="email">Email Address</label>
          <div class="auth-input-wrap">
            <span class="material-symbols-outlined">mail</span>
            <input id="email" class="auth-input" type="email" name="email" maxlength="150" required value="<?= e($old['email']) ?>" placeholder="mail@example.com">
          </div>
        </div>

        <div class="auth-field">
          <label for="phone">Phone Number</label>
          <div class="auth-input-wrap">
            <span class="material-symbols-outlined">call</span>
            <input id="phone" class="auth-input" type="text" name="phone" maxlength="20" value="<?= e($old['phone']) ?>" placeholder="+255 ...">
          </div>
        </div>

        <div class="auth-grid-2">
          <div class="auth-field">
            <label for="password">Password</label>
            <input id="password" class="auth-input no-icon" type="password" name="password" minlength="8" required>
          </div>
          <div class="auth-field">
            <label for="confirm">Confirm</label>
            <input id="confirm" class="auth-input no-icon" type="password" name="confirm" minlength="8" required>
          </div>
        </div>

        <label class="auth-check">
          <input type="checkbox" name="terms">
          <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>, acknowledging the conservation efforts of Safari Tanzania.</span>
        </label>

        <button type="submit" class="btn btn-primary auth-submit">Create Account</button>

        <p class="auth-switch">Already have an account? <a href="<?= $loginUrl ?>">Login here</a></p>
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
<script>
(function () {
  const ownerGate = document.getElementById('ownerGate');
  const progressBar = document.getElementById('ownerProgressBar');
  const stepCount = document.getElementById('ownerStepCount');
  const roleInputs = document.querySelectorAll('input[name="role"]');
  const ownerInputs = ownerGate ? Array.from(ownerGate.querySelectorAll('input')) : [];
  const steps = ownerGate ? Array.from(ownerGate.querySelectorAll('.owner-step')) : [];
  const questionNames = ['property_type', 'government_recognized', 'legal_owner'];
  let currentStep = 0;

  function isOwnerSelected() {
    const selectedRole = document.querySelector('input[name="role"]:checked');
    return selectedRole && selectedRole.value === 'owner';
  }

  function hasCompletedQuestions() {
    return questionNames.every((name) => document.querySelector('input[name="' + name + '"]:checked'));
  }

  function setOwnerInputsActive(isOwner) {
    ownerInputs.forEach((input) => {
      input.required = isOwner;
      input.disabled = !isOwner;
    });
  }

  function showStep(index) {
    currentStep = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, i) => step.classList.toggle('is-active', i === currentStep));
    const progress = steps.length ? ((currentStep + 1) / steps.length) * 100 : 100;
    if (progressBar) progressBar.style.width = progress + '%';
    if (stepCount) stepCount.textContent = 'Step ' + (currentStep + 1) + ' of ' + steps.length;
  }

  function openOwnerWizard() {
    if (!ownerGate) return;
    setOwnerInputsActive(true);
    ownerGate.classList.add('is-open');
    ownerGate.setAttribute('aria-hidden', 'false');
    document.body.classList.add('owner-wizard-lock');
    const firstUnanswered = questionNames.findIndex((name) => !document.querySelector('input[name="' + name + '"]:checked'));
    showStep(firstUnanswered === -1 ? steps.length - 1 : firstUnanswered);
  }

  function closeOwnerWizard() {
    if (!ownerGate) return;
    ownerGate.classList.remove('is-open');
    ownerGate.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('owner-wizard-lock');
  }

  function syncOwnerGate() {
    const isOwner = isOwnerSelected();
    setOwnerInputsActive(isOwner);
    if (isOwner && !hasCompletedQuestions()) {
      openOwnerWizard();
    }
    if (!isOwner) {
      closeOwnerWizard();
    }
  }

  ownerInputs.forEach((input) => {
    input.addEventListener('change', () => {
      window.setTimeout(() => {
        if (currentStep < steps.length - 1) {
          showStep(currentStep + 1);
        } else if (hasCompletedQuestions()) {
          closeOwnerWizard();
        }
      }, 220);
    });
  });

  roleInputs.forEach((input) => input.addEventListener('change', syncOwnerGate));
  syncOwnerGate();
})();
</script>
</body>
</html>










