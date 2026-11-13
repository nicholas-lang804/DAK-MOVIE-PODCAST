# THE MOVIE ZONE PODCAST 256 Platform

A comprehensive entertainment platform featuring podcasts, movies, articles, and events. Built with PHP, MySQL, and modern MVC architecture.

## Features

### Public Website
- **Homepage**: Featured episodes, articles, movies, and events
- **Episodes**: Browse and listen to podcast episodes
- **Articles**: Read entertainment news and updates
- **Movies**: Discover and review movies
- **Events**: View upcoming and past events
- **Hosts**: Meet the podcast hosts and guests
- **Newsletter**: Subscribe to email updates
- **Contact**: Submit contact forms

### User Dashboard
- **Personalized Content**: View episodes, articles, movies, and events
- **Profile Management**: Update account information
- **Settings**: Manage notification preferences
- **Comments**: View and manage your comments

### Admin Dashboard
- **Content Management**: Create, edit, and delete all content types
- **Media Library**: Upload and manage media assets
- **User Management**: Manage user accounts and roles
- **Comment Moderation**: Approve/reject user comments
- **Contact Management**: Handle contact form submissions
- **Newsletter Management**: Manage email subscribers
- **Sponsor Management**: Manage sponsors and advertisements
- **Activity Logs**: Track user activity
- **Site Settings**: Configure site-wide settings

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP (or any PHP/MySQL server)

### Setup Steps

1. **Clone/Download the Project**
   - Place the project folder in `C:\xampp\htdocs\TMZ PODCAST\`

2. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `tmz_podcast`
   - Import the `database/tmz_podcast_complete.sql` file

3. **Configure Database Connection**
   - Open `config/database.php`
   - Update the database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'tmz_podcast');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Set File Permissions**
   - Ensure the `storage/uploads/` and `storage/logs/` directories have write permissions

5. **Run Installation**
   - Visit `http://localhost/TMZ%20PODCAST/install.php`
   - This will create the admin user and verify the database

6. **Start Apache and MySQL**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

## Default Credentials

- **Admin**: `admin` / `admin123`
- **User**: Register through the website

⚠️ **Important**: Change the default admin password immediately after first login!

## Project Structure

```
TMZ PODCAST/
├── app/
│   ├── Controllers/     # MVC Controllers
│   │   ├── Controller.php      # Base controller
│   │   ├── HomeController.php  # Public pages
│   │   ├── EpisodeController.php
│   │   ├── ArticleController.php
│   │   ├── MovieController.php
│   │   ├── EventController.php
│   │   ├── HostController.php
│   │   ├── AdminController.php # Admin dashboard
│   │   └── UserController.php  # User dashboard
│   ├── Models/          # Database Models
│   │   ├── User.php
│   │   ├── Episode.php
│   │   ├── Article.php
│   │   ├── Movie.php
│   │   ├── MediaAsset.php
│   │   ├── Host.php
│   │   ├── Guest.php
│   │   ├── Event.php
│   │   ├── Category.php
│   │   ├── Comment.php
│   │   ├── ContactMessage.php
│   │   ├── NewsletterSubscriber.php
│   │   ├── MovieReview.php
│   │   ├── Sponsor.php
│   │   ├── Advertisement.php
│   │   ├── SiteSetting.php
│   │   ├── ActivityLog.php
│   │   └── PasswordReset.php
│   ├── Middleware/      # Authentication & CSRF
│   │   ├── Auth.php
│   │   └── CSRF.php
│   └── Helpers/         # Utility functions
│       └── Validation.php
├── config/              # Configuration files
│   └── database.php
├── database/            # Database schema and seeders
│   ├── tmz_podcast_complete.sql
│   └── seeders/
├── public/              # Public website router
│   └── index.php
├── admin/               # Admin dashboard router
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   └── dashboard.php
├── user/                # User dashboard router
│   └── index.php
├── resources/
│   └── views/           # View templates
│       ├── layouts/     # Layout templates
│       │   ├── main.php
│       │   └── admin.php
│       ├── pages/       # Public pages
│       │   ├── home.php
│       │   ├── episodes.php
│       │   ├── articles.php
│       │   ├── movies.php
│       │   ├── events.php
│       │   ├── hosts.php
│       │   ├── about.php
│       │   ├── contact.php
│       │   ├── episode-detail.php
│       │   ├── article-detail.php
│       │   ├── movie-detail.php
│       │   ├── event-detail.php
│       │   ├── host-detail.php
│       │   └── 404.php
│       ├── admin/       # Admin views
│       │   ├── dashboard.php
│       │   ├── episodes.php
│       │   ├── articles.php
│       │   ├── movies.php
│       │   ├── media.php
│       │   ├── users.php
│       │   ├── settings.php
│       │   ├── comments.php
│       │   ├── contacts.php
│       │   ├── newsletter.php
│       │   ├── sponsors.php
│       │   ├── advertisements.php
│       │   ├── hosts.php
│       │   ├── guests.php
│       │   ├── events.php
│       │   ├── categories.php
│       │   ├── reviews.php
│       │   └── logs.php
│       └── user/        # User views
│           ├── dashboard.php
│           ├── profile.php
│           └── settings.php
├── assets/              # CSS, JS, images
│   ├── css/
│   │   ├── style.css
│   │   └── modern.css
│   └── js/
├── storage/             # Uploads and logs
│   ├── uploads/
│   └── logs/
├── index.php            # Main entry point
├── login.php            # User login
├── register.php         # User registration
├── logout.php           # User logout
├── install.php          # Installation script
└── README.md            # This file
```

## Usage

### Public Website
- Access at `http://localhost/TMZ%20PODCAST/`
- Browse episodes, articles, movies, and events
- Subscribe to newsletter
- Submit contact forms

### User Dashboard
- Login at `http://localhost/TMZ%20PODCAST/login.php`
- Access dashboard at `http://localhost/TMZ%20PODCAST/user/index.php`
- Manage profile and settings
- View personalized content

### Admin Dashboard
- Login at `http://localhost/TMZ%20PODCAST/admin/login.php`
- Access dashboard at `http://localhost/TMZ%20PODCAST/admin/index.php`
- Manage all content types
- Moderate comments and messages
- View analytics and logs

## Security

- CSRF protection on all forms
- Password hashing with bcrypt
- Role-based access control
- Input validation and sanitization
- SQL injection prevention with prepared statements
- XSS protection with output escaping
- Activity logging for security monitoring

## Development

- PHP 7.4+
- MySQL 5.7+
- PDO for database access
- MVC architecture pattern
- Responsive design with modern CSS
- Vanilla JavaScript (no frameworks)

## License

MIT License - See LICENSE file for details


## Event ticketing (Ordinary / VIP / VVIP)

* **Admin → Events**: create an event with venue, contact person/phone/email, any number of ticket levels
  (name, price, currency, capacity) and discounts (code, who receives it, %, start/end, online/physical/both).
* **Physical sales**: on an event's edit page, "Record a physical sale" issues tickets instantly (with an optional discount code).
* **Gate check-in**: enter/scan the code on the ticket on the same page. Used tickets are rejected the second time.
* **Customers**: upcoming events appear in a banner on every public page (before login) and on the member dashboard
  (right after login). Buying needs a login; payment is through Pesapal; tickets are under **My Tickets** (`my-tickets.php`)
  with a printable ticket + QR (`ticket.php`).
* Database tables are created/repaired automatically the first time the site loads. `database/migrations/013_event_ticketing_complete.sql` is optional.
* Pesapal must be configured in Admin → Settings (keys, callback URL, IPN notification id) for online payment.

## Live show recordings

Live media is recorded by **LiveKit Egress** and stored in a private S3-compatible bucket; PHP does not carry the media. Enable an Egress service/worker in the LiveKit deployment and grant its storage credentials permission to write recording objects. The bucket must be reachable by the Egress worker. HTTPS is required for production browser access.

1. Apply `database/migrations/018_live_recordings.sql` to the `tmz_podcast` database.
2. Configure storage in Admin → Settings → LiveKit recording storage, or set these values in the Apache/PHP process environment or a private server configuration outside the web root. Do not commit credentials:
   - `LIVEKIT_RECORDING_S3_ACCESS_KEY`
   - `LIVEKIT_RECORDING_S3_SECRET`
   - `LIVEKIT_RECORDING_S3_REGION`
   - `LIVEKIT_RECORDING_S3_BUCKET`
   - Optional: `LIVEKIT_RECORDING_S3_ENDPOINT` for a compatible provider, and `LIVEKIT_RECORDING_S3_FORCE_PATH_STYLE` (`true`/`false`).
3. Keep the existing `LIVEKIT_URL`, `LIVEKIT_API_KEY`, and `LIVEKIT_API_SECRET` configuration available to PHP. Restart Apache after changing its process environment.
4. Restart Apache after configuring the recording environment values. Confirm the LiveKit deployment has an Egress worker and that its credentials can write to the configured bucket. In Admin → Live Studio, the recording status reports missing PHP-side configuration; Egress or bucket access errors appear when a recording is started. Start video (MP4) or audio (MP3/OGG) after the show is live, then stop to finalize the file. Ending a show also attempts to stop an active recording.
5. Once Egress marks the file complete, authorized viewers can download it from the live-show page. Paid-show downloads require the same purchase/subscription access as watching. Transcript TXT/SRT/VTT downloads can be limited to a start/end offset entered as `HH:MM:SS`.

The local PHP and JavaScript syntax checks do not verify a real LiveKit Egress worker or S3-compatible provider. Test a short recording and its download against the configured deployment before relying on it for a broadcast.

## Notifications and member messages

Apply `database/migrations/019_notifications_and_messages.sql` to enable the shared notification panels on public, member, and admin pages. Signed-in members can message the podcast team and reply in existing conversations; admins can answer from their panel. Public live-start notices and live speaker invitations/acceptances appear as clearable notifications. Guests can see public notices but must sign in to use private messages. Panels refresh automatically while the site is open.
