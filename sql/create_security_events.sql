-- Create table for security events used by System Monitoring
CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NULL,
  event_type VARCHAR(100) NOT NULL,
  meta JSON NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional test inserts (uncomment to insert test events)
-- INSERT INTO security_events (user_id, event_type, meta) VALUES (1, 'login_success', '{"ip":"127.0.0.1"}');
-- INSERT INTO security_events (user_id, event_type, meta) VALUES (NULL, 'failed_login', '{"ip":"127.0.0.1","email":"test@example.com"}');
