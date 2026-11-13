<?php
/**
 * Music Zone page. $musicView: home | search | genre | artist | album | track | videos | library | playlist
 * The persistent player (layouts/main.php) handles playback; links inside #musicApp load without a page reload.
 */
require_once __DIR__ . '/../partials/music-ui.php';
$mzGenreColors = ['tone-1', 'tone-2', 'tone-3', 'tone-4'];
?>
<div id="musicApp" class="mz-app" data-music-view="<?php echo tmz_mz_h($musicView); ?>">
    <header class="mz-subnav">
        <nav class="mz-tabs" aria-label="Music sections">
            <a href="/TMZ%20PODCAST/music.php" class="<?php echo $musicView === 'home' ? 'is-active' : ''; ?>">Home</a>
            <a href="/TMZ%20PODCAST/music.php?view=videos" class="<?php echo $musicView === 'videos' ? 'is-active' : ''; ?>">Videos</a>
            <?php if (!empty($canLibrary)): ?>
                <a href="/TMZ%20PODCAST/music.php?view=library" class="<?php echo in_array($musicView, ['library', 'playlist'], true) ? 'is-active' : ''; ?>">Your Library</a>
            <?php else: ?>
                <a href="/TMZ%20PODCAST/login.php">Sign in for Library</a>
            <?php endif; ?>
        </nav>
        <form class="mz-search" method="GET" action="/TMZ%20PODCAST/music.php" role="search">
            <input type="search" name="q" value="<?php echo tmz_mz_h($search ?? ''); ?>" placeholder="Search songs, artists, albums…" aria-label="Search music" autocomplete="off">
            <button type="submit" aria-label="Search">⌕</button>
        </form>
    </header>

<?php if ($musicView === 'home'): ?>
    <?php if (!empty($featured)): $f = $featured; ?>
    <section class="mz-hero" data-track="<?php echo tmz_mz_json($f); ?>" data-track-id="<?php echo (int)$f['id']; ?>">
        <?php echo tmz_mz_cover($f['cover'], $f['title'], 'mz-hero-art'); ?>
        <div class="mz-hero-copy">
            <span class="cine-eyebrow">Featured track</span>
            <h1><?php echo tmz_mz_h($f['title']); ?></h1>
            <p><a href="/TMZ%20PODCAST/music.php?artist=<?php echo rawurlencode($f['artist_slug']); ?>"><?php echo tmz_mz_h($f['artist']); ?></a><?php echo $f['album'] ? ' · ' . tmz_mz_h($f['album']) : ''; ?> · <?php echo number_format($f['plays']); ?> plays</p>
            <div class="mz-actions">
                <button type="button" class="btn btn-primary mz-big-play" data-act="play">▶ Listen now</button>
                <?php if ($f['video']): ?><button type="button" class="btn btn-outline" data-act="play-video">▶ Watch video</button><?php endif; ?>
                <button type="button" class="mz-like mz-like-lg<?php echo $f['liked'] ? ' is-on' : ''; ?>" data-act="like" aria-pressed="<?php echo $f['liked'] ? 'true' : 'false'; ?>" aria-label="Like">♥</button>
            </div>
        </div>
    </section>
    <?php else: ?>
    <section class="cine-page-hero">
        <span class="cine-eyebrow">The DAK Film</span>
        <h1>Music Zone</h1>
        <p>Listen, watch music videos and download tracks. New music is added by the team, so check back soon.</p>
    </section>
    <?php endif; ?>

    <?php if (!empty($genreList)): ?>
    <section class="mz-section" aria-label="Genres">
        <div class="mz-chips">
            <?php foreach ($genreList as $gi => $g): ?>
                <a class="mz-chip <?php echo $mzGenreColors[$gi % 4]; ?>" href="/TMZ%20PODCAST/music.php?genre=<?php echo rawurlencode($g['genre']); ?>"><?php echo tmz_mz_h($g['genre']); ?> <small><?php echo (int)$g['total']; ?></small></a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($recentTracks)): ?>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Jump back in</span><h2>Recently played</h2></div></div>
        <div class="mz-rail" data-track-list><?php foreach ($recentTracks as $t) { tmz_mz_tile($t); } ?></div></section>
    <?php endif; ?>

    <?php if (!empty($trending)): ?>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">This fortnight</span><h2>Trending now <img class="tmz-chart-logo-inline" src="/TMZ%20PODCAST/assets/img/dak-mark.png" alt="THE DAK FILM PODCAST MEDIA"></h2></div><?php tmz_mz_actions('#mzTrending'); ?></div>
        <ol class="mz-list" id="mzTrending" data-track-list><?php foreach ($trending as $i => $t) { tmz_mz_row($t, $i + 1); } ?></ol></section>
    <?php endif; ?>

    <?php if (!empty($newTracks)): ?>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Fresh</span><h2>New releases</h2></div></div>
        <div class="mz-rail" data-track-list><?php foreach ($newTracks as $t) { tmz_mz_tile($t); } ?></div></section>
    <?php endif; ?>

    <?php if (!empty($videoTracks)): ?>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Watch</span><h2>Music videos</h2></div><a class="btn btn-outline" href="/TMZ%20PODCAST/music.php?view=videos">All videos</a></div>
        <div class="mz-vgrid" data-track-list><?php foreach ($videoTracks as $t) { tmz_mz_vtile($t); } ?></div></section>
    <?php endif; ?>

    <?php if (!empty($artists)): ?>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Popular</span><h2>Artists</h2></div></div>
        <div class="mz-rail mz-rail-artists"><?php foreach ($artists as $a) { tmz_mz_artist($a); } ?></div></section>
    <?php endif; ?>

    <?php if (!empty($albums)): ?>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Collections</span><h2>Albums</h2></div></div>
        <div class="mz-rail"><?php foreach ($albums as $al) { tmz_mz_album($al); } ?></div></section>
    <?php endif; ?>

<?php elseif ($musicView === 'track'): ?>
    <section class="mz-detail" data-track="<?php echo tmz_mz_json($track); ?>" data-track-id="<?php echo (int)$track['id']; ?>">
        <?php echo tmz_mz_cover($track['cover'], $track['title'], 'mz-detail-art'); ?>
        <div class="mz-detail-copy">
            <span class="cine-eyebrow">Song</span>
            <h1><?php echo tmz_mz_h($track['title']); ?><?php if ($track['explicit']): ?> <span class="mz-badge">E</span><?php endif; ?></h1>
            <p><a href="/TMZ%20PODCAST/music.php?artist=<?php echo rawurlencode($track['artist_slug']); ?>"><?php echo tmz_mz_h($track['artist']); ?></a>
               <?php if ($track['album']): ?> · <a href="/TMZ%20PODCAST/music.php?album=<?php echo rawurlencode($track['album_slug']); ?>"><?php echo tmz_mz_h($track['album']); ?></a><?php endif; ?>
               <?php if (!empty($trackRow['genre'])): ?> · <a href="/TMZ%20PODCAST/music.php?genre=<?php echo rawurlencode($trackRow['genre']); ?>"><?php echo tmz_mz_h($trackRow['genre']); ?></a><?php endif; ?>
               · <?php echo tmz_mz_fmt($track['duration']); ?> · <?php echo number_format($track['plays']); ?> plays</p>
            <div class="mz-actions">
                <button type="button" class="btn btn-primary mz-big-play" data-act="play">▶ Play</button>
                <?php if ($track['video']): ?><button type="button" class="btn btn-outline" data-act="play-video">▶ Watch video</button><?php endif; ?>
                <button type="button" class="mz-like mz-like-lg<?php echo $track['liked'] ? ' is-on' : ''; ?>" data-act="like" aria-pressed="<?php echo $track['liked'] ? 'true' : 'false'; ?>" aria-label="Like">♥</button>
                <?php if (!empty($track['downloads_enabled'])): ?><button type="button" class="btn btn-outline" data-act="download">⬇ Download audio</button><?php endif; ?>
                <?php if (!empty($track['downloads_enabled']) && !empty($track['download_video'])): ?><button type="button" class="btn btn-outline" data-act="download-video">⬇ Download video</button><?php endif; ?>
                <button type="button" class="btn btn-outline" data-act="share">Share</button>
            </div>
            <?php if ($track['locked']): ?><p class="mz-note"><?php echo tmz_mz_h($track['lock_hint']); ?> <a href="/TMZ%20PODCAST/<?php echo strpos($track['lock_hint'], 'All Access') !== false ? 'membership.php' : 'login.php'; ?>">Continue</a></p><?php endif; ?>
        </div>
    </section>
    <?php if (!empty($trackRow['lyrics']) && !$track['locked']): ?>
        <section class="mz-section mz-lyrics-static"><div class="cine-heading"><div><span class="cine-eyebrow">Words</span><h2>Lyrics</h2></div></div>
            <pre class="mz-lyrics-text"><?php echo tmz_mz_h(preg_replace('/\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\]/', '', $trackRow['lyrics'])); ?></pre></section>
    <?php endif; ?>
    <?php if (!empty($moreTracks)): ?>
        <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">More</span><h2>More from <?php echo tmz_mz_h($track['artist']); ?></h2></div></div>
            <div class="mz-rail" data-track-list><?php foreach ($moreTracks as $t) { tmz_mz_tile($t); } ?></div></section>
    <?php endif; ?>

<?php elseif ($musicView === 'artist'): ?>
    <section class="mz-detail">
        <?php echo tmz_mz_cover(!empty($artist['photo_path']) ? Music::url($artist['photo_path']) : (!empty($tracks[0]['cover']) ? $tracks[0]['cover'] : ''), $artist['name'], 'mz-detail-art mz-round'); ?>
        <div class="mz-detail-copy"><span class="cine-eyebrow">Artist</span><h1><?php echo tmz_mz_h($artist['name']); ?></h1>
            <?php if (!empty($artist['bio'])): ?><p class="mz-bio"><?php echo nl2br(tmz_mz_h($artist['bio'])); ?></p><?php endif; ?>
            <?php if (!empty($tracks)) { tmz_mz_actions('#mzArtistTracks'); } ?></div>
    </section>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Popular</span><h2>Songs</h2></div></div>
        <?php if (empty($tracks)): ?><p class="cine-empty">No songs yet.</p><?php else: ?>
        <ol class="mz-list" id="mzArtistTracks" data-track-list><?php foreach ($tracks as $i => $t) { tmz_mz_row($t, $i + 1); } ?></ol><?php endif; ?></section>
    <?php if (!empty($albums)): ?><section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Discography</span><h2>Albums</h2></div></div>
        <div class="mz-rail"><?php foreach ($albums as $al) { tmz_mz_album($al); } ?></div></section><?php endif; ?>

<?php elseif ($musicView === 'album'): ?>
    <section class="mz-detail">
        <?php echo tmz_mz_cover(Music::url($album['cover_path'] ?? ''), $album['title'], 'mz-detail-art'); ?>
        <div class="mz-detail-copy"><span class="cine-eyebrow">Album</span><h1><?php echo tmz_mz_h($album['title']); ?></h1>
            <p><a href="/TMZ%20PODCAST/music.php?artist=<?php echo rawurlencode($album['artist_slug']); ?>"><?php echo tmz_mz_h($album['artist_name']); ?></a><?php echo !empty($album['release_year']) ? ' · ' . (int)$album['release_year'] : ''; ?> · <?php echo count($tracks); ?> songs</p>
            <?php if (!empty($tracks)) { tmz_mz_actions('#mzAlbumTracks'); } ?></div>
    </section>
    <section class="mz-section"><?php if (empty($tracks)): ?><p class="cine-empty">No songs yet.</p><?php else: ?>
        <ol class="mz-list" id="mzAlbumTracks" data-track-list><?php foreach ($tracks as $i => $t) { tmz_mz_row($t, $i + 1, false); } ?></ol><?php endif; ?></section>

<?php elseif (in_array($musicView, ['genre', 'search', 'videos', 'playlist'], true)): ?>
    <header class="cine-page-hero">
        <span class="cine-eyebrow"><?php echo $musicView === 'genre' ? 'Genre' : ($musicView === 'search' ? 'Search' : ($musicView === 'playlist' ? 'Playlist' : 'Watch')); ?></span>
        <h1><?php echo $musicView === 'genre' ? tmz_mz_h($genre) : ($musicView === 'search' ? '“' . tmz_mz_h($search) . '”' : ($musicView === 'playlist' ? tmz_mz_h($playlist['name']) : 'Music videos')); ?></h1>
        <p><?php echo count($tracks); ?> <?php echo count($tracks) === 1 ? 'result' : 'results'; ?></p>
    </header>
    <?php if (empty($tracks)): ?>
        <p class="cine-empty"><?php echo $musicView === 'playlist' ? 'This playlist is empty. Use ⋯ on any song and choose “Add to playlist”.' : 'Nothing here yet.'; ?></p>
    <?php elseif ($musicView === 'videos'): ?>
        <div class="mz-vgrid" data-track-list><?php foreach ($tracks as $t) { tmz_mz_vtile($t); } ?></div>
    <?php else: ?>
        <?php tmz_mz_actions('#mzResults'); ?>
        <ol class="mz-list" id="mzResults" data-track-list data-playlist-id="<?php echo $musicView === 'playlist' ? (int)$playlist['id'] : 0; ?>"><?php foreach ($tracks as $i => $t) { tmz_mz_row($t, $i + 1); } ?></ol>
    <?php endif; ?>

<?php elseif ($musicView === 'library'): ?>
    <header class="cine-page-hero"><span class="cine-eyebrow">Yours</span><h1>Your Library</h1><p>Liked songs, playlists and what you played recently.</p></header>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">Collections</span><h2>Playlists</h2></div>
        <button type="button" class="btn btn-outline" id="mzNewPlaylist">＋ New playlist</button></div>
        <?php if (empty($playlists)): ?><p class="cine-empty">No playlists yet. Create one, then add songs from the ⋯ menu.</p><?php else: ?>
        <div class="mz-rail">
            <?php foreach ($playlists as $pl): ?>
                <a class="mz-album" href="/TMZ%20PODCAST/music.php?playlist=<?php echo (int)$pl['id']; ?>">
                    <?php echo tmz_mz_cover('', $pl['name'], 'mz-album-art'); ?><strong><?php echo tmz_mz_h($pl['name']); ?></strong><small><?php echo (int)$pl['tracks']; ?> songs</small>
                </a>
            <?php endforeach; ?>
        </div><?php endif; ?></section>
    <section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">You liked</span><h2>Liked songs</h2></div><?php if (!empty($likedTracks)) { tmz_mz_actions('#mzLiked'); } ?></div>
        <?php if (empty($likedTracks)): ?><p class="cine-empty">Tap the ♥ on any song to save it here.</p><?php else: ?>
        <ol class="mz-list" id="mzLiked" data-track-list><?php foreach ($likedTracks as $i => $t) { tmz_mz_row($t, $i + 1); } ?></ol><?php endif; ?></section>
    <?php if (!empty($recentTracks)): ?><section class="mz-section"><div class="cine-heading"><div><span class="cine-eyebrow">History</span><h2>Recently played</h2></div></div>
        <div class="mz-rail" data-track-list><?php foreach ($recentTracks as $t) { tmz_mz_tile($t); } ?></div></section><?php endif; ?>
<?php endif; ?>
</div>
