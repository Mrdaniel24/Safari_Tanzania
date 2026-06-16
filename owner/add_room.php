<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$accId   = (int)($_GET['acc_id'] ?? $_POST['acc_id'] ?? 0);
$stmt    = $pdo->prepare("SELECT * FROM accommodations WHERE id = ? AND owner_id = ?");
$stmt->execute([$accId, $ownerId]);
$acc     = $stmt->fetch();
if (!$acc) { http_response_code(403); die('Property not found or access denied.'); }

$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rooms' AND COLUMN_NAME = 'room_amenities'");
$stmt->execute();
if ((int)$stmt->fetchColumn() === 0) $pdo->exec("ALTER TABLE rooms ADD COLUMN room_amenities TEXT NULL AFTER total_rooms");

$pdo->exec("CREATE TABLE IF NOT EXISTS room_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NULL,
    is_cover TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_room_image_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$errors = [];
$old = ['room_type' => '', 'price' => '', 'capacity' => '2', 'total_rooms' => '1', 'room_amenities' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $room_type      = trim($_POST['room_type']      ?? '');
    $price          = trim($_POST['price']           ?? '');
    $capacity       = (int)($_POST['capacity']       ?? 1);
    $total_rooms    = (int)($_POST['total_rooms']    ?? 1);
    $room_amenities = trim($_POST['room_amenities']  ?? '');
    $old            = compact('room_type', 'price', 'capacity', 'total_rooms', 'room_amenities');

    if ($room_type === '' || mb_strlen($room_type) > 100) $errors[] = 'Room type name is required.';
    if (!is_numeric($price) || (float)$price <= 0) $errors[] = 'Price must be a positive number.';
    if ($capacity < 1 || $capacity > 20) $errors[] = 'Capacity must be between 1 and 20 guests.';
    if ($total_rooms < 1 || $total_rooms > 500) $errors[] = 'Number of units must be between 1 and 500.';

    $cropped = array_values(array_filter((array)($_POST['cropped_images'] ?? [])));
    if (empty($cropped)) $errors[] = 'Upload at least one room image.';
    if (count($cropped) > 4) $errors[] = 'Upload no more than 4 room images.';

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO rooms (accommodation_id, room_type, price, capacity, total_rooms, room_amenities) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$accId, $room_type, (float)$price, $capacity, $total_rooms, $room_amenities ?: null]);
        $roomId = (int)$pdo->lastInsertId();

        $uploadDir = __DIR__ . '/../public/uploads/rooms';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        foreach ($cropped as $idx => $b64) {
            if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $b64, $m)) continue;
            $imgData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $b64));
            if (!$imgData || strlen($imgData) > 6 * 1024 * 1024) continue;
            $ext      = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $safeName = 'room_' . $roomId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (file_put_contents($uploadDir . '/' . $safeName, $imgData) !== false) {
                $path = base_url('public/uploads/rooms/' . $safeName);
                $pdo->prepare('INSERT INTO room_images (room_id, image_path, original_name, is_cover, sort_order) VALUES (?, ?, ?, ?, ?)')->execute([$roomId, $path, 'photo-' . ($idx + 1) . '.jpg', $idx === 0 ? 1 : 0, $idx]);
            }
        }
        flash_set('success', 'Room type added successfully.');
        redirect('owner/rooms.php?acc_id=' . $accId);
    }
}

$activePage = 'properties';
$pageTitle  = 'Add Room';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/crop-upload.css')) ?>?v=2">
<style>
.room-page { padding:28px 20px 42px; }
.room-inner { max-width:920px; margin:0 auto; display:grid; gap:18px; }
.room-card { border:1px solid rgba(15,36,82,.08); background:rgba(255,255,255,.92); box-shadow:0 18px 44px rgba(7,20,47,.07); border-radius:24px; padding:24px; }
.room-field label { display:block; font-weight:750; color:#0F1E3A; margin-bottom:7px; font-size:.9rem; }
.room-field input, .room-field textarea { width:100%; border:1px solid rgba(15,36,82,.14); border-radius:16px; padding:13px 14px; color:#07142F; background:#fff; outline:none; font-size:.94rem; }
.room-field textarea { min-height:90px; resize:vertical; }
.room-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
.room-alert { display:flex; gap:10px; border-radius:16px; padding:13px 14px; font-size:.9rem; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; margin-bottom:10px; }
.room-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:12px 16px; font-weight:780; border:none; cursor:pointer; font-family:inherit; text-decoration:none; }
.room-btn.primary { color:#fff; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); box-shadow:0 16px 34px rgba(15,123,217,.22); }
.room-btn.soft { color:#0B1E5B; background:#EEF6FF; border:1px solid rgba(15,123,217,.16); }
@media(max-width:760px){ .room-grid { grid-template-columns:1fr; } }
</style>

<div class="room-page"><div class="room-inner">

<a href="<?= e(base_url('owner/rooms.php?acc_id=' . $accId)) ?>" class="room-btn soft"><span class="material-symbols-outlined">arrow_back</span>Back to rooms</a>

<div class="room-card">
  <h2 class="text-2xl font-bold text-slate-900">Add Room Type</h2>
  <p class="text-slate-500 text-sm mt-1 mb-5">Adding to: <strong><?= e($acc['name']) ?></strong> — images cropped to <strong>4:3</strong> (960&times;720 px)</p>

  <?php foreach ($errors as $err): ?>
    <div class="room-alert"><span class="material-symbols-outlined">error</span><span><?= e($err) ?></span></div>
  <?php endforeach; ?>

  <form method="post" id="room-form" class="grid gap-5">
    <input type="hidden" name="_csrf"   value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="acc_id"  value="<?= $accId ?>">

    <div class="room-field">
      <label>Room Category / Type</label>
      <input name="room_type" maxlength="100" required value="<?= e($old['room_type']) ?>" placeholder="VIP, Regular, Single, Double, Suite, Family Room">
    </div>

    <div class="room-grid">
      <div class="room-field"><label>Price / Night (Tsh)</label><input type="number" name="price" min="1" step="0.01" required value="<?= e($old['price']) ?>"></div>
      <div class="room-field"><label>Max Guests</label><input type="number" name="capacity" min="1" max="20" required value="<?= e($old['capacity']) ?>"></div>
      <div class="room-field"><label>Total Units</label><input type="number" name="total_rooms" min="1" max="500" required value="<?= e($old['total_rooms']) ?>"></div>
    </div>

    <div class="room-field">
      <label>Room Amenities / Notes</label>
      <textarea name="room_amenities" placeholder="AC, private bathroom, balcony, hot shower..."><?= e($old['room_amenities']) ?></textarea>
    </div>

    <!-- Crop upload section -->
    <div>
      <p class="text-sm font-bold text-slate-700 mb-3">Room Images <span class="font-normal text-red-500">*</span> <span class="font-normal text-slate-400">(1–4 photos)</span></p>
      <div class="flex flex-wrap items-center gap-3 mb-3">
        <button type="button" id="room-add-btn" class="cu-trigger">
          <span class="material-symbols-outlined" style="font-size:18px;">add_photo_alternate</span>Add Image
        </button>
        <span class="text-xs text-slate-400">Crop ratio 4:3 · 960&times;720 px · show bed, bathroom, view, details</span>
      </div>
      <div class="cu-queue-wrap">
        <div class="cu-queue-label">
          <span>Cropped images ready to submit</span>
          <span id="room-count" class="text-xs font-bold"></span>
        </div>
        <div id="room-queue" class="cu-queue"></div>
      </div>
      <div id="room-hidden-inputs"></div>
    </div>

    <div class="flex flex-wrap gap-3">
      <button type="submit" id="room-submit-btn" class="room-btn primary" disabled>
        <span class="material-symbols-outlined">add</span>Add Room
      </button>
      <a href="<?= e(base_url('owner/rooms.php?acc_id=' . $accId)) ?>" class="room-btn soft">Cancel</a>
    </div>
  </form>
</div>

</div></div>

<!-- File picker outside the form -->
<input type="file" id="room-file-pick" accept="image/*" style="display:none" tabindex="-1">

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script src="<?= e(base_url('assets/js/crop-upload.js')) ?>?v=2"></script>
<script>
new CropUploader({
    triggerBtn:      document.getElementById('room-add-btn'),
    fileInput:       document.getElementById('room-file-pick'),
    queueEl:         document.getElementById('room-queue'),
    hiddenContainer: document.getElementById('room-hidden-inputs'),
    submitBtn:       document.getElementById('room-submit-btn'),
    countEl:         document.getElementById('room-count'),
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
