<?php
require_once __DIR__ . '/../config/db.php';

echo "Starting backfill of booking_items and room_inventory...\n";

$sel = $pdo->prepare("SELECT b.id, b.room_id, b.check_in, b.check_out, b.traveler_id
                      FROM bookings b
                      WHERE b.booking_status = 'confirmed'
                        AND b.room_id IS NOT NULL
                        AND NOT EXISTS (SELECT 1 FROM booking_items bi WHERE bi.booking_id = b.id)");
$sel->execute();
$rows = $sel->fetchAll(PDO::FETCH_ASSOC);

$biStmt = $pdo->prepare('INSERT INTO booking_items (booking_id, room_id, rooms_booked, price_per_room, subtotal) VALUES (?, ?, ?, ?, ?)');
$invStmt = $pdo->prepare("INSERT INTO room_inventory (room_id, inventory_date, booked_rooms) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE booked_rooms = booked_rooms + ?");
$roomPriceStmt = $pdo->prepare('SELECT price FROM rooms WHERE id = ? LIMIT 1');

foreach ($rows as $r) {
    $bookingId = (int)$r['id'];
    $roomId = (int)$r['room_id'];
    $checkIn = $r['check_in'];
    $checkOut = $r['check_out'];

    $roomPriceStmt->execute([$roomId]);
    $p = (float)$roomPriceStmt->fetchColumn();
    $nights = max(1, (int)( (strtotime($checkOut) - strtotime($checkIn)) / 86400 ));
    $subtotal = $p * 1 * $nights;

    echo "Backfilling booking_items for booking {$bookingId} room {$roomId} ({$checkIn} to {$checkOut})...\n";
    $biStmt->execute([$bookingId, $roomId, 1, $p, $subtotal]);

    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    foreach ($period as $dt) {
        $date = $dt->format('Y-m-d');
        $invStmt->execute([$roomId, $date, 1, 1]);
    }
}

echo "Backfill complete. Processed " . count($rows) . " bookings.\n";
