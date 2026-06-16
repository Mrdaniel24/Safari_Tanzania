<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('traveler');

$bookingId  = (int)($_GET['booking_id'] ?? 0);
$travelerId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT b.*,
           r.room_type, r.price AS room_price,
           a.id AS acc_id, a.name AS acc_name, a.accommodation_type,
           a.location, a.address, a.image_url,
           tu.full_name  AS traveler_name,
           tu.email      AS traveler_email,
           tu.phone      AS traveler_phone,
           ou.full_name  AS owner_name,
           p.transaction_reference, p.payment_method,
           p.paid_at, p.amount AS paid_amount
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN accommodations a ON a.id = r.accommodation_id
    JOIN users tu ON tu.id = b.traveler_id
    JOIN users ou ON ou.id = a.owner_id
    LEFT JOIN payments p ON p.booking_id = b.id AND p.payment_status = 'success'
    WHERE b.id = ? AND b.traveler_id = ?
    ORDER BY p.paid_at DESC
    LIMIT 1
");
$stmt->execute([$bookingId, $travelerId]);
$d = $stmt->fetch();

if (!$d) {
    flash_set('error', 'Booking not found.');
    redirect('traveler/my_bookings.php');
}

$nights       = max(1, (int)(new DateTime($d['check_out']))->diff(new DateTime($d['check_in']))->days);
$isPaid       = $d['payment_status'] === 'paid';
$issuedFmt    = date('d M Y', strtotime($d['created_at']));
$paidAtFmt    = $d['paid_at'] ? date('d M Y, H:i', strtotime($d['paid_at'])) : '—';
$checkInFmt   = date('d M Y', strtotime($d['check_in']));
$checkOutFmt  = date('d M Y', strtotime($d['check_out']));
$receiptNo    = 'ST' . date('Y', strtotime($d['created_at'])) . str_pad($d['id'], 6, '0', STR_PAD_LEFT);

$accType = $d['accommodation_type'] ?? '';
$accTypeLabel = match($accType) {
    'guest_house' => 'Guest House',
    'lodge'       => 'Safari Lodge',
    'hotel'       => 'Hotel',
    default       => 'Accommodation',
};
$accentGrad = match($accType) {
    'guest_house' => 'linear-gradient(90deg,#059669,#34D399)',
    'lodge'       => 'linear-gradient(90deg,#D97706,#FCD34D)',
    'hotel'       => 'linear-gradient(90deg,#1D4ED8,#60A5FA)',
    default       => 'linear-gradient(90deg,#0F7BD9,#1EC6FF)',
};
$badgeBg = match($accType) {
    'guest_house' => '#ECFDF5',
    'lodge'       => '#FFFBEB',
    'hotel'       => '#EFF6FF',
    default       => '#E0F2FE',
};
$badgeColor = match($accType) {
    'guest_house' => '#065F46',
    'lodge'       => '#92400E',
    'hotel'       => '#1E40AF',
    default       => '#075985',
};
$methodLabel = match($d['payment_method'] ?? '') {
    'mobile_money'  => 'Mobile Money (M-Pesa / Tigo)',
    'card'          => 'Card Payment',
    'bank_transfer' => 'Bank Transfer',
    default         => '—',
};

$logoUrl    = base_url('assets/images/logo-cropped-transparent.png');
$homeUrl    = base_url('public/index.php');
$bookingsUrl = base_url('traveler/my_bookings.php');

$bsColors = match($d['booking_status']) {
    'confirmed' => ['#ECFDF5', '#047857'],
    'cancelled' => ['#FEF2F2', '#B91C1C'],
    'completed' => ['#EFF6FF', '#1D4ED8'],
    default     => ['#F1F5F9', '#475569'],
};

$details = [
    ['Room Type',  e($d['room_type'])],
    ['Check-in',   $checkInFmt],
    ['Check-out',  $checkOutFmt],
    ['Duration',   $nights . ' night' . ($nights !== 1 ? 's' : '')],
    ['Guests',     (int)$d['guests'] . ' guest' . ((int)$d['guests'] !== 1 ? 's' : '')],
    ['Booking ID', '#' . (int)$d['id']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receipt <?= e($receiptNo) ?> · Safari Tanzania</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    *  { letter-spacing:0; box-sizing:border-box; }
    body { font-family:Inter,Arial,sans-serif; background:#eef2f7; color:#07142F; min-height:100vh; }
    .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 430,'GRAD' 0,'opsz' 24; vertical-align:middle; }
    .topbar { background:rgba(6,14,42,.97); border-bottom:1px solid rgba(30,198,255,.12); }
    .logo-img { height:44px; width:auto; object-fit:contain; filter:drop-shadow(0 8px 16px rgba(0,0,0,.22)); }
    .dl-btn {
      background:linear-gradient(135deg,#0F7BD9,#1EC6FF);
      color:#fff; border:none; cursor:pointer;
      font-family:Inter,sans-serif; font-size:.875rem; font-weight:700;
      display:inline-flex; align-items:center; gap:8px;
      padding:12px 22px; border-radius:12px;
      box-shadow:0 10px 28px rgba(15,123,217,.28);
      transition:.2s ease;
    }
    .dl-btn:hover { transform:translateY(-1px); box-shadow:0 14px 34px rgba(15,123,217,.34); }
    .dl-btn:disabled { opacity:.55; cursor:wait; transform:none; }
    .print-btn {
      font-family:Inter,sans-serif; font-size:.875rem; font-weight:700; cursor:pointer;
      display:inline-flex; align-items:center; gap:8px;
      padding:12px 22px; border-radius:12px; border:1.5px solid #D1D5DB;
      background:#fff; color:#374151;
      transition:.2s ease;
    }
    .print-btn:hover { background:#F9FAFB; border-color:#9CA3AF; }
    @media print {
      body { background:#fff !important; }
      .no-print { display:none !important; }
      #receipt { box-shadow:none !important; border-radius:0 !important; }
    }
  </style>
</head>
<body>

<!-- ── NAV ──────────────────────────────────────────────────────────────── -->
<header class="topbar sticky top-0 z-50 no-print">
  <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between gap-4">
    <a href="<?= e($homeUrl) ?>"><img src="<?= e($logoUrl) ?>" alt="Safari Tanzania" class="logo-img"></a>
    <a href="<?= e($bookingsUrl) ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-white/65 hover:text-white transition">
      <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>My Bookings
    </a>
  </div>
</header>

<main class="py-10 px-4">
  <div class="max-w-4xl mx-auto">

    <!-- Action bar -->
    <div class="flex items-center justify-between flex-wrap gap-4 mb-8 no-print">
      <div>
        <p class="text-xs font-bold tracking-widest text-sky-700 uppercase">Booking Receipt</p>
        <h1 class="text-3xl font-black text-zinc-900 mt-1"><?= e($receiptNo) ?></h1>
        <p class="text-sm text-zinc-500 mt-1">Issued <?= $issuedFmt ?></p>
      </div>
      <div class="flex gap-3 flex-wrap">
        <button id="dl-btn" onclick="downloadReceipt()" class="dl-btn">
          <span class="material-symbols-outlined" style="font-size:20px;">download</span>Download PNG
        </button>
        <button onclick="window.print()" class="print-btn">
          <span class="material-symbols-outlined" style="font-size:20px;">print</span>Print
        </button>
      </div>
    </div>

    <!-- Scroll wrapper keeps receipt at fixed 860 px on small screens -->
    <div class="overflow-x-auto rounded-2xl shadow-2xl">

    <!-- ══ RECEIPT ════════════════════════════════════════════════════════ -->
    <div id="receipt" style="
        width:860px;
        background:#ffffff;
        border-radius:20px;
        overflow:hidden;
        font-family:Arial,Helvetica,sans-serif;
        color:#07142F;
    ">

      <!-- HEADER -->
      <div style="
          background:linear-gradient(135deg,#07142F 0%,#0B1E5B 100%);
          padding:30px 38px;
          display:flex;
          justify-content:space-between;
          align-items:center;
          gap:20px;
      ">
        <div style="display:flex; align-items:center; gap:16px;">
          <img src="<?= e($logoUrl) ?>" alt="Safari Tanzania"
               crossorigin="anonymous"
               style="height:56px; width:auto; object-fit:contain;">
          <div style="border-left:1px solid rgba(255,255,255,.18); padding-left:16px;">
            <div style="font-size:10px; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,.45); margin-bottom:3px;">Tanzania</div>
            <div style="font-size:19px; font-weight:900; color:#ffffff; line-height:1;">Safari Tanzania</div>
            <div style="font-size:11px; color:rgba(255,255,255,.45); margin-top:2px;">Premier Safari Booking Platform</div>
          </div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:10px; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:rgba(255,255,255,.45); margin-bottom:8px;">Official Receipt</div>
          <div style="font-size:28px; font-weight:900; color:#1EC6FF; line-height:1; letter-spacing:1px;"><?= e($receiptNo) ?></div>
          <div style="font-size:12px; color:rgba(255,255,255,.45); margin-top:6px;">Issued: <?= $issuedFmt ?></div>
        </div>
      </div>

      <!-- Accent line - color changes by accommodation type -->
      <div style="height:5px; background:<?= $accentGrad ?>;"></div>

      <!-- ACCOMMODATION + TRAVELER -->
      <div style="display:flex; border-bottom:1px solid #EEF2F7;">

        <!-- Accommodation column -->
        <div style="flex:1.15; padding:28px 32px; border-right:1px solid #EEF2F7;">
          <div style="font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#9CA3AF; margin-bottom:14px;">Accommodation</div>

          <?php if ($d['image_url']): ?>
          <div style="border-radius:14px; overflow:hidden; margin-bottom:14px; height:148px;">
            <img src="<?= e($d['image_url']) ?>" alt="<?= e($d['acc_name']) ?>"
                 crossorigin="anonymous"
                 style="width:100%; height:148px; object-fit:cover; display:block;">
          </div>
          <?php endif; ?>

          <!-- Type badge -->
          <div style="
              display:inline-block;
              background:<?= $badgeBg ?>;
              color:<?= $badgeColor ?>;
              padding:5px 13px;
              border-radius:999px;
              font-size:11px;
              font-weight:800;
              letter-spacing:.5px;
              margin-bottom:11px;
          "><?= e($accTypeLabel) ?></div>

          <div style="font-size:21px; font-weight:900; color:#07142F; line-height:1.2; margin-bottom:6px;"><?= e($d['acc_name']) ?></div>
          <div style="font-size:13px; color:#6B7B99; margin-bottom:3px;"><?= e($d['location']) ?></div>
          <?php if ($d['address']): ?>
          <div style="font-size:12px; color:#B0BAC8; margin-bottom:10px;"><?= e($d['address']) ?></div>
          <?php endif; ?>

          <div style="margin-top:12px; padding-top:12px; border-top:1px solid #EEF2F7; font-size:12px; color:#9CA3AF;">
            Hosted by&nbsp;<span style="font-weight:800; color:#07142F;"><?= e($d['owner_name']) ?></span>
          </div>
        </div>

        <!-- Traveler column -->
        <div style="flex:1; padding:28px 32px;">
          <div style="font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#9CA3AF; margin-bottom:14px;">Traveler</div>
          <div style="font-size:21px; font-weight:900; color:#07142F; margin-bottom:8px;"><?= e($d['traveler_name']) ?></div>
          <div style="font-size:13px; color:#6B7B99; margin-bottom:4px;"><?= e($d['traveler_email']) ?></div>
          <?php if ($d['traveler_phone']): ?>
          <div style="font-size:13px; color:#6B7B99; margin-bottom:4px;"><?= e($d['traveler_phone']) ?></div>
          <?php endif; ?>

          <div style="margin-top:22px;">
            <div style="font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#9CA3AF; margin-bottom:10px;">Booking status</div>
            <span style="
                display:inline-block;
                background:<?= $bsColors[0] ?>;
                color:<?= $bsColors[1] ?>;
                padding:6px 16px;
                border-radius:999px;
                font-size:12px;
                font-weight:800;
                text-transform:capitalize;
            "><?= e($d['booking_status']) ?></span>
          </div>

          <?php if ($isPaid && $d['paid_at']): ?>
          <div style="margin-top:18px;">
            <div style="font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#9CA3AF; margin-bottom:8px;">Payment confirmed</div>
            <div style="font-size:13px; font-weight:700; color:#047857;"><?= $paidAtFmt ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- BOOKING DETAILS ROW -->
      <div style="background:#FAFBFD; padding:24px 32px; border-bottom:1px solid #EEF2F7;">
        <div style="font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#9CA3AF; margin-bottom:18px;">Booking Details</div>
        <div style="display:flex; gap:0;">
          <?php foreach ($details as $i => [$label, $value]): ?>
          <div style="
              flex:1;
              padding:0 20px;
              <?= $i === 0 ? 'padding-left:0;' : '' ?>
              <?= $i < count($details) - 1 ? 'border-right:1px solid #EEF2F7;' : 'border-right:0;' ?>
          ">
            <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#B0BAC8; margin-bottom:6px;"><?= $label ?></div>
            <div style="font-size:13px; font-weight:700; color:#07142F;"><?= $value ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- PAYMENT SUMMARY -->
      <div style="padding:26px 32px; display:flex; gap:28px; align-items:flex-start; border-bottom:1px solid #EEF2F7;">
        <div style="flex:1;">
          <div style="font-size:10px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#9CA3AF; margin-bottom:16px;">Payment Summary</div>

          <!-- Line item -->
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <span style="font-size:13px; color:#6B7B99;"><?= e($d['room_type']) ?> &times; <?= $nights ?> night<?= $nights !== 1 ? 's' : '' ?></span>
            <span style="font-size:13px; font-weight:600; color:#07142F;">Tsh <?= number_format((float)$d['total_price'], 0) ?></span>
          </div>

          <div style="border-top:1.5px dashed #D1D5DB; margin:12px 0;"></div>

          <!-- Total -->
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <span style="font-size:17px; font-weight:900; color:#07142F;">Total</span>
            <span style="font-size:19px; font-weight:900; color:#07142F;">Tsh <?= number_format((float)($d['paid_amount'] ?? $d['total_price']), 0) ?></span>
          </div>

          <!-- Payment meta -->
          <div style="background:#F8FAFC; border-radius:13px; padding:14px 16px; font-size:12px;">
            <?php if ($d['payment_method']): ?>
            <div style="display:flex; gap:10px; margin-bottom:7px;">
              <span style="font-weight:700; color:#9CA3AF; width:78px; flex-shrink:0;">Method</span>
              <span style="font-weight:700; color:#07142F;"><?= $methodLabel ?></span>
            </div>
            <?php endif; ?>
            <?php if ($d['transaction_reference']): ?>
            <div style="display:flex; gap:10px; margin-bottom:7px;">
              <span style="font-weight:700; color:#9CA3AF; width:78px; flex-shrink:0;">Reference</span>
              <span style="font-weight:700; color:#07142F; font-family:'Courier New',Courier,monospace;"><?= e($d['transaction_reference']) ?></span>
            </div>
            <?php endif; ?>
            <div style="display:flex; gap:10px;">
              <span style="font-weight:700; color:#9CA3AF; width:78px; flex-shrink:0;">Paid on</span>
              <span style="font-weight:700; color:#07142F;"><?= $paidAtFmt ?></span>
            </div>
          </div>
        </div>

        <!-- PAID / PENDING stamp -->
        <div style="flex:0 0 auto; text-align:center; padding-top:32px;">
          <?php if ($isPaid): ?>
          <div style="
              border:3px solid #6EE7B7;
              background:linear-gradient(145deg,#F0FDF4,#ECFDF5);
              border-radius:18px;
              padding:20px 28px;
              display:inline-block;
          ">
            <div style="font-size:32px; font-weight:900; color:#047857; letter-spacing:4px;">PAID</div>
            <div style="font-size:12px; color:#065F46; font-weight:700; margin-top:5px;">&#10003; Verified</div>
          </div>
          <?php else: ?>
          <div style="
              border:3px solid #FCD34D;
              background:linear-gradient(145deg,#FFFBEB,#FEF3C7);
              border-radius:18px;
              padding:20px 28px;
              display:inline-block;
          ">
            <div style="font-size:22px; font-weight:900; color:#92400E; letter-spacing:2px;">PENDING</div>
            <div style="font-size:11px; color:#92400E; font-weight:700; margin-top:5px;">Payment required</div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- FOOTER -->
      <div style="
          background:linear-gradient(135deg,#07142F 0%,#0B1E5B 100%);
          padding:22px 32px;
          display:flex;
          justify-content:space-between;
          align-items:center;
          gap:16px;
      ">
        <div>
          <div style="font-size:14px; font-weight:800; color:#ffffff; margin-bottom:3px;">Safari Tanzania</div>
          <div style="font-size:11px; color:rgba(255,255,255,.45);">Tanzania's Premier Safari Booking Platform</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:rgba(255,255,255,.35);">Thank you for booking with us</div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:11px; color:rgba(255,255,255,.45);">contact@safaritanzania.test</div>
          <div style="font-size:11px; color:rgba(255,255,255,.45); margin-top:2px;">safaritanzania.test</div>
        </div>
      </div>

    </div><!-- /#receipt -->
    </div><!-- overflow-x wrapper -->

    <p class="text-center text-sm text-zinc-400 mt-6 no-print">
      This is an official receipt generated by Safari Tanzania. Keep it for your records.
    </p>

    <div class="flex justify-center gap-3 mt-4 no-print">
      <a href="<?= e($bookingsUrl) ?>" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white border border-zinc-200 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition shadow-sm">
        <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>Back to My Bookings
      </a>
      <?php if (!$isPaid && $d['booking_status'] === 'confirmed'): ?>
      <a href="<?= e(base_url('traveler/payment.php?booking_id=' . (int)$d['id'])) ?>" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold text-white shadow-lg" style="background:linear-gradient(135deg,#0F7BD9,#1EC6FF);">
        <span class="material-symbols-outlined" style="font-size:18px;">payments</span>Pay now
      </a>
      <?php endif; ?>
    </div>

  </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
async function downloadReceipt() {
    const el  = document.getElementById('receipt');
    const btn = document.getElementById('dl-btn');
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-outlined" style="font-size:20px;">hourglass_empty</span>Generating…';
    btn.disabled  = true;
    try {
        const canvas = await html2canvas(el, {
            scale:           2,
            useCORS:         true,
            allowTaint:      false,
            backgroundColor: '#ffffff',
            logging:         false,
            scrollX:         0,
            scrollY:         0,
        });
        const link      = document.createElement('a');
        link.download   = 'receipt-<?= e($receiptNo) ?>.png';
        link.href       = canvas.toDataURL('image/png');
        link.click();
    } catch (err) {
        console.error(err);
        alert('PNG generation failed — please use the Print button instead.');
    } finally {
        btn.innerHTML = orig;
        btn.disabled  = false;
    }
}
</script>
</body>
</html>
