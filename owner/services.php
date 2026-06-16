<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$accId   = (int)($_GET['acc_id'] ?? $_POST['acc_id'] ?? 0);
$stmt    = $pdo->prepare('SELECT * FROM accommodations WHERE id = ? AND owner_id = ?');
$stmt->execute([$accId, $ownerId]);
$acc     = $stmt->fetch();
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

    /* ── Delete service ─────────────────────────────────────────────── */
    if (($_POST['action'] ?? '') === 'delete') {
        $sid = (int)($_POST['service_id'] ?? 0);
        $s = $pdo->prepare('SELECT s.id FROM accommodation_services s JOIN accommodations a ON a.id = s.accommodation_id WHERE s.id = ? AND a.owner_id = ?');
        $s->execute([$sid, $ownerId]);
        if ($s->fetch()) {
            $imgs = $pdo->prepare('SELECT image_path FROM service_images WHERE service_id = ?');
            $imgs->execute([$sid]);
            foreach ($imgs->fetchAll() as $img) {
                $f = __DIR__ . '/../public/uploads/services/' . basename($img['image_path']);
                if (str_contains($img['image_path'], '/uploads/') && file_exists($f)) @unlink($f);
            }
            $pdo->prepare('DELETE FROM accommodation_services WHERE id = ?')->execute([$sid]);
            flash_set('success', 'Service removed.');
        }
        redirect('owner/services.php?acc_id=' . $accId);
    }

    /* ── Add service ────────────────────────────────────────────────── */
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = trim($_POST['price']       ?? '');
    $is_visible  = !empty($_POST['is_visible']) ? '1' : '0';
    $old         = compact('name', 'description', 'price', 'is_visible');

    if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Service name is required.';
    if ($price !== '' && (!is_numeric($price) || (float)$price < 0)) $errors[] = 'Price must be a valid positive number.';

    $cropped = array_values(array_filter((array)($_POST['cropped_images'] ?? [])));
    if (count($cropped) > 4) $errors[] = 'Upload no more than 4 images per service.';

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO accommodation_services (accommodation_id, name, description, price, is_visible) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$accId, $name, $description ?: null, $price === '' ? null : (float)$price, (int)$is_visible]);
        $serviceId = (int)$pdo->lastInsertId();

        if ($cropped) {
            $uploadDir = __DIR__ . '/../public/uploads/services';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            foreach ($cropped as $idx => $b64) {
                if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $b64, $m)) continue;
                $imgData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $b64));
                if (!$imgData || strlen($imgData) > 6 * 1024 * 1024) continue;
                $ext      = $m[1] === 'jpeg' ? 'jpg' : $m[1];
                $safeName = 'service_' . $serviceId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (file_put_contents($uploadDir . '/' . $safeName, $imgData) !== false) {
                    $path = base_url('public/uploads/services/' . $safeName);
                    $pdo->prepare('INSERT INTO service_images (service_id, image_path, original_name, sort_order) VALUES (?, ?, ?, ?)')->execute([$serviceId, $path, 'photo-' . ($idx + 1) . '.jpg', $idx]);
                }
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
$pageTitle  = 'Services';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/crop-upload.css')) ?>?v=2">
<style>
.service-page { padding:28px 20px 42px; }
.service-inner { max-width:1120px; margin:0 auto; display:grid; gap:18px; }
.service-card { border:1px solid rgba(15,36,82,.08); background:rgba(255,255,255,.92); box-shadow:0 18px 44px rgba(7,20,47,.07); border-radius:24px; padding:24px; }
.service-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; align-items:start; }
.service-field label { display:block; font-weight:750; color:#0F1E3A; margin-bottom:7px; font-size:.9rem; }
.service-field input, .service-field select, .service-field textarea { width:100%; border:1px solid rgba(15,36,82,.14); border-radius:16px; padding:13px 14px; color:#07142F; background:#fff; outline:none; font-size:.94rem; }
.service-field textarea { min-height:100px; resize:vertical; }
.service-alert { display:flex; gap:10px; border-radius:16px; padding:13px 14px; font-size:.9rem; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; margin-bottom:10px; }
.service-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:12px 16px; font-weight:780; border:none; cursor:pointer; font-family:inherit; text-decoration:none; }
.service-btn.primary { color:#fff; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); box-shadow:0 16px 34px rgba(15,123,217,.22); }
.service-btn.soft { color:#0B1E5B; background:#EEF6FF; border:1px solid rgba(15,123,217,.16); }
.service-list { display:grid; gap:12px; }
.service-item { border:1px solid rgba(15,36,82,.08); border-radius:18px; padding:16px; background:#fff; }
.service-del-btn { display:inline-flex; align-items:center; gap:5px; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; border-radius:999px; padding:5px 12px; font-size:.78rem; font-weight:700; cursor:pointer; font-family:inherit; }
.service-del-btn:hover { background:#FEE2E2; }
@media(max-width:900px){ .service-grid { grid-template-columns:1fr; } }
</style>

<div class="service-page"><div class="service-inner">

<a href="<?= e(base_url('owner/properties.php')) ?>" class="service-btn soft"><span class="material-symbols-outlined">arrow_back</span>Back to properties</a>

<div class="service-card">
  <h2 class="text-2xl font-bold text-slate-900">Services for <?= e($acc['name']) ?></h2>
  <p class="text-slate-500 text-sm mt-1">Add breakfast, pool, parking, tours, laundry, spa, and other services. Service images are cropped to <strong>4:3</strong> (960&times;720 px).</p>
</div>

<div class="service-grid">

  <!-- Add service form -->
  <div class="service-card">
    <h3 class="text-xl font-bold text-slate-900 mb-4">Add Service</h3>
    <?php foreach ($errors as $err): ?>
      <div class="service-alert"><span class="material-symbols-outlined">error</span><span><?= e($err) ?></span></div>
    <?php endforeach; ?>

    <form method="post" id="svc-form" class="grid gap-4">
      <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acc_id"  value="<?= (int)$accId ?>">

      <div class="service-field">
        <label>Service Name</label>
        <select name="name" required>
          <option value="">Choose service</option>
          <?php foreach ($serviceOptions as $opt): ?>
            <option value="<?= e($opt) ?>" <?= $old['name'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="service-field">
        <label>Description</label>
        <textarea name="description" placeholder="Describe what guests get..."><?= e($old['description']) ?></textarea>
      </div>

      <div class="service-field">
        <label>Price Optional (Tsh)</label>
        <input type="number" name="price" min="0" step="0.01" value="<?= e($old['price']) ?>" placeholder="Leave blank if free/included">
      </div>

      <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="checkbox" name="is_visible" value="1" <?= $old['is_visible'] === '1' ? 'checked' : '' ?>>
        Show to customers
      </label>

      <!-- Crop upload section -->
      <div>
        <p class="text-sm font-bold text-slate-700 mb-3">Service Images <span class="font-normal text-slate-400">(optional, max 4)</span></p>
        <div class="flex flex-wrap items-center gap-3 mb-3">
          <button type="button" id="svc-add-btn" class="cu-trigger">
            <span class="material-symbols-outlined" style="font-size:18px;">add_photo_alternate</span>Add Image
          </button>
          <span class="text-xs text-slate-400">Crop ratio 4:3 · 960&times;720 px</span>
        </div>
        <div class="cu-queue-wrap">
          <div class="cu-queue-label">
            <span>Cropped images</span>
            <span id="svc-count" class="text-xs font-bold"></span>
          </div>
          <div id="svc-queue" class="cu-queue"></div>
        </div>
        <div id="svc-hidden-inputs"></div>
      </div>

      <button type="submit" class="service-btn primary"><span class="material-symbols-outlined">add</span>Add Service</button>
    </form>
  </div>

  <!-- Current services -->
  <div class="service-card">
    <h3 class="text-xl font-bold text-slate-900 mb-4">Current Services</h3>
    <div class="service-list">
      <?php if (!$services): ?>
        <p class="text-slate-500">No services added yet.</p>
      <?php endif; ?>
      <?php foreach ($services as $s): ?>
        <div class="service-item">
          <div class="flex justify-between gap-3 flex-wrap">
            <div>
              <h4 class="font-bold text-slate-900"><?= e($s['name']) ?></h4>
              <p class="text-sm text-slate-500 mt-1"><?= e($s['description'] ?? 'No description') ?></p>
            </div>
            <span class="text-xs font-bold <?= (int)$s['is_visible'] ? 'text-emerald-700' : 'text-slate-400' ?>">
              <?= (int)$s['is_visible'] ? 'Visible' : 'Hidden' ?>
            </span>
          </div>
          <div class="flex items-center justify-between mt-3 flex-wrap gap-2">
            <p class="text-sm text-slate-600">
              <?= $s['price'] !== null ? 'Tsh ' . number_format((float)$s['price'], 2) : 'No fixed price' ?>
              &nbsp;·&nbsp; <?= (int)$s['image_count'] ?> image<?= (int)$s['image_count'] === 1 ? '' : 's' ?>
            </p>
            <form method="post" onsubmit="return confirm('Remove this service?');" style="margin:0;">
              <input type="hidden" name="_csrf"       value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="acc_id"      value="<?= (int)$accId ?>">
              <input type="hidden" name="action"      value="delete">
              <input type="hidden" name="service_id"  value="<?= (int)$s['id'] ?>">
              <button type="submit" class="service-del-btn"><span class="material-symbols-outlined" style="font-size:14px;">delete</span>Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
</div></div>

<!-- File picker outside the form -->
<input type="file" id="svc-file-pick" accept="image/*" style="display:none" tabindex="-1">

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script src="<?= e(base_url('assets/js/crop-upload.js')) ?>?v=2"></script>
<script>
new CropUploader({
    triggerBtn:      document.getElementById('svc-add-btn'),
    fileInput:       document.getElementById('svc-file-pick'),
    queueEl:         document.getElementById('svc-queue'),
    hiddenContainer: document.getElementById('svc-hidden-inputs'),
    submitBtn:       null,
    countEl:         document.getElementById('svc-count'),
    maxImages:       4,
    inputName:       'cropped_images[]',
    aspectRatio:     4 / 3,
    outputWidth:     960,
    outputHeight:    720,
    ratioLabel:      '4 : 3',
    sizeLabel:       '960 × 720 px',
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
