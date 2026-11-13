USE tmz_podcast;

CREATE TABLE IF NOT EXISTS live_participant_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT NOT NULL,
    user_id INT NULL,
    identity VARCHAR(255) NOT NULL,
    role ENUM('host','co_host','presenter','moderator','participant','guest') NOT NULL DEFAULT 'participant',
    can_publish_microphone TINYINT(1) NOT NULL DEFAULT 0,
    can_publish_camera TINYINT(1) NOT NULL DEFAULT 0,
    can_publish_screen_share TINYINT(1) NOT NULL DEFAULT 0,
    can_chat TINYINT(1) NOT NULL DEFAULT 0,
    removed_at DATETIME NULL,
    banned_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_live_participant_identity (live_id, identity),
    KEY idx_live_participant_user (live_id, user_id),
    CONSTRAINT fk_live_participant_live FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_room_controls (
    live_id INT NOT NULL PRIMARY KEY,
    room_locked TINYINT(1) NOT NULL DEFAULT 0,
    mute_all TINYINT(1) NOT NULL DEFAULT 0,
    disable_cameras TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_live_room_controls_live FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS live_permission_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT NOT NULL,
    actor_admin_id INT NOT NULL,
    participant_identity VARCHAR(255) NULL,
    action VARCHAR(40) NOT NULL,
    before_state JSON NULL,
    after_state JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_live_permission_audit (live_id, created_at),
    CONSTRAINT fk_live_permission_audit_live FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
