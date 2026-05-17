<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$accId = (int)($_GET['acc_id'] ?? $_POST['acc_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM accommodations WHERE id = ? AND owner_id = ?');
$stmt->execute([$accId, $ownerId]);
$acc = $stmt->fetch();
if (!$acc) { http_response_code(403); die('Accommodation not found.'); }

$pdo->exec("CREATE TABLE IF NOT EXISTS accommodation_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accommodation_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_acc FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
) ENGINE=InnoDB");
$pdo->exec("CREATE TABLE IF NOT EXISTS service_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_image_service FOREIGN KEY (service_id) REFERENCES accommodation_services(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$errors = [];
$old = ['name' => '', 'description' => '', 'price' => '', 'is_visible' => '1'];
$serviceOptions = ['Breakfast', 'Restaurant / Food', 'Swimming Pool', 'Parking', 'Airport Pickup', 'Tour Guide', 'Laundry', 'Conference Hall', 'Spa', 'Gym', 'Bar', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $is_visible = !empty($_POST['is_visible']) ? '1' : '0';
    $old = compact('name', 'description', 'price', 'is_visible');

    if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Service name is required.';
    if ($price !== '' && (!is_numeric($price) || (float)$price < 0)) $errors[] = 'Price must be a valid number.';

    $uploads = [];
    $files = $_FILES['service_images'] ?? null;
    $picked = 0;
    if ($files && isset($files['name']) && is_array($files['name'])) {
        foreach ($files['name'] as $idx => $fileName) {
            $err = $files['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            $picked++;
            if ($err !== UPLOAD_ERR_OK) { $errors[] = 'One service image failed to upload.'; continue; }
            if (($files['size'][$idx] ?? 0) > 5 * 1024 * 1024) { $errors[] = 'Each service image must be 5MB or smaller.'; continue; }
            $tmp = $files['tmp_name'][$idx] ?? '';
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $tmp ? finfo_file($finfo, $tmp) : '';
            finfo_close($finfo);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) { $errors[] = 'Service images must be JPG, PNG, or WEBP.'; continue; }
            $uploads[] = ['tmp' => $tmp, 'ext' => $allowed[$mime], 'name' => basename($fileName)];
        }
    }
    if ($picked > 4) $errors[] = 'Upload no more than 4 images for one service.';

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO accommodation_services (accommodation_id, name, description, price, is_visible) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$accId, $name, $description ?: null, $price === '' ? null : (float)$price, (int)$is_visible]);
        $serviceId = (int)$pdo->lastInsertId();
        $uploadDir = __DIR__ . '/../public/uploads/services';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        foreach ($uploads as $idx => $img) {
            $safeName = 'service_' . $serviceId . '_' . bin2hex(random_bytes(8)) . '.' . $img['ext'];
            $target = $uploadDir . '/' . $safeName;
            if (move_uploaded_file($img['tmp'], $target)) {
                $path = base_url('public/uploads/services/' . $safeName);
                $stmt = $pdo->prepare('INSERT INTO service_images (service_id, image_path, original_name, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$serviceId, $path, $img['name'], $idx]);
            }
        }
        flash_set('success', 'Service added successfully.');
        redirect('owner/services.php?acc_id=' . $accId);
    }
}

$stmt = $pdo->prepare('SELECT s.*, COUNT(si.id) AS image_count FROM accommodation_services s LEFT JOIN service_images si ON si.service_id = s.id WHERE s.accommodation_id = ? GROUP BY s.id ORDER BY s.created_at DESC');
$stmt->execute([$accId]);
$services = $stmt->fetchAll();

$activePage = 'properties';
$pageTitle = 'Services';
include __DIR__ . '/../includes/header.php';
?>
<style>
.service-page { padding:28px 20px 42px; }
.service-inner { max-width:1120px; margin:0 auto; display:grid; gap:18px; }
.service-card { border:1px solid rgba(15,36,82,.08); background:rgba(255,255,255,.92); box-shadow:0 18px 44px rgba(7,20,47,.07); border-radius:24px; padding:24px; }
.service-grid { display:grid; grid-template-columns: 1fr 1fr; gap:18px; align-items:start; }
.service-field label, .service-upload label { display:block; font-weight:750; color:#0F1E3A; margin-bottom:7px; font-size:.9rem; }
.service-field input, .service-field select, .service-field textarea, .service-upload input { width:100%; border:1px solid rgba(15,36,82,.14); border-radius:16px; padding:13px 14px; color:#07142F; background:#fff; outline:none; font-size:.94rem; }
.service-field textarea { min-height:100px; resize:vertical; }
.service-upload { border:1px dashed rgba(15,123,217,.30); background:rgba(30,198,255,.06); border-radius:18px; padding:18px; }
.service-alert { display:flex; gap:10px; border-radius:16px; padding:13px 14px; font-size:.9rem; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; }
.service-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:12px 16px; font-weight:780; }
.service-btn.primary { color:#fff; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); box-shadow:0 16px 34px rgba(15,123,217,.22); }
.service-btn.soft { color:#0B1E5B; background:#EEF6FF; border:1px solid rgba(15,123,217,.16); }
.service-list { display:grid; gap:12px; }
.service-item { border:1px solid rgba(15,36,82,.08); border-radius:18px; padding:16px; background:#fff; }
@media(max-width:900px){ .service-grid { grid-template-columns:1fr; } }
</style>
<div class="service-page"><div class="service-inner">
<a href="<?= e(base_url('owner/properties.php')) ?>" class="service-btn soft"><span class="material-symbols-outlined">arrow_back</span>Back to properties</a>
<div class="service-card"><h2 class="text-2xl font-bold text-slate-900">Services for <?= e($acc['name']) ?></h2><p class="text-slate-500 text-sm mt-1">Add breakfast, food, swimming pool, parking, tours, laundry, spa, and other services.</p></div>
<div class="service-grid"><div class="service-card">
<h3 class="text-xl font-bold text-slate-900 mb-4">Add Service</h3>
<?php foreach ($errors as $err): ?><div class="service-alert"><span class="material-symbols-outlined">error</span><span><?= e($err) ?></span></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="grid gap-4"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acc_id" value="<?= (int)$accId ?>">
<div class="service-field"><label>Service Name</label><select name="name" required><option value="">Choose service</option><?php foreach ($serviceOptions as $opt): ?><option value="<?= e($opt) ?>" <?= $old['name'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select></div>
<div class="service-field"><label>Description</label><textarea name="description" placeholder="Describe what guests get..."><?= e($old['description']) ?></textarea></div>
<div class="service-field"><label>Price Optional (USD)</label><input type="number" name="price" min="0" step="0.01" value="<?= e($old['price']) ?>" placeholder="Leave blank if free/included"></div>
<label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700"><input type="checkbox" name="is_visible" value="1" <?= $old['is_visible'] === '1' ? 'checked' : '' ?>> Show to customers</label>
<div class="service-upload"><label>Service Images</label><input type="file" name="service_images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="block text-slate-500 mt-2">Optional, max 4 images per service.</small></div>
<button class="service-btn primary" type="submit"><span class="material-symbols-outlined">add</span>Add Service</button></form></div>
<div class="service-card"><h3 class="text-xl font-bold text-slate-900 mb-4">Current Services</h3><div class="service-list">
<?php if (!$services): ?><p class="text-slate-500">No services added yet.</p><?php endif; ?>
<?php foreach ($services as $s): ?><div class="service-item"><div class="flex justify-between gap-3"><div><h4 class="font-bold text-slate-900"><?= e($s['name']) ?></h4><p class="text-sm text-slate-500 mt-1"><?= e($s['description'] ?? 'No description') ?></p></div><span class="text-xs font-bold <?= (int)$s['is_visible'] ? 'text-emerald-700' : 'text-slate-400' ?>"><?= (int)$s['is_visible'] ? 'Visible' : 'Hidden' ?></span></div><p class="text-sm text-slate-600 mt-2"><?= $s['price'] !== null ? '$' . number_format((float)$s['price'], 2) : 'No fixed price' ?> · <?= (int)$s['image_count'] ?> image<?= (int)$s['image_count'] === 1 ? '' : 's' ?></p></div><?php endforeach; ?>
</div></div></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
