<?php
/**
 * Main Layout
 * Public website layout
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?php echo isset($title) ? htmlspecialchars($title) . ' - ' : ''; ?>THE DAK FILM PODCAST MEDIA</title>
    <meta name="description" content="<?php echo isset($description) ? htmlspecialchars($description) : 'Discover the latest films, exclusive live podcasts, and entertainment news.'; ?>">
    <?php if (!empty($socialShare['url'])): ?>
        <link rel="canonical" href="<?php echo htmlspecialchars($socialShare['url'], ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="THE DAK FILM PODCAST MEDIA">
        <meta property="og:title" content="<?php echo htmlspecialchars($socialShare['title'], ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($socialShare['description'], ENT_QUOTES, 'UTF-8'); ?>">
        <meta property="og:url" content="<?php echo htmlspecialchars($socialShare['url'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($socialShare['image'])): ?>
            <meta property="og:image" content="<?php echo htmlspecialchars($socialShare['image'], ENT_QUOTES, 'UTF-8'); ?>">
            <meta property="og:image:alt" content="<?php echo htmlspecialchars($socialShare['title'], ENT_QUOTES, 'UTF-8'); ?>">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="<?php echo htmlspecialchars($socialShare['image'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <meta name="twitter:card" content="summary">
        <?php endif; ?>
        <meta name="twitter:title" content="<?php echo htmlspecialchars($socialShare['title'], ENT_QUOTES, 'UTF-8'); ?>">
        <meta name="twitter:description" content="<?php echo htmlspecialchars($socialShare['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/style.css?v=tmz-20261113b">
    <link rel="stylesheet" href="/assets/css/cinematic.css?v=cine-2">
    <meta name="theme-color" content="#050a1a">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <link rel="stylesheet" href="/assets/css/site-extras.css?v=se-1">
    <link rel="stylesheet" href="/assets/css/music.css?v=mz-2">
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="tmz-cinema">
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/">
                    <img class="tmz-logo" src="/assets/img/dak-mark.png" alt="THE DAK FILM PODCAST MEDIA logo">
                    <h1>THE DAK FILM PODCAST MEDIA</h1>
                </a>
            </div>
            <button type="button" class="nav-toggle" aria-label="Menu" aria-expanded="false" aria-controls="primary-menu"><span></span><span></span><span></span></button>
            <div class="nav-menu" id="primary-menu">
                <a href="/episodes.php">Episodes</a>
                <a href="/videos.php">Videos</a>
                <a href="/live.php">Live</a>
                <a href="/movies.php">Movies</a>
                <a href="/music.php">Music</a>
                <a href="/stars.php">Stars</a>
                <a href="/search.php" aria-label="Search the site">Search</a>
                <a href="/news.php">News</a>
                <a href="/hosts.php">Hosts</a>
                <a href="/events.php">Events</a>
                <a href="/sell-tickets.php" class="btn btn-primary" style="padding:.45rem .9rem">Sale Tickets With Us</a>
                <a href="/about.php">About</a>
                <a href="/contact.php">Contact</a>
                <a href="/membership.php">All Access</a>
                <?php if (isLoggedIn()): ?>
                    <?php if (!empty($_SESSION['user_id'])): ?><a href="/my-list.php">My List</a><a href="/my-tickets.php">My Tickets</a><?php endif; ?>
                    <a href="/dashboard.php" class="btn btn-outline">Dashboard</a>
                    <a href="/logout.php" class="btn btn-secondary">Logout</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-outline">Login</a>
                    <a href="/register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php include __DIR__ . '/../partials/notification-panel.php'; ?>

    <?php
    // Upcoming-events strip: shown to visitors before login and to members after login
    // (the member dashboard has its own fuller events section, so it is skipped there).
    if (!isset($activeTab)) {
        $tmzBannerEvents = [];
        try {
            require_once __DIR__ . '/../../../app/Models/Event.php';
            $tmzBannerEvents = (new Event())->getUpcoming(3);
        } catch (Throwable $e) { error_log('Event banner: ' . $e->getMessage()); }
    }
    if (!empty($tmzBannerEvents)): ?>
    <aside class="event-banner" aria-label="Upcoming events">
        <div class="event-banner-inner">
            <span class="event-banner-label">Upcoming events</span>
            <div class="event-banner-list">
                <?php foreach ($tmzBannerEvents as $bev): ?>
                    <a class="event-banner-item" href="/events.php?slug=<?php echo urlencode($bev['slug']); ?>">
                        <strong><?php echo htmlspecialchars($bev['title']); ?></strong>
                        <span><?php echo !empty($bev['event_date']) ? htmlspecialchars(date('M d · H:i', strtotime($bev['event_date']))) : ''; ?><?php echo !empty($bev['location']) ? ' · ' . htmlspecialchars($bev['location']) : ''; ?></span>
                        <?php if (isset($bev['min_price']) && $bev['min_price'] !== null): ?><em>From <?php echo htmlspecialchars(Event::money($bev['price_currency'], $bev['min_price'])); ?></em><?php endif; ?>
                        <?php if (!empty($bev['active_discounts'])): ?><b class="discount-pill">Discount</b><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a class="btn btn-small btn-primary" href="/events.php">Get tickets</a>
        </div>
    </aside>
    <?php endif; ?>

    <div class="tmz-topads"><?php tmz_ad_slot('banner', 'leaderboard', 3); ?></div>

    <main class="main-content">
        <?php echo isset($content) ? $content : ''; ?>
    </main>

    <div class="tmz-footads"><?php tmz_ad_slot('footer', 'leaderboard', 4); ?></div>

    <?php include __DIR__ . '/../partials/music-player.php'; ?>

    <div id="cookieNotice" class="cookie-notice" role="dialog" aria-label="Cookie notice" hidden>
        <p>We use essential cookies to keep you signed in and secure. <a href="/cookies.php">Learn more</a></p>
        <button type="button" class="btn btn-primary btn-small" id="cookieNoticeOk">Got it</button>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-sections">
                <div class="footer-section">
                    <img class="tmz-logo tmz-logo-footer" src="/assets/img/dak-mark.png" alt="THE DAK FILM PODCAST MEDIA logo">
                    <h4>THE DAK FILM PODCAST MEDIA</h4>
                    <p>Your ultimate destination for films, podcasts, and entertainment.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="/episodes.php">Episodes</a></li>
                        <li><a href="/videos.php">Videos</a></li>
                        <li><a href="/movies.php">Movies</a></li>
                        <li><a href="/stars.php">Actors &amp; Actresses</a></li>
                        <li><a href="/news.php">News</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Upcoming Events</h4>
                    <ul>
                        <?php
                        $tmzFooterEvents = $tmzBannerEvents ?? [];
                        if (empty($tmzFooterEvents)) {
                            try { require_once __DIR__ . '/../../../app/Models/Event.php'; $tmzFooterEvents = (new Event())->getUpcoming(4); } catch (Throwable $e) { $tmzFooterEvents = []; }
                        }
                        if (empty($tmzFooterEvents)): ?>
                            <li><a href="/events.php">Browse all events</a></li>
                        <?php else: foreach (array_slice($tmzFooterEvents, 0, 4) as $fev): ?>
                            <li><a href="/events.php?slug=<?php echo urlencode($fev['slug']); ?>"><?php echo htmlspecialchars($fev['title']); ?><?php echo !empty($fev['event_date']) ? ' — ' . date('M d', strtotime($fev['event_date'])) : ''; ?></a></li>
                        <?php endforeach; endif; ?>
                        <li><a href="/events.php">All events →</a></li>
                        <li><a href="/sell-tickets.php">Sale Tickets With Us</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Connect</h4>
                    <ul>
                        <li><a href="https://twitter.com/tmzpodcast" target="_blank">Twitter</a></li>
                        <li><a href="https://instagram.com/tmzpodcast" target="_blank">Instagram</a></li>
                        <li><a href="https://youtube.com/tmzpodcast" target="_blank">YouTube</a></li>
                        <li><a href="https://facebook.com/tmzpodcast" target="_blank">Facebook</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact Us</h4>
                    <ul>
                        <?php if (!empty($contactEmail)): ?>
                            <li><a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($contactPhone)): ?>
                            <li><a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+*#,;]/', '', $contactPhone), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactPhone, ENT_QUOTES, 'UTF-8'); ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($contactLocation)): ?>
                            <li><?php echo htmlspecialchars($contactLocation, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> THE DAK FILM PODCAST MEDIA. All rights reserved.</p>
                <p class="footer-legal"><a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a> · <a href="/cookies.php">Cookies</a></p>
            </div>
        </div>
    </footer>

    <script src="/assets/js/main.js?v=tmz-20261106"></script>
    <script src="/assets/js/cinematic.js?v=cine-1" defer></script>
    <script src="/assets/js/site-extras.js?v=se-1" defer></script>
    <script src="/assets/js/music-player.js?v=mz-4" defer></script>
    <script src="/assets/js/notifications.js?v=notifications-2" defer></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?php echo htmlspecialchars($js); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
