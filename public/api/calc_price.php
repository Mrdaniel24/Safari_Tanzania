<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../models/Availability.php';
require_once __DIR__ . '/../../models/Pricing.php';

// Accept CSRF token from POST or header
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

$accId = (int)($_GET['acc_id'] ?? $_POST['acc_id'] ?? 0);
$checkIn = $_GET['check_in'] ?? $_POST['check_in'] ?? '';
$checkOut = $_GET['check_out'] ?? $_POST['check_out'] ?? '';
$guests = (int)($_GET['guests'] ?? $_POST['guests'] ?? 1);

if (!validate_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// validate inputs
if ($accId <= 0 || !validate_date($checkIn) || !validate_date($checkOut) || !validate_positive_int($guests)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input parameters']);
    exit;
}

// ensure check-out after check-in
if (new DateTime($checkOut) <= new DateTime($checkIn)) {
    http_response_code(400);
    echo json_encode(['error' => 'Check-out must be after check-in']);
    exit;
}

if ($accId <= 0 || !$checkIn || !$checkOut) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    $rooms = getAvailableRooms($accId, $checkIn, $checkOut);
    $suggest = suggestRoomAllocation($accId, $guests, $checkIn, $checkOut);

    // build pricing lines
    $lines = [];
    foreach ($suggest as $roomId => $qty) {
        // find room details
        $r = null;
        foreach ($rooms as $rr) if ($rr['id'] === $roomId) { $r = $rr; break; }
        if (!$r) continue;
        $lines[] = ['price' => $r['price'], 'quantity' => $qty, 'nights' => (new DateTime($checkIn))->diff(new DateTime($checkOut))->days];
    }

    $pricing = calculatePricing($lines);

    echo json_encode(['success' => true, 'suggestion' => $suggest, 'lines' => $lines, 'pricing' => $pricing]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
