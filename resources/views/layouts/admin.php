<?php
/**
 * Admin Layout
 * Admin dashboard layout
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? htmlspecialchars($title) . ' - ' : ''; ?>Admin - THE DAK FILM PODCAST MEDIA</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=tmz-20261113a">
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="admin-page">
    <nav class="navbar admin-navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <img class="tmz-logo" src="/assets/img/dak-mark.png" alt="THE DAK FILM PODCAST MEDIA logo">
                <h1>THE DAK FILM PODCAST MEDIA Admin</h1>
            </div>
            <div class="nav-menu">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                <a href="/admin/change_password.php" class="btn btn-outline">Change Password</a>
                <a href="/admin/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </nav>

    <?php include __DIR__ . '/../partials/notification-panel.php'; ?>

    <div class="admin-container">
        <aside class="admin-sidebar">
            <ul class="sidebar-menu">
                <li>
                    <a href="/admin/dashboard.php?tab=live" class="<?php echo isset($activeTab) && $activeTab === 'live' ? 'active' : ''; ?>">Live Studio</a>
                </li>
                <li>
                    <a href="/admin/dashboard.php" class="<?php echo isset($activeTab) && $activeTab === 'dashboard' ? 'active' : ''; ?>">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=finance" class="<?php echo isset($activeTab) && $activeTab === 'finance' ? 'active' : ''; ?>">
                        Financial Control
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=payments" class="<?php echo isset($activeTab) && $activeTab === 'payments' ? 'active' : ''; ?>">
                        Pesapal Payments
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=episodes" class="<?php echo isset($activeTab) && $activeTab === 'episodes' ? 'active' : ''; ?>">
                        Podcast Episodes
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=videos" class="<?php echo isset($activeTab) && $activeTab === 'videos' ? 'active' : ''; ?>">
                        Video Library
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=movies" class="<?php echo isset($activeTab) && $activeTab === 'movies' ? 'active' : ''; ?>">
                        Movies
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=reviews" class="<?php echo isset($activeTab) && $activeTab === 'reviews' ? 'active' : ''; ?>">
                        Movie Reviews
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=articles" class="<?php echo isset($activeTab) && $activeTab === 'articles' ? 'active' : ''; ?>">
                        Entertainment News
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=categories" class="<?php echo isset($activeTab) && $activeTab === 'categories' ? 'active' : ''; ?>">
                        Categories
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=hosts" class="<?php echo isset($activeTab) && $activeTab === 'hosts' ? 'active' : ''; ?>">
                        Hosts
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=guests" class="<?php echo isset($activeTab) && $activeTab === 'guests' ? 'active' : ''; ?>">
                        Guests
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=stars" class="<?php echo isset($activeTab) && $activeTab === 'stars' ? 'active' : ''; ?>">
                        Stars (Actors)
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=music" class="<?php echo isset($activeTab) && $activeTab === 'music' ? 'active' : ''; ?>">
                        Music
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=gifts" class="<?php echo isset($activeTab) && $activeTab === 'gifts' ? 'active' : ''; ?>">
                        Live Gifts
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=events" class="<?php echo isset($activeTab) && $activeTab === 'events' ? 'active' : ''; ?>">
                        Events
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=comments" class="<?php echo isset($activeTab) && $activeTab === 'comments' ? 'active' : ''; ?>">
                        Comments
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=newsletter" class="<?php echo isset($activeTab) && $activeTab === 'newsletter' ? 'active' : ''; ?>">
                        Newsletter
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=contacts" class="<?php echo isset($activeTab) && $activeTab === 'contacts' ? 'active' : ''; ?>">
                        Contact Messages
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=sponsors" class="<?php echo isset($activeTab) && $activeTab === 'sponsors' ? 'active' : ''; ?>">
                        Sponsorships
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=advertisements" class="<?php echo isset($activeTab) && $activeTab === 'advertisements' ? 'active' : ''; ?>">
                        Advertisements
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=media" class="<?php echo isset($activeTab) && $activeTab === 'media' ? 'active' : ''; ?>">
                        Media Library
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=users" class="<?php echo isset($activeTab) && $activeTab === 'users' ? 'active' : ''; ?>">
                        Users & Roles
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=settings" class="<?php echo isset($activeTab) && $activeTab === 'settings' ? 'active' : ''; ?>">
                        Website Settings
                    </a>
                </li>
                <li>
                    <a href="/admin/dashboard.php?tab=logs" class="<?php echo isset($activeTab) && $activeTab === 'logs' ? 'active' : ''; ?>">
                        Activity Logs
                    </a>
                </li>
            </ul>
        </aside>

        <main class="admin-main-content">
            <?php echo isset($content) ? $content : ''; ?>
        </main>
    </div>

    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/dashboard.js"></script>
    <script src="/assets/js/notifications.js?v=notifications-2" defer></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?php echo htmlspecialchars($js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
