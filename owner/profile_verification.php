<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$errors = [];

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

$stmt = $pdo->prepare('SELECT * FROM owner_verifications WHERE owner_id = ? LIMIT 1');
$stmt->execute([$ownerId]);
$verification = $stmt->fetch();

$old = [
    'business_name' => $verification['business_name'] ?? '',
    'property_type' => $verification['property_type'] ?? 'hotel',
    'registration_number' => $verification['registration_number'] ?? '',
    'business_address' => $verification['business_address'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $business_name = trim($_POST['business_name'] ?? '');
    $property_type = $_POST['property_type'] ?? '';
    $registration_number = trim($_POST['registration_number'] ?? '');
    $business_address = trim($_POST['business_address'] ?? '');
    $old = compact('business_name', 'property_type', 'registration_number', 'business_address');

    $validTypes = ['guest_house', 'lodge', 'hotel'];
    if ($business_name === '' || mb_strlen($business_name) > 150) $errors[] = 'Business name is required.';
    if (!in_array($property_type, $validTypes, true)) $errors[] = 'Choose a valid accommodation type.';
    if ($registration_number === '' || mb_strlen($registration_number) > 100) $errors[] = 'Registration number is required.';
    if ($business_address === '' || mb_strlen($business_address) > 255) $errors[] = 'Business address is required.';

    $documentPath = $verification['document_path'] ?? '';
    $documentName = $verification['document_name'] ?? '';
    $hasUpload = isset($_FILES['verification_document']) && ($_FILES['verification_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!$hasUpload && !$documentPath) {
        $errors[] = 'Upload your business license, registration certificate, or ownership proof.';
    }

    if ($hasUpload && !$errors) {
        $file = $_FILES['verification_document'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Document upload failed. Please try again.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Document must be 5MB or smaller.';
        } else {
            $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) {
                $errors[] = 'Only PDF, JPG, and PNG documents are allowed.';
            } else {
                $uploadDir = __DIR__ . '/../public/uploads/owner_verifications';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $safeName = 'owner_' . $ownerId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                $target = $uploadDir . '/' . $safeName;
                if (!move_uploaded_file($file['tmp_name'], $target)) {
                    $errors[] = 'Could not save the uploaded document.';
                } else {
                    $documentPath = 'public/uploads/owner_verifications/' . $safeName;
                    $documentName = basename($file['name']);
                }
            }
        }
    }

    if (!$errors) {
        if ($verification) {
            $stmt = $pdo->prepare("UPDATE owner_verifications
                SET business_name = ?, property_type = ?, registration_number = ?, business_address = ?, document_path = ?, document_name = ?, status = 'pending', admin_notes = NULL, submitted_at = NOW(), reviewed_at = NULL
                WHERE owner_id = ?");
            $stmt->execute([$business_name, $property_type, $registration_number, $business_address, $documentPath, $documentName, $ownerId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO owner_verifications
                (owner_id, business_name, property_type, registration_number, business_address, document_path, document_name, status, submitted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$ownerId, $business_name, $property_type, $registration_number, $business_address, $documentPath, $documentName]);
        }
        flash_set('success', 'Verification submitted. Admin will review your profile.');
        redirect('owner/profile_verification.php');
    }
}

$stmt = $pdo->prepare('SELECT * FROM owner_verifications WHERE owner_id = ? LIMIT 1');
$stmt->execute([$ownerId]);
$verification = $stmt->fetch();
$status = $verification['status'] ?? 'not_submitted';
$statusMeta = [
    'not_submitted' => ['label' => 'Not submitted', 'class' => 'neutral', 'icon' => 'assignment_late'],
    'pending' => ['label' => 'Pending review', 'class' => 'pending', 'icon' => 'hourglass_top'],
    'approved' => ['label' => 'Approved', 'class' => 'approved', 'icon' => 'verified'],
    'rejected' => ['label' => 'Rejected', 'class' => 'rejected', 'icon' => 'report'],
][$status] ?? ['label' => ucfirst($status), 'class' => 'neutral', 'icon' => 'info'];

$activePage = 'verification';
$pageTitle  = 'Profile Verification';
include __DIR__ . '/../includes/header.php';
?>
<style>
.verify-page { padding: 28px 20px 42px; }
.verify-inner { max-width: 1120px; margin: 0 auto; display: grid; gap: 20px; }
.verify-hero { border-radius: 28px; padding: clamp(24px, 4vw, 36px); color: #fff; background: linear-gradient(135deg, rgba(7,20,47,.96), rgba(11,30,91,.76)), url('https://images.unsplash.com/photo-1528277342758-f1d7613953a2?auto=format&fit=crop&w=1500&q=82') center/cover; box-shadow: 0 26px 70px rgba(7,20,47,.22); }
.verify-hero h2 { font-size: clamp(2rem, 4vw, 3.2rem); line-height:1; font-weight:800; margin: 8px 0 10px; }
.verify-hero p { color: rgba(255,255,255,.76); max-width: 680px; line-height: 1.65; }
.verify-kicker { color:#8BE7FF; font-weight:800; font-size:.78rem; text-transform:uppercase; }
.verify-grid { display:grid; grid-template-columns: minmax(0,1fr) 340px; gap:20px; align-items:start; }
.verify-card, .verify-side-card { border:1px solid rgba(15,36,82,.08); background:rgba(255,255,255,.9); box-shadow:0 18px 44px rgba(7,20,47,.07); border-radius:24px; }
.verify-card { padding:24px; }
.verify-form { display:grid; gap:18px; }
.verify-field label { display:block; font-weight:750; color:#0F1E3A; margin-bottom:7px; font-size:.9rem; }
.verify-field input, .verify-field select { width:100%; border:1px solid rgba(15,36,82,.14); border-radius:16px; padding:13px 14px; color:#07142F; background:#fff; outline:none; font-size:.94rem; }
.verify-field input:focus, .verify-field select:focus { border-color:#1EC6FF; box-shadow:0 0 0 4px rgba(30,198,255,.13); }
.verify-two { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
.verify-upload { border:1px dashed rgba(15,123,217,.34); background:rgba(30,198,255,.06); border-radius:18px; padding:18px; }
.verify-upload p { color:#5B6C86; font-size:.88rem; margin-top:6px; }
.verify-submit { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:13px 18px; color:#fff; font-weight:780; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); box-shadow:0 16px 34px rgba(15,123,217,.22); }
.verify-alert { display:flex; gap:10px; align-items:flex-start; border-radius:16px; padding:13px 14px; font-size:.9rem; margin-bottom:12px; }
.verify-alert.error { background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; }
.verify-side-card { padding:20px; }
.verify-status { display:flex; gap:14px; align-items:center; }
.verify-status-icon { width:52px; height:52px; border-radius:18px; display:grid; place-items:center; color:#fff; }
.verify-status-icon.pending { background:linear-gradient(135deg,#D97706,#FBBF24); }
.verify-status-icon.approved { background:linear-gradient(135deg,#059669,#34D399); }
.verify-status-icon.rejected { background:linear-gradient(135deg,#DC2626,#FB7185); }
.verify-status-icon.neutral { background:linear-gradient(135deg,#64748B,#94A3B8); }
.verify-status h3 { font-weight:800; color:#07142F; }
.verify-status p { color:#6B7B99; font-size:.86rem; }
.verify-list { margin-top:18px; display:grid; gap:12px; color:#5B6C86; font-size:.9rem; }
.verify-list div { display:flex; gap:9px; align-items:flex-start; }
.verify-list span { color:#0F7BD9; font-size:19px; }
.verify-document { margin-top:18px; padding:14px; border-radius:16px; background:#F5F8FC; color:#5B6C86; font-size:.9rem; }
.verify-document a { color:#0F7BD9; font-weight:750; }
@media (max-width: 920px) { .verify-grid, .verify-two { grid-template-columns:1fr; } }
</style>
<div class="verify-page"><div class="verify-inner">
<section class="verify-hero"><div class="verify-kicker">Owner trust center</div><h2>Verify your owner profile</h2><p>Submit your business details and supporting document so Safari Tanzania can review your property partner profile.</p></section>
<section class="verify-grid"><div class="verify-card">
<?php foreach ($errors as $err): ?><div class="verify-alert error"><span class="material-symbols-outlined">error</span><span><?= e($err) ?></span></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="verify-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<div class="verify-two"><div class="verify-field"><label for="business_name">Business / Property Name</label><input id="business_name" name="business_name" maxlength="150" required value="<?= e($old['business_name']) ?>" placeholder="e.g. Serengeti Safari Lodge"></div><div class="verify-field"><label for="property_type">Accommodation Type</label><select id="property_type" name="property_type" required><option value="guest_house" <?= $old['property_type'] === 'guest_house' ? 'selected' : '' ?>>Guest House</option><option value="lodge" <?= $old['property_type'] === 'lodge' ? 'selected' : '' ?>>Lodge</option><option value="hotel" <?= $old['property_type'] === 'hotel' ? 'selected' : '' ?>>Hotel</option></select></div></div>
<div class="verify-two"><div class="verify-field"><label for="registration_number">Government Registration / License Number</label><input id="registration_number" name="registration_number" maxlength="100" required value="<?= e($old['registration_number']) ?>" placeholder="e.g. TALA-2026-0001"></div><div class="verify-field"><label for="business_address">Business Address</label><input id="business_address" name="business_address" maxlength="255" required value="<?= e($old['business_address']) ?>" placeholder="Region, district, street"></div></div>
<div class="verify-upload"><div class="verify-field"><label for="verification_document">Verification Document <?= $verification ? '(upload again only if replacing)' : '' ?></label><input id="verification_document" name="verification_document" type="file" accept=".pdf,.jpg,.jpeg,.png" <?= $verification ? '' : 'required' ?>><p>Accepted: PDF, JPG, PNG. Maximum size: 5MB.</p></div></div>
<button type="submit" class="verify-submit"><span class="material-symbols-outlined">send</span>Submit for Review</button></form></div>
<aside class="verify-side-card"><div class="verify-status"><div class="verify-status-icon <?= e($statusMeta['class']) ?>"><span class="material-symbols-outlined"><?= e($statusMeta['icon']) ?></span></div><div><h3><?= e($statusMeta['label']) ?></h3><p><?= $verification ? 'Last submitted ' . e(date('M d, Y', strtotime($verification['submitted_at'] ?? $verification['created_at']))) : 'Complete the form to start review.' ?></p></div></div>
<?php if (!empty($verification['admin_notes'])): ?><div class="verify-document"><strong>Admin note:</strong><br><?= e($verification['admin_notes']) ?></div><?php endif; ?>
<?php if (!empty($verification['document_path'])): ?><div class="verify-document"><strong>Current document</strong><br><a href="<?= e(base_url($verification['document_path'])) ?>" target="_blank" rel="noopener"><?= e($verification['document_name']) ?></a></div><?php endif; ?>
<div class="verify-list"><div><span class="material-symbols-outlined">check_circle</span><p>Use the same business details shown on your official document.</p></div><div><span class="material-symbols-outlined">shield</span><p>Your document is only used for admin verification.</p></div><div><span class="material-symbols-outlined">schedule</span><p>After submission, the status changes to pending review.</p></div></div></aside></section>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
