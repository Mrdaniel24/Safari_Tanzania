<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('owner');

$ownerId = (int)$_SESSION['user_id'];
$id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM accommodations WHERE id = ? AND owner_id = ?");
$stmt->execute([$id, $ownerId]);
$prop = $stmt->fetch();
if (!$prop) { http_response_code(403); die('Property not found or access denied.'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['_csrf'] ?? null);

    $name        = trim($_POST['name'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image_url   = trim($_POST['image_url'] ?? '');

    if ($name === '' || mb_strlen($name) > 150)         $errors[] = 'Property name is required.';
    if ($location === '' || mb_strlen($location) > 150) $errors[] = 'Region / location is required.';
    if ($image_url !== '' && !filter_var($image_url, FILTER_VALIDATE_URL)) $errors[] = 'Image URL is not a valid URL.';

    if (!$errors) {
        $pdo->prepare("
            UPDATE accommodations
            SET name = ?, description = ?, location = ?, address = ?, image_url = ?
            WHERE id = ? AND owner_id = ?
        ")->execute([$name, $description ?: null, $location, $address ?: null, $image_url ?: null, $id, $ownerId]);
        flash_set('success', 'Property updated successfully.');
        redirect('owner/properties.php');
    }

    $prop = array_merge($prop, compact('name', 'location', 'address', 'description', 'image_url'));
}

$activePage = 'properties';
$pageTitle  = 'Edit Property';
include __DIR__ . '/../includes/header.php';
?>
<div class="p-6 md:p-10 max-w-2xl mx-auto w-full">
    <a href="<?= e(base_url('owner/properties.php')) ?>"
       class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900 mb-6">
        <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span> Back to properties
    </a>

    <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm p-8">
        <h2 class="text-2xl font-bold text-zinc-900 mb-1">Edit Property</h2>
        <p class="text-zinc-500 text-sm mb-6">
            Current status: <span class="font-semibold"><?= e($prop['status']) ?></span>.
            Editing does not change the approval status.
        </p>

        <?php foreach ($errors as $err): ?>
            <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg mb-4">
                <span class="material-symbols-outlined" style="font-size:18px;">error</span><?= e($err) ?>
            </div>
        <?php endforeach; ?>

        <form method="post" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$prop['id'] ?>">

            <div>
                <label class="block text-sm font-semibold text-zinc-700 mb-1">
                    Property Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" maxlength="150" required value="<?= e($prop['name']) ?>"
                       class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-zinc-700 mb-1">
                        Region / Location <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="location" maxlength="150" required value="<?= e($prop['location']) ?>"
                           class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-zinc-700 mb-1">Full Address</label>
                    <input type="text" name="address" maxlength="255" value="<?= e($prop['address'] ?? '') ?>"
                           class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-zinc-700 mb-1">Description</label>
                <textarea name="description" rows="5"
                          class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm"><?= e($prop['description'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-zinc-700 mb-1">Cover Image URL</label>
                <input type="url" name="image_url" maxlength="500" value="<?= e($prop['image_url'] ?? '') ?>"
                       class="w-full rounded-lg border-zinc-300 focus:border-emerald-600 focus:ring-emerald-600 text-sm">
                <?php if (!empty($prop['image_url'])): ?>
                    <img src="<?= e($prop['image_url']) ?>" alt="Current cover"
                         class="mt-2 h-28 w-full object-cover rounded-lg border border-zinc-200">
                <?php endif; ?>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 bg-primary text-white py-3 rounded-lg font-bold hover:bg-emerald-800 transition">
                    Save Changes
                </button>
                <a href="<?= e(base_url('owner/properties.php')) ?>"
                   class="flex-1 text-center border border-zinc-300 py-3 rounded-lg font-semibold text-zinc-700 hover:bg-zinc-50 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
