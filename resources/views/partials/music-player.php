<?php
/**
 * Persistent music player (bottom bar + full-screen "Now Playing" sheet).
 * Rendered on every public page, hidden until something is played or restored. Behaviour: assets/js/music-player.js
 */
?>
<div id="mzPlayer" class="mz-player" hidden
     data-api="/TMZ%20PODCAST/api/music.php"
     data-csrf="<?php echo htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8'); ?>"
     data-logged-in="<?php echo !empty($_SESSION['user_id']) ? '1' : '0'; ?>">
    <audio id="mzAudio" preload="metadata"></audio>

    <div class="mz-bar" role="region" aria-label="Music player">
        <div class="mz-bar-seek-mobile"><i id="mzMiniFill"></i></div>
        <button type="button" class="mz-bar-track" id="mzOpenSheet" aria-label="Open now playing">
            <span class="mz-bar-art" id="mzBarArt"></span>
            <span class="mz-bar-meta"><strong id="mzBarTitle">Nothing playing</strong><small id="mzBarArtist"></small></span>
        </button>
        <button type="button" class="mz-like" id="mzBarLike" aria-pressed="false" aria-label="Like this song">♥</button>

        <div class="mz-bar-center">
            <div class="mz-transport">
                <button type="button" class="mz-ctl" id="mzShuffle" aria-pressed="false" aria-label="Shuffle" title="Shuffle (S)">⤮</button>
                <button type="button" class="mz-ctl" id="mzPrev" aria-label="Previous" title="Previous (Shift+←)">⏮</button>
                <button type="button" class="mz-ctl mz-ctl-main" id="mzPlay" aria-label="Play" title="Play / pause (Space)">▶</button>
                <button type="button" class="mz-ctl" id="mzNext" aria-label="Next" title="Next (Shift+→)">⏭</button>
                <button type="button" class="mz-ctl" id="mzRepeat" aria-label="Repeat: off" data-mode="off" title="Repeat (R)">⟲</button>
            </div>
            <div class="mz-seek">
                <span id="mzCur">0:00</span>
                <input type="range" id="mzSeek" min="0" max="1000" value="0" step="1" aria-label="Seek" disabled>
                <span id="mzDur">0:00</span>
            </div>
        </div>

        <div class="mz-bar-right">
            <button type="button" class="mz-ctl mz-close" id="mzClose" aria-label="Close player and stop music" title="Close player">✕</button>
            <button type="button" class="mz-ctl" id="mzVideoBtn" aria-label="Watch music video" title="Watch video" hidden>▣</button>
            <button type="button" class="mz-ctl" id="mzDownload" aria-label="Download" title="Download">⬇</button>
            <button type="button" class="mz-ctl" id="mzQueueBtn" aria-label="Queue" title="Queue (Q)">☰</button>
            <button type="button" class="mz-ctl" id="mzMute" aria-label="Mute" title="Mute (M)">🔊</button>
            <input type="range" id="mzVolume" min="0" max="100" value="80" aria-label="Volume">
        </div>
        <button type="button" class="mz-ctl mz-bar-mobile-play" id="mzPlayMobile" aria-label="Play">▶</button>
    </div>

    <div class="mz-sheet" id="mzSheet" role="dialog" aria-modal="true" aria-label="Now playing" hidden>
        <div class="mz-sheet-top">
            <button type="button" class="mz-ctl" id="mzCloseSheet" aria-label="Close now playing">⌄</button>
            <span class="mz-sheet-label">NOW PLAYING</span>
            <button type="button" class="mz-ctl" id="mzSheetMenu" aria-label="More options" aria-haspopup="true">⋯</button>
        </div>
        <div class="mz-sheet-body">
            <div class="mz-stage">
                <div class="mz-stage-art" id="mzStageArt"></div>
                <canvas id="mzViz" class="mz-viz" aria-hidden="true"></canvas>
                <video id="mzVideo" class="mz-video" playsinline hidden></video>
                <div class="mz-stage-info"><h2 id="mzStageTitle"></h2><p id="mzStageArtist"></p></div>
                <div class="mz-stage-actions">
                    <button type="button" class="btn btn-outline btn-small" id="mzModeBtn" hidden>▣ Watch video</button>
                    <button type="button" class="btn btn-outline btn-small" id="mzPip" hidden>Picture in picture</button>
                    <button type="button" class="btn btn-outline btn-small" id="mzFullscreen" hidden>Full screen</button>
                </div>
            </div>
            <div class="mz-panel">
                <div class="mz-panel-tabs" role="tablist">
                    <button type="button" role="tab" data-tab="lyrics" aria-selected="true">Lyrics</button>
                    <button type="button" role="tab" data-tab="queue" aria-selected="false">Queue</button>
                    <button type="button" role="tab" data-tab="sound" aria-selected="false">Sound</button>
                </div>
                <div class="mz-panel-body" data-panel="lyrics"><div id="mzLyrics" class="mz-lyrics" tabindex="0"><p class="mz-muted">No lyrics for this song.</p></div></div>
                <div class="mz-panel-body" data-panel="queue" hidden>
                    <div class="mz-queue-head"><strong>Up next</strong><button type="button" class="btn btn-outline btn-small" id="mzClearQueue">Clear</button></div>
                    <ol id="mzQueue" class="mz-queue"></ol>
                </div>
                <div class="mz-panel-body" data-panel="sound" hidden>
                    <label class="mz-field">Equaliser preset
                        <select id="mzEqPreset"></select></label>
                    <div class="mz-eq" id="mzEq"></div>
                    <label class="mz-field">Speed
                        <select id="mzSpeed"><option value="0.75">0.75×</option><option value="1" selected>Normal</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select></label>
                    <label class="mz-field">Sleep timer
                        <select id="mzSleep"><option value="0">Off</option><option value="15">15 minutes</option><option value="30">30 minutes</option><option value="45">45 minutes</option><option value="60">1 hour</option><option value="end">End of this song</option></select></label>
                    <p class="mz-muted" id="mzSleepNote" hidden></p>
                    <details class="mz-keys"><summary>Keyboard shortcuts</summary>
                        <p><kbd>Space</kbd> play/pause · <kbd>←</kbd><kbd>→</kbd> seek 5s · <kbd>Shift</kbd>+<kbd>←</kbd><kbd>→</kbd> previous/next · <kbd>↑</kbd><kbd>↓</kbd> volume · <kbd>M</kbd> mute · <kbd>S</kbd> shuffle · <kbd>R</kbd> repeat · <kbd>L</kbd> like · <kbd>Q</kbd> queue</p></details>
                </div>
            </div>
        </div>
    </div>

    <div class="mz-toast" id="mzToast" role="status" aria-live="polite" hidden></div>
    <div class="mz-menu" id="mzMenu" role="menu" hidden></div>
    <div class="mz-modal" id="mzModal" hidden><div class="mz-modal-card" role="dialog" aria-modal="true" aria-labelledby="mzModalTitle"></div></div>
</div>
