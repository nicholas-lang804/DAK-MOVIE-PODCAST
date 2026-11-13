-- THE MOVIE ZONE PODCAST 256 - Complete Database Schema
-- Version: 2.0
-- Compatible with MySQL/MariaDB
-- Date: 2026-11-05

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS tmz_podcast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tmz_podcast;

-- ============================================
-- EXISTING TABLES (PRESERVED)
-- ============================================

-- Users table (enhanced)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    profile_image_path VARCHAR(500) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    role_id INT NULL,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email),
    UNIQUE KEY unique_phone (phone),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Media table (films/videos) - preserved for backward compatibility
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL,
    price DECIMAL(10, 2) DEFAULT 0.00,
    access_type ENUM('free', 'pay_to_watch', 'pay_to_download') DEFAULT 'free',
    download_fee DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_access_type (access_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live podcasts table - preserved for backward compatibility
CREATE TABLE IF NOT EXISTS live_podcasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    stream_url VARCHAR(500) NOT NULL,
    cover_image_path VARCHAR(500) NULL,
    access_type ENUM('free', 'paid') DEFAULT 'free',
    price DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_access_type (access_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advertisements table - preserved for backward compatibility
CREATE TABLE IF NOT EXISTS adverts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('photo', 'video', 'text') NOT NULL,
    content_path VARCHAR(500),
    text_body TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Actors and Actresses table - preserved for backward compatibility
CREATE TABLE IF NOT EXISTS actors_actresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    bio TEXT,
    photo_path VARCHAR(500),
    sport_updates_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    reference VARCHAR(255),
    payment_method VARCHAR(50),
    media_id INT,
    podcast_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_reference (reference),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User media access table
CREATE TABLE IF NOT EXISTS user_media_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    media_id INT NOT NULL,
    access_type ENUM('watch', 'download') NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_media_access (user_id, media_id, access_type),
    INDEX idx_user_id (user_id),
    INDEX idx_media_id (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User podcast access table
CREATE TABLE IF NOT EXISTS user_podcast_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    podcast_id INT NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (podcast_id) REFERENCES live_podcasts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_podcast (user_id, podcast_id),
    INDEX idx_user_id (user_id),
    INDEX idx_podcast_id (podcast_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discounts table
CREATE TABLE IF NOT EXISTS discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    discount_percentage DECIMAL(5, 2) NOT NULL,
    applicable_type ENUM('media', 'podcast', 'all') NOT NULL,
    item_id INT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_applicable_type (applicable_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NEW COMPREHENSIVE SCHEMA
-- ============================================

-- A. AUTHENTICATION AND PERMISSIONS

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_role_name (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permission_name (permission_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions junction table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role_id (role_id),
    INDEX idx_permission_id (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for users.role_id
ALTER TABLE users ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

-- B. PODCAST MANAGEMENT

-- Episodes table
CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    episode_number INT,
    season_number INT DEFAULT 1,
    category_id INT NULL,
    featured_image_id INT NULL,
    audio_media_id INT NULL,
    video_url VARCHAR(500),
    duration_seconds INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_is_featured (is_featured),
    INDEX idx_published_at (published_at),
    INDEX idx_slug (slug),
    INDEX idx_episode_season (season_number, episode_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Episode guests junction table
CREATE TABLE IF NOT EXISTS episode_guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    guest_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_episode_guest (episode_id, guest_id),
    INDEX idx_episode_id (episode_id),
    INDEX idx_guest_id (guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Episode categories junction table
CREATE TABLE IF NOT EXISTS episode_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_episode_category (episode_id, category_id),
    INDEX idx_episode_id (episode_id),
    INDEX idx_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- C. MOVIES AND REVIEWS

-- Movies table
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    synopsis TEXT,
    poster_media_id INT NULL,
    poster_path VARCHAR(500) NULL,
    trailer_url VARCHAR(500),
    release_date DATE,
    director VARCHAR(255),
    runtime_minutes INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    video_display ENUM('none', 'homepage', 'video_centre', 'both') NOT NULL DEFAULT 'video_centre',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_release_date (release_date),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movie reviews table
CREATE TABLE IF NOT EXISTS movie_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    author_id INT NULL,
    review_title VARCHAR(255),
    review_content TEXT,
    rating DECIMAL(3, 1) CHECK (rating >= 0 AND rating <= 10),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_movie_id (movie_id),
    INDEX idx_status (status),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- D. ENTERTAINMENT ARTICLES

-- Articles table
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NULL,
    category_id INT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT,
    featured_image_id INT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_is_featured (is_featured),
    INDEX idx_published_at (published_at),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    category_type ENUM('episode', 'article', 'movie', 'video') DEFAULT 'article',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_type (category_type),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE articles
    ADD CONSTRAINT fk_articles_category
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Article categories junction table
CREATE TABLE IF NOT EXISTS article_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_article_category (article_id, category_id),
    INDEX idx_article_id (article_id),
    INDEX idx_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E. HOSTS AND GUESTS

-- Hosts table
CREATE TABLE IF NOT EXISTS hosts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    biography TEXT,
    profile_image_id INT NULL,
    role VARCHAR(100),
    social_links JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guests table
CREATE TABLE IF NOT EXISTS guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    biography TEXT,
    profile_image_id INT NULL,
    profession VARCHAR(255),
    social_links JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_profession (profession)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- F. MEDIA MANAGEMENT

-- Media assets table (central media library)
CREATE TABLE IF NOT EXISTS media_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    media_type ENUM('image', 'video', 'audio', 'document') NOT NULL,
    mime_type VARCHAR(100),
    file_size BIGINT,
    width INT,
    height INT,
    duration_seconds INT,
    alt_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_media_type (media_type),
    INDEX idx_stored_filename (stored_filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Episode media junction table
CREATE TABLE IF NOT EXISTS episode_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NOT NULL,
    media_id INT NOT NULL,
    media_role ENUM('featured', 'audio', 'video', 'gallery') DEFAULT 'gallery',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE,
    INDEX idx_episode_id (episode_id),
    INDEX idx_media_id (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Article media junction table
CREATE TABLE IF NOT EXISTS article_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    media_id INT NOT NULL,
    media_role ENUM('featured', 'gallery', 'inline') DEFAULT 'gallery',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE,
    INDEX idx_article_id (article_id),
    INDEX idx_media_id (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movie media junction table
CREATE TABLE IF NOT EXISTS movie_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    media_id INT NOT NULL,
    media_role ENUM('poster', 'backdrop', 'gallery', 'trailer') DEFAULT 'gallery',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE,
    INDEX idx_movie_id (movie_id),
    INDEX idx_media_id (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Host media junction table
CREATE TABLE IF NOT EXISTS host_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_id INT NOT NULL,
    media_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_host_media (host_id, media_id),
    INDEX idx_host_id (host_id),
    INDEX idx_media_id (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event media junction table
CREATE TABLE IF NOT EXISTS event_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    media_id INT NOT NULL,
    media_role ENUM('poster', 'gallery') DEFAULT 'gallery',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_media_id (media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- G. ENGAGEMENT

-- Comments table
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NULL,
    article_id INT NULL,
    movie_id INT NULL,
    user_name VARCHAR(255),
    user_email VARCHAR(255),
    comment TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'spam') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    CHECK (
        (episode_id IS NOT NULL AND article_id IS NULL AND movie_id IS NULL) OR
        (episode_id IS NULL AND article_id IS NOT NULL AND movie_id IS NULL) OR
        (episode_id IS NULL AND article_id IS NULL AND movie_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reactions table
CREATE TABLE IF NOT EXISTS reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    episode_id INT NULL,
    article_id INT NULL,
    reaction_type ENUM('like', 'love', 'clap', 'fire') NOT NULL,
    visitor_identifier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_reaction_type (reaction_type),
    INDEX idx_visitor_identifier (visitor_identifier),
    CHECK (
        (episode_id IS NOT NULL AND article_id IS NULL) OR
        (episode_id IS NULL AND article_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter subscribers table
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('pending', 'active', 'unsubscribed') DEFAULT 'pending',
    confirmation_token VARCHAR(100),
    subscribed_at TIMESTAMP NULL,
    unsubscribed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- H. EVENTS AND BUSINESS

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    location VARCHAR(255),
    event_date DATETIME,
    poster_media_id INT NULL,
    registration_url VARCHAR(500),
    status ENUM('draft', 'published', 'archived', 'past') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_event_date (event_date),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact messages table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    enquiry_type ENUM('general', 'business', 'interview', 'sponsorship', 'collaboration') DEFAULT 'general',
    status ENUM('new', 'read', 'replied', 'archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_enquiry_type (enquiry_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4_unicode_ci;

-- Sponsors table
CREATE TABLE IF NOT EXISTS sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proper advertisements table
CREATE TABLE IF NOT EXISTS advertisements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sponsor_id INT NULL,
    title VARCHAR(255),
    image_media_id INT NULL,
    target_url VARCHAR(500),
    placement ENUM('homepage', 'sidebar', 'episode', 'article', 'video') DEFAULT 'homepage',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sponsor_id) REFERENCES sponsors(id) ON DELETE SET NULL,
    FOREIGN KEY (image_media_id) REFERENCES media_assets(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_placement (placement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- I. SYSTEM CONFIGURATION

-- Site settings table
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED INITIAL DATA
-- ============================================

-- Seed roles
INSERT INTO roles (role_name, description) VALUES
('super_admin', 'Full system access with all permissions'),
('admin', 'Administrative access to manage content'),
('editor', 'Can create and edit content'),
('author', 'Can create articles and episodes'),
('user', 'Regular user with view access')
ON DUPLICATE KEY UPDATE role_name=role_name;

-- Seed permissions
INSERT INTO permissions (permission_name, description) VALUES
('manage_users', 'Manage user accounts and roles'),
('manage_episodes', 'Create, edit, and delete podcast episodes'),
('manage_videos', 'Create, edit, and delete videos'),
('manage_movies', 'Create, edit, and delete movies'),
('manage_reviews', 'Create, edit, and delete movie reviews'),
('manage_articles', 'Create, edit, and delete articles'),
('manage_categories', 'Manage content categories'),
('manage_hosts', 'Manage podcast hosts'),
('manage_guests', 'Manage podcast guests'),
('manage_events', 'Create and manage events'),
('manage_comments', 'Moderate user comments'),
('manage_newsletter', 'Manage newsletter subscribers'),
('manage_contacts', 'View and respond to contact messages'),
('manage_sponsors', 'Manage sponsorship relationships'),
('manage_advertisements', 'Manage advertisements'),
('manage_media', 'Access and manage media library'),
('manage_settings', 'Modify website settings'),
('view_analytics', 'View website analytics and reports'),
('publish_content', 'Publish draft content')
ON DUPLICATE KEY UPDATE permission_name=permission_name;

-- Assign super_admin all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.role_name = 'super_admin'
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Migrate existing admin to super_admin role
UPDATE users u SET role_id = (SELECT id FROM roles WHERE role_name = 'super_admin' LIMIT 1) WHERE u.role = 'admin';
UPDATE users u SET role_id = (SELECT id FROM roles WHERE role_name = 'user' LIMIT 1) WHERE u.role = 'user';

-- Seed categories
INSERT INTO categories (name, slug, description, category_type) VALUES
('Interview', 'interview', 'Interview episodes with guests', 'episode'),
('News', 'news', 'News and updates episodes', 'episode'),
('Review', 'review', 'Movie and entertainment reviews', 'episode'),
('Discussion', 'discussion', 'Panel discussions and debates', 'episode'),
('Behind the Scenes', 'behind-the-scenes', 'Behind the scenes content', 'episode'),
('Entertainment News', 'entertainment-news', 'Latest entertainment industry news', 'article'),
('Movie Reviews', 'movie-reviews', 'Professional movie reviews', 'article'),
('Celebrity Stories', 'celebrity-stories', 'Celebrity news and stories', 'article'),
('Industry Updates', 'industry-updates', 'Film and podcast industry updates', 'article'),
('Interviews', 'interviews', 'Exclusive interviews', 'article'),
('Editorial', 'editorial', 'Opinion pieces and editorials', 'article'),
('Action', 'action', 'Action movies', 'movie'),
('Comedy', 'comedy', 'Comedy movies', 'movie'),
('Drama', 'drama', 'Drama movies', 'movie'),
('Horror', 'horror', 'Horror movies', 'movie'),
('Sci-Fi', 'sci-fi', 'Science fiction movies', 'movie'),
('Thriller', 'thriller', 'Thriller movies', 'movie'),
('Romance', 'romance', 'Romance movies', 'movie'),
('Documentary', 'documentary', 'Documentary films', 'movie'),
('Trailers', 'trailers', 'Movie trailers', 'video'),
('Behind the Scenes', 'behind-the-scenes-video', 'Behind the scenes footage', 'video'),
('Interviews', 'interviews-video', 'Video interviews', 'video'),
('Reviews', 'reviews-video', 'Video reviews', 'video'),
('Clips', 'clips', 'Video clips and highlights', 'video')
ON DUPLICATE KEY UPDATE slug=slug;

-- Seed site settings
INSERT INTO site_settings (setting_key, setting_value) VALUES
('site_name', 'THE MOVIE ZONE PODCAST 256'),
('site_tagline', 'Your Ultimate Entertainment Destination'),
('site_description', 'Discover the latest films, exclusive live podcasts, and entertainment news.'),
('site_keywords', 'podcast, movies, entertainment, film reviews, celebrity news'),
('contact_email', 'info@tmzpodcast.com'),
('contact_phone', '+254700000000'),
('social_twitter', 'https://twitter.com/tmzpodcast'),
('social_instagram', 'https://instagram.com/tmzpodcast'),
('social_youtube', 'https://youtube.com/tmzpodcast'),
('social_facebook', 'https://facebook.com/tmzpodcast'),
('default_episode_image', 'assets/images/default-episode.jpg'),
('default_movie_poster', 'assets/images/default-poster.jpg'),
('items_per_page', '12'),
('enable_comments', '1'),
('require_comment_approval', '1'),
('enable_newsletter', '1')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- ============================================
-- END OF SCHEMA
-- ============================================
