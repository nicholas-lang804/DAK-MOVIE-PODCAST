USE tmz_podcast;

ALTER TABLE live_speaker_requests
    ADD COLUMN participant_identity VARCHAR(255) NULL AFTER user_id,
    MODIFY status ENUM('pending','invited','approved','denied','accepted','declined','ended') NOT NULL DEFAULT 'pending';

ALTER TABLE live_permission_audit
    MODIFY actor_admin_id INT NULL;

CREATE INDEX idx_live_speaker_request_identity
    ON live_speaker_requests(live_id, user_id, participant_identity, status);

CREATE TABLE IF NOT EXISTS live_transcript_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    live_id INT NOT NULL,
    speaker_identity VARCHAR(255) NOT NULL,
    speaker_name VARCHAR(120) NOT NULL,
    caption_text VARCHAR(1200) NOT NULL,
    language VARCHAR(12) NOT NULL DEFAULT 'en-US',
    start_time_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
    corrected_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_live_transcript_order (live_id, start_time_ms, id),
    FULLTEXT KEY idx_live_transcript_search (caption_text),
    CONSTRAINT fk_live_transcript_live FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
