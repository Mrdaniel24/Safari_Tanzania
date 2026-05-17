<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

function ensure_accommodation_setup(PDO $pdo): void {
    $cols = [
        'accommodation_type' => "accommodation_type ENUM('guest_house','lodge','hotel') NULL AFTER owner_id",
        'region' => "region VARCHAR(100) NULL AFTER location",
        'district' => "district VARCHAR(120) NULL AFTER region",
        'ward_area' => "ward_area VARCHAR(150) NULL AFTER district",
        'area_other' => "area_other VARCHAR(150) NULL AFTER ward_area",
        'latitude' => "latitude DECIMAL(10,7) NULL AFTER area_other",
        'longitude' => "longitude DECIMAL(10,7) NULL AFTER latitude",
    ];
    foreach ($cols as $name => $definition) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accommodations' AND COLUMN_NAME = ?");
        $stmt->execute([$name]);
        if ((int)$stmt->fetchColumn() === 0) $pdo->exec("ALTER TABLE accommodations ADD COLUMN $definition");
    }
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
}
ensure_accommodation_setup($pdo);

$errors = [];
$old = [
    'name' => '', 'accommodation_type' => 'hotel', 'region' => '', 'district' => '', 'ward_area' => '',
    'area_other' => '', 'address' => '', 'latitude' => '', 'longitude' => '', 'description' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name = trim($_POST['name'] ?? '');
    $accommodation_type = $_POST['accommodation_type'] ?? '';
    $region = trim($_POST['region'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $ward_area = trim($_POST['ward_area'] ?? '');
    $area_other = trim($_POST['area_other'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $old = compact('name', 'accommodation_type', 'region', 'district', 'ward_area', 'area_other', 'address', 'latitude', 'longitude', 'description');

    $validTypes = ['guest_house', 'lodge', 'hotel'];
    if ($name === '' || mb_strlen($name) > 150) $errors[] = 'Accommodation / business name is required.';
    if (!in_array($accommodation_type, $validTypes, true)) $errors[] = 'Choose a valid accommodation type.';
    if ($region === '' || mb_strlen($region) > 100) $errors[] = 'Choose the Tanzania region.';
    if ($district === '' || mb_strlen($district) > 120) $errors[] = 'Choose the district.';
    if ($ward_area === '' || mb_strlen($ward_area) > 150) $errors[] = 'Choose the area / ward.';
    if ($ward_area === 'Other' && $area_other === '') $errors[] = 'Write the area name when you choose Other.';
    if ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
        $errors[] = 'Add the exact map location coordinates.';
    } else {
        $lat = (float)$latitude;
        $lng = (float)$longitude;
        if ($lat < -12.5 || $lat > 1.5 || $lng < 28.0 || $lng > 42.5) $errors[] = 'The map location must be inside Tanzania.';
    }

    $imageUploads = [];
    $imageFiles = $_FILES['accommodation_images'] ?? null;
    $imageCount = 0;
    if ($imageFiles && isset($imageFiles['name']) && is_array($imageFiles['name'])) {
        foreach ($imageFiles['name'] as $idx => $fileName) {
            $err = $imageFiles['error'][$idx] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) continue;
            $imageCount++;
            if ($err !== UPLOAD_ERR_OK) { $errors[] = 'One of the accommodation images failed to upload.'; continue; }
            if (($imageFiles['size'][$idx] ?? 0) > 5 * 1024 * 1024) { $errors[] = 'Each accommodation image must be 5MB or smaller.'; continue; }
            $tmp = $imageFiles['tmp_name'][$idx] ?? '';
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $tmp ? finfo_file($finfo, $tmp) : '';
            finfo_close($finfo);
            $allowedImages = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowedImages[$mime])) { $errors[] = 'Accommodation images must be JPG, PNG, or WEBP.'; continue; }
            $imageUploads[] = ['tmp' => $tmp, 'ext' => $allowedImages[$mime], 'name' => basename($fileName)];
        }
    }
    if ($imageCount === 0) $errors[] = 'Upload at least one accommodation image.';
    if ($imageCount > 6) $errors[] = 'Upload no more than 6 accommodation images.';

    if (!$errors) {
        $areaName = $ward_area === 'Other' ? $area_other : $ward_area;
        $location = $region;
        $fullAddress = trim($address !== '' ? $address : ($areaName . ', ' . $district . ', ' . $region));
        $stmt = $pdo->prepare("INSERT INTO accommodations
            (owner_id, accommodation_type, name, description, location, region, district, ward_area, area_other, latitude, longitude, address, image_url, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'pending')");
        $stmt->execute([
            (int)$_SESSION['user_id'], $accommodation_type, $name, $description ?: null, $location,
            $region, $district, $ward_area, $area_other ?: null, (float)$latitude, (float)$longitude,
            $fullAddress ?: null,
        ]);
        $accommodationId = (int)$pdo->lastInsertId();
        $uploadDir = __DIR__ . '/../public/uploads/accommodations';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
        $coverPath = null;
        foreach ($imageUploads as $idx => $img) {
            $safeName = 'acc_' . $accommodationId . '_' . bin2hex(random_bytes(8)) . '.' . $img['ext'];
            $target = $uploadDir . '/' . $safeName;
            if (move_uploaded_file($img['tmp'], $target)) {
                $relativePath = base_url('public/uploads/accommodations/' . $safeName);
                if ($idx === 0) $coverPath = $relativePath;
                $stmt = $pdo->prepare('INSERT INTO accommodation_images (accommodation_id, image_path, original_name, is_cover, sort_order) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$accommodationId, $relativePath, $img['name'], $idx === 0 ? 1 : 0, $idx]);
            }
        }
        if ($coverPath) {
            $stmt = $pdo->prepare('UPDATE accommodations SET image_url = ? WHERE id = ?');
            $stmt->execute([$coverPath, $accommodationId]);
        }
        flash_set('success', 'Accommodation submitted. Next you can add rooms, services, and workers.');
        redirect('owner/properties.php');
    }
}

$activePage = 'properties';
$pageTitle  = 'Register Accommodation';
include __DIR__ . '/../includes/header.php';
?>
<style>
.add-acc-page { padding: 28px 20px 42px; }
.add-acc-inner { max-width: 1120px; margin: 0 auto; display: grid; gap: 20px; }
.add-acc-hero { border-radius: 28px; padding: clamp(24px, 4vw, 36px); color: #fff; background: linear-gradient(135deg, rgba(7,20,47,.96), rgba(11,30,91,.75)), url('https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=1600&q=82') center/cover; box-shadow: 0 26px 70px rgba(7,20,47,.22); }
.add-acc-hero h2 { font-size: clamp(2rem, 4vw, 3.2rem); line-height:1; font-weight:800; margin: 8px 0 10px; }
.add-acc-hero p { color: rgba(255,255,255,.78); max-width: 720px; line-height: 1.65; }
.add-acc-kicker { color:#8BE7FF; font-weight:800; font-size:.78rem; text-transform:uppercase; }
.add-acc-card { border:1px solid rgba(15,36,82,.08); background:rgba(255,255,255,.92); box-shadow:0 18px 44px rgba(7,20,47,.07); border-radius:24px; padding:24px; }
.acc-form { display:grid; gap:18px; }
.acc-section-title { display:flex; align-items:center; gap:9px; color:#07142F; font-weight:800; font-size:1.02rem; margin-top:4px; }
.acc-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap:14px; }
.acc-grid-3 { display:grid; grid-template-columns: repeat(3, 1fr); gap:14px; }
.acc-field label, .acc-upload label { display:block; font-weight:750; color:#0F1E3A; margin-bottom:7px; font-size:.9rem; }
.acc-field input, .acc-field select, .acc-field textarea, .acc-upload input { width:100%; border:1px solid rgba(15,36,82,.14); border-radius:16px; padding:13px 14px; color:#07142F; background:#fff; outline:none; font-size:.94rem; }
.acc-field textarea { min-height: 120px; resize: vertical; }
.acc-field input:focus, .acc-field select:focus, .acc-field textarea:focus { border-color:#1EC6FF; box-shadow:0 0 0 4px rgba(30,198,255,.13); }
.acc-field small, .acc-upload small { display:block; color:#6B7B99; margin-top:6px; font-size:.78rem; }
.acc-alert { display:flex; gap:10px; align-items:flex-start; border-radius:16px; padding:13px 14px; font-size:.9rem; background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; }
.acc-map-box { border-radius:22px; overflow:hidden; border:1px solid rgba(15,36,82,.10); background:#F5F8FC; }
.acc-map-preview { width:100%; height:310px; border:0; display:block; background:#E8F2FF; }
.acc-map-actions { display:flex; flex-wrap:wrap; gap:10px; padding:14px; border-top:1px solid rgba(15,36,82,.08); }
.acc-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding:12px 16px; font-weight:780; transition:.2s ease; }
.acc-btn.primary { color:#fff; background:linear-gradient(135deg,#0F7BD9,#1EC6FF); box-shadow:0 16px 34px rgba(15,123,217,.22); }
.acc-btn.soft { color:#0B1E5B; background:#EEF6FF; border:1px solid rgba(15,123,217,.16); }
.acc-btn:hover { transform: translateY(-1px); }
.acc-upload { border:1px dashed rgba(15,123,217,.30); background:rgba(30,198,255,.06); border-radius:18px; padding:18px; }
.acc-upload-grid { display:grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap:10px; margin-top:14px; }
.acc-upload-thumb { position:relative; aspect-ratio:1/1; border-radius:14px; overflow:hidden; background:#E8F2FF; border:1px solid rgba(15,36,82,.08); }
.acc-upload-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.acc-upload-thumb span { position:absolute; left:7px; top:7px; background:rgba(7,20,47,.78); color:#fff; font-size:.68rem; font-weight:800; padding:4px 7px; border-radius:999px; }
#otherAreaWrap { display:none; }
#otherAreaWrap.is-visible { display:block; }
@media (max-width: 860px) { .acc-grid-2, .acc-grid-3 { grid-template-columns:1fr; } .acc-upload-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
</style>
<div class="add-acc-page"><div class="add-acc-inner">
<section class="add-acc-hero"><div class="add-acc-kicker">Accommodation registration</div><h2>Register a real place in Tanzania</h2><p>Add verified location details for your hotel, lodge, or guest house. Rooms, services, and workers will come after this registration.</p></section>
<div class="add-acc-card">
<?php foreach ($errors as $err): ?><div class="acc-alert"><span class="material-symbols-outlined">error</span><span><?= e($err) ?></span></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="acc-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<div class="acc-section-title"><span class="material-symbols-outlined">apartment</span> Business details</div>
<div class="acc-grid-2"><div class="acc-field"><label>Business / Accommodation Name</label><input name="name" maxlength="150" required value="<?= e($old['name']) ?>" placeholder="e.g. Serengeti Safari Lodge"></div><div class="acc-field"><label>Accommodation Type</label><select name="accommodation_type" required><option value="hotel" <?= $old['accommodation_type'] === 'hotel' ? 'selected' : '' ?>>Hotel</option><option value="lodge" <?= $old['accommodation_type'] === 'lodge' ? 'selected' : '' ?>>Lodge</option><option value="guest_house" <?= $old['accommodation_type'] === 'guest_house' ? 'selected' : '' ?>>Guest House</option></select></div></div>
<div class="acc-section-title"><span class="material-symbols-outlined">pin_drop</span> Tanzania location</div>
<div class="acc-grid-3"><div class="acc-field"><label>Region</label><select id="regionSelect" name="region" data-current="<?= e($old['region']) ?>" required><option value="">Choose region</option></select></div><div class="acc-field"><label>District</label><select id="districtSelect" name="district" data-current="<?= e($old['district']) ?>" required><option value="">Choose district</option></select></div><div class="acc-field"><label>Area / Ward</label><select id="areaSelect" name="ward_area" data-current="<?= e($old['ward_area']) ?>" required><option value="">Choose area</option></select></div></div>
<div id="otherAreaWrap" class="acc-field"><label>Write Area / Street Name</label><input id="otherAreaInput" name="area_other" maxlength="150" value="<?= e($old['area_other']) ?>" placeholder="Write the local name exactly as people use it"></div>
<div class="acc-field"><label>Physical Address / Nearby Landmark</label><input name="address" maxlength="255" value="<?= e($old['address']) ?>" placeholder="e.g. Near main road, beach, park gate, or landmark"></div>
<div class="acc-section-title"><span class="material-symbols-outlined">map</span> Exact map location</div>
<div class="acc-grid-2"><div class="acc-field"><label>Latitude</label><input id="latInput" name="latitude" required value="<?= e($old['latitude']) ?>" placeholder="e.g. -6.7924"><small>Use current location or paste coordinates from Google Maps.</small></div><div class="acc-field"><label>Longitude</label><input id="lngInput" name="longitude" required value="<?= e($old['longitude']) ?>" placeholder="e.g. 39.2083"><small>Coordinates must be inside Tanzania.</small></div></div>
<div class="acc-map-box"><iframe id="mapPreview" class="acc-map-preview" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe><div class="acc-map-actions"><button type="button" id="useLocationBtn" class="acc-btn soft"><span class="material-symbols-outlined">my_location</span>Use current location</button><a id="openMapsLink" href="https://www.google.com/maps/search/?api=1&query=Tanzania" target="_blank" rel="noopener" class="acc-btn soft"><span class="material-symbols-outlined">open_in_new</span>Open Google Maps</a></div></div>
<div class="acc-section-title"><span class="material-symbols-outlined">photo_library</span> Accommodation images</div>
<div class="acc-upload"><label>Accommodation Images</label><input id="accImagesInput" type="file" name="accommodation_images[]" accept="image/jpeg,image/png,image/webp" multiple required><small>Upload 1 to 6 images. The first image becomes the cover photo.</small><div id="accImagePreview" class="acc-upload-grid"></div></div>
<div class="acc-section-title"><span class="material-symbols-outlined">notes</span> Description</div>
<div class="acc-field"><label>Description</label><textarea name="description" maxlength="2000" placeholder="Describe the location, nearby attractions, and guest experience..."><?= e($old['description']) ?></textarea></div>
<div class="flex flex-wrap gap-3 pt-2"><button type="submit" class="acc-btn primary"><span class="material-symbols-outlined">send</span>Submit Accommodation</button><a href="<?= e(base_url('owner/properties.php')) ?>" class="acc-btn soft">Cancel</a></div>
</form></div></div></div>
<script src="<?= e(base_url('assets/js/tanzania_locations.js')) ?>"></script>
<script>
(function(){
  const regions = window.TANZANIA_LOCATIONS || {};
  const areas = window.TANZANIA_AREAS || {};
  const defaultAreas = window.DEFAULT_TANZANIA_AREAS || ['Other'];
  const regionSelect = document.getElementById('regionSelect');
  const districtSelect = document.getElementById('districtSelect');
  const areaSelect = document.getElementById('areaSelect');
  const otherWrap = document.getElementById('otherAreaWrap');
  const otherInput = document.getElementById('otherAreaInput');
  const latInput = document.getElementById('latInput');
  const lngInput = document.getElementById('lngInput');
  const mapPreview = document.getElementById('mapPreview');
  const openMapsLink = document.getElementById('openMapsLink');
  const useLocationBtn = document.getElementById('useLocationBtn');
  const accImagesInput = document.getElementById('accImagesInput');
  const accImagePreview = document.getElementById('accImagePreview');
  function option(value, label){ const opt=document.createElement('option'); opt.value=value; opt.textContent=label; return opt; }
  function fillRegions(){ Object.keys(regions).sort().forEach(r => regionSelect.appendChild(option(r, r))); if(regionSelect.dataset.current){ regionSelect.value=regionSelect.dataset.current; } fillDistricts(); }
  function fillDistricts(){ const current=districtSelect.dataset.current; districtSelect.innerHTML='<option value="">Choose district</option>'; areaSelect.innerHTML='<option value="">Choose area</option>'; (regions[regionSelect.value]||[]).forEach(d => districtSelect.appendChild(option(d,d))); if(current){ districtSelect.value=current; districtSelect.dataset.current=''; } fillAreas(); }
  function fillAreas(){ const current=areaSelect.dataset.current; areaSelect.innerHTML='<option value="">Choose area</option>'; const list=areas[districtSelect.value] || defaultAreas; list.forEach(a => areaSelect.appendChild(option(a,a))); if(!list.includes('Other')) areaSelect.appendChild(option('Other','Other')); if(current){ areaSelect.value=current; areaSelect.dataset.current=''; } syncOther(); }
  function syncOther(){ const show=areaSelect.value==='Other'; otherWrap.classList.toggle('is-visible', show); otherInput.required=show; }
  function updateMap(){ const lat=parseFloat(latInput.value), lng=parseFloat(lngInput.value); if(!isFinite(lat)||!isFinite(lng)){ mapPreview.src='https://maps.google.com/maps?q=Tanzania&z=5&output=embed'; openMapsLink.href='https://www.google.com/maps/search/?api=1&query=Tanzania'; return; } const q=lat+','+lng; mapPreview.src='https://maps.google.com/maps?q='+encodeURIComponent(q)+'&z=15&output=embed'; openMapsLink.href='https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(q); }
  regionSelect.addEventListener('change', fillDistricts); districtSelect.addEventListener('change', fillAreas); areaSelect.addEventListener('change', syncOther); latInput.addEventListener('input', updateMap); lngInput.addEventListener('input', updateMap);
  useLocationBtn.addEventListener('click', function(){ if(!navigator.geolocation){ alert('Location is not available in this browser.'); return; } useLocationBtn.disabled=true; useLocationBtn.textContent='Getting location...'; navigator.geolocation.getCurrentPosition(function(pos){ latInput.value=pos.coords.latitude.toFixed(7); lngInput.value=pos.coords.longitude.toFixed(7); updateMap(); useLocationBtn.disabled=false; useLocationBtn.innerHTML='<span class="material-symbols-outlined">my_location</span>Use current location'; }, function(){ alert('Could not get your location. You can paste coordinates from Google Maps.'); useLocationBtn.disabled=false; useLocationBtn.innerHTML='<span class="material-symbols-outlined">my_location</span>Use current location'; }, {enableHighAccuracy:true, timeout:12000}); });
  if (accImagesInput && accImagePreview) {
    accImagesInput.addEventListener('change', function(){
      accImagePreview.innerHTML = '';
      const files = Array.from(accImagesInput.files || []);
      if (files.length > 6) { alert('Please upload no more than 6 images.'); accImagesInput.value = ''; return; }
      files.forEach(function(file, idx){
        const wrap = document.createElement('div'); wrap.className = 'acc-upload-thumb';
        const img = document.createElement('img'); img.alt = file.name; img.src = URL.createObjectURL(file);
        const badge = document.createElement('span'); badge.textContent = idx === 0 ? 'Cover' : String(idx + 1);
        wrap.appendChild(img); wrap.appendChild(badge); accImagePreview.appendChild(wrap);
      });
    });
  }
  fillRegions(); updateMap();
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>


