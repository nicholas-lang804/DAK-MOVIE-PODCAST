USE tmz_podcast;

CREATE TABLE IF NOT EXISTS episode_guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    guest_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_episode_guests_episode FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_episode_guests_guest FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_episode_guest (episode_id, guest_id),
    INDEX idx_episode_guests_episode (episode_id),
    INDEX idx_episode_guests_guest (guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS episode_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_episode_categories_episode FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_episode_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_episode_category (episode_id, category_id),
    INDEX idx_episode_categories_episode (episode_id),
    INDEX idx_episode_categories_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    media_id INT NOT NULL,
    media_role ENUM('featured', 'gallery', 'inline') DEFAULT 'gallery',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_media_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    CONSTRAINT fk_article_media_media FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE,
    INDEX idx_article_media_article (article_id),
    INDEX idx_article_media_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    media_id INT NOT NULL,
    media_role ENUM('poster', 'gallery') DEFAULT 'gallery',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_media_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_media_media FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE,
    INDEX idx_event_media_event (event_id),
    INDEX idx_event_media_media (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    enquiry_type ENUM('general', 'business', 'interview', 'sponsorship', 'collaboration') DEFAULT 'general',
    status ENUM('new', 'read', 'replied', 'archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_messages_status (status),
    INDEX idx_contact_messages_type (enquiry_type),
    INDEX idx_contact_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
