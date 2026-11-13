<?php
/** Admin: Music Zone management (songs, music videos, covers, lyrics, access rules, artist bios). */
$e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$access = ['everyone' => 'Everyone', 'members' => 'Signed-in members', 'subscribers' => 'All Access subscribers only'];
?>
<section class="admin-section">
    <div class="section-header">
        <h1>Manage Music</h1>
        <div>
            <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=music&amp;action=artists" class="btn btn-outline">Artist profiles</a>
            <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=music&amp;action=create" class="btn btn-primary">Upload a song</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?><div class="alert alert-error"><?php echo $e($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): $track = $track ?: []; ?>
    <div class="form-container">
        <h2><?php echo $action === 'create' ? 'Upload a song' : 'Edit ' . $e($track['title'] ?? 'song'); ?></h2>
        <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=music" class="admin-form" enctype="multipart/form-data" id="musicForm">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="id" value="<?php echo (int)($track['id'] ?? 0); ?>">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="duration_seconds" id="durationSeconds" value="<?php echo (int)($track['duration_seconds'] ?? 0); ?>">

            <div class="form-group"><label for="title">Song title *</label>
                <input type="text" id="title" name="title" maxlength="190" required value="<?php echo $e($track['title'] ?? ''); ?>"></div>

            <div class="form-group"><label for="artist_name">Artist *</label>
                <input type="text" id="artist_name" name="artist_name" maxlength="160" required list="artistList" value="<?php echo $e($track['artist_name'] ?? ''); ?>" placeholder="Type a new artist or pick an existing one">
                <datalist id="artistList"><?php foreach ($artistNames as $n): ?><option value="<?php echo $e($n); ?>"><?php endforeach; ?></datalist></div>

            <div class="form-group"><label for="album_title">Album (optional)</label>
                <input type="text" id="album_title" name="album_title" maxlength="190" value="<?php echo $e($track['album_title'] ?? ''); ?>" placeholder="Leave empty for a single">
                <small>Songs with the same artist and album name are grouped into one album automatically.</small></div>

            <div class="form-group"><label for="genre">Genre</label>
                <input type="text" id="genre" name="genre" maxlength="60" list="genreList" value="<?php echo $e($track['genre'] ?? ''); ?>">
                <datalist id="genreList"><?php foreach ($genres as $g): ?><option value="<?php echo $e($g); ?>"><?php endforeach; ?></datalist></div>

            <div class="form-group"><label for="release_year">Release year</label>
                <input type="number" id="release_year" name="release_year" min="1900" max="2100" value="<?php echo $e($track['release_year'] ?? ''); ?>"></div>

            <div class="form-group"><label for="track_no">Track number in album</label>
                <input type="number" id="track_no" name="track_no" min="0" value="<?php echo (int)($track['track_no'] ?? 0); ?>"></div>

            <div class="form-group"><label for="audio_file">Audio file (MP3, M4A, AAC, OGG, WAV, FLAC, up to 300 MB)</label>
                <?php if (!empty($track['audio_path'])): ?><p><audio controls preload="none" src="/TMZ%20PODCAST/music-stream.php?id=<?php echo (int)$track['id']; ?>" style="width:100%;max-width:420px"></audio></p><?php endif; ?>
                <input type="file" id="audio_file" name="audio_file" accept="audio/*,.mp3,.m4a,.aac,.ogg,.wav,.flac">
                <small id="durationNote"><?php echo !empty($track['duration_seconds']) ? 'Length: ' . floor($track['duration_seconds'] / 60) . ':' . str_pad((string)($track['duration_seconds'] % 60), 2, '0', STR_PAD_LEFT) : 'Length is read automatically from the file.'; ?></small>
                <small>Optional when a music video is uploaded below — the song's audio is extracted from the video automatically.</small></div>

            <div class="form-group"><label for="video_file">Music video (optional: MP4, WebM, MOV, up to 2 GB)</label>
                <?php if (!empty($track['video_path'])): ?><label style="font-weight:normal"><input type="checkbox" name="remove_video" value="1"> Remove the current video</label><?php endif; ?>
                <input type="file" id="video_file" name="video_file" accept="video/mp4,video/webm,video/quicktime"></div>

            <div class="form-group"><label for="cover_file">Cover art (JPG, PNG or WebP, square works best)</label>
                <?php if (!empty($track['cover_path'])): ?><p><img src="<?php echo $e(Music::url($track['cover_path'])); ?>" alt="" style="width:90px;height:90px;object-fit:cover;border-radius:8px"></p><?php endif; ?>
                <input type="file" id="cover_file" name="cover_file" accept="image/jpeg,image/png,image/webp"></div>

            <div class="form-group"><label for="lyrics">Lyrics (optional)</label>
                <textarea id="lyrics" name="lyrics" rows="10" placeholder="Plain text, or timed lines like:&#10;[00:12.50] First line&#10;[00:18.00] Second line"><?php echo $e($track['lyrics'] ?? ''); ?></textarea>
                <small>Timed lines (in square brackets) make the lyrics scroll and highlight in time with the song.</small></div>

            <div class="form-group"><label for="play_access">Who can listen</label>
                <select id="play_access" name="play_access"><?php foreach ($access as $k => $label): ?><option value="<?php echo $k; ?>" <?php echo ($track['play_access'] ?? 'everyone') === $k ? 'selected' : ''; ?>><?php echo $e($label); ?></option><?php endforeach; ?></select></div>

            <div class="form-group"><label for="download_access">Who can download</label>
                <select id="download_access" name="download_access">
                    <option value="off" <?php echo ($track['download_access'] ?? 'members') === 'off' ? 'selected' : ''; ?>>Nobody (streaming only)</option>
                    <?php foreach ($access as $k => $label): ?><option value="<?php echo $k; ?>" <?php echo ($track['download_access'] ?? 'members') === $k ? 'selected' : ''; ?>><?php echo $e($label); ?></option><?php endforeach; ?>
                </select></div>

            <div class="form-group"><label style="font-weight:normal"><input type="checkbox" name="is_explicit" value="1" <?php echo !empty($track['is_explicit']) ? 'checked' : ''; ?>> Explicit lyrics</label></div>
            <div class="form-group"><label style="font-weight:normal"><input type="checkbox" name="is_featured" value="1" <?php echo !empty($track['is_featured']) ? 'checked' : ''; ?>> Feature on the Music Zone home page</label></div>

            <div class="form-group"><label for="status">Status</label>
                <select id="status" name="status">
                    <option value="published" <?php echo ($track['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="hidden" <?php echo ($track['status'] ?? '') === 'hidden' ? 'selected' : ''; ?>>Hidden</option></select></div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save song</button>
                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=music" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
    <script>
    (function () {
        var input = document.getElementById('audio_file'), videoInput = document.getElementById('video_file'),
            out = document.getElementById('durationSeconds'), note = document.getElementById('durationNote');
        var hasAudio = <?php echo !empty($track['audio_path']) ? 'true' : 'false'; ?>;
        var hasVideo = <?php echo !empty($track['video_path']) ? 'true' : 'false'; ?>;
        if (!input) return;

        function videoSelected() { return !!(videoInput && videoInput.files && videoInput.files[0]); }
        function refreshRequired() {
            input.required = !(hasAudio || hasVideo || videoSelected());
        }
        function readDuration(file) {
            if (!file) return;
            var url = URL.createObjectURL(file), media = document.createElement(videoInput && videoInput.files && videoInput.files[0] === file ? 'video' : 'audio');
            media.preload = 'metadata';
            media.onloadedmetadata = function () {
                if (isFinite(media.duration)) {
                    out.value = Math.round(media.duration);
                    note.textContent = 'Length: ' + Math.floor(media.duration / 60) + ':' + ('0' + Math.round(media.duration % 60)).slice(-2);
                }
                URL.revokeObjectURL(url);
            };
            media.onerror = function () { URL.revokeObjectURL(url); };
            media.src = url;
        }
        input.addEventListener('change', function () {
            refreshRequired();
            if (input.files && input.files[0]) readDuration(input.files[0]);
        });
        if (videoInput) videoInput.addEventListener('change', function () {
            refreshRequired();
            if (videoInput.files && videoInput.files[0] && !input.files.length) readDuration(videoInput.files[0]);
        });
        refreshRequired();
        var title = document.getElementById('title');
        input.addEventListener('change', function () {
            if (title && !title.value && input.files[0]) title.value = input.files[0].name.replace(/\.[^.]+$/, '').replace(/[_]+/g, ' ');
        });
    })();
    </script>

<?php elseif ($action === 'artists'): ?>
    <div class="form-container">
        <h2>Artist profile</h2>
        <p class="form-help">Add a short bio and a photo. The artist page shows them.</p>
        <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=music" class="admin-form" enctype="multipart/form-data">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="form_action" value="artist">
            <div class="form-group"><label for="artist_pick">Artist *</label>
                <input type="text" id="artist_pick" name="artist_name" required list="artistList2" placeholder="Choose an existing artist">
                <datalist id="artistList2"><?php foreach ($artistNames as $n): ?><option value="<?php echo $e($n); ?>"><?php endforeach; ?></datalist></div>
            <div class="form-group"><label for="artist_bio">Bio</label><textarea id="artist_bio" name="artist_bio" rows="6"></textarea></div>
            <div class="form-group"><label for="artist_photo">Photo (JPG, PNG or WebP)</label><input type="file" id="artist_photo" name="artist_photo" accept="image/jpeg,image/png,image/webp"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Save profile</button>
                <a href="/TMZ%20PODCAST/admin/dashboard.php?tab=music" class="btn btn-outline">Back</a></div>
        </form>
    </div>

<?php else: ?>
    <div class="table-container">
        <table class="admin-table">
            <thead><tr><th>Cover</th><th>Song</th><th>Artist</th><th>Genre</th><th>Video</th><th>Plays</th><th>Downloads</th><th>Access</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($tracks)): ?><tr><td colspan="10">No songs yet. Click “Upload a song”.</td></tr><?php endif; ?>
            <?php foreach ($tracks as $t): ?>
                <tr>
                    <td><?php if (!empty($t['cover'])): ?><img src="<?php echo $e(Music::url($t['cover'])); ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px"><?php else: ?>—<?php endif; ?></td>
                    <td><?php echo $e($t['title']); ?><?php echo !empty($t['is_featured']) ? ' ★' : ''; ?></td>
                    <td><?php echo $e($t['artist_name']); ?></td>
                    <td><?php echo $e($t['genre'] ?? ''); ?></td>
                    <td><?php echo !empty($t['video_path']) ? 'Yes' : '—'; ?></td>
                    <td><?php echo number_format((int)$t['plays_total']); ?></td>
                    <td><?php echo number_format((int)$t['downloads_total']); ?></td>
                    <td><small>Listen: <?php echo $e($t['play_access']); ?><br>Download: <?php echo $e($t['download_access']); ?></small></td>
                    <td><span class="status-badge status-<?php echo $t['status'] === 'published' ? 'active' : 'inactive'; ?>"><?php echo $t['status'] === 'published' ? 'Published' : 'Hidden'; ?></span></td>
                    <td>
                        <a class="btn btn-small" target="_blank" rel="noopener" href="/TMZ%20PODCAST/music.php?track=<?php echo urlencode($t['slug']); ?>">View</a>
                        <a class="btn btn-small" href="/TMZ%20PODCAST/admin/dashboard.php?tab=music&amp;action=edit&amp;id=<?php echo (int)$t['id']; ?>">Edit</a>
                        <form method="POST" action="/TMZ%20PODCAST/admin/dashboard.php?tab=music" style="display:inline" onsubmit="return confirm('Delete this song and its files?')">
                            <?php echo CSRF::getField(); ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                            <button type="submit" class="btn btn-small btn-danger">Delete</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</section>
