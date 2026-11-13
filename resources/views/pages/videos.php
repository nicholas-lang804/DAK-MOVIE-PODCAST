<section class="content-section video-centre">
    <div class="section-header">
        <div>
            <span class="eyebrow">DAK WATCH</span>
            <h1>Video Centre</h1>
            <p>Watch the latest videos published by the THE DAK FILM PODCAST MEDIA team.</p>
        </div>
        <form class="content-search" method="GET" action="/TMZ%20PODCAST/videos.php">
            <input type="search" name="q" value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>" placeholder="Search videos">
            <button class="btn btn-primary" type="submit">Search</button>
        </form>
    </div>

    <?php if (!empty($videos)): ?>
        <div class="video-grid">
            <?php foreach ($videos as $video): ?>
                <article class="video-card">
                    <video controls preload="metadata" playsinline src="/TMZ%20PODCAST/video-stream.php?media_id=<?php echo (int)$video['id']; ?>"></video>
                    <div class="video-card-body">
                        <span class="video-kicker">STANDALONE VIDEO</span>
                        <h2><?php echo htmlspecialchars($video['alt_text'] ?: pathinfo($video['original_filename'], PATHINFO_FILENAME)); ?></h2>
                        <p><?php echo htmlspecialchars($video['original_filename']); ?></p>
                        <span class="movie-stream-only-note">Stream only — downloads are currently disabled.</span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><h2>No videos published yet</h2><p>Videos uploaded in the admin media library will appear here automatically.</p></div>
    <?php endif; ?>
</section>
