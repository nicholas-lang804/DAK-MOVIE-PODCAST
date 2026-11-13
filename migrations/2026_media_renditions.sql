-- TMZ Podcast 256 — Phase: remote media storage (ADDITIVE, reversible)
-- Safe to run on MySQL 5.7+/MariaDB 10.3+. Creates only NEW tables.
-- Rollback: DROP TABLE media_renditions, upload_jobs, media_objects;

CREATE TABLE IF NOT EXISTS media_renditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id INT NOT NULL,
    quality VARCHAR(10) NOT NULL,          -- 'original','1080p','720p','480p','hls'
    storage_driver VARCHAR(10) NOT NULL DEFAULT 'local', -- 'local' or 'remote'
    storage_path VARCHAR(500) NOT NULL,    -- path relative to driver root / remote key
    file_size BIGINT NOT NULL DEFAULT 0,
    sha256 CHAR(64) NULL,
    status ENUM('pending','processing','ready','failed') NOT NULL DEFAULT 'pending',
    error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rendition (media_id, quality, storage_driver),
    INDEX idx_rendition_status (status),
    INDEX idx_rendition_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS upload_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id INT NULL,                     -- set once the media_assets row exists
    kind VARCHAR(20) NOT NULL DEFAULT 'upload', -- 'upload','rendition','migrate','backup'
    status ENUM('queued','uploading','processing','ready','failed','cancelled') NOT NULL DEFAULT 'queued',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0-100
    speed_bps BIGINT NOT NULL DEFAULT 0,
    bytes_done BIGINT NOT NULL DEFAULT 0,
    bytes_total BIGINT NOT NULL DEFAULT 0,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    last_error TEXT NULL,
    payload TEXT NULL,                     -- JSON: driver, remote key, chunk size, options
    remote_token VARCHAR(120) NULL,        -- short-lived upload session id (never a permanent credential)
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jobs_status (status, kind),
    INDEX idx_jobs_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_objects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    media_id INT NULL,
    driver VARCHAR(10) NOT NULL DEFAULT 'local',
    object_key VARCHAR(500) NOT NULL,      -- path inside the driver root
    sha256 CHAR(64) NULL,
    size_bytes BIGINT NOT NULL DEFAULT 0,
    is_original TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_object (driver, object_key),
    INDEX idx_object_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
