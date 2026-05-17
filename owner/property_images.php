<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$accId = (int)($_GET['acc_id'] ?? $_POST['acc_id'] ?? 0);

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
    $stmt = $pdo->prepare('SELECT * FROM accommodation_images WHERE accommodation_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC');
    $stmt->execute([$accId]);
    return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);
    $action = $_POST['action'] ?? 'upload';

    if ($action === 'cover') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM accommodation_images WHERE id = ? AND accommodation_id = ?');
        $stmt->execute([$imageId, $accId]);
        $img = $stmt->fetch();
        if ($img) {
            $pdo->prepare('UPDATE accommodation_images SET is_cover = 0 WHERE accommodation_id = ?')->execute([$accId]);
            $pdo->prepare('UPDATE accommodation_images SET is_cover = 1 WHERE id = ?')->execute([$imageId]);
            $pdo->prepare('UPDATE accommodations SET image_url = ? WHERE id = ? AND owner_id = ?')->execute([$img['image_path'], $accId, $ownerId]);
            flash_set('success', 'Cover image updated.');
            redirect('owner/property_images.php?acc_id=' . $accId);
        }
        $errors[] = 'Image not found.';
    }
    if ($action === 'upload') {
        $existing = load_images($pdo, $accId);
        $remaining = 6 - count($existing);
        if ($remaining <= 0) {
            $errors[] = 'This accommodation already has the maximum of 6 images.';
        } else {
            $files = $_FILES['accommodation_images'] ?? null;
            $uploads = [];
            $picked = 0;
            if ($files && isset($files['name']) && is_array($files['name'])) {
                foreach ($files['name'] as $idx => $fileName) {
                    $err = $files['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
                    if ($err === UPLOAD_ERR_NO_FILE) continue;
                    $picked++;
                    if ($err !== UPLOAD_ERR_OK) { $errors[] = 'One image failed to upload.'; continue; }
                    if (($files['size'][$idx] ?? 0) > 5 * 1024 * 1024) { $errors[] = 'Each image must be 5MB or smaller.'; continue; }
                    $tmp = $files['tmp_name'][$idx] ?? '';
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $tmp ? finfo_file($finfo, $tmp) : '';
                    finfo_close($finfo);
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (!isset($allowed[$mime])) { $errors[] = 'Images must be JPG, PNG, or WEBP.'; continue; }
                    $uploads[] = ['tmp' => $tmp, 'ext' => $allowed[$mime], 'name' => basename($fileName)];
                }
            }
            if ($picked === 0) $errors[] = 'Choose at least one image.';
            if ($picked > $remaining) $errors[] = 'You can add only ' . $remaining . ' more image' . ($remaining === 1 ? '' : 's') . '.';

            if (!$errors) {
                $uploadDir = __DIR__ . '/../public/uploads/accommodations';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $startOrder = count($existing);
                foreach ($uploads as $idx => $img) {
                    $safeName = 'acc_' . $accId . '_' . bin2hex(random_bytes(8)) . '.' . $img['ext'];
                    $target = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($img['tmp'], $target)) {
                        $relativePath = base_url('public/uploads/accommodations/' . $safeName);
                        $isCover = count($existing) === 0 && $idx === 0 ? 1 : 0;
                        $stmt = $pdo->prepare('INSERT INTO accommodation_images (accommodation_id, image_path, original_name, is_cover, sort_order) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$accId, $relativePath, $img['name'], $isCover, $startOrder + $idx]);
                        if ($isCover) $pdo->prepare('UPDATE accommodations SET image_url = ? WHERE id = ? AND owner_id = ?')->execute([$relativePath, $accId, $ownerId]);
                    }
                }
                flash_set('success', 'Images uploaded successfully.');
                redirect('owner/property_images.php?acc_id=' . $accId);
            }
        }
    }
}

$images = load_images($pdo, $accId);
$remaining = max(0, 6 - count($images));

$activePage = 'properties';
$pageTitle = 'Accommodation Images';
include __DIR__ . '/../includes/header.php';
?>
<style>
.img-page { padding: 28px 20px 42px; }
.img-inner { max-width: 1120px; margin: 0 auto; display:grid; gap:20px; }
.img-hero { border-radius:28px; padding:clamp(24px,4vw,34px); color:#fff; background:linear-gradient(135deg, rgba(7,20,47,.96), rgba(11,30,91,.76)); box-shadow:0 26px 70px rgba(7,20,47,.18); }
.img-hero h2 { font-size:clamp(1.8rem,4vw,3rem); line-height:1; font-weight:800; margin:8px 0 10px; }
.img-hero p { color:rgba(255,255,255,.76); max-width:680px; line-height:1.6; }
.img-card { border:1px solid rgba(15,36,82,.08); background:rgba(255,255,255,.92); box-shadow:0 18px 44px rgba(7,20,47,.07); border-radius:24px; padding:22px; }
.img-alert { display:flex; gap:10px; align-items:flex-start; border-radius:16px; padding:13px 14px; font-size:.9rem; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; margin-bottom:12px; }
.img-upload { border:1px dashed rgba(15,123,217,.30); background:rgba(30,198,255,.06); border-radius:18px; padding:18px; }
.img-upload label { display:block; font-weight:750; color:#0F1E3A; margin-bottom:7px; font-size:.9rem; }
.img-upload input { width:100%; border:1px solid rgba(15,36,82,.14); border-radius:16px; padding:12px; background:#fff; }
.img-upload small { display:block; color:#6B7B99; margin-top:8px; font-size:.82rem; }
.img-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:12px 16px; font-weight:780; transition:.2s ease; }
.img-btn.primary { color:#fff; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); box-shadow:0 16px 34px rgba(15,123,217,.22); }
.img-btn.soft { color:#0B1E5B; background:#EEF6FF; border:1px solid rgba(15,123,217,.16); }
.img-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:16px; }
.img-tile { border-radius:20px; overflow:hidden; border:1px solid rgba(15,36,82,.08); background:#fff; box-shadow:0 14px 34px rgba(7,20,47,.06); }
.img-tile img { width:100%; aspect-ratio: 4 / 3; object-fit:cover; display:block; }
.img-meta { padding:12px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
.img-pill { display:inline-flex; align-items:center; border-radius:999px; padding:6px 10px; background:#E8F8F0; color:#047857; font-size:.74rem; font-weight:800; }
@media (max-width: 820px) { .img-grid { grid-template-columns:1fr; } }
</style>
<div class="img-page"><div class="img-inner">
  <section class="img-hero">
    <div class="text-xs font-bold uppercase text-cyan-200">Phase 2</div>
    <h2><?= e($property['name']) ?> images</h2>
    <p>Upload up to 6 real accommodation photos. The cover photo is used in listing cards and the main details hero.</p>
  </section>
  <div class="img-card">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
      <div><h3 class="text-xl font-bold text-slate-900">Accommodation gallery</h3><p class="text-sm text-slate-500 mt-1"><?= count($images) ?> of 6 images uploaded.</p></div>
      <a href="<?= e(base_url('owner/properties.php')) ?>" class="img-btn soft"><span class="material-symbols-outlined">arrow_back</span>Back</a>
    </div>
    <?php foreach ($errors as $err): ?><div class="img-alert"><span class="material-symbols-outlined">error</span><span><?= e($err) ?></span></div><?php endforeach; ?>
    <?php if ($remaining > 0): ?>
      <form method="post" enctype="multipart/form-data" class="img-upload mb-6">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acc_id" value="<?= (int)$accId ?>"><input type="hidden" name="action" value="upload">
        <label>Add more images</label>
        <input type="file" name="accommodation_images[]" accept="image/jpeg,image/png,image/webp" multiple required>
        <small>You can add <?= $remaining ?> more image<?= $remaining === 1 ? '' : 's' ?>. If your device selects one at a time, upload again from this page.</small>
        <button class="img-btn primary mt-4" type="submit"><span class="material-symbols-outlined">upload</span>Upload Images</button>
      </form>
    <?php endif; ?>
    <?php if (!$images): ?>
      <div class="text-center py-14 text-slate-500">No images yet.</div>
    <?php else: ?>
      <div class="img-grid">
        <?php foreach ($images as $img): ?>
          <div class="img-tile">
            <img src="<?= e($img['image_path']) ?>" alt="<?= e($property['name']) ?> image">
            <div class="img-meta">
              <?php if ((int)$img['is_cover'] === 1): ?><span class="img-pill">Cover</span><?php else: ?><span class="text-xs text-slate-500">Gallery image</span><?php endif; ?>
              <?php if ((int)$img['is_cover'] !== 1): ?>
                <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acc_id" value="<?= (int)$accId ?>"><input type="hidden" name="action" value="cover"><input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>"><button class="text-sm font-bold text-blue-600" type="submit">Set cover</button></form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
