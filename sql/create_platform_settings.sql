-- Create table for platform settings (key-value store)
CREATE TABLE IF NOT EXISTS platform_settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Example default settings
INSERT INTO platform_settings (`key`, `value`) VALUES
('site_title', 'Safari Tanzania'),
('maintenance_mode', '0'),
('login_rate_limit_per_minute', '10'),
('verification_required', '1')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
