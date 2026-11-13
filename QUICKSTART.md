# THE MOVIE ZONE PODCAST 256 Platform

A comprehensive entertainment platform featuring podcasts, movies, articles, and events.

## Quick Start

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

2. **Access the Application**
   - Homepage: http://localhost/
   - Admin Login: http://localhost/admin/login.php
   - User Login: http://localhost/login.php

3. **Default Credentials**
   - Admin: `admin` / `admin123`
   - User: Register through the website

## Installation

### Database Setup

1. **Create Database**
   ```sql
   CREATE DATABASE tmz_podcast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema**
   ```bash
   mysql -u root -p tmz_podcast < database/tmz_podcast_complete.sql
   ```

3. **Run Installation**
   ```bash
   # Visit in browser
   http://localhost/install.php
   ```

### Configuration

1. **Database Configuration**
   - Edit `config/database.php`
   - Update credentials if needed

2. **File Permissions**
   ```bash
   chmod -R 755 storage/uploads
   chmod -R 755 storage/logs
   ```

## Project Structure

```
TMZ PODCAST/
├── app/                    # Application core
│   ├── Controllers/        # MVC Controllers
│   ├── Models/            # Database Models
│   ├── Middleware/        # Authentication & CSRF
│   └── Helpers/           # Utility functions
├── config/                # Configuration files
├── database/              # Database schema
├── public/                # Public website router
├── admin/                 # Admin dashboard router
├── user/                  # User dashboard router
├── resources/             # View templates
├── assets/                # CSS, JS, images
├── storage/               # Uploads and logs
└── index.php              # Main entry point
```

## Features

### Public Website
- Browse episodes, articles, movies, and events
- Subscribe to newsletter
- Submit contact forms
- View hosts and guests

### User Dashboard
- Personalized content management
- Profile and settings management
- Comment management

### Admin Dashboard
- Complete content management
- User and role management
- Comment moderation
- Media library management
- Activity logging
- Site settings

## Security

- CSRF protection on all forms
- Password hashing with bcrypt
- Role-based access control
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- Activity logging

## Development

- PHP 7.4+
- MySQL 5.7+
- PDO for database access
- MVC architecture
- Responsive design
- Vanilla JavaScript

## License

MIT License
