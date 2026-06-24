-- =====================================================================
-- SAFARI TANZANIA - Database Schema
-- Tourism Accommodation Booking System
-- Import this file via phpMyAdmin or: mysql -u root -p < schema.sql
-- =====================================================================

DROP DATABASE IF EXISTS safari_tanzania;
CREATE DATABASE safari_tanzania CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE safari_tanzania;

-- ---------------------------------------------------------------------
-- 3.1 users
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    phone       VARCHAR(20)  NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('traveler','owner','admin','worker') NOT NULL DEFAULT 'traveler',
    owner_id    INT NULL,
    status      ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.2 accommodations
-- ---------------------------------------------------------------------
CREATE TABLE accommodations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    owner_id    INT NOT NULL,
    accommodation_type ENUM('guest_house','lodge','hotel') NULL,
    name        VARCHAR(150) NOT NULL,
    description TEXT,
    location    VARCHAR(150) NOT NULL,
    region      VARCHAR(100),
    district    VARCHAR(120),
    ward_area   VARCHAR(150),
    area_other  VARCHAR(150),
    latitude    DECIMAL(10,7),
    longitude   DECIMAL(10,7),
    address     VARCHAR(255),
    rating      DECIMAL(2,1) DEFAULT 0.0,
    image_url   VARCHAR(500),
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_acc_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.3 rooms
-- ---------------------------------------------------------------------
CREATE TABLE rooms (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    accommodation_id  INT NOT NULL,
    room_type         VARCHAR(100) NOT NULL,
    price             DECIMAL(10,2) NOT NULL,
    capacity          INT NOT NULL DEFAULT 1,
    total_rooms       INT NOT NULL DEFAULT 1,
    room_amenities    TEXT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_room_acc FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.4 amenities
-- ---------------------------------------------------------------------
CREATE TABLE amenities (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.5 accommodation_amenities (many-to-many)
-- ---------------------------------------------------------------------
CREATE TABLE accommodation_amenities (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    accommodation_id INT NOT NULL,
    amenity_id       INT NOT NULL,
    UNIQUE KEY uniq_acc_amenity (accommodation_id, amenity_id),
    CONSTRAINT fk_aa_acc FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE,
    CONSTRAINT fk_aa_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.6 bookings
-- ---------------------------------------------------------------------
CREATE TABLE bookings (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    traveler_id      INT NOT NULL,
    accommodation_id INT NOT NULL,
    check_in         DATE NOT NULL,
    check_out        DATE NOT NULL,
    nights           INT NOT NULL DEFAULT 1,
    guests           INT NOT NULL DEFAULT 1,
    total_price      DECIMAL(12,2) NOT NULL,
    booking_status   ENUM('confirmed','cancelled','completed') NOT NULL DEFAULT 'confirmed',
    payment_status   ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_book_user FOREIGN KEY (traveler_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_book_acc  FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.7 payments
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    booking_id            INT NOT NULL,
    amount                DECIMAL(10,2) NOT NULL,
    payment_method        ENUM('card','mobile_money','bank_transfer') NOT NULL,
    transaction_reference VARCHAR(150),
    payment_status        ENUM('success','failed') NOT NULL DEFAULT 'success',
    paid_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_book FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

    -- ---------------------------------------------------------------------
    -- 3.14 booking_items (supports multiple room lines per booking)
    -- ---------------------------------------------------------------------
    CREATE TABLE booking_items (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        booking_id      INT NOT NULL,
        room_id         INT NOT NULL,
        rooms_booked    INT NOT NULL DEFAULT 1,
        price_per_room  DECIMAL(10,2) NOT NULL,
        subtotal        DECIMAL(12,2) NOT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_bi_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        CONSTRAINT fk_bi_room    FOREIGN KEY (room_id)    REFERENCES rooms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;

    -- ---------------------------------------------------------------------
    -- 3.15 room_inventory (tracks booked rooms per date for availability)
    -- ---------------------------------------------------------------------
    CREATE TABLE room_inventory (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        room_id        INT NOT NULL,
        inventory_date DATE NOT NULL,
        booked_rooms   INT NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_room_date (room_id, inventory_date),
        CONSTRAINT fk_ri_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.8 admin_logs
-- ---------------------------------------------------------------------
CREATE TABLE admin_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    admin_id   INT NOT NULL,
    action     TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.16 notifications
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(80) NOT NULL,
    payload JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.17 activity_logs
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    meta JSON NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default admin (password: Admin@123)
-- Hash generated with: password_hash('Admin@123', PASSWORD_DEFAULT)
INSERT INTO users (full_name, email, phone, password, role, status) VALUES
('System Admin', 'admin@safaritanzania.test', '+255700000000',
 '$2y$10$wH8bKQ9z5qg6r1XU7m6lCO9pWcF5mE8qZJ2lYJ8m3oHvDnKjB1eAa', 'admin', 'active'),
('Demo Owner',   'owner@safaritanzania.test', '+255711111111',
 '$2y$10$wH8bKQ9z5qg6r1XU7m6lCO9pWcF5mE8qZJ2lYJ8m3oHvDnKjB1eAa', 'owner', 'active'),
('Demo Traveler','traveler@safaritanzania.test', '+255722222222',
 '$2y$10$wH8bKQ9z5qg6r1XU7m6lCO9pWcF5mE8qZJ2lYJ8m3oHvDnKjB1eAa', 'traveler', 'active');

-- NOTE: The seed password hashes above are placeholders that will NOT verify.
-- After importing, run this in PHP to set a working password for all demo accounts:
--   <?php require 'config/db.php';
--   $h = password_hash('Admin@123', PASSWORD_DEFAULT);
--   $pdo->exec("UPDATE users SET password = " . $pdo->quote($h));
-- Or simply REGISTER new accounts via /auth/register.php and promote in DB:
--   UPDATE users SET role='owner' WHERE email='you@example.com';

INSERT INTO amenities (name) VALUES
('WiFi'), ('Swimming Pool'), ('Breakfast'), ('Parking'),
('Air Conditioning'), ('Restaurant'), ('Game Drive'), ('Airport Shuttle');

INSERT INTO accommodations (owner_id, name, description, location, address, rating, image_url, status) VALUES
(2, 'Serengeti Safari Lodge',
 'Luxury tented camp on the edge of the Serengeti with sweeping savannah views, gourmet dining, and guided game drives.',
 'Serengeti', 'Seronera, Serengeti National Park', 4.8,
 'https://images.unsplash.com/photo-1516426122078-c23e76319801?w=1200', 'approved'),
(2, 'Zanzibar Beach Resort',
 'Boutique beachfront resort with white sand, turquoise water, and Swahili-inspired cuisine.',
 'Zanzibar', 'Nungwi Beach Road, Zanzibar', 4.7,
 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=1200', 'approved'),
(2, 'Kilimanjaro View Hotel',
 'Modern hotel in Moshi with panoramic Mount Kilimanjaro views and easy access to trekking routes.',
 'Moshi', 'Lema Road, Moshi', 4.5,
 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=1200', 'approved');

INSERT INTO rooms (accommodation_id, room_type, price, capacity, total_rooms) VALUES
(1, 'Luxury Tented Suite', 450.00, 2, 6),
(1, 'Family Tent',         600.00, 4, 3),
(2, 'Ocean View Room',     220.00, 2, 10),
(2, 'Beachfront Villa',    520.00, 4, 4),
(3, 'Standard Double',     120.00, 2, 12),
(3, 'Mountain Suite',      280.00, 3, 5);

INSERT INTO accommodation_amenities (accommodation_id, amenity_id) VALUES
(1,1),(1,3),(1,6),(1,7),(1,8),
(2,1),(2,2),(2,3),(2,4),(2,6),
(3,1),(3,3),(3,4),(3,5),(3,6);


-- ---------------------------------------------------------------------
-- 3.9 owner_verifications
-- ---------------------------------------------------------------------
CREATE TABLE owner_verifications (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    owner_id            INT NOT NULL UNIQUE,
    business_name       VARCHAR(150) NOT NULL,
    property_type       ENUM('guest_house','lodge','hotel') NOT NULL,
    registration_number VARCHAR(100) NOT NULL,
    business_address    VARCHAR(255) NOT NULL,
    document_path       VARCHAR(500) NOT NULL,
    document_name       VARCHAR(255) NOT NULL,
    status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes         TEXT NULL,
    submitted_at        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at         TIMESTAMP NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_owner_verification_user FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- 3.10 accommodation_images
-- ---------------------------------------------------------------------
CREATE TABLE accommodation_images (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    accommodation_id INT NOT NULL,
    image_path       VARCHAR(500) NOT NULL,
    original_name    VARCHAR(255),
    is_cover         TINYINT(1) NOT NULL DEFAULT 0,
    sort_order       INT NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_acc_image_acc FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.11 room_images
-- ---------------------------------------------------------------------
CREATE TABLE room_images (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    room_id       INT NOT NULL,
    image_path    VARCHAR(500) NOT NULL,
    original_name VARCHAR(255),
    is_cover      TINYINT(1) NOT NULL DEFAULT 0,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_room_image_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.12 accommodation_services
-- ---------------------------------------------------------------------
CREATE TABLE accommodation_services (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    accommodation_id INT NOT NULL,
    name             VARCHAR(120) NOT NULL,
    description      TEXT,
    price            DECIMAL(10,2),
    is_visible       TINYINT(1) NOT NULL DEFAULT 1,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_acc FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE service_images (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    service_id    INT NOT NULL,
    image_path    VARCHAR(500) NOT NULL,
    original_name VARCHAR(255),
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_image_service FOREIGN KEY (service_id) REFERENCES accommodation_services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3.13 accommodation_workers (many-to-many: owner staff <-> properties)
-- ---------------------------------------------------------------------
CREATE TABLE accommodation_workers (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    accommodation_id INT NOT NULL,
    worker_id        INT NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_acc_worker (accommodation_id, worker_id),
    CONSTRAINT fk_aw_acc    FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE,
    CONSTRAINT fk_aw_worker FOREIGN KEY (worker_id)        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

