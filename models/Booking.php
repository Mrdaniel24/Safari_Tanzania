<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Create a booking with items and update room_inventory per date.
 * $items = [ ['room_id'=>int, 'rooms'=>int, 'price'=>float], ... ]
 * Returns booking id on success or throws exception on failure.
 */
function createBooking(int $travelerId, int $accommodationId, string $checkIn, string $checkOut, int $guests, array $items): int {
    global $pdo;

    // calculate nights
    $d1 = new DateTime($checkIn);
    $d2 = new DateTime($checkOut);
    $interval = $d1->diff($d2);
    $nights = (int)$interval->days;
    if ($nights <= 0) throw new InvalidArgumentException('Check-out must be after check-in');

    // compute total
    $total = 0.0;
    foreach ($items as $it) {
        $subtotal = ((float)$it['price']) * ((int)$it['rooms']) * $nights;
        $total += $subtotal;
    }

    try {
        $pdo->beginTransaction();

        // --- Final availability re-check: ensure every room has enough capacity for EVERY date ---
        // (uses MAX(booked) because we need the peak occupancy across any single date)
        $checkSql = "SELECT r.id, r.total_rooms,
                            COALESCE(MAX(ri.booked_rooms), 0) AS max_booked
                     FROM rooms r
                     LEFT JOIN room_inventory ri
                        ON ri.room_id = r.id AND ri.inventory_date BETWEEN :check_in AND :check_out
                     WHERE r.accommodation_id = :acc_id
                     GROUP BY r.id, r.total_rooms";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':check_in' => $checkIn, ':check_out' => $checkOut, ':acc_id' => $accommodationId]);
        $currentInventory = [];
        foreach ($checkStmt->fetchAll() as $row) {
            $currentInventory[(int)$row['id']] = [
                'total'  => (int)$row['total_rooms'],
                'booked' => (int)$row['max_booked'],
            ];
        }

        foreach ($items as $it) {
            $roomId = (int)$it['room_id'];
            $roomsQty = (int)$it['rooms'];
            $inv = $currentInventory[$roomId] ?? null;
            if ($inv === null) {
                throw new RuntimeException("Room ID {$roomId} not found for this accommodation.");
            }
            $available = $inv['total'] - $inv['booked'];
            if ($roomsQty > $available) {
                throw new RuntimeException(
                    "Room ID {$roomId} only has {$available} unit(s) available, but {$roomsQty} requested."
                );
            }
        }

        $sql = "INSERT INTO bookings (traveler_id, accommodation_id, check_in, check_out, nights, guests, total_price)
                VALUES (:traveler, :acc, :ci, :co, :nights, :guests, :total)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':traveler' => $travelerId,
            ':acc' => $accommodationId,
            ':ci' => $checkIn,
            ':co' => $checkOut,
            ':nights' => $nights,
            ':guests' => $guests,
            ':total' => $total,
        ]);
        $bookingId = (int)$pdo->lastInsertId();

        // insert booking items and update inventory per date
        $biSql = "INSERT INTO booking_items (booking_id, room_id, rooms_booked, price_per_room, subtotal)
                  VALUES (:booking_id, :room_id, :rooms_booked, :price, :subtotal)";
        $biStmt = $pdo->prepare($biSql);

        $invSql = "INSERT INTO room_inventory (room_id, inventory_date, booked_rooms)
                   VALUES (:room_id, :inv_date, :qty)
                   ON DUPLICATE KEY UPDATE booked_rooms = booked_rooms + :qty";
        $invStmt = $pdo->prepare($invSql);

        $period = new DatePeriod($d1, new DateInterval('P1D'), $d2);

        foreach ($items as $it) {
            $roomId = (int)$it['room_id'];
            $roomsQty = (int)$it['rooms'];
            $price = (float)$it['price'];
            $subtotal = $price * $roomsQty * $nights;

            $biStmt->execute([
                ':booking_id' => $bookingId,
                ':room_id' => $roomId,
                ':rooms_booked' => $roomsQty,
                ':price' => $price,
                ':subtotal' => $subtotal,
            ]);

            foreach ($period as $dt) {
                $date = $dt->format('Y-m-d');
                $invStmt->execute([':room_id' => $roomId, ':inv_date' => $date, ':qty' => $roomsQty]);
            }
        }

        // create notifications for owner and log activity
        // fetch accommodation owner
        $ownerStmt = $pdo->prepare('SELECT owner_id, name FROM accommodations WHERE id = ? LIMIT 1');
        $ownerStmt->execute([$accommodationId]);
        $accRow = $ownerStmt->fetch();
        $ownerId = $accRow ? (int)$accRow['owner_id'] : null;

        if ($ownerId) {
            $noteSql = 'INSERT INTO notifications (user_id, type, payload) VALUES (:user_id, :type, :payload)';
            $noteStmt = $pdo->prepare($noteSql);
            $payload = json_encode(['booking_id' => $bookingId, 'accommodation_id' => $accommodationId, 'check_in' => $checkIn, 'check_out' => $checkOut]);
            $noteStmt->execute([':user_id' => $ownerId, ':type' => 'new_booking', ':payload' => $payload]);
        }

        // activity log
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $actSql = 'INSERT INTO activity_logs (user_id, action, meta, ip_address) VALUES (:user_id, :action, :meta, :ip)';
        $actStmt = $pdo->prepare($actSql);
        $actStmt->execute([
            ':user_id' => $travelerId,
            ':action' => 'create_booking',
            ':meta' => json_encode(['booking_id' => $bookingId, 'accommodation_id' => $accommodationId, 'items' => $items]),
            ':ip' => $ip,
        ]);

        $pdo->commit();
        return $bookingId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
