USE tmz_podcast;
ALTER TABLE live_podcasts
    ADD COLUMN description TEXT NULL AFTER title,
    ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'USD' AFTER price,
    ADD COLUMN status ENUM('scheduled','live','ended') NOT NULL DEFAULT 'scheduled' AFTER access_type,
    ADD COLUMN started_at DATETIME NULL AFTER status,
    ADD COLUMN ended_at DATETIME NULL AFTER started_at;
ALTER TABLE live_podcasts
    ADD COLUMN room_name VARCHAR(255) NULL UNIQUE AFTER stream_url,
    ADD COLUMN started_by INT NULL AFTER room_name,
    ADD COLUMN viewer_peak INT NOT NULL DEFAULT 0,
    ADD COLUMN chat_enabled TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE live_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    live_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    status ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE live_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    live_id INT NOT NULL,
    user_id INT NOT NULL,
    reaction_type ENUM('like','love','fire','clap') NOT NULL DEFAULT 'like',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_live_reaction (live_id,user_id,reaction_type),
    FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE live_speaker_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    live_id INT NOT NULL,
    user_id INT NOT NULL,
    request_type ENUM('audio','video') NOT NULL,
    status ENUM('pending','approved','denied','ended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (live_id) REFERENCES live_podcasts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
