<?php
/**
 * User Dashboard View
 */
?>

<section class="user-dashboard">
    <div class="section-header">
        <div>
            <span class="member-eyebrow">THE DAK FILM PODCAST MEDIA</span>
            <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?></h1>
            <p>Your private cinema desk for the latest films, episodes, videos and stories.</p>
        </div>
        <a href="/TMZ%20PODCAST/movies.php" class="btn btn-primary member-hero-action">Explore the catalogue</a>
    </div>

    <?php if (isset($_SESSION['profile_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['profile_success']); ?></div>
        <?php unset($_SESSION['profile_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['profile_error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['profile_error']); ?></div>
        <?php unset($_SESSION['profile_error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['settings_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['settings_success']); ?></div>
        <?php unset($_SESSION['settings_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['settings_error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['settings_error']); ?></div>
        <?php unset($_SESSION['settings_error']); ?>
    <?php endif; ?>

    <?php $dashboardShowcaseMovies = array_values($recentMovies ?? []); ?>
    <?php $dashboardFeaturedMovie = $dashboardShowcaseMovies[0] ?? null; ?>
    <?php if ($dashboardFeaturedMovie): ?>
        <?php $dashboardFeaturedLink = (($dashboardFeaturedMovie['access_type'] ?? 'free') === 'paid') ? '/TMZ%20PODCAST/payment.php?movie_id=' . (int)$dashboardFeaturedMovie['id'] : '/TMZ%20PODCAST/movies.php?slug=' . urlencode($dashboardFeaturedMovie['slug']) . '#watch-movie'; ?>
        <?php $dashboardFeaturedLabel = (($dashboardFeaturedMovie['access_type'] ?? 'free') === 'paid') ? 'Pay to Watch' : 'Watch now'; ?>
        <section class="movie-showcase">
            <div class="movie-showcase-shell">
                <div class="movie-showcase-feature">
                    <div class="movie-showcase-poster">
                        <?php if (!empty($dashboardFeaturedMovie['poster_path'])): ?>
                            <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($dashboardFeaturedMovie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($dashboardFeaturedMovie['title']); ?>">
                        <?php else: ?>
                            <div class="movie-showcase-fallback"><span>DAK</span><strong><?php echo htmlspecialchars($dashboardFeaturedMovie['title']); ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <div class="movie-showcase-content">
                        <span class="movie-showcase-label">Featured today</span>
                        <h2><?php echo htmlspecialchars($dashboardFeaturedMovie['title']); ?></h2>
                        <div class="movie-showcase-meta">
                            <span class="movie-showcase-tag"><?php echo htmlspecialchars($dashboardFeaturedMovie['genre_names'] ?: 'Adventure'); ?></span>
                            <span><?php echo htmlspecialchars(!empty($dashboardFeaturedMovie['release_date']) ? date('Y', strtotime($dashboardFeaturedMovie['release_date'])) : '2025'); ?></span>
                            <?php if (!empty($dashboardFeaturedMovie['runtime_minutes'])): ?>
                                <span><?php echo (int)$dashboardFeaturedMovie['runtime_minutes']; ?> min</span>
                            <?php endif; ?>
                        </div>
                        <p><?php echo htmlspecialchars(substr($dashboardFeaturedMovie['synopsis'] ?? '', 0, 160)); ?><?php echo !empty($dashboardFeaturedMovie['synopsis']) && strlen($dashboardFeaturedMovie['synopsis']) > 160 ? '...' : ''; ?></p>
                        <div class="movie-showcase-actions">
                            <a href="<?php echo htmlspecialchars($dashboardFeaturedLink); ?>" class="btn btn-primary"><?php echo htmlspecialchars($dashboardFeaturedLabel); ?></a>
                            <a href="/TMZ%20PODCAST/membership.php" class="btn btn-outline">Plans from UGX 500</a>
                        </div>
                    </div>
                </div>

                <div class="movie-showcase-controls" aria-label="Movie navigation">
                    <button type="button" class="movie-showcase-arrow prev" aria-label="Previous movie">‹</button>
                    <button type="button" class="movie-showcase-arrow next" aria-label="Next movie">›</button>
                </div>

                <div class="movie-showcase-rail" aria-label="Featured movies" hidden>
                    <div class="movie-showcase-track">
                        <?php foreach (array_merge($dashboardShowcaseMovies, $dashboardShowcaseMovies) as $movie): ?>
                            <?php $movieIsPaid = (($movie['access_type'] ?? 'free') === 'paid'); ?>
                            <?php $movieLink = $movieIsPaid ? '/TMZ%20PODCAST/payment.php?movie_id=' . (int)$movie['id'] : '/TMZ%20PODCAST/movies.php?slug=' . urlencode($movie['slug']) . '#watch-movie'; ?>
                            <?php $movieAction = $movieIsPaid ? 'Pay to Watch' : 'Watch now'; ?>
                            <article class="movie-showcase-item"
                                data-title="<?php echo htmlspecialchars(addslashes($movie['title'])); ?>"
                                data-image="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim((string)($movie['poster_path'] ?? ''), '/')); ?>"
                                data-link="<?php echo htmlspecialchars($movieLink); ?>"
                                data-button-label="<?php echo htmlspecialchars($movieAction); ?>"
                                data-synopsis="<?php echo htmlspecialchars(addslashes(substr($movie['synopsis'] ?? '', 0, 160))); ?>"
                                data-genre="<?php echo htmlspecialchars(addslashes($movie['genre_names'] ?: 'Adventure')); ?>"
                                data-year="<?php echo htmlspecialchars(!empty($movie['release_date']) ? date('Y', strtotime($movie['release_date'])) : '2025'); ?>"
                                data-runtime="<?php echo htmlspecialchars(!empty($movie['runtime_minutes']) ? (int)$movie['runtime_minutes'] . ' min' : ''); ?>"
                                data-paid="<?php echo $movieIsPaid ? '1' : '0'; ?>">
                                <div class="movie-showcase-item-poster">
                                    <?php if (!empty($movie['poster_path'])): ?>
                                        <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($movie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                                    <?php else: ?>
                                        <div class="movie-showcase-item-fallback"><?php echo htmlspecialchars($movie['title']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="movie-showcase-item-content">
                                    <span class="movie-showcase-item-status"><?php echo $movieIsPaid ? 'Paid' : 'New'; ?></span>
                                    <strong><?php echo htmlspecialchars($movie['title']); ?></strong>
                                    <a href="<?php echo htmlspecialchars($movieLink); ?>" class="movie-showcase-item-button"><?php echo htmlspecialchars($movieAction); ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="dashboard-tabs">
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=dashboard" class="tab <?php echo $activeTab === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=episodes" class="tab <?php echo $activeTab === 'episodes' ? 'active' : ''; ?>">Episodes</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=articles" class="tab <?php echo $activeTab === 'articles' ? 'active' : ''; ?>">Articles</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=movies" class="tab <?php echo $activeTab === 'movies' ? 'active' : ''; ?>">Movies</a>
        <a href="/TMZ%20PODCAST/stars.php" class="tab">Stars</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=events" class="tab <?php echo $activeTab === 'events' ? 'active' : ''; ?>">Events</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=tickets" class="tab <?php echo $activeTab === 'tickets' ? 'active' : ''; ?>">My Tickets</a>
        <span class="tab-sep">· EVENT MANAGEMENT ·</span>
        <a href="/TMZ%20PODCAST/sell-tickets.php" class="tab" style="color:#c9a227;font-weight:800">🎟 Sale Tickets With Us</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=event-create" class="tab <?php echo $activeTab === 'event-create' ? 'active' : ''; ?>">Create New Event</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=my-events" class="tab <?php echo $activeTab === 'my-events' ? 'active' : ''; ?>">My Events</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=event-fees" class="tab <?php echo $activeTab === 'event-fees' ? 'active' : ''; ?>">Publishing &amp; Payments</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=payment-history" class="tab <?php echo $activeTab === 'payment-history' ? 'active' : ''; ?>">Payment History</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=event-reports" class="tab <?php echo $activeTab === 'event-reports' ? 'active' : ''; ?>">Event Reports</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=hosts" class="tab <?php echo $activeTab === 'hosts' ? 'active' : ''; ?>">Hosts</a>
        <a href="/TMZ%20PODCAST/user/dashboard.php?tab=comments" class="tab <?php echo $activeTab === 'comments' ? 'active' : ''; ?>">My Comments</a>
        <a href="/TMZ%20PODCAST/user/profile.php" class="tab">Profile</a>
        <a href="/TMZ%20PODCAST/user/settings.php" class="tab">Settings</a>
    </div>

    <!-- Dashboard Overview -->
    <?php if ($activeTab === 'dashboard'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-value"><?php echo $stats['episodes']; ?></div>
                <div class="stat-card-label">Episodes</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?php echo $stats['articles']; ?></div>
                <div class="stat-card-label">Articles</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?php echo $stats['movies']; ?></div>
                <div class="stat-card-label">Movies</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?php echo $stats['events']; ?></div>
                <div class="stat-card-label">Upcoming Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?php echo $stats['comments']; ?></div>
                <div class="stat-card-label">My Comments</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-value"><?php echo $stats['videos']; ?></div>
                <div class="stat-card-label">Video Releases</div>
            </div>
        </div>

        <section class="member-spotlight" style="border-left:4px solid #c9a227">
            <div class="member-spotlight-copy">
                <span class="member-eyebrow">ORGANISE YOUR EVENT</span>
                <h2>Sale Tickets With Us</h2>
                <p>Create your event, set ticket prices and start selling — pay a one-time publishing fee to go live.</p>
                <div class="member-spotlight-actions">
                    <a class="btn btn-primary" href="/TMZ%20PODCAST/sell-tickets.php">Create Your Event</a>
                    <a class="btn btn-outline" href="/TMZ%20PODCAST/user/dashboard.php?tab=event-fees">Publishing &amp; Payments</a>
                </div>
            </div>
        </section>

        <section class="member-spotlight">
            <div class="member-spotlight-copy">
                <span class="member-eyebrow">YOUR NEXT SCREENING</span>
                <h2>Fresh cinema. New voices. More to discover.</h2>
                <p>Stay close to every new release from the THE DAK FILM PODCAST MEDIA newsroom and screening room.</p>
                <div class="member-spotlight-actions">
                    <a class="btn btn-primary" href="/TMZ%20PODCAST/videos.php">Open Video Centre</a>
                    <a class="btn btn-outline" href="/TMZ%20PODCAST/news.php">Read New Stories</a>
                </div>
            </div>
            <div class="member-spotlight-lines" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
        </section>

        <?php include __DIR__ . '/../partials/stars-strip.php'; ?>

        <?php tmz_ad_slot('dashboard', 'leaderboard', 3); ?>

        <section class="member-content-section">
            <div class="member-section-heading"><div><span class="member-eyebrow">SCREENING ROOM</span><h2>Latest videos</h2></div><a href="/TMZ%20PODCAST/videos.php">View all videos</a></div>
            <div class="member-video-grid">
                <?php foreach (($recentVideos ?? []) as $video): ?>
                    <article class="member-video-card">
                        <video controls preload="metadata" src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($video['storage_path'], '/')); ?>"></video>
                        <div><h3><?php echo htmlspecialchars($video['alt_text'] ?: $video['original_filename']); ?></h3><p>New video release</p></div>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($recentVideos)): ?><div class="member-empty-state">New videos will appear here as soon as they are published.</div><?php endif; ?>
            </div>
        </section>

        <section class="member-content-section">
            <div class="member-section-heading"><div><span class="member-eyebrow">FEATURED FILMS</span><h2>Latest movies</h2></div><a href="/TMZ%20PODCAST/movies.php">Browse movies</a></div>
            <div class="member-movie-strip">
                <?php foreach (($recentMovies ?? []) as $movie): ?>
                    <article class="member-movie-card">
                        <?php if (!empty($movie['poster_path'])): ?><img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($movie['poster_path'], '/')); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                        <?php else: ?><div class="member-movie-fallback"><span>DAK</span><strong><?php echo htmlspecialchars($movie['title']); ?></strong></div><?php endif; ?>
                        <div class="member-movie-card-body"><h3><?php echo htmlspecialchars($movie['title']); ?></h3><p><?php echo !empty($movie['release_date']) ? date('Y', strtotime($movie['release_date'])) : 'New release'; ?></p><a href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($movie['slug']); ?>">Open film</a></div>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($recentMovies)): ?><div class="member-empty-state">New movies will appear here as the catalogue grows.</div><?php endif; ?>
            </div>
        </section>

        <?php tmz_ad_slot('between_content', 'leaderboard', 3); ?>

        <div class="dashboard-grid">
            <div class="dashboard-section">
                <h2>Recent Episodes</h2>
                <div class="content-list">
                    <?php foreach ($recentEpisodes as $episode): ?>
                        <div class="content-item">
                            <h3><?php echo htmlspecialchars($episode['title']); ?></h3>
                            <p><?php echo date('M d, Y', strtotime($episode['published_at'])); ?></p>
                            <a href="/TMZ%20PODCAST/episodes.php?slug=<?php echo $episode['slug']; ?>" class="btn btn-small">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dashboard-section">
                <h2>Recent Articles</h2>
                <div class="content-list">
                    <?php foreach ($recentArticles as $article): ?>
                        <div class="content-item">
                            <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                            <p><?php echo date('M d, Y', strtotime($article['published_at'])); ?></p>
                            <a href="/TMZ%20PODCAST/news.php?slug=<?php echo $article['slug']; ?>" class="btn btn-small">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dashboard-section dashboard-events">
                <h2>Upcoming Events <a class="btn btn-small btn-outline" href="/TMZ%20PODCAST/user/dashboard.php?tab=tickets">My Tickets (<?php echo (int)($myTicketCount ?? 0); ?>)</a></h2>
                <div class="content-list">
                    <?php if (empty($upcomingEvents)): ?><p class="form-help">No upcoming events right now — check back soon.</p><?php endif; ?>
                    <?php foreach ($upcomingEvents as $event): ?>
                        <div class="event-dash-card">
                            <div>
                                <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p><?php echo !empty($event['event_date']) ? htmlspecialchars(date('D, M d Y · H:i', strtotime($event['event_date']))) : 'Date to be announced'; ?></p>
                                <p><?php echo htmlspecialchars($event['location'] ?? 'Venue to be announced'); ?></p>
                                <?php if (isset($event['min_price']) && $event['min_price'] !== null): ?>
                                    <p class="event-from-price">Tickets from <strong><?php echo htmlspecialchars(Event::money($event['price_currency'], $event['min_price'])); ?></strong>
                                    <?php if (!empty($event['active_discounts'])): ?><span class="discount-pill">Discount available</span><?php endif; ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="/TMZ%20PODCAST/events.php?slug=<?php echo urlencode($event['slug']); ?>#tickets" class="btn btn-small btn-primary">Buy tickets</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Episodes Tab -->
    <?php if ($activeTab === 'episodes'): ?>
        <div class="content-grid">
            <?php foreach ($episodes as $episode): ?>
                <div class="content-card">
                    <h3><?php echo htmlspecialchars($episode['title']); ?></h3>
                    <p><?php echo date('M d, Y', strtotime($episode['published_at'])); ?></p>
                    <p><?php echo htmlspecialchars(substr($episode['description'], 0, 150)); ?>...</p>
                    <a href="/TMZ%20PODCAST/episodes.php?slug=<?php echo $episode['slug']; ?>" class="btn btn-small">View Episode</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Articles Tab -->
    <?php if ($activeTab === 'articles'): ?>
        <div class="content-grid">
            <?php foreach ($articles as $article): ?>
                <div class="content-card">
                    <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                    <p><?php echo date('M d, Y', strtotime($article['published_at'])); ?></p>
                    <p><?php echo htmlspecialchars(substr($article['excerpt'], 0, 150)); ?>...</p>
                    <a href="/TMZ%20PODCAST/news.php?slug=<?php echo $article['slug']; ?>" class="btn btn-small">Read Article</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Movies Tab -->
    <?php if ($activeTab === 'movies'): ?>
        <div class="content-grid">
            <?php foreach ($movies as $movie): ?>
                <div class="content-card">
                    <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                    <p><?php echo !empty($movie['release_date']) ? date('M d, Y', strtotime($movie['release_date'])) : 'Release date not set'; ?></p>
                    <p><?php echo htmlspecialchars(substr($movie['synopsis'] ?? 'No synopsis available.', 0, 150)); ?>...</p>
                    <a href="/TMZ%20PODCAST/movies.php?slug=<?php echo $movie['slug']; ?>" class="btn btn-small">View Movie</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Events Tab -->
    <?php if ($activeTab === 'events'): ?>
        <div class="event-dash-list">
            <?php if (empty($events)): ?><p class="form-help">No upcoming events right now.</p><?php endif; ?>
            <?php foreach ($events as $event): ?>
                <div class="event-dash-card">
                            <div>
                                <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p><?php echo !empty($event['event_date']) ? htmlspecialchars(date('D, M d Y · H:i', strtotime($event['event_date']))) : 'Date to be announced'; ?></p>
                                <p><?php echo htmlspecialchars($event['location'] ?? 'Venue to be announced'); ?></p>
                                <?php if (isset($event['min_price']) && $event['min_price'] !== null): ?>
                                    <p class="event-from-price">Tickets from <strong><?php echo htmlspecialchars(Event::money($event['price_currency'], $event['min_price'])); ?></strong>
                                    <?php if (!empty($event['active_discounts'])): ?><span class="discount-pill">Discount available</span><?php endif; ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="/TMZ%20PODCAST/events.php?slug=<?php echo urlencode($event['slug']); ?>#tickets" class="btn btn-small btn-primary">Buy tickets</a>
                        </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($pastEvents)): ?>
            <h2 class="dash-sub">Past events</h2>
            <div class="content-grid">
                <?php foreach ($pastEvents as $event): ?>
                    <div class="content-card">
                        <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                        <p><?php echo !empty($event['event_date']) ? htmlspecialchars(date('M d, Y', strtotime($event['event_date']))) : ''; ?></p>
                        <a href="/TMZ%20PODCAST/events.php?slug=<?php echo urlencode($event['slug']); ?>" class="btn btn-small">View</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Tickets Tab -->
    <?php if ($activeTab === 'tickets'): ?>
        <?php include __DIR__ . '/../partials/tickets-list.php'; ?>
    <?php endif; ?>

    <!-- Hosts Tab -->
    <?php if ($activeTab === 'hosts'): ?>
        <div class="content-grid">
            <?php foreach ($hosts as $host): ?>
                <div class="content-card">
                    <h3><?php echo htmlspecialchars($host['name']); ?></h3>
                    <p><?php echo htmlspecialchars($host['profession'] ?? 'N/A'); ?></p>
                    <p><?php echo htmlspecialchars(substr($host['biography'], 0, 150)); ?>...</p>
                    <a href="/TMZ%20PODCAST/hosts.php?slug=<?php echo $host['slug']; ?>" class="btn btn-small">View Profile</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Comments Tab -->
    <?php if ($activeTab === 'comments'): ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-card">
                    <div class="comment-header">
                        <span class="comment-author"><?php echo htmlspecialchars($comment['user_name']); ?></span>
                        <span class="comment-date"><?php echo date('M d, Y H:i', strtotime($comment['created_at'])); ?></span>
                    </div>
                    <div class="comment-content">
                        <?php echo htmlspecialchars($comment['comment']); ?>
                    </div>
                    <div class="comment-status">
                        <span class="status-badge status-<?php echo $comment['status']; ?>"><?php echo ucfirst($comment['status']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ============ EVENT MANAGEMENT ============ -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['fee_notice'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['fee_notice']); ?></div>
        <?php unset($_SESSION['fee_notice']); ?>
    <?php endif; ?>

    <?php if ($activeTab === 'event-create'): ?>
        <?php $ev = $editEvent ?? null; $fs = $feeSettings ?? ['enabled'=>false,'amount'=>0,'currency'=>'UGX']; $ef = $eventFee ?? null; ?>
        <div class="form-container" style="max-width:860px">
            <h2><?php echo $ev ? 'Edit Event' : 'Create New Event'; ?></h2>
            <?php if (!empty($fs['enabled'])): ?>
            <div class="form-container" style="border-left:4px solid #c9a227;background:#1a1a2e">
                <h3 style="margin-top:0">EVENT PUBLISHING FEE</h3>
                <p style="font-size:1.6rem;font-weight:700;color:#c9a227"><?php echo htmlspecialchars($fs['currency'] . ' ' . number_format($fs['amount'])); ?></p>
                <p class="form-help">One-time fixed publishing fee per event. Pay it before your event can go live.</p>
            </div>
            <?php endif; ?>
            <form method="POST" action="/TMZ%20PODCAST/user/dashboard.php?tab=event-create" enctype="multipart/form-data" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="org_action" value="save_event">
                <input type="hidden" name="id" value="<?php echo (int)($ev['id'] ?? 0); ?>">

                <h3>Event information</h3>
                <div class="form-group"><label>Event title *</label><input type="text" name="title" required value="<?php echo htmlspecialchars($ev['title'] ?? ''); ?>"></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?php echo htmlspecialchars($ev['description'] ?? ''); ?></textarea></div>
                <div class="event-contact-grid">
                    <div class="form-group"><label>Event category</label><input type="text" name="category" value="<?php echo htmlspecialchars($ev['category'] ?? ''); ?>" placeholder="e.g. Concert, Conference, Festival"></div>
                    <div class="form-group"><label>Organiser name</label><input type="text" name="contact_name" value="<?php echo htmlspecialchars($ev['contact_name'] ?? ($user['username'] ?? '')); ?>"></div>
                    <div class="form-group"><label>Contact phone</label><input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($ev['contact_phone'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Contact email</label><input type="email" name="contact_email" value="<?php echo htmlspecialchars($ev['contact_email'] ?? ''); ?>"></div>
                </div>
                <div class="form-group"><label>Poster / banner image (JPG, PNG or WebP, max 5MB)</label><input type="file" name="poster" accept="image/jpeg,image/png,image/webp"></div>

                <h3>Date &amp; venue</h3>
                <div class="event-contact-grid">
                    <div class="form-group"><label>Event date *</label><input type="date" name="event_date" value="<?php echo !empty($ev['event_date']) ? date('Y-m-d', strtotime($ev['event_date'])) : ''; ?>"></div>
                    <div class="form-group"><label>Venue name</label><input type="text" name="venue_name" value="<?php echo htmlspecialchars($ev['venue_name'] ?? $ev['location'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Start time</label><input type="time" name="start_time" value="<?php echo !empty($ev['start_time']) ? substr($ev['start_time'],0,5) : ''; ?>"></div>
                    <div class="form-group"><label>End time</label><input type="time" name="end_time" value="<?php echo !empty($ev['end_time']) ? substr($ev['end_time'],0,5) : ''; ?>"></div>
                </div>
                <div class="form-group"><label>Venue address / location</label><input type="text" name="venue_address" value="<?php echo htmlspecialchars($ev['venue_address'] ?? ''); ?>"></div>
                <div class="event-contact-grid">
                    <div class="form-group"><label>Ticket sales open</label><input type="date" name="sales_open_date" value="<?php echo !empty($ev['sales_open_at']) ? date('Y-m-d', strtotime($ev['sales_open_at'])) : ''; ?>"></div>
                    <div class="form-group"><label>Ticket sales close</label><input type="date" name="sales_close_date" value="<?php echo !empty($ev['sales_close_at']) ? date('Y-m-d', strtotime($ev['sales_close_at'])) : ''; ?>"></div>
                </div>

                <h3>Ticket categories</h3>
                <p class="form-help">Add one or more ticket categories with prices and quantities.</p>
                <div id="ticket-levels">
                <?php $rows = $editTickets ?? []; if (empty($rows)) $rows = [['name'=>'','code'=>'','price'=>'','currency'=>'UGX','capacity'=>'','description'=>'','sales_open_at'=>'','sales_close_at'=>'']];
                foreach ($rows as $i => $t): ?>
                    <div class="ticket-level-row" style="flex-wrap:wrap">
                        <input name="tickets[<?php echo $i; ?>][name]" value="<?php echo htmlspecialchars($t['name'] ?? ''); ?>" placeholder="Category (e.g. VIP)">
                        <input name="tickets[<?php echo $i; ?>][code]" value="<?php echo htmlspecialchars($t['code'] ?? ''); ?>" placeholder="CODE">
                        <input type="number" min="0" step="0.01" name="tickets[<?php echo $i; ?>][price]" value="<?php echo htmlspecialchars($t['price'] ?? ''); ?>" placeholder="Price">
                        <input maxlength="3" name="tickets[<?php echo $i; ?>][currency]" value="<?php echo htmlspecialchars($t['currency'] ?? 'UGX'); ?>" placeholder="UGX">
                        <input type="number" min="1" name="tickets[<?php echo $i; ?>][capacity]" value="<?php echo htmlspecialchars($t['capacity'] ?? ''); ?>" placeholder="Qty">
                        <input name="tickets[<?php echo $i; ?>][description]" value="<?php echo htmlspecialchars($t['description'] ?? ''); ?>" placeholder="Description">
                        <input type="datetime-local" name="tickets[<?php echo $i; ?>][sales_open_at]" value="<?php echo !empty($t['sales_open_at']) ? date('Y-m-d\TH:i', strtotime($t['sales_open_at'])) : ''; ?>" title="Sales open">
                        <input type="datetime-local" name="tickets[<?php echo $i; ?>][sales_close_at]" value="<?php echo !empty($t['sales_close_at']) ? date('Y-m-d\TH:i', strtotime($t['sales_close_at'])) : ''; ?>" title="Sales close">
                        <button type="button" class="btn btn-small btn-danger remove-ticket">Remove</button>
                    </div>
                <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline btn-small" id="add-ticket-cat">+ Add Ticket Category</button>

                <div class="admin-actions" style="margin-top:20px">
                    <button class="btn btn-outline" type="submit" name="save_as" value="draft">Save as Draft</button>
                    <button class="btn btn-primary" type="submit" name="save_as" value="publish">Save &amp; Publish</button>
                </div>
            </form>
        </div>
        <script>
        (function(){var c=0;document.getElementById('add-ticket-cat').addEventListener('click',function(){c++;var d=document.createElement('div');d.className='ticket-level-row';d.style.flexWrap='wrap';d.innerHTML='<input name="tickets['+c+'][name]" placeholder="Category"><input name="tickets['+c+'][code]" placeholder="CODE"><input type="number" min="0" step="0.01" name="tickets['+c+'][price]" placeholder="Price"><input maxlength="3" name="tickets['+c+'][currency]" value="UGX" placeholder="UGX"><input type="number" min="1" name="tickets['+c+'][capacity]" placeholder="Qty"><input name="tickets['+c+'][description]" placeholder="Description"><input type="datetime-local" name="tickets['+c+'][sales_open_at]" title="Sales open"><input type="datetime-local" name="tickets['+c+'][sales_close_at]" title="Sales close"><button type="button" class="btn btn-small btn-danger remove-ticket">Remove</button>';document.getElementById('ticket-levels').appendChild(d);});document.getElementById('ticket-levels').addEventListener('click',function(e){if(e.target.classList.contains('remove-ticket'))e.target.parentElement.remove();});})();
        </script>
    <?php endif; ?>

    <?php if ($activeTab === 'my-events'): ?>
        <div class="section-header"><h2>My Events</h2><a class="btn btn-primary" href="/TMZ%20PODCAST/user/dashboard.php?tab=event-create">+ Create Event</a></div>
        <?php $feeModel = $feeModel ?? new EventPublishFee(); ?>
        <?php if (empty($myEvents)): ?><p class="form-help">No events yet — create your first one.</p><?php endif; ?>
        <div class="content-grid">
        <?php foreach (($myEvents ?? []) as $ev): $fee = $feeModel->forEvent($ev['id']); $fstatus = $fee['status'] ?? 'none';
            $pubStatus = $ev['status'] === 'published' ? 'Published' : ($fstatus === 'pending' ? 'Awaiting Payment' : ($fstatus === 'paid' ? 'Ready to Publish' : ($fstatus === 'exempt' ? 'Ready to Publish' : ucfirst($ev['status'])))); ?>
            <div class="content-card" style="position:relative">
                <?php if (!empty($ev['poster_path'])): ?><img src="/TMZ%20PODCAST/<?php echo htmlspecialchars($ev['poster_path']); ?>" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:8px;margin-bottom:10px"><?php endif; ?>
                <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
                <p><?php echo !empty($ev['event_date']) ? htmlspecialchars(date('D, M d Y H:i', strtotime($ev['event_date']))) : 'Date TBA'; ?> · <?php echo htmlspecialchars($ev['venue_name'] ?? $ev['location'] ?? 'Venue TBA'); ?></p>
                <p class="form-help"><?php echo (int)($ev['ticket_count'] ?? 0); ?> ticket categories · <?php echo (int)($ev['tickets_sold'] ?? 0); ?> sold</p>
                <p>Publishing fee: <strong><?php echo $fee ? htmlspecialchars($fee['currency'] . ' ' . number_format((float)$fee['amount'])) : '—'; ?></strong>
                    · <span class="status-badge status-<?php echo $ev['status'] === 'published' ? 'published' : 'draft'; ?>"><?php echo htmlspecialchars($pubStatus); ?></span></p>
                <div class="admin-actions" style="flex-wrap:wrap">
                    <a class="btn btn-small btn-outline" href="/TMZ%20PODCAST/events.php?slug=<?php echo urlencode($ev['slug']); ?>" target="_blank">Preview</a>
                    <a class="btn btn-small" href="/TMZ%20PODCAST/user/dashboard.php?tab=event-create&id=<?php echo (int)$ev['id']; ?>">Edit</a>
                    <?php if ($ev['status'] !== 'published'): ?>
                    <form method="POST" action="/TMZ%20PODCAST/user/dashboard.php?tab=my-events" class="inline-form"><?php echo CSRF::getField(); ?>
                        <input type="hidden" name="org_action" value="publish_event"><input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                        <button class="btn btn-small btn-primary" type="submit" <?php echo in_array($fstatus, ['paid','exempt'], true) || !EventPublishFee::feeEnabled() ? '' : 'disabled title="Pay the publishing fee first"'; ?>>Publish</button></form>
                    <?php else: ?>
                    <form method="POST" action="/TMZ%20PODCAST/user/dashboard.php?tab=my-events" class="inline-form" onsubmit="return confirm('Unpublish this event?')"><?php echo CSRF::getField(); ?>
                        <input type="hidden" name="org_action" value="unpublish_event"><input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                        <button class="btn btn-small" type="submit">Unpublish</button></form>
                    <?php endif; ?>
                    <a class="btn btn-small btn-outline" href="/TMZ%20PODCAST/user/dashboard.php?tab=event-fees">Payments</a>
                    <form method="POST" action="/TMZ%20PODCAST/user/dashboard.php?tab=my-events" class="inline-form" onsubmit="return confirm('Delete this event?')"><?php echo CSRF::getField(); ?>
                        <input type="hidden" name="org_action" value="delete_event"><input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button></form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'event-fees'): ?>
        <div class="section-header"><h2>Event Publishing &amp; Payments</h2></div>
        <?php $fs = $feeSettings ?? null; if ($fs === null) { $fs = ['enabled'=>EventPublishFee::feeEnabled(),'amount'=>EventPublishFee::feeAmount(),'currency'=>EventPublishFee::feeCurrency()]; } ?>
        <div class="form-container" style="border-left:4px solid #c9a227">
            <h3 style="margin-top:0">EVENT PUBLISHING FEE</h3>
            <p style="font-size:1.5rem;font-weight:700;color:#c9a227"><?php echo htmlspecialchars($fs['currency'] . ' ' . number_format($fs['amount'])); ?></p>
            <p class="form-help">One-time fixed publishing fee per event, set by the platform administrator.</p>
        </div>
        <?php if (empty($feeEvents)): ?><p class="form-help">No events yet — <a href="/TMZ%20PODCAST/user/dashboard.php?tab=event-create">create one</a> to see its publishing fee here.</p><?php endif; ?>
        <?php foreach (($feeEvents ?? []) as $ev): $f = $ev['fee']; ?>
        <div class="content-card">
            <h3><?php echo htmlspecialchars($ev['title']); ?></h3>
            <p>Fee: <strong><?php echo $f ? htmlspecialchars($f['currency'] . ' ' . number_format((float)$f['amount'])) : htmlspecialchars($fs['currency'] . ' ' . number_format($fs['amount'])); ?></strong>
                · Status: <span class="status-badge status-<?php echo ($f['status'] ?? '') === 'paid' ? 'published' : 'draft'; ?>"><?php echo htmlspecialchars(ucfirst($f['status'] ?? 'pending')); ?></span>
                <?php if (!empty($f['reference'])): ?> · Ref <?php echo htmlspecialchars($f['reference']); ?><?php endif; ?>
                <?php if (!empty($f['paid_at'])): ?> · Paid <?php echo htmlspecialchars($f['paid_at']); ?><?php endif; ?></p>
            <?php if (!$ev['fee_ok'] && ($f['status'] ?? 'pending') !== 'exempt'): ?>
            <form method="POST" action="/TMZ%20PODCAST/user/dashboard.php?tab=event-fees" class="inline-form"><?php echo CSRF::getField(); ?>
                <input type="hidden" name="org_action" value="pay_event_fee"><input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                <button class="btn btn-primary btn-small" type="submit">Pay Publishing Fee — <?php echo htmlspecialchars(($f['currency'] ?? $fs['currency']) . ' ' . number_format((float)($f['amount'] ?? $fs['amount']))); ?></button></form>
            <?php elseif ($ev['fee_ok'] && $ev['status'] !== 'published'): ?>
            <form method="POST" action="/TMZ%20PODCAST/user/dashboard.php?tab=my-events" class="inline-form"><?php echo CSRF::getField(); ?>
                <input type="hidden" name="org_action" value="publish_event"><input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                <button class="btn btn-primary btn-small" type="submit">Publish Event</button></form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($activeTab === 'payment-history'): ?>
        <div class="section-header"><h2>Publishing Fee Payment History</h2></div>
        <div class="table-container"><table class="admin-table">
            <thead><tr><th>Event</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th><th>Paid at</th></tr></thead>
            <tbody>
            <?php if (empty($feePayments)): ?><tr><td colspan="6">No fee payments yet.</td></tr><?php endif; ?>
            <?php foreach (($feePayments ?? []) as $fp): ?>
                <tr><td><?php echo htmlspecialchars($fp['event_title'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($fp['currency'] . ' ' . number_format((float)$fp['amount'], 2)); ?></td>
                    <td><span class="status-badge status-<?php echo $fp['status'] === 'completed' ? 'published' : 'draft'; ?>"><?php echo htmlspecialchars(ucfirst($fp['status'])); ?></span></td>
                    <td><?php echo htmlspecialchars($fp['reference']); ?></td>
                    <td><?php echo htmlspecialchars($fp['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($fp['paid_at'] ?? '—'); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
    <?php endif; ?>

    <?php if ($activeTab === 'event-reports'): ?>
        <div class="section-header"><h2>Event Reports</h2></div>
        <div class="table-container"><table class="admin-table">
            <thead><tr><th>Event</th><th>Date</th><th>Venue</th><th>Categories</th><th>Tickets sold</th><th>Status</th><th>Fee status</th></tr></thead>
            <tbody>
            <?php if (empty($reportEvents)): ?><tr><td colspan="7">No events yet.</td></tr><?php endif; ?>
            <?php $fm = new EventPublishFee(); foreach (($reportEvents ?? []) as $re): $rf = $fm->forEvent($re['id']); ?>
                <tr><td><?php echo htmlspecialchars($re['title']); ?></td>
                    <td><?php echo !empty($re['event_date']) ? htmlspecialchars(date('M d, Y', strtotime($re['event_date']))) : 'TBA'; ?></td>
                    <td><?php echo htmlspecialchars($re['venue_name'] ?? $re['location'] ?? '—'); ?></td>
                    <td><?php echo (int)($re['ticket_count'] ?? 0); ?></td>
                    <td><?php echo (int)($re['tickets_sold'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($re['status'])); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($rf['status'] ?? 'none')); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
    <?php endif; ?>
</section>
