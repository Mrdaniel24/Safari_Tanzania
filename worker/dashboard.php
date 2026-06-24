<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('worker');

$workerId = (int)$_SESSION['user_id'];
$accIds   = worker_accommodation_ids($workerId);

$totalProps     = count($accIds);
$totalBookings  = 0;
$confirmedCount = 0;
$pendingPayments= 0;
$recentBookings = [];

if ($accIds) {
    $placeholders = implode(',', array_fill(0, count($accIds), '?'));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE r.accommodation_id IN ($placeholders)");
    $stmt->execute($accIds);
    $totalBookings = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE r.accommodation_id IN ($placeholders) AND b.booking_status = 'confirmed'");
    $stmt->execute($accIds);
    $confirmedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE r.accommodation_id IN ($placeholders) AND b.payment_status = 'pending'");
    $stmt->execute($accIds);
    $pendingPayments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT b.*, u.full_name AS traveler_name, r.room_type, a.name AS acc_name
        FROM bookings b
        JOIN rooms r ON r.id = b.room_id
        JOIN accommodations a ON a.id = r.accommodation_id
        JOIN users u ON u.id = b.traveler_id
        WHERE r.accommodation_id IN ($placeholders)
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $stmt->execute($accIds);
    $recentBookings = $stmt->fetchAll();
}

$activePage = 'dashboard';
$pageTitle  = 'Dashboard';
include __DIR__ . '/../includes/worker_header.php';
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
.owner-hero h2 { font-size: clamp(1.8rem, 4vw, 3.2rem); line-height: 1; font-weight: 800; margin: 0 0 12px; }
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
.owner-stat-icon.blue  { background:linear-gradient(135deg,#0F7BD9,#1EC6FF); }
.owner-stat-icon.navy  { background:linear-gradient(135deg,#0B1E5B,#2446A8); }
.owner-stat-icon.green { background:linear-gradient(135deg,#0E9F6E,#35D399); }
.owner-stat-icon.amber { background:linear-gradient(135deg,#D97706,#FBBF24); }
.owner-stat small { color:#6B7B99; font-weight:750; }
.owner-stat strong { display:block; color:#07142F; font-size:2rem; line-height:1; font-weight:800; margin-top:6px; }
.owner-grid { display:grid; grid-template-columns: minmax(0,1.45fr) minmax(280px,.55fr); gap:18px; }
.owner-panel { overflow:hidden; }
.owner-panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:20px 22px; border-bottom:1px solid rgba(15,36,82,.07); }
.owner-panel-head h3 { font-size:1.05rem; font-weight:800; color:#07142F; }
.owner-panel-head a { color:#0F7BD9; font-weight:750; font-size:.9rem; }
.owner-booking-row { display:grid; grid-template-columns: 1fr auto; gap:16px; padding:16px 22px; border-bottom:1px solid rgba(15,36,82,.06); }
.owner-booking-row:last-child { border-bottom:0; }
.owner-booking-title { font-weight:760; color:#07142F; }
.owner-booking-meta { color:#6B7B99; font-size:.86rem; margin-top:4px; }
.owner-pill { display:inline-flex; align-items:center; border-radius:999px; padding:6px 10px; font-size:.75rem; font-weight:800; }
.owner-pill.confirmed { background:#E8F8F0; color:#047857; }
.owner-pill.cancelled { background:#FEE2E2; color:#B91C1C; }
.owner-pill.completed { background:#E8F2FF; color:#1D4ED8; }
.owner-pill.default   { background:#EEF2F7; color:#475569; }
.owner-empty { padding:44px 22px; text-align:center; color:#7A8AA2; }
.owner-actions { display:grid; gap:14px; }
.owner-action-card { padding:18px; display:flex; gap:14px; align-items:center; transition:.2s ease; }
.owner-action-card:hover { transform:translateY(-2px); box-shadow:0 22px 48px rgba(7,20,47,.10); }
.owner-action-card .icon { width:46px; height:46px; border-radius:16px; display:grid; place-items:center; color:#fff; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); flex: 0 0 auto; }
.owner-action-card h4 { font-weight:800; color:#07142F; }
.owner-action-card p  { color:#6B7B99; font-size:.86rem; margin-top:2px; }
@media (max-width: 1020px) { .owner-stats { grid-template-columns:1fr 1fr; } .owner-grid { grid-template-columns:1fr; } }
@media (max-width: 640px) { .owner-dashboard { padding:18px 14px 32px; } .owner-stats { grid-template-columns:1fr; } .owner-booking-row { grid-template-columns:1fr; } }
</style>

<div class="owner-dashboard">
    <div class="owner-dashboard-inner">
        <section class="owner-hero">
            <div class="owner-hero-content">
                <div class="owner-kicker"><span class="material-symbols-outlined">badge</span> Staff Member</div>
                <h2>Welcome, <?= e($_SESSION['full_name'] ?? 'Worker') ?></h2>
                <p>Manage bookings and payments for the properties assigned to you. Contact your owner if you need access to additional properties.</p>
                <div class="owner-hero-actions">
                    <a href="<?= e(base_url('worker/bookings.php')) ?>" class="owner-btn primary"><span class="material-symbols-outlined">calendar_month</span>View bookings</a>
                    <a href="<?= e(base_url('worker/payments.php')) ?>" class="owner-btn secondary"><span class="material-symbols-outlined">payments</span>Manage payments</a>
                </div>
            </div>
        </section>

        <?php if (!$accIds): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-6 flex items-start gap-3">
                <span class="material-symbols-outlined mt-0.5" style="font-size:22px;">warning</span>
                <div>
                    <p class="font-bold">No properties assigned yet</p>
                    <p class="text-sm mt-1">Your owner has not yet assigned any properties to your account. Once assigned, you will be able to manage bookings and payments here.</p>
                </div>
            </div>
        <?php endif; ?>

        <section class="owner-stats">
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon blue"><span class="material-symbols-outlined">home_work</span></div><small>Assigned</small></div>
                <strong><?= $totalProps ?></strong><small>Propert<?= $totalProps === 1 ? 'y' : 'ies' ?></small>
            </div>
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon navy"><span class="material-symbols-outlined">calendar_month</span></div><small>Bookings</small></div>
                <strong><?= $totalBookings ?></strong><small>Total reservations</small>
            </div>
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon green"><span class="material-symbols-outlined">event_available</span></div><small>Confirmed</small></div>
                <strong><?= $confirmedCount ?></strong><small>Active bookings</small>
            </div>
            <div class="owner-stat">
                <div class="owner-stat-top"><div class="owner-stat-icon amber"><span class="material-symbols-outlined">pending_actions</span></div><small>Payments</small></div>
                <strong><?= $pendingPayments ?></strong><small>Pending verification</small>
            </div>
        </section>

        <section class="owner-grid">
            <div class="owner-panel">
                <div class="owner-panel-head">
                    <h3>Recent Bookings</h3>
                    <a href="<?= e(base_url('worker/bookings.php')) ?>">View all</a>
                </div>
                <?php if (!$recentBookings): ?>
                    <div class="owner-empty">
                        <span class="material-symbols-outlined" style="font-size:44px;color:#B8C4D6;">event_busy</span>
                        <p class="mt-2 font-semibold">No bookings yet</p>
                        <p class="text-sm mt-1"><?= $accIds ? 'No reservations have been made for your assigned properties.' : 'Assign a property first to see bookings here.' ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentBookings as $b):
                        $statusClass = in_array($b['booking_status'], ['confirmed','cancelled','completed'], true) ? $b['booking_status'] : 'default';
                    ?>
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
                <a href="<?= e(base_url('worker/bookings.php')) ?>" class="owner-action-card">
                    <div class="icon"><span class="material-symbols-outlined">event_note</span></div>
                    <div>
                        <h4>Manage bookings</h4>
                        <p><?= $confirmedCount ?> confirmed, <?= $totalBookings ?> total.</p>
                    </div>
                </a>
                <a href="<?= e(base_url('worker/payments.php')) ?>" class="owner-action-card">
                    <div class="icon"><span class="material-symbols-outlined">payments</span></div>
                    <div>
                        <h4>Payment verification</h4>
                        <p><?= $pendingPayments ?> payment<?= $pendingPayments === 1 ? '' : 's' ?> awaiting review.</p>
                    </div>
                </a>
            </aside>
        </section>
    </div>
</div>
<?php include __DIR__ . '/../includes/worker_footer.php'; ?>
