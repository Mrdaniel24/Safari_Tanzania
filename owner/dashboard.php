<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];

$pdo->exec("CREATE TABLE IF NOT EXISTS owner_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL UNIQUE,
    business_name VARCHAR(150) NOT NULL,
    property_type ENUM('guest_house','lodge','hotel') NOT NULL,
    registration_number VARCHAR(100) NOT NULL,
    business_address VARCHAR(255) NOT NULL,
    document_path VARCHAR(500) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    submitted_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_owner_verification_user FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$stmt = $pdo->prepare('SELECT status, submitted_at FROM owner_verifications WHERE owner_id = ? LIMIT 1');
$stmt->execute([$ownerId]);
$verificationStatus = $stmt->fetch();
$verificationLabel = match($verificationStatus['status'] ?? 'not_submitted') {
    'pending' => 'Pending admin review',
    'approved' => 'Verified owner profile',
    'rejected' => 'Needs updates',
    default => 'Not submitted yet',
};
$verificationMessage = match($verificationStatus['status'] ?? 'not_submitted') {
    'pending' => 'Your details and document are with admin for review.',
    'approved' => 'Your profile is verified and ready for trusted partner operations.',
    'rejected' => 'Admin requested updates. Review your profile verification page.',
    default => 'Complete your business details and upload your document for admin review.',
};

$stmt = $pdo->prepare("SELECT COUNT(*) FROM accommodations WHERE owner_id = ?");
$stmt->execute([$ownerId]);
$totalProps = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM accommodations WHERE owner_id = ? AND status = 'approved'");
$stmt->execute([$ownerId]);
$approvedProps = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM accommodations WHERE owner_id = ? AND status = 'pending'");
$stmt->execute([$ownerId]);
$pendingProps = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN accommodations a ON a.id = r.accommodation_id
    WHERE a.owner_id = ?
");
$stmt->execute([$ownerId]);
$totalBookings = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(b.total_price), 0) FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN accommodations a ON a.id = r.accommodation_id
    WHERE a.owner_id = ? AND b.booking_status != 'cancelled'
");
$stmt->execute([$ownerId]);
$totalRevenue = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN accommodations a ON a.id = r.accommodation_id
    WHERE a.owner_id = ? AND b.booking_status = 'confirmed' AND b.payment_status = 'pending'
");
$stmt->execute([$ownerId]);
$pendingPayments = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT b.*, u.full_name AS traveler_name, r.room_type, a.name AS acc_name
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN accommodations a ON a.id = r.accommodation_id
    JOIN users u ON u.id = b.traveler_id
    WHERE a.owner_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->execute([$ownerId]);
$recentBookings = $stmt->fetchAll();

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<style>
.owner-dashboard { padding: 28px 20px 42px; }
.owner-dashboard-inner { max-width: 1180px; margin: 0 auto; display: grid; gap: 22px; }
.owner-hero {
    position: relative;
    overflow: hidden;
    border-radius: 28px;
    padding: clamp(24px, 4vw, 38px);
    color: #fff;
    background:
        linear-gradient(135deg, rgba(7,20,47,.96), rgba(11,30,91,.76)),
        url('https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=1600&q=82') center/cover;
    box-shadow: 0 26px 70px rgba(7,20,47,.22);
}
.owner-hero::after {
    content: '';
    position: absolute;
    inset: auto -10% -45% 45%;
    height: 78%;
    background: radial-gradient(circle, rgba(30,198,255,.32), transparent 62%);
}
.owner-hero-content { position: relative; z-index: 1; max-width: 720px; }
.owner-kicker { display:inline-flex; align-items:center; gap:8px; color:#8BE7FF; font-weight:800; font-size:.78rem; text-transform:uppercase; margin-bottom:12px; }
.owner-hero h2 { font-size: clamp(2rem, 4vw, 3.6rem); line-height: 1; font-weight: 800; margin: 0 0 12px; }
.owner-hero p { color: rgba(255,255,255,.76); max-width: 620px; line-height: 1.7; }
.owner-hero-actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:24px; }
.owner-btn { display:inline-flex; align-items:center; gap:8px; border-radius:999px; padding:12px 17px; font-weight:750; font-size:.92rem; transition:.2s ease; }
.owner-btn.primary { background: linear-gradient(135deg,#0F7BD9,#1EC6FF); color:#fff; box-shadow:0 16px 36px rgba(30,198,255,.24); }
.owner-btn.secondary { background: rgba(255,255,255,.12); color:#fff; border:1px solid rgba(255,255,255,.16); }
.owner-btn:hover { transform: translateY(-1px); }
.owner-stats { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:16px; }
.owner-stat, .owner-panel, .owner-action-card {
    border: 1px solid rgba(15,36,82,.08);
    background: rgba(255,255,255,.86);
    box-shadow: 0 18px 44px rgba(7,20,47,.07);
    border-radius: 22px;
}
.owner-stat { padding: 18px; }
.owner-stat-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.owner-stat-icon { width:44px; height:44px; border-radius:15px; display:grid; place-items:center; color:#fff; }
.owner-stat-icon.blue { background:linear-gradient(135deg,#0F7BD9,#1EC6FF); }
.owner-stat-icon.navy { background:linear-gradient(135deg,#0B1E5B,#2446A8); }
.owner-stat-icon.green { background:linear-gradient(135deg,#0E9F6E,#35D399); }
.owner-stat-icon.amber { background:linear-gradient(135deg,#D97706,#FBBF24); }
.owner-stat small { color:#6B7B99; font-weight:750; }
.owner-stat strong { display:block; color:#07142F; font-size:2rem; line-height:1; font-weight:800; margin-top:6px; }
.owner-grid { display:grid; grid-template-columns: minmax(0,1.35fr) minmax(320px,.65fr); gap:18px; }
.owner-panel { overflow:hidden; }
.owner-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:20px 22px; border-bottom:1px solid rgba(15,36,82,.07); }
.owner-panel-head h3 { font-size:1.05rem; font-weight:800; color:#07142F; }
.owner-panel-head a { color:#0F7BD9; font-weight:750; font-size:.9rem; }
.owner-verify { padding:20px 22px; display:flex; gap:15px; align-items:flex-start; background:linear-gradient(135deg,rgba(30,198,255,.12),rgba(15,123,217,.06)); }
.owner-verify-icon { width:48px; height:48px; border-radius:17px; display:grid; place-items:center; background:#fff; color:#0F7BD9; box-shadow:0 12px 28px rgba(15,123,217,.14); flex:0 0 auto; }
.owner-verify h3 { font-weight:800; color:#07142F; margin-bottom:4px; }
.owner-verify p { color:#5B6C86; line-height:1.55; font-size:.92rem; }
.owner-booking-row { display:grid; grid-template-columns: 1fr auto; gap:16px; padding:16px 22px; border-bottom:1px solid rgba(15,36,82,.06); }
.owner-booking-row:last-child { border-bottom:0; }
.owner-booking-title { font-weight:760; color:#07142F; }
.owner-booking-meta { color:#6B7B99; font-size:.86rem; margin-top:4px; }
.owner-pill { display:inline-flex; align-items:center; border-radius:999px; padding:6px 10px; font-size:.75rem; font-weight:800; }
.owner-pill.confirmed { background:#E8F8F0; color:#047857; }
.owner-pill.cancelled { background:#FEE2E2; color:#B91C1C; }
.owner-pill.completed { background:#E8F2FF; color:#1D4ED8; }
.owner-pill.default { background:#EEF2F7; color:#475569; }
.owner-empty { padding:44px 22px; text-align:center; color:#7A8AA2; }
.owner-actions { display:grid; gap:14px; }
.owner-action-card { padding:18px; display:flex; gap:14px; align-items:center; transition:.2s ease; }
.owner-action-card:hover { transform:translateY(-2px); box-shadow:0 22px 48px rgba(7,20,47,.10); }
.owner-action-card .icon { width:46px; height:46px; border-radius:16px; display:grid; place-items:center; color:#fff; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); }
.owner-action-card h4 { font-weight:800; color:#07142F; }
.owner-action-card p { color:#6B7B99; font-size:.86rem; margin-top:2px; }
@media (max-width: 1020px) { .owner-stats, .owner-grid { grid-template-columns:1fr 1fr; } .owner-grid { grid-template-columns:1fr; } }
@media (max-width: 640px) { .owner-dashboard { padding:18px 14px 32px; } .owner-stats { grid-template-columns:1fr; } .owner-booking-row { grid-template-columns:1fr; } }
</style>

<div class="owner-dashboard">
    <div class="owner-dashboard-inner">
        <section class="owner-hero">
            <div class="owner-hero-content">
                <div class="owner-kicker"><span class="material-symbols-outlined">workspace_premium</span> Safari Tanzania Partner</div>
                <h2>Welcome back, <?= e($_SESSION['full_name'] ?? 'Owner') ?></h2>
                <p>Manage your properties, monitor bookings, and prepare your profile verification from one clear workspace.</p>
                <div class="owner-hero-actions">
                    <a href="<?= e(base_url('owner/add_property.php')) ?>" class="owner-btn primary"><span class="material-symbols-outlined">add_home</span>Add property</a>
                    <a href="<?= e(base_url('owner/properties.php')) ?>" class="owner-btn secondary"><span class="material-symbols-outlined">domain</span>View properties</a>
                </div>
            </div>
        </section>

        <section class="owner-stats">
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon blue"><span class="material-symbols-outlined">home_work</span></div><small>Total</small></div>
                <strong><?= $totalProps ?></strong><small>Properties listed</small>
            </div>
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon green"><span class="material-symbols-outlined">verified</span></div><small>Approved</small></div>
                <strong><?= $approvedProps ?></strong><small>Live properties</small>
            </div>
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon navy"><span class="material-symbols-outlined">calendar_month</span></div><small>Bookings</small></div>
                <strong><?= $totalBookings ?></strong><small>Total reservations</small>
            </div>
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon amber"><span class="material-symbols-outlined">payments</span></div><small>Revenue</small></div>
                <strong>Tsh <?= number_format($totalRevenue, 0) ?></strong><small><?= $pendingPayments ?> pending payment<?= $pendingPayments === 1 ? '' : 's' ?></small>
            </div>
        </section>

        <section class="owner-grid">
            <div class="owner-panel">
                <div class="owner-panel-head">
                    <h3>Recent Bookings</h3>
                    <a href="<?= e(base_url('owner/bookings.php')) ?>">View all</a>
                </div>
                <?php if (!$recentBookings): ?>
                    <div class="owner-empty">
                        <span class="material-symbols-outlined" style="font-size:44px;color:#B8C4D6;">event_busy</span>
                        <p class="mt-2 font-semibold">No bookings yet</p>
                        <p class="text-sm mt-1">Bookings will appear here once travelers reserve your rooms.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentBookings as $b): ?>
                        <?php $statusClass = in_array($b['booking_status'], ['confirmed','cancelled','completed'], true) ? $b['booking_status'] : 'default'; ?>
                        <div class="owner-booking-row">
                            <div>
                                <div class="owner-booking-title"><?= e($b['traveler_name']) ?></div>
                                <div class="owner-booking-meta"><?= e($b['acc_name']) ?> - <?= e($b['room_type']) ?></div>
                                <div class="owner-booking-meta"><?= e($b['check_in']) ?> to <?= e($b['check_out']) ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-slate-900">Tsh <?= number_format((float)$b['total_price'], 0) ?></div>
                                <span class="owner-pill <?= e($statusClass) ?> mt-2"><?= e($b['booking_status']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <aside class="owner-actions">
                <div class="owner-panel">
                    <div class="owner-verify">
                        <a href="<?= e(base_url('owner/profile_verification.php')) ?>" class="owner-verify-icon"><span class="material-symbols-outlined">verified_user</span></a>
                        <div>
                            <h3>Profile verification</h3><p class="text-xs font-bold uppercase text-blue-600 mb-1"><?= e($verificationLabel) ?></p>
                            <p><?= e($verificationMessage) ?></p>
                        </div>
                    </div>
                </div>
                <a href="<?= e(base_url('owner/add_property.php')) ?>" class="owner-action-card">
                    <div class="icon"><span class="material-symbols-outlined">add_business</span></div>
                    <div><h4>Add accommodation</h4><p>Create a hotel, lodge, or guest house listing.</p></div>
                </a>
                <a href="<?= e(base_url('owner/properties.php')) ?>" class="owner-action-card">
                    <div class="icon"><span class="material-symbols-outlined">holiday_village</span></div>
                    <div><h4>Manage properties</h4><p><?= $pendingProps ?> pending approval, <?= $approvedProps ?> approved.</p></div>
                </a>
                <a href="<?= e(base_url('owner/bookings.php')) ?>" class="owner-action-card">
                    <div class="icon"><span class="material-symbols-outlined">event_note</span></div>
                    <div><h4>Review bookings</h4><p>Track reservations and payment status.</p></div>
                </a>
            </aside>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

