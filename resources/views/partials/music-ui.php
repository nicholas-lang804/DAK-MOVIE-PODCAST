<?php
/** Shared building blocks for the Music Zone. Track arrays come from Music::export(). */

if (!function_exists('tmz_mz_h')) {
    function tmz_mz_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

    function tmz_mz_json(array $t) {
        return htmlspecialchars(json_encode($t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    }

    function tmz_mz_fmt($seconds) {
        $s = max(0, (int)$seconds);
        return $s ? floor($s / 60) . ':' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT) : '–:––';
    }

    /** Cover art, or a branded gradient with the first letter when there is no image. */
    function tmz_mz_cover($url, $title, $class = '') {
        $letter = function_exists('mb_substr') ? mb_strtoupper(mb_substr((string)$title, 0, 1)) : strtoupper(substr((string)$title, 0, 1));
        $tone = (abs(crc32((string)$title)) % 4) + 1;
        if ($url) {
            return '<span class="mz-cover ' . $class . '"><img src="' . tmz_mz_h($url) . '" alt="" loading="lazy"></span>';
        }
        return '<span class="mz-cover mz-cover-fallback tone-' . $tone . ' ' . $class . '" aria-hidden="true">' . tmz_mz_h($letter) . '</span>';
    }

    /** One line in a track list. */
    function tmz_mz_row(array $t, $i, $showAlbum = true) {
        ?>
        <li class="mz-row<?php echo $t['locked'] ? ' is-locked' : ''; ?>" data-track="<?php echo tmz_mz_json($t); ?>" data-track-id="<?php echo (int)$t['id']; ?>">
            <button type="button" class="mz-row-play" data-act="play" aria-label="Play <?php echo tmz_mz_h($t['title']); ?>">
                <span class="mz-row-num"><?php echo (int)$i; ?></span><span class="mz-row-icon" aria-hidden="true">▶</span>
            </button>
            <?php echo tmz_mz_cover($t['cover'], $t['title'], 'mz-row-cover'); ?>
            <span class="mz-row-main">
                <a class="mz-row-title" href="/TMZ%20PODCAST/music.php?track=<?php echo rawurlencode($t['slug']); ?>"><?php echo tmz_mz_h($t['title']); ?></a>
                <span class="mz-row-sub">
                    <?php if ($t['explicit']): ?><span class="mz-badge" title="Explicit">E</span><?php endif; ?>
                    <?php if ($t['video']): ?><span class="mz-badge mz-badge-video" title="Has music video">VIDEO</span><?php endif; ?>
                    <a href="/TMZ%20PODCAST/music.php?artist=<?php echo rawurlencode($t['artist_slug']); ?>"><?php echo tmz_mz_h($t['artist']); ?></a>
                </span>
            </span>
            <?php if ($showAlbum): ?>
                <span class="mz-row-album"><?php if ($t['album']): ?><a href="/TMZ%20PODCAST/music.php?album=<?php echo rawurlencode($t['album_slug']); ?>"><?php echo tmz_mz_h($t['album']); ?></a><?php endif; ?></span>
            <?php endif; ?>
            <button type="button" class="mz-like<?php echo $t['liked'] ? ' is-on' : ''; ?>" data-act="like" aria-pressed="<?php echo $t['liked'] ? 'true' : 'false'; ?>" aria-label="Like <?php echo tmz_mz_h($t['title']); ?>">♥</button>
            <span class="mz-row-time"><?php echo tmz_mz_fmt($t['duration']); ?></span>
            <button type="button" class="mz-row-more" data-act="menu" aria-haspopup="true" aria-label="More options for <?php echo tmz_mz_h($t['title']); ?>">⋯</button>
        </li>
        <?php
    }

    /** Square tile for shelves. */
    function tmz_mz_tile(array $t) {
        ?>
        <div class="mz-tile<?php echo $t['locked'] ? ' is-locked' : ''; ?>" data-track="<?php echo tmz_mz_json($t); ?>" data-track-id="<?php echo (int)$t['id']; ?>">
            <div class="mz-tile-art">
                <?php echo tmz_mz_cover($t['cover'], $t['title']); ?>
                <button type="button" class="mz-tile-play" data-act="play" aria-label="Play <?php echo tmz_mz_h($t['title']); ?>">▶</button>
            </div>
            <a class="mz-tile-title" href="/TMZ%20PODCAST/music.php?track=<?php echo rawurlencode($t['slug']); ?>"><?php echo tmz_mz_h($t['title']); ?></a>
            <a class="mz-tile-sub" href="/TMZ%20PODCAST/music.php?artist=<?php echo rawurlencode($t['artist_slug']); ?>"><?php echo tmz_mz_h($t['artist']); ?></a>
        </div>
        <?php
    }

    /** 16:9 music video card. */
    function tmz_mz_vtile(array $t) {
        ?>
        <div class="mz-vtile<?php echo $t['locked'] ? ' is-locked' : ''; ?>" data-track="<?php echo tmz_mz_json($t); ?>" data-track-id="<?php echo (int)$t['id']; ?>">
            <button type="button" class="mz-vtile-art" data-act="play-video" aria-label="Watch <?php echo tmz_mz_h($t['title']); ?>">
                <?php echo tmz_mz_cover($t['cover'], $t['title']); ?>
                <span class="mz-vtile-play" aria-hidden="true">▶</span>
                <span class="mz-vtile-time"><?php echo tmz_mz_fmt($t['duration']); ?></span>
            </button>
            <a class="mz-tile-title" href="/TMZ%20PODCAST/music.php?track=<?php echo rawurlencode($t['slug']); ?>"><?php echo tmz_mz_h($t['title']); ?></a>
            <span class="mz-tile-sub"><?php echo tmz_mz_h($t['artist']); ?> · <?php echo number_format((int)$t['plays']); ?> plays</span>
        </div>
        <?php
    }

    function tmz_mz_artist(array $a) {
        $img = !empty($a['photo_path']) ? Music::url($a['photo_path']) : (!empty($a['cover']) ? Music::url($a['cover']) : '');
        ?>
        <a class="mz-artist" href="/TMZ%20PODCAST/music.php?artist=<?php echo rawurlencode($a['slug']); ?>">
            <?php echo tmz_mz_cover($img, $a['name'], 'mz-artist-pic'); ?>
            <strong><?php echo tmz_mz_h($a['name']); ?></strong>
            <small>Artist<?php echo isset($a['tracks']) ? ' · ' . (int)$a['tracks'] . ' tracks' : ''; ?></small>
        </a>
        <?php
    }

    function tmz_mz_album(array $al) {
        ?>
        <a class="mz-album" href="/TMZ%20PODCAST/music.php?album=<?php echo rawurlencode($al['slug']); ?>">
            <?php echo tmz_mz_cover(Music::url($al['cover_path'] ?? ''), $al['title'], 'mz-album-art'); ?>
            <strong><?php echo tmz_mz_h($al['title']); ?></strong>
            <small><?php echo !empty($al['release_year']) ? (int)$al['release_year'] . ' · ' : ''; ?><?php echo tmz_mz_h($al['artist_name']); ?></small>
        </a>
        <?php
    }

    /** Play / shuffle buttons for a list. $target is a CSS selector for the container. */
    function tmz_mz_actions($target) {
        ?>
        <div class="mz-actions">
            <button type="button" class="btn btn-primary mz-big-play" data-play-list="<?php echo tmz_mz_h($target); ?>">▶ Play</button>
            <button type="button" class="btn btn-outline" data-play-list="<?php echo tmz_mz_h($target); ?>" data-shuffle="1">⤮ Shuffle</button>
        </div>
        <?php
    }
}
