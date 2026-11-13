USE tmz_podcast;

CREATE TABLE IF NOT EXISTS site_message_threads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(180) NOT NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_site_message_threads_user (user_id, last_message_at),
    INDEX idx_site_message_threads_latest (last_message_at),
    CONSTRAINT fk_site_message_threads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id BIGINT UNSIGNED NOT NULL,
    sender_role ENUM('admin', 'user') NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_site_messages_thread (thread_id, id),
    INDEX idx_site_messages_unread (thread_id, sender_role, read_at),
    CONSTRAINT fk_site_messages_thread FOREIGN KEY (thread_id) REFERENCES site_message_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_role ENUM('admin', 'user', 'all') NOT NULL,
    recipient_id BIGINT UNSIGNED NULL,
    category VARCHAR(32) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body VARCHAR(1000) NOT NULL,
    target_url VARCHAR(500) NULL,
    thread_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_site_notifications_recipient (recipient_role, recipient_id, created_at),
    INDEX idx_site_notifications_thread (thread_id, created_at),
    CONSTRAINT fk_site_notifications_thread FOREIGN KEY (thread_id) REFERENCES site_message_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_notification_states (
    notification_id BIGINT UNSIGNED NOT NULL,
    recipient_role ENUM('admin', 'user', 'guest') NOT NULL,
    recipient_id VARCHAR(128) NOT NULL,
    read_at DATETIME NULL,
    cleared_at DATETIME NULL,
    PRIMARY KEY (notification_id, recipient_role, recipient_id),
    INDEX idx_site_notification_states_recipient (recipient_role, recipient_id, cleared_at),
    CONSTRAINT fk_site_notification_states_notification FOREIGN KEY (notification_id) REFERENCES site_notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
