<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

/**
 * Get available rooms for an accommodation between two dates.
 * Returns array of rooms with keys: id, room_type, capacity, price, total_rooms, available_rooms
 */
function getAvailableRooms(int $accommodationId, string $checkIn, string $checkOut): array {
    global $pdo;

    $sql = "SELECT r.id, r.room_type, r.capacity, r.price, r.total_rooms,
                   IFNULL(MAX(ri.booked_rooms), 0) AS max_booked
            FROM rooms r
            LEFT JOIN room_inventory ri
              ON ri.room_id = r.id AND ri.inventory_date BETWEEN :check_in AND :check_out
            WHERE r.accommodation_id = :acc_id
            GROUP BY r.id, r.room_type, r.capacity, r.price, r.total_rooms";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':check_in' => $checkIn, ':check_out' => $checkOut, ':acc_id' => $accommodationId]);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $r) {
        $available = (int)$r['total_rooms'] - (int)$r['max_booked'];
        if ($available < 0) $available = 0;
        $result[] = [
            'id' => (int)$r['id'],
            'room_type' => $r['room_type'],
            'capacity' => (int)$r['capacity'],
            'price' => (float)$r['price'],
            'total_rooms' => (int)$r['total_rooms'],
            'available_rooms' => $available,
        ];
    }

    return $result;
}

/**
 * Suggest room allocation for given guests using available room types.
 * Simple greedy algorithm: prefer larger capacity rooms to minimize count.
 * Returns array of ['room_id' => nRooms, ...] suggestion counts.
 */
function suggestRoomAllocation(int $accommodationId, int $guests, string $checkIn, string $checkOut): array {
    $availableRooms = getAvailableRooms($accommodationId, $checkIn, $checkOut);

    // sort by capacity desc
    usort($availableRooms, function($a, $b){ return $b['capacity'] <=> $a['capacity']; });

    $remaining = $guests;
    $allocation = [];

    foreach ($availableRooms as $room) {
        if ($remaining <= 0) break;
        $cap = $room['capacity'];
        $maxUnits = (int)$room['available_rooms'];
        if ($maxUnits <= 0) continue;

        $need = intdiv($remaining, $cap);
        if ($remaining % $cap !== 0) $need++;

        $use = min($need, $maxUnits);
        if ($use <= 0) continue;

        $allocation[$room['id']] = $use;
        $remaining -= $use * $cap;
    }

    // If still remaining (couldn't fit exactly), try filling with smallest capacity available
    if ($remaining > 0) {
        // sort by capacity asc
        usort($availableRooms, function($a,$b){ return $a['capacity'] <=> $b['capacity']; });
        foreach ($availableRooms as $room) {
            if ($remaining <= 0) break;
            $cap = $room['capacity'];
            $maxUnits = (int)$room['available_rooms'];
            $already = $allocation[$room['id']] ?? 0;
            $canUse = $maxUnits - $already;
            if ($canUse <= 0) continue;
            $allocation[$room['id']] = ($allocation[$room['id']] ?? 0) + 1;
            $remaining -= $cap;
        }
    }

    return $allocation; // map room_id => quantity
}
