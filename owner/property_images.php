<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$accId   = (int)($_GET['acc_id'] ?? $_POST['acc_id'] ?? 0);

$pdo->exec("CREATE TABLE IF NOT EXISTS accommodation_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accommodation_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NULL,
    is_cover TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_acc_image_acc FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$stmt = $pdo->prepare('SELECT * FROM accommodations WHERE id = ? AND owner_id = ?');
$stmt->execute([$accId, $ownerId]);
$property = $stmt->fetch();
if (!$property) { http_response_code(404); die('Accommodation not found.'); }

$errors = [];

function load_images(PDO $pdo, int $accId): array {
    $s = $pdo->prepare('SELECT * FROM accommodation_images WHERE accommodation_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC');
    $s->execute([$accId]);
    return $s->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    $action = $_POST['action'] ?? 'upload';

    /* ── Set cover ─────────────────────────────────────────────────── */
    if ($action === 'cover') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        $s = $pdo->prepare('SELECT * FROM accommodation_images WHERE id = ? AND accommodation_id = ?');
        $s->execute([$imageId, $accId]);
        $img = $s->fetch();
        if ($img) {
            $pdo->prepare('UPDATE accommodation_images SET is_cover = 0 WHERE accommodation_id = ?')->execute([$accId]);
            $pdo->prepare('UPDATE accommodation_images SET is_cover = 1 WHERE id = ?')->execute([$imageId]);
            $pdo->prepare('UPDATE accommodations SET image_url = ? WHERE id = ? AND owner_id = ?')->execute([$img['image_path'], $accId, $ownerId]);
            flash_set('success', 'Cover image updated.');
        } else {
            flash_set('error', 'Image not found.');
        }
        redirect('owner/property_images.php?acc_id=' . $accId);
    }

    /* ── Delete image ──────────────────────────────────────────────── */
    if ($action === 'delete') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        $s = $pdo->prepare('SELECT * FROM accommodation_images WHERE id = ? AND accommodation_id = ?');
        $s->execute([$imageId, $accId]);
        $img = $s->fetch();
        if ($img) {
            $localFile = __DIR__ . '/../public/uploads/accommodations/' . basename($img['image_path']);
            if (str_contains($img['image_path'], '/uploads/') && file_exists($localFile)) @unlink($localFile);
            $pdo->prepare('DELETE FROM accommodation_images WHERE id = ?')->execute([$imageId]);
            if ((int)$img['is_cover'] === 1) {
                $first = $pdo->prepare('SELECT id, image_path FROM accommodation_images WHERE accommodation_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
                $first->execute([$accId]);
                $newCover = $first->fetch();
                if ($newCover) {
                    $pdo->prepare('UPDATE accommodation_images SET is_cover = 1 WHERE id = ?')->execute([$newCover['id']]);
                    $pdo->prepare('UPDATE accommodations SET image_url = ? WHERE id = ? AND owner_id = ?')->execute([$newCover['image_path'], $accId, $ownerId]);
                }
            }
            flash_set('success', 'Image deleted.');
        } else {
            flash_set('error', 'Image not found.');
        }
        redirect('owner/property_images.php?acc_id=' . $accId);
    }

    /* ── Upload cropped images ─────────────────────────────────────── */
    if ($action === 'upload') {
        $existing  = load_images($pdo, $accId);
        $remaining = 6 - count($existing);

        $cropped = array_values(array_filter((array)($_POST['cropped_images'] ?? [])));

        if (empty($cropped)) {
            $errors[] = 'Crop and add at least one image before uploading.';
        } elseif (count($cropped) > $remaining) {
            $errors[] = 'You can only add ' . $remaining . ' more image' . ($remaining === 1 ? '' : 's') . '.';
        } else {
            $uploadDir = __DIR__ . '/../public/uploads/accommodations';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            $startOrder = count($existing);
            $added = 0;
            foreach ($cropped as $b64) {
                if (!preg_match('/^data:image\/(jpeg|png|webp);base64,/', $b64, $m)) continue;
                $imgData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $b64));
                if (!$imgData || strlen($imgData) > 6 * 1024 * 1024) { $errors[] = 'One image was too large.'; continue; }
                $ext      = $m[1] === 'jpeg' ? 'jpg' : $m[1];
                $safeName = 'acc_' . $accId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (file_put_contents($uploadDir . '/' . $safeName, $imgData) !== false) {
                    $relPath  = base_url('public/uploads/accommodations/' . $safeName);
                    $isCover  = (count($existing) === 0 && $added === 0) ? 1 : 0;
                    $pdo->prepare('INSERT INTO accommodation_images (accommodation_id, image_path, original_name, is_cover, sort_order) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$accId, $relPath, 'photo-' . ($startOrder + $added + 1) . '.jpg', $isCover, $startOrder + $added]);
                    if ($isCover) $pdo->prepare('UPDATE accommodations SET image_url = ? WHERE id = ? AND owner_id = ?')->execute([$relPath, $accId, $ownerId]);
                    $added++;
                }
            }
            if (!$errors) {
                flash_set('success', $added . ' image' . ($added === 1 ? '' : 's') . ' uploaded.');
                redirect('owner/property_images.php?acc_id=' . $accId);
            }
        }
    }
}

$images    = load_images($pdo, $accId);
$remaining = max(0, 6 - count($images));

$activePage = 'properties';
$pageTitle  = 'Accommodation Images';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/crop-upload.css')) ?>?v=2">
<style>
.img-page { padding:28px 20px 48px; }
.img-inner { max-width:1120px; margin:0 auto; display:grid; gap:20px; }
.img-hero { border-radius:26px; padding:clamp(22px,4vw,34px); color:#fff; background:linear-gradient(135deg,rgba(7,20,47,.96),rgba(11,30,91,.76)); box-shadow:0 26px 70px rgba(7,20,47,.18); }
.img-hero h2 { font-size:clamp(1.6rem,4vw,2.8rem); font-weight:800; margin:8px 0 10px; line-height:1.1; }
.img-hero p { color:rgba(255,255,255,.72); line-height:1.6; }
.img-card { border:1px solid rgba(15,36,82,.08); background:rgba(255,255,255,.92); box-shadow:0 18px 44px rgba(7,20,47,.07); border-radius:24px; padding:24px; }
.img-alert { display:flex; gap:10px; align-items:flex-start; border-radius:14px; padding:12px 14px; font-size:.9rem; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; margin-bottom:12px; }
.img-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
.img-tile { border-radius:18px; overflow:hidden; border:1px solid rgba(15,36,82,.08); background:#fff; box-shadow:0 10px 28px rgba(7,20,47,.06); }
.img-tile-photo { width:100%; aspect-ratio:16/9; object-fit:cover; display:block; }
.img-meta { padding:10px 12px; display:flex; align-items:center; justify-content:space-between; gap:8px; border-top:1px solid #F1F5F9; }
.img-pill { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; background:#E8F8F0; color:#047857; font-size:.74rem; font-weight:800; }
.img-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; border-radius:999px; padding:10px 16px; font-weight:750; transition:.2s ease; border:none; cursor:pointer; font-family:inherit; font-size:.88rem; }
.img-btn.soft { color:#0B1E5B; background:#EEF6FF; border:1px solid rgba(15,123,217,.18); }
.img-btn.soft:hover { background:#DBF0FF; }
.img-btn-del { background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; font-size:.8rem; padding:6px 12px; }
.img-btn-del:hover { background:#FEE2E2; }
@media (max-width:820px) { .img-grid { grid-template-columns:1fr 1fr; } }
@media (max-width:540px) { .img-grid { grid-template-columns:1fr; } }
</style>

<div class="img-page"><div class="img-inner">

  <section class="img-hero">
    <div class="text-xs font-bold uppercase tracking-widest text-cyan-200 mb-1">Property Gallery</div>
    <h2><?= e($property['name']) ?></h2>
    <p>Upload up to 6 photos cropped to <strong>16:9 landscape</strong> (1280&times;720 px). Every accommodation will have consistent, professional-quality images.</p>
  </section>

  <!-- Upload card -->
  <?php if ($remaining > 0): ?>
  <div class="img-card">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div>
        <h3 class="text-lg font-bold text-slate-900">Add Images</h3>
        <p class="text-sm text-slate-500 mt-0.5"><?= count($images) ?> of 6 uploaded &nbsp;·&nbsp; <?= $remaining ?> slot<?= $remaining !== 1 ? 's' : '' ?> remaining</p>
      </div>
      <a href="<?= e(base_url('owner/properties.php')) ?>" class="img-btn soft"><span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>Back</a>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="img-alert"><span class="material-symbols-outlined" style="font-size:18px;">error</span><span><?= e($err) ?></span></div>
    <?php endforeach; ?>

    <!-- The actual upload form (no enctype needed — base64 in hidden inputs) -->
    <form method="post" id="img-upload-form">
      <input type="hidden" name="_csrf"    value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="acc_id"  value="<?= (int)$accId ?>">
      <input type="hidden" name="action"  value="upload">

      <div class="flex flex-wrap items-center gap-3 mb-4">
        <button type="button" id="add-img-btn" class="cu-trigger">
          <span class="material-symbols-outlined" style="font-size:20px;">add_photo_alternate</span>Add Image
        </button>
        <span class="text-xs text-slate-400">Select one image at a time, crop it, then add more.</span>
      </div>

      <!-- Thumbnail queue -->
      <div class="cu-queue-wrap">
        <div class="cu-queue-label">
          <span>Cropped images ready to upload</span>
          <span id="crop-count" class="text-xs font-bold text-primary"></span>
        </div>
        <div id="img-queue" class="cu-queue"></div>
      </div>

      <!-- Hidden base64 inputs appended here by CropUploader -->
      <div id="img-hidden-inputs"></div>

      <div class="flex flex-wrap items-center gap-3 mt-5">
        <button type="submit" id="img-submit-btn" class="cu-submit" disabled>
          <span class="material-symbols-outlined" style="font-size:20px;">cloud_upload</span>Upload Images
        </button>
        <p class="text-xs text-slate-400">Images are saved at 1280&times;720 px JPEG — all accommodations will look the same.</p>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="img-card flex items-center justify-between flex-wrap gap-4">
    <div>
      <p class="font-bold text-slate-900">Gallery full — 6 of 6 images uploaded.</p>
      <p class="text-sm text-slate-500 mt-1">Delete an image below to make room for a new one.</p>
    </div>
    <a href="<?= e(base_url('owner/properties.php')) ?>" class="img-btn soft"><span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>Back</a>
  </div>
  <?php endif; ?>

  <!-- Gallery grid -->
  <?php if (!$images): ?>
    <div class="img-card text-center py-16">
      <span class="material-symbols-outlined text-slate-300" style="font-size:64px;">photo_library</span>
      <p class="text-slate-500 mt-4 font-semibold">No images yet</p>
      <p class="text-slate-400 text-sm mt-1">Add your first accommodation photo above.</p>
    </div>
  <?php else: ?>
    <div class="img-card">
      <h3 class="text-base font-bold text-slate-900 mb-4">Gallery <span class="text-slate-400 font-normal">(<?= count($images) ?>)</span></h3>
      <div class="img-grid">
        <?php foreach ($images as $img): ?>
          <div class="img-tile">
            <img src="<?= e($img['image_path']) ?>" alt="Property image" class="img-tile-photo">
            <div class="img-meta">
              <?php if ((int)$img['is_cover'] === 1): ?>
                <span class="img-pill"><span class="material-symbols-outlined" style="font-size:13px;">star</span>&nbsp;Cover</span>
              <?php else: ?>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="_csrf"     value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="acc_id"   value="<?= (int)$accId ?>">
                  <input type="hidden" name="action"   value="cover">
                  <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                  <button type="submit" class="img-btn soft" style="padding:5px 12px;font-size:.78rem;">Set cover</button>
                </form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('Delete this image?');" style="margin:0;">
                <input type="hidden" name="_csrf"     value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acc_id"   value="<?= (int)$accId ?>">
                <input type="hidden" name="action"   value="delete">
                <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                <button type="submit" class="img-btn img-btn-del"><span class="material-symbols-outlined" style="font-size:15px;">delete</span>Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div></div>

<!-- File picker — OUTSIDE the upload form; JS-only use, no name attribute -->
<input type="file" id="raw-file-pick" accept="image/*" style="display:none" tabindex="-1">

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script src="<?= e(base_url('assets/js/crop-upload.js')) ?>?v=2"></script>
<script>
new CropUploader({
    triggerBtn:      document.getElementById('add-img-btn'),
    fileInput:       document.getElementById('raw-file-pick'),
    queueEl:         document.getElementById('img-queue'),
    hiddenContainer: document.getElementById('img-hidden-inputs'),
    submitBtn:       document.getElementById('img-submit-btn'),
    countEl:         document.getElementById('crop-count'),
    maxImages:       <?= $remaining ?>,
    inputName:       'cropped_images[]',
    aspectRatio:     16 / 9,
    outputWidth:     1280,
    outputHeight:    720,
    ratioLabel:      '16 : 9',
    sizeLabel:       '1280 × 720 px',
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
