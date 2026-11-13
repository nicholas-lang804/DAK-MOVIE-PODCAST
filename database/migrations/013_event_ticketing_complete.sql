-- Event ticketing completion (run in phpMyAdmin if you prefer manual migrations).
-- NOTE: the application also creates/repairs these automatically the first time Event.php loads,
-- so running this file is optional. Safe to run more than once on MariaDB (XAMPP).

CREATE TABLE IF NOT EXISTS event_ticket_types (
    id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, name VARCHAR(80) NOT NULL, code VARCHAR(40) NOT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0, currency VARCHAR(3) NOT NULL DEFAULT 'UGX', capacity INT NULL,
    sold_count INT NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY event_ticket_code (event_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_discount_rules (
    id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, code VARCHAR(60) NOT NULL, eligible_label VARCHAR(120) NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0, starts_at DATETIME NULL, ends_at DATETIME NULL,
    channel ENUM('online','physical','both') NOT NULL DEFAULT 'both', status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY event_discount_code (event_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_orders (
    id INT AUTO_INCREMENT PRIMARY KEY, event_id INT NOT NULL, user_id INT NULL, reference VARCHAR(100) NOT NULL UNIQUE,
    subtotal DECIMAL(12,2) NOT NULL, discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0, total DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) NOT NULL, discount_code VARCHAR(60) NULL, channel ENUM('online','physical') NOT NULL DEFAULT 'online',
    status ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending', payment_id INT NULL,
    buyer_name VARCHAR(160) NULL, buyer_phone VARCHAR(60) NULL, buyer_email VARCHAR(190) NULL, sold_by_admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, paid_at DATETIME NULL, INDEX idx_event_order_status (event_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, ticket_type_id INT NOT NULL, quantity INT NOT NULL, unit_price DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, event_id INT NOT NULL, ticket_type_id INT NOT NULL, user_id INT NULL,
    ticket_code VARCHAR(80) NOT NULL UNIQUE, status ENUM('valid','used','cancelled') NOT NULL DEFAULT 'valid',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, checked_in_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payments ADD COLUMN IF NOT EXISTS event_order_id INT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS contact_name VARCHAR(160) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(60) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS contact_email VARCHAR(190) NULL;
ALTER TABLE event_orders ADD COLUMN IF NOT EXISTS buyer_name VARCHAR(160) NULL;
ALTER TABLE event_orders ADD COLUMN IF NOT EXISTS buyer_phone VARCHAR(60) NULL;
ALTER TABLE event_orders ADD COLUMN IF NOT EXISTS buyer_email VARCHAR(190) NULL;
ALTER TABLE event_orders ADD COLUMN IF NOT EXISTS sold_by_admin_id INT NULL;
ALTER TABLE event_tickets ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL;
ALTER TABLE event_orders MODIFY user_id INT NULL;
ALTER TABLE event_tickets MODIFY user_id INT NULL;
