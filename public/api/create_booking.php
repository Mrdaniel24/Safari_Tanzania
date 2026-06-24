<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../models/Booking.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// enforce role: only travelers (and admins) can create bookings
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['traveler', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Only travelers may create bookings']);
    exit;
}

// Rate limiting: max 5 booking creates per 30 seconds per IP
$remaining = rate_limit_check('booking');
if ($remaining <= 0) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many booking requests. Please wait 30 seconds.']);
    exit;
}

// Accept CSRF token in JSON body or POST
$raw = json_decode(file_get_contents('php://input'), true);
$data = $raw ?: $_POST;
$csrf = $data['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

$accId = (int)($data['acc_id'] ?? 0);
$checkIn = $data['check_in'] ?? '';
$checkOut = $data['check_out'] ?? '';
$guests = (int)($data['guests'] ?? 1);
$items = $data['items'] ?? [];

if (!validate_csrf($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// validate basic inputs
if ($accId <= 0 || !validate_date($checkIn) || !validate_date($checkOut) || !validate_positive_int($guests)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input parameters']);
    exit;
}

if (new DateTime($checkOut) <= new DateTime($checkIn)) {
    http_response_code(400);
    echo json_encode(['error' => 'Check-out must be after check-in']);
    exit;
}

if ($accId <= 0 || !$checkIn || !$checkOut || empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing data']);
    exit;
}

try {
    // Validate items structure
    $norm = [];
    if (!is_array($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid items']);
        exit;
    }
    foreach ($items as $it) {
        $roomId = (int)($it['room_id'] ?? 0);
        $rooms = (int)($it['rooms'] ?? 0);
        $price = (float)($it['price'] ?? 0.0);
        if ($roomId <= 0 || $rooms <= 0 || $price <= 0.0) continue;
        $norm[] = ['room_id' => $roomId, 'rooms' => $rooms, 'price' => $price];
    }
    if (empty($norm)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid items']);
        exit;
    }

    $bookingId = createBooking($userId, $accId, $checkIn, $checkOut, $guests, $norm);
    rate_limit_clear('booking');
    echo json_encode(['success' => true, 'booking_id' => $bookingId]);
} catch (Throwable $e) {
    rate_limit_tick('booking');
    http_response_code(500);
    echo json_encode(['error' => 'Could not create booking', 'message' => $e->getMessage()]);
}
