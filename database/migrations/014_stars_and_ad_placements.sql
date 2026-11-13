-- Stars (actors & actresses) + wider advert placements.
-- Optional: the application creates the `stars` table automatically the first time Star.php loads.

CREATE TABLE IF NOT EXISTS stars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category ENUM('actor','actress') NOT NULL DEFAULT 'actor',
    known_for VARCHAR(255) NULL,
    biography TEXT NULL,
    photo_path VARCHAR(500) NULL,
    video_path VARCHAR(500) NULL,
    video_url VARCHAR(500) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_star_status (status),
    INDEX idx_star_featured (is_featured, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The admin form already offered "footer" and "between_content" but the column did not accept them.
ALTER TABLE advertisements MODIFY placement
    ENUM('homepage','sidebar','episode','article','video','footer','between_content','dashboard','banner')
    NOT NULL DEFAULT 'homepage';
