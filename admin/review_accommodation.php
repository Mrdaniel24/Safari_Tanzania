<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('admin/accommodations.php');

// Only load accommodation record and verification documents - no owner PII, no rooms, no booking info
$stmt = $pdo->prepare("SELECT a.*, ov.business_name AS verification_business_name, ov.property_type AS verification_property_type,
                              ov.registration_number, ov.business_address, ov.document_path, ov.document_name,
                              ov.status AS verification_status, ov.admin_notes, ov.submitted_at, ov.reviewed_at
                       FROM accommodations a
                       LEFT JOIN owner_verifications ov ON ov.owner_id = a.owner_id
                       WHERE a.id = ?");
$stmt->execute([$id]);
$acc = $stmt->fetch();
if (!$acc) {
    http_response_code(404);
    die('Accommodation not found.');
}

$verificationDocs = [];
try {
    if (!empty($acc['document_path'])) {
        $verificationDocs[] = ['path' => $acc['document_path'], 'name' => $acc['document_name'] ?? 'Document'];
    }
    $img = $pdo->prepare('SELECT * FROM accommodation_images WHERE accommodation_id = ? ORDER BY is_cover DESC, sort_order ASC, id ASC');
    $img->execute([$id]);
    // Keep images only for context, not room or owner PII
    $images = $img->fetchAll();
} catch (Throwable $e) { $images = []; }

$cover = $images[0]['image_path'] ?? ($acc['image_url'] ?: 'https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=1400&q=80');
$area = ($acc['ward_area'] ?? '') === 'Other' ? ($acc['area_other'] ?? '') : ($acc['ward_area'] ?? '');
$locationLine = implode(', ', array_filter([$area, $acc['district'] ?? '', $acc['region'] ?? '', 'Tanzania']));
$statusClass = match($acc['status']) {
    'approved' => 'bg-emerald-100 text-emerald-800',
    'pending' => 'bg-amber-100 text-amber-800',
    'rejected' => 'bg-red-100 text-red-700',
    default => 'bg-slate-100 text-slate-700',
};
$verificationClass = match($acc['verification_status'] ?? '') {
    'approved' => 'bg-emerald-100 text-emerald-800',
    'pending' => 'bg-amber-100 text-amber-800',
    'rejected' => 'bg-red-100 text-red-700',
    default => 'bg-slate-100 text-slate-700',
};
// Rooms and services are intentionally not loaded into admin view per privacy policy.
// Define as empty arrays so downstream `count()` checks do not fail.
$rooms = [];
$services = [];
$checks = [
    ['label' => 'Accommodation images', 'ok' => count($images) > 0, 'value' => count($images) . ' uploaded'],
    ['label' => 'Room setup', 'ok' => count($rooms) > 0, 'value' => count($rooms) . ' room type' . (count($rooms) === 1 ? '' : 's')],
    ['label' => 'Services', 'ok' => count($services) > 0, 'value' => count($services) . ' service' . (count($services) === 1 ? '' : 's')],
    ['label' => 'Owner verification', 'ok' => ($acc['verification_status'] ?? '') === 'approved', 'value' => $acc['verification_status'] ?: 'not submitted'],
    ['label' => 'Location coordinates', 'ok' => is_numeric($acc['latitude'] ?? null) && is_numeric($acc['longitude'] ?? null), 'value' => is_numeric($acc['latitude'] ?? null) ? ($acc['latitude'] . ', ' . $acc['longitude']) : 'missing'],
];

$activePage = 'accommodations';
$pageTitle = 'Review Accommodation';
include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.review-hero { min-height: 310px; background: linear-gradient(90deg, rgba(8,20,46,.76), rgba(8,20,46,.22)), var(--review-cover); background-size: cover; background-position: center; border-radius: 24px; overflow: hidden; }
.review-card { background: rgba(255,255,255,.96); border: 1px solid #e3ebf3; box-shadow: 0 18px 48px rgba(21,34,56,.08); border-radius: 20px; }
.review-hero h2 { color:#fff !important; text-shadow: 0 10px 28px rgba(0,0,0,.45); }
.review-hero p { color: rgba(255,255,255,.9) !important; text-shadow: 0 6px 18px rgba(0,0,0,.38); }
.review-thumb-grid { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; }
.review-thumb { width:100%; aspect-ratio:4/3; object-fit:cover; border-radius:14px; border:1px solid #e3ebf3; cursor: zoom-in; transition: transform .18s ease, filter .18s ease; }
.review-thumb:hover { transform: scale(1.015); filter: brightness(.92); }
.review-fact span { display:block; color:#71809a; font-size:.74rem; font-weight:600; text-transform:uppercase; margin-bottom:2px; }
.review-fact strong { color:#152238; font-weight:600; }
.review-lightbox { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 22px; background: rgba(2,8,23,.88); backdrop-filter: blur(10px); }
.review-lightbox.is-open { display: flex; }
.review-lightbox-frame { position: relative; width: min(1120px, 100%); height: min(760px, calc(100vh - 44px)); display: flex; flex-direction: column; gap: 12px; }
.review-lightbox-top { display: flex; align-items: center; justify-content: space-between; gap: 14px; color: #fff; }
.review-lightbox-title { min-width: 0; font-weight: 700; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.review-lightbox-count { color: rgba(255,255,255,.64); font-size: .84rem; }
.review-lightbox-close, .review-lightbox-nav { border: 0; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; background: rgba(255,255,255,.12); transition: background .18s ease; }
.review-lightbox-close { width: 40px; height: 40px; border-radius: 999px; flex-shrink: 0; }
.review-lightbox-close:hover, .review-lightbox-nav:hover { background: rgba(255,255,255,.22); }
.review-lightbox-stage { position: relative; flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 14px; background: rgba(255,255,255,.04); }
.review-lightbox-img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 10px; box-shadow: 0 24px 70px rgba(0,0,0,.42); }
.review-lightbox-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 46px; height: 62px; border-radius: 999px; }
.review-lightbox-prev { left: 14px; }
.review-lightbox-next { right: 14px; }
@media (max-width: 768px) { .review-thumb-grid { grid-template-columns:1fr 1fr; } }
@media (max-width: 768px) { .review-lightbox { padding: 12px; } .review-lightbox-frame { height: calc(100vh - 24px); } .review-lightbox-nav { width: 40px; height: 54px; } }
</style>
<div class="p-6 md:p-10 max-w-7xl mx-auto w-full space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <a href="<?= e(base_url('admin/accommodations.php')) ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-sky-700 hover:underline">
            <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span> Back to accommodations
        </a>
        <a href="<?= e(base_url('public/accommodation_details.php?id=' . (int)$acc['id'])) ?>" target="_blank" class="inline-flex items-center gap-2 bg-sky-50 text-sky-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-sky-100 transition">
            <span class="material-symbols-outlined" style="font-size:18px;">open_in_new</span> Customer Preview
        </a>
    </div>

    <section class="review-hero flex items-end" style="--review-cover:url('<?= e($cover) ?>')">
        <div class="p-6 md:p-8 text-white max-w-3xl">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>"><?= e($acc['status']) ?></span>
                <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-white/15 text-white border border-white/20"><?= e(ucwords(str_replace('_', ' ', $acc['accommodation_type'] ?: 'Accommodation'))) ?></span>
            </div>
            <h2 class="text-3xl md:text-4xl font-bold leading-tight"><?= e($acc['name']) ?></h2>
            <p class="mt-2 text-white/85 max-w-2xl"><?= e($locationLine ?: ($acc['location'] . ', Tanzania')) ?></p>
        </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="review-card p-6">
                <h3 class="text-xl font-bold mb-4">Review Checklist</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($checks as $c): ?>
                        <div class="flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                            <span class="material-symbols-outlined <?= $c['ok'] ? 'text-emerald-600' : 'text-amber-600' ?>"><?= $c['ok'] ? 'check_circle' : 'info' ?></span>
                            <div>
                                <p class="font-semibold text-slate-900"><?= e($c['label']) ?></p>
                                <p class="text-sm text-slate-500"><?= e($c['value']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="review-card p-6">
                <h3 class="text-xl font-bold mb-4">Accommodation Details</h3>
                <p class="text-slate-600 leading-relaxed mb-5"><?= e($acc['description'] ?: 'No description provided.') ?></p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="review-fact"><span>Region</span><strong><?= e($acc['region'] ?: $acc['location']) ?></strong></div>
                    <div class="review-fact"><span>District</span><strong><?= e($acc['district'] ?: 'Not specified') ?></strong></div>
                    <div class="review-fact"><span>Area</span><strong><?= e($area ?: 'Not specified') ?></strong></div>
                    <div class="review-fact"><span>Rating</span><strong><?= e(number_format((float)$acc['rating'], 1)) ?></strong></div>
                </div>
            </div>

            <div class="review-card p-6">
                <h3 class="text-xl font-bold mb-4">Verification Documents</h3>
                <?php if (empty($verificationDocs) && empty($images)): ?>
                    <p class="text-slate-500">No verification documents uploaded.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($verificationDocs as $doc): ?>
                            <a href="<?= e($doc['path']) ?>" target="_blank" class="inline-flex items-center gap-2 text-sm font-semibold text-sky-700 hover:underline">
                                <span class="material-symbols-outlined">description</span> <?= e($doc['name'] ?? 'Document') ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!empty($images)): ?>
                            <div class="review-thumb-grid mt-3">
                                <?php foreach (array_slice($images, 0, 6) as $img): ?>
                                    <img src="<?= e($img['image_path']) ?>" alt="Verification image" class="review-thumb" data-review-image>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="review-card p-6 sticky top-24">
                <h3 class="text-lg font-bold mb-4">Approval Decision</h3>
                <p class="text-sm text-slate-500 mb-5">Approve only after confirming the owner, location, images, rooms, and services are acceptable.</p>
                <div class="space-y-3">
                    <?php if ($acc['status'] !== 'approved'): ?>
                    <form method="post" action="<?= e(base_url('admin/approve_accommodation.php')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$acc['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="back" value="admin/review_accommodation.php?id=<?= (int)$acc['id'] ?>">
                        <button type="submit" class="w-full inline-flex justify-center items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-3 rounded-xl font-semibold transition">
                            <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span> Approve Accommodation
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($acc['status'] !== 'rejected'): ?>
                    <form method="post" action="<?= e(base_url('admin/approve_accommodation.php')) ?>" onsubmit="return confirm('Reject this accommodation?');">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$acc['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="back" value="admin/review_accommodation.php?id=<?= (int)$acc['id'] ?>">
                        <button type="submit" class="w-full inline-flex justify-center items-center gap-2 bg-red-50 hover:bg-red-100 text-red-700 px-4 py-3 rounded-xl font-semibold transition">
                            <span class="material-symbols-outlined" style="font-size:18px;">cancel</span> Reject Accommodation
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="review-card p-6">
                <h3 class="text-lg font-bold mb-4">Verification Summary</h3>
                <div class="text-sm text-slate-600">
                    <p><strong>Verification Status:</strong> <?= e($acc['verification_status'] ?? 'not submitted') ?></p>
                    <?php if (!empty($acc['verification_business_name'])): ?>
                        <p class="mt-2"><strong>Business:</strong> <?= e($acc['verification_business_name']) ?></p>
                        <p><strong>Reg No:</strong> <?= e($acc['registration_number']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($acc['document_path'])): ?>
                        <a href="<?= e($acc['document_path']) ?>" target="_blank" class="inline-flex items-center gap-2 mt-4 text-sm font-semibold text-sky-700 hover:underline">
                            <span class="material-symbols-outlined" style="font-size:18px;">description</span> View verification document
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="review-card p-6">
                <h3 class="text-lg font-bold mb-4">Location</h3>
                <p class="text-sm text-slate-600"><?= e($acc['address'] ?: $locationLine ?: $acc['location']) ?></p>
                <div class="mt-4 grid grid-cols-1 gap-3">
                    <div class="review-fact"><span>Latitude</span><strong><?= e($acc['latitude'] ?: 'Missing') ?></strong></div>
                    <div class="review-fact"><span>Longitude</span><strong><?= e($acc['longitude'] ?: 'Missing') ?></strong></div>
                </div>
            </div>
        </aside>
    </section>
</div>

<div class="review-lightbox" id="reviewLightbox" aria-hidden="true">
    <div class="review-lightbox-frame" role="dialog" aria-modal="true" aria-label="Image preview">
        <div class="review-lightbox-top">
            <div class="min-w-0">
                <div class="review-lightbox-title" id="reviewLightboxTitle">Image preview</div>
                <div class="review-lightbox-count" id="reviewLightboxCount">1 / 1</div>
            </div>
            <button type="button" class="review-lightbox-close" id="reviewLightboxClose" aria-label="Close preview">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="review-lightbox-stage">
            <button type="button" class="review-lightbox-nav review-lightbox-prev" id="reviewLightboxPrev" aria-label="Previous image">
                <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <img src="" alt="" class="review-lightbox-img" id="reviewLightboxImg">
            <button type="button" class="review-lightbox-nav review-lightbox-next" id="reviewLightboxNext" aria-label="Next image">
                <span class="material-symbols-outlined">chevron_right</span>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const thumbs = Array.from(document.querySelectorAll('[data-review-image]'));
    if (!thumbs.length) return;

    const lightbox = document.getElementById('reviewLightbox');
    const image = document.getElementById('reviewLightboxImg');
    const title = document.getElementById('reviewLightboxTitle');
    const count = document.getElementById('reviewLightboxCount');
    const closeBtn = document.getElementById('reviewLightboxClose');
    const prevBtn = document.getElementById('reviewLightboxPrev');
    const nextBtn = document.getElementById('reviewLightboxNext');
    let current = 0;

    function show(index) {
        current = (index + thumbs.length) % thumbs.length;
        const thumb = thumbs[current];
        image.src = thumb.currentSrc || thumb.src;
        image.alt = thumb.alt || 'Preview image';
        title.textContent = thumb.dataset.caption || thumb.alt || 'Image preview';
        count.textContent = (current + 1) + ' / ' + thumbs.length;
    }

    function open(index) {
        show(index);
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
    }

    function close() {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        image.src = '';
    }

    thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', () => open(index));
        thumb.setAttribute('tabindex', '0');
        thumb.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(index);
            }
        });
    });

    closeBtn.addEventListener('click', close);
    prevBtn.addEventListener('click', () => show(current - 1));
    nextBtn.addEventListener('click', () => show(current + 1));
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) close();
    });

    document.addEventListener('keydown', (event) => {
        if (!lightbox.classList.contains('is-open')) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') show(current - 1);
        if (event.key === 'ArrowRight') show(current + 1);
    });
})();
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
