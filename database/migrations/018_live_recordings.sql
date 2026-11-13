USE tmz_podcast;

CREATE TABLE IF NOT EXISTS live_recordings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT NOT NULL,
    egress_id VARCHAR(128) NOT NULL UNIQUE,
    media_type ENUM('video', 'audio') NOT NULL,
    format ENUM('mp4', 'mp3', 'ogg') NOT NULL,
    object_key VARCHAR(1024) NOT NULL,
    status ENUM('starting', 'active', 'ending', 'complete', 'failed', 'aborted') NOT NULL DEFAULT 'starting',
    duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
    file_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_synced_at DATETIME NULL,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    created_by_admin_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_live_recordings_live_status (live_id, status, created_at),
    CONSTRAINT fk_live_recordings_live FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
