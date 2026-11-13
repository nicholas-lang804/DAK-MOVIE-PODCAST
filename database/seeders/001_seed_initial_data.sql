-- THE MOVIE ZONE PODCAST 256 Database Seeder
-- Version: 001
-- Description: Seed initial data for comprehensive schema
-- Date: 2026-11-05

USE tmz_podcast;

-- ============================================
-- SEED ROLES
-- ============================================

INSERT INTO roles (role_name, description) VALUES
('super_admin', 'Full system access with all permissions'),
('admin', 'Administrative access to manage content'),
('editor', 'Can create and edit content'),
('author', 'Can create articles and episodes'),
('user', 'Regular user with view access')
ON DUPLICATE KEY UPDATE role_name=role_name;

-- ============================================
-- SEED PERMISSIONS
-- ============================================

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

-- ============================================
-- ASSIGN PERMISSIONS TO ROLES
-- ============================================

-- Super Admin gets all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.role_name = 'super_admin'
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Admin gets most permissions (except user management)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name = 'admin' AND p.permission_name != 'manage_users'
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Editor gets content management permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name = 'editor' 
AND p.permission_name IN (
    'manage_episodes', 'manage_videos', 'manage_movies', 'manage_reviews',
    'manage_articles', 'manage_categories', 'manage_hosts', 'manage_guests',
    'manage_events', 'manage_media', 'publish_content'
)
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Author gets limited content creation permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_name = 'author' 
AND p.permission_name IN ('manage_articles', 'manage_episodes', 'publish_content')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- ============================================
-- MIGRATE EXISTING USERS TO NEW ROLE SYSTEM
-- ============================================

-- Get role IDs
SET @super_admin_id = (SELECT id FROM roles WHERE role_name = 'super_admin' LIMIT 1);
SET @admin_id = (SELECT id FROM roles WHERE role_name = 'admin' LIMIT 1);
SET @user_id = (SELECT id FROM roles WHERE role_name = 'user' LIMIT 1);

-- Update existing admin users
UPDATE users SET role_id = @super_admin_id WHERE role = 'admin';
UPDATE users SET role_id = @user_id WHERE role = 'user';

-- ============================================
-- SEED CATEGORIES
-- ============================================

-- Episode categories
INSERT INTO categories (name, slug, description, category_type) VALUES
('Interview', 'interview', 'Interview episodes with guests', 'episode'),
('News', 'news', 'News and updates episodes', 'episode'),
('Review', 'review', 'Movie and entertainment reviews', 'episode'),
('Discussion', 'discussion', 'Panel discussions and debates', 'episode'),
('Behind the Scenes', 'behind-the-scenes', 'Behind the scenes content', 'episode')
ON DUPLICATE KEY UPDATE slug=slug;

-- Article categories
INSERT INTO categories (name, slug, description, category_type) VALUES
('Entertainment News', 'entertainment-news', 'Latest entertainment industry news', 'article'),
('Movie Reviews', 'movie-reviews', 'Professional movie reviews', 'article'),
('Celebrity Stories', 'celebrity-stories', 'Celebrity news and stories', 'article'),
('Industry Updates', 'industry-updates', 'Film and podcast industry updates', 'article'),
('Interviews', 'interviews', 'Exclusive interviews', 'article'),
('Editorial', 'editorial', 'Opinion pieces and editorials', 'article')
ON DUPLICATE KEY UPDATE slug=slug;

-- Movie categories
INSERT INTO categories (name, slug, description, category_type) VALUES
('Action', 'action', 'Action movies', 'movie'),
('Comedy', 'comedy', 'Comedy movies', 'movie'),
('Drama', 'drama', 'Drama movies', 'movie'),
('Horror', 'horror', 'Horror movies', 'movie'),
('Sci-Fi', 'sci-fi', 'Science fiction movies', 'movie'),
('Thriller', 'thriller', 'Thriller movies', 'movie'),
('Romance', 'romance', 'Romance movies', 'movie'),
('Documentary', 'documentary', 'Documentary films', 'movie')
ON DUPLICATE KEY UPDATE slug=slug;

-- Video categories
INSERT INTO categories (name, slug, description, category_type) VALUES
('Trailers', 'trailers', 'Movie trailers', 'video'),
('Behind the Scenes', 'behind-the-scenes-video', 'Behind the scenes footage', 'video'),
('Interviews', 'interviews-video', 'Video interviews', 'video'),
('Reviews', 'reviews-video', 'Video reviews', 'video'),
('Clips', 'clips', 'Video clips and highlights', 'video')
ON DUPLICATE KEY UPDATE slug=slug;

-- ============================================
-- SEED SITE SETTINGS
-- ============================================

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
-- END OF SEEDER
-- ============================================
