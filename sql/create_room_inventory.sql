-- create_room_inventory.sql
CREATE TABLE IF NOT EXISTS room_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    inventory_date DATE NOT NULL,
    booked_rooms INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_room_date (room_id, inventory_date),
    CONSTRAINT fk_ri_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
