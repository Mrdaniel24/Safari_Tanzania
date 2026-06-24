<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
checkRole('admin');

$activePage = 'settings';
$pageTitle  = 'Platform Settings';

// Allowed settings keys (whitelist)
$allowedKeys = [
    'site_title',
    'maintenance_mode',
    'login_rate_limit_per_minute',
    'verification_required'
];

// Handle save
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), $_POST['_csrf'] ?? '')) {
        $message = ['type' => 'error', 'text' => 'Invalid CSRF token.'];
    } else {
        $vals = [];
        foreach ($allowedKeys as $k) {
            if (isset($_POST[$k])) {
                $vals[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k];
            } else {
                // unchecked checkboxes -> false/0
                if (in_array($k, ['maintenance_mode','verification_required'], true)) {
                    $vals[$k] = '0';
                }
            }
        }

        try {
            $pdo->beginTransaction();
            $up = $pdo->prepare("INSERT INTO platform_settings (`key`,`value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            foreach ($vals as $k => $v) {
                $up->execute([$k, (string)$v]);
            }

            // Audit log (best-effort)
            try {
                if (isset($_SESSION['user_id'])) {
                    $log = $pdo->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
                    $log->execute([(int)($_SESSION['user_id'] ?? 0), 'updated platform settings']);
                }
            } catch (Throwable $e) {
                // ignore logging errors
            }

            $pdo->commit();
            $message = ['type' => 'success', 'text' => 'Settings saved.'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = ['type' => 'error', 'text' => 'Failed to save settings.'];
        }
    }
}

// Load current settings
$settings = [];
try {
    $rows = $pdo->query("SELECT `key`,`value` FROM platform_settings")->fetchAll();
    foreach ($rows as $r) $settings[$r['key']] = $r['value'];
} catch (Throwable $e) {
    // table might not exist yet
}

include __DIR__ . '/../includes/admin_header.php';
?>
<div class="p-6 md:p-10 max-w-3xl mx-auto w-full space-y-6">
    <h2 class="text-2xl font-bold">Platform Settings</h2>
    <?php if ($message): ?>
        <div class="px-4 py-3 rounded-lg <?= $message['type'] === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-800' ?>">
            <?= e($message['text']) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <label class="block mb-3">
            <span class="text-sm font-semibold">Site Title</span>
            <input name="site_title" type="text" value="<?= e($settings['site_title'] ?? 'Safari Tanzania') ?>" class="mt-1 block w-full border rounded-lg px-3 py-2">
        </label>

        <label class="flex items-center gap-3 mb-3">
            <input type="checkbox" name="maintenance_mode" value="1" <?= !empty($settings['maintenance_mode']) && $settings['maintenance_mode'] !== '0' ? 'checked' : '' ?>>
            <span class="text-sm">Maintenance mode</span>
        </label>

        <label class="block mb-3">
            <span class="text-sm font-semibold">Login rate limit (per minute)</span>
            <input name="login_rate_limit_per_minute" type="number" min="1" value="<?= e($settings['login_rate_limit_per_minute'] ?? '10') ?>" class="mt-1 block w-40 border rounded-lg px-3 py-2">
        </label>

        <label class="flex items-center gap-3 mb-3">
            <input type="checkbox" name="verification_required" value="1" <?= !empty($settings['verification_required']) && $settings['verification_required'] !== '0' ? 'checked' : '' ?>>
            <span class="text-sm">Require owner verification for listings</span>
        </label>

        <div class="mt-4">
            <button type="submit" class="bg-sky-700 text-white px-4 py-2 rounded-lg">Save Settings</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
