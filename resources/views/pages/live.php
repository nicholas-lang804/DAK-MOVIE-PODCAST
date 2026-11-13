<?php
$chatName = $chatUser['username'] ?? 'Viewer';
?>
<section class="live-page">
    <div class="section-header">
        <div>
            <span class="eyebrow">DAK LIVE</span>
            <h1>Live Podcast Room</h1>
            <p>Watch the show and join the conversation when signed in.</p>
        </div>
    </div>

    <?php if (!$live): ?>
        <div class="empty-state"><h2>No live episode right now</h2><p>Check back when the next broadcast begins.</p></div>
    <?php else: ?>
        <article class="live-room">
            <div class="live-room-head">
                <span class="live-pill">● <?php echo $live['status'] === 'live' ? 'LIVE NOW' : ($live['status'] === 'ended' ? 'REPLAY' : 'UP NEXT'); ?></span>
                <h2><?php echo htmlspecialchars($live['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo htmlspecialchars($live['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty($live['cover_image_path'])): ?>
                    <img class="live-share-cover" src="/TMZ%20PODCAST/<?php echo htmlspecialchars(ltrim($live['cover_image_path'], '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($live['title'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
            </div>

            <?php if ($live['status'] === 'scheduled'): ?>
                <div class="live-gate">
                    <h3>Up next</h3>
                    <p>This show has not started yet. Return when the host goes live.</p>
                    <?php
                    $lxIso = null; $lxHasReminder = false;
                    try {
                        require_once __DIR__ . '/../../../app/Models/LiveExtras.php';
                        $lxGateModel = new LiveExtras();
                        $lxGateRow = $lxGateModel->liveRow($live['id']);
                        $lxIso = LiveExtras::toIsoUtc($lxGateRow['scheduled_at'] ?? null);
                        $lxHasReminder = !empty($_SESSION['user_id']) && $lxGateModel->hasReminder($live['id'], (int)$_SESSION['user_id']);
                    } catch (Throwable $e) { error_log('Live gate: ' . $e->getMessage()); }
                    if ($lxIso): ?>
                        <p class="lobby-when">Starts <time data-lx-localtime="<?php echo htmlspecialchars($lxIso, ENT_QUOTES, 'UTF-8'); ?>" datetime="<?php echo htmlspecialchars($lxIso, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($lxIso, ENT_QUOTES, 'UTF-8'); ?></time></p>
                        <p class="live-countdown" data-lx-countdown="<?php echo htmlspecialchars($lxIso, ENT_QUOTES, 'UTF-8'); ?>" data-now="<?php echo gmdate('Y-m-d\TH:i:s\Z'); ?>" data-live-id="<?php echo (int)$live['id']; ?>" role="timer">--:--:--</p>
                    <?php endif; ?>
                    <div class="live-gate-actions">
                        <button type="button" class="btn btn-primary" data-lx-remind="<?php echo (int)$live['id']; ?>" data-csrf="<?php echo htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8'); ?>" data-logged-in="<?php echo !empty($_SESSION['user_id']) ? '1' : '0'; ?>" aria-pressed="<?php echo $lxHasReminder ? 'true' : 'false'; ?>"><?php echo $lxHasReminder ? '🔔 Reminder set' : '🔔 Remind me'; ?></button>
                        <?php if ($lxIso): ?><a class="btn btn-outline" href="/TMZ%20PODCAST/api/live-calendar.php?live_id=<?php echo (int)$live['id']; ?>">📅 Add to calendar</a><?php endif; ?>
                    </div>
                    <link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/live-extras.css?v=lx-1">
                    <script src="/TMZ%20PODCAST/assets/js/live-countdown.js?v=lx-1" defer></script>
                </div>
            <?php elseif (!$canJoin): ?>
                <div class="live-gate">
                    <h3>🔒 Paid live episode</h3>
                    <p>This live room requires a completed purchase or active all-access membership. Sign in to unlock access.</p>
                    <a class="btn btn-primary" href="/TMZ%20PODCAST/membership.php">Unlock this live show</a>
                </div>
            <?php elseif ($live['status'] === 'ended'): ?>
                <div class="live-gate live-replay-gate">
                    <h3>Show recording downloads</h3>
                    <p>The host’s video and audio recordings are available below when processing is complete. Transcript downloads are also available in TXT, SRT, and VTT formats.</p>
                    <form class="live-transcript-range" data-live-id="<?php echo (int)$live['id']; ?>">
                        <label>From (HH:MM:SS)<input name="from" inputmode="numeric" placeholder="00:00:00" pattern="[0-9]{1,5}:[0-5][0-9]:[0-5][0-9]"></label>
                        <label>To (HH:MM:SS)<input name="to" inputmode="numeric" placeholder="01:00:00" pattern="[0-9]{1,5}:[0-5][0-9]:[0-5][0-9]"></label>
                        <button class="btn btn-outline" type="submit">Apply time range</button>
                        <button class="btn btn-outline" type="reset">Full transcript</button>
                    </form>
                    <nav class="live-transcript-downloads" aria-label="Download transcript">
                        <?php foreach (['txt' => 'TXT', 'srt' => 'SRT', 'vtt' => 'VTT'] as $format => $label): ?>
                            <a class="btn btn-outline" href="/TMZ%20PODCAST/api/live-captions.php?live_id=<?php echo (int)$live['id']; ?>&amp;format=<?php echo $format; ?>"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            <?php else: ?>
                <div class="live-prejoin" id="livePrejoin">
                    <span class="live-pill">● LIVE NOW</span>
                    <h3>Ready to watch?</h3>
                    <p>You join with your microphone and camera off, and nothing is requested from your browser until you choose. When you want to talk, tap <strong>Unmute</strong> (no host approval needed); tap <strong>Start video</strong> to switch to camera. The host can mute or block anyone, and must allow them again before they can talk. <strong>Recording notice:</strong> The host may start audio or video recording at any time during this live show. By selecting “Watch live show,” you acknowledge that the live session may be recorded. Speakers should use headphones: nearby computers playing audio through speakers can cause echo even when browser echo cancellation is enabled.</p>
                    <input type="hidden" id="liveCsrfToken" value="<?php echo htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-primary" type="button" id="joinLiveRoom">Watch live show</button>
                    <p id="liveJoinStatus" class="live-join-status" role="status" aria-live="polite"></p>
                </div>
                <div class="live-player" id="livePlayerStage" hidden>
                    <div class="live-layout-switch" role="group" aria-label="Video layout">
                        <button class="btn btn-outline" type="button" data-live-layout="gallery" aria-pressed="true">Gallery view</button>
                        <button class="btn btn-outline" type="button" data-live-layout="speaker" aria-pressed="false">Speaker view</button>
                    </div>
                    <div id="livekitPlayer" class="livekit-player" data-live-id="<?php echo (int)$live['id']; ?>">
                        <div class="stream-placeholder">Waiting for the live broadcast…</div>
                    </div>
                    <p id="liveRecordingNotice" class="live-recording-live-notice" role="status" aria-live="polite" hidden>● This live show is being recorded.</p>
                    <div class="live-call-bar" id="liveCallBar" role="toolbar" aria-label="Your microphone and camera" hidden>
                        <button type="button" class="live-call-btn" id="liveMicToggle" data-state="off" aria-pressed="false"><span class="live-call-icon" aria-hidden="true">🎤</span><span class="live-call-label">Unmute</span></button>
                        <button type="button" class="live-call-btn" id="liveCamToggle" data-state="off" aria-pressed="false"><span class="live-call-icon" aria-hidden="true">📷</span><span class="live-call-label">Start video</span></button>
                        <button type="button" class="live-call-btn" id="liveEnableSound" data-state="off" hidden><span class="live-call-icon" aria-hidden="true">🔊</span><span class="live-call-label">Tap to hear the room</span></button>
                        <button type="button" class="btn btn-outline" id="toggleSpeakerCaptions">Start your captions</button>
                        <button type="button" class="live-call-btn" id="liveDeviceSettingsBtn" aria-expanded="false" aria-controls="liveDevicePanel"><span class="live-call-icon" aria-hidden="true">⚙️</span><span class="live-call-label">Devices</span></button>
                        <span id="speakerCaptionStatus" role="status">Captions off</span>
                        <p class="live-call-note" id="liveCallNote" role="status" aria-live="polite"></p>
                        <div class="live-device-panel" id="liveDevicePanel" hidden>
                            <label>Camera
                                <select id="liveCameraSelect"><option value="">Default camera</option></select>
                            </label>
                            <label>Microphone
                                <select id="liveMicSelect"><option value="">Default microphone</option></select>
                            </label>
                            <label>Video quality
                                <select id="liveVideoQuality">
                                    <option value="hd">HD · 720p</option>
                                    <option value="sd" selected>Standard · 360p</option>
                                    <option value="data">Data saver · 180p</option>
                                </select>
                            </label>
                            <p class="live-call-note">Changes apply instantly while you are live.</p>
                        </div>
                        <div class="live-roster" id="liveRoster" aria-label="People talking in the room"></div>
                    </div>
                    <div class="live-view-controls" id="liveViewControls" hidden aria-label="Shared screen viewing controls">
                        <span class="live-view-label">HOST SCREEN SHARE</span>
                        <button type="button" class="btn btn-outline" id="screenFullscreen">Full screen</button>
                        <button type="button" class="btn btn-outline" id="screenPictureInPicture">Picture in picture</button>
                        <span class="live-view-help">Keep watching while you browse other tabs.</span>
                    </div>
                    <div id="livekitViewerStatus" class="livekit-status">WAITING</div>
                    <button class="btn btn-outline live-reconnect-button" type="button" id="retryLiveRoom" hidden>Reconnect</button>
                </div>

                <?php include __DIR__ . '/../partials/live-extras-viewer.php'; ?>
                <section class="live-caption-panel" id="liveCaptionPanel" data-live-id="<?php echo (int)$live['id']; ?>">
                    <div class="live-caption-toolbar">
                        <label><input type="checkbox" id="liveCaptionsEnabled" checked> Live captions</label>
                        <label>Text size
                            <select id="liveCaptionSize"><option value="small">Small</option><option value="medium" selected>Medium</option><option value="large">Large</option></select>
                        </label>
                        <label><input type="checkbox" id="liveCaptionContrast"> High contrast</label>
                        <label>Position
                            <select id="liveCaptionPosition"><option value="below" selected>Below video</option><option value="overlay">On video</option></select>
                        </label>
                    </div>
                    <div id="liveCaptionLines" class="live-caption-lines" aria-live="polite" aria-atomic="true">
                        <p class="live-caption-placeholder">Live captions will appear here when a speaker enables them.</p>
                    </div>
                    <p class="live-caption-note">Captions use your speaker's browser speech-recognition support. Accuracy and availability vary by browser and language.</p>
                    <details class="live-transcript">
                        <summary>Search and download transcript</summary>
                        <form id="liveTranscriptSearch" class="live-transcript-search">
                            <label>Search transcript <input id="liveTranscriptQuery" type="search" maxlength="100"></label>
                            <button class="btn btn-outline" type="submit">Search</button>
                        </form>
                        <form class="live-transcript-range" data-live-id="<?php echo (int)$live['id']; ?>">
                            <label>From (HH:MM:SS)<input name="from" inputmode="numeric" placeholder="00:00:00" pattern="[0-9]{1,5}:[0-5][0-9]:[0-5][0-9]"></label>
                            <label>To (HH:MM:SS)<input name="to" inputmode="numeric" placeholder="01:00:00" pattern="[0-9]{1,5}:[0-5][0-9]:[0-5][0-9]"></label>
                            <button class="btn btn-outline" type="submit">Apply time range</button>
                            <button class="btn btn-outline" type="reset">Full transcript</button>
                        </form>
                        <nav class="live-transcript-downloads" aria-label="Download transcript">
                            <?php foreach (['txt' => 'TXT', 'srt' => 'SRT', 'vtt' => 'VTT'] as $format => $label): ?>
                                <a class="btn btn-outline" href="/TMZ%20PODCAST/api/live-captions.php?live_id=<?php echo (int)$live['id']; ?>&amp;format=<?php echo $format; ?>"><?php echo $label; ?></a>
                            <?php endforeach; ?>
                        </nav>
                        <div id="liveTranscriptResults" class="live-transcript-results" aria-live="polite"></div>
                    </details>
                </section>

                <div id="liveSpeakerInvite" class="live-speaker-invite" role="dialog" aria-modal="true" aria-labelledby="liveSpeakerInviteTitle" hidden>
                    <h3 id="liveSpeakerInviteTitle">You’ve been invited to speak</h3>
                    <p id="liveSpeakerInviteMessage">Allow microphone access? Your microphone stays muted until you hold the push-to-talk button.</p>
                    <div><button type="button" class="btn btn-primary" id="acceptLiveSpeakerInvite">Allow and continue</button><button type="button" class="btn btn-outline" id="declineLiveSpeakerInvite">Decline</button><button type="button" class="btn btn-outline" id="closeLiveSpeakerError" hidden>Close</button></div>
                </div>
                <div id="livePushToTalk" class="live-push-to-talk" hidden>
                    <p>Use headphones for the clearest sound. Tap once to turn your live microphone on; tap again to mute. This is live audio, not a saved recording.</p>
                    <button type="button" class="btn btn-primary live-talk-toggle" id="livePushToTalkButton" aria-pressed="false">Start talking</button>
                    <span id="liveTalkDuration" class="live-talk-duration" role="timer" aria-live="off" hidden>00:00</span>
                    <button type="button" class="btn btn-outline" id="leaveLiveStage">Leave speaking role</button>
                </div>

                <?php if ($canChat): ?>
                    <div class="live-actions">
                        <form id="liveActionsForm" method="post" action="/TMZ%20PODCAST/live-action.php">
                            <?php echo CSRF::getField(); ?>
                            <input type="hidden" name="live_id" value="<?php echo (int)$live['id']; ?>">
                            <button type="button" data-reaction="like">👍 Like</button>
                            <button type="button" data-reaction="love">❤️ Love</button>
                            <button type="button" data-reaction="fire">🔥 Fire</button>
                            <button type="button" data-speaker-request="audio">Request to speak (audio)</button>
                            <button type="button" data-speaker-request="video">Request to speak (video)</button>
                        </form>
                        <a class="btn btn-primary" href="/TMZ%20PODCAST/gift.php?live_id=<?php echo (int)$live['id']; ?>">🎁 Send a gift</a>
                    </div>
                <?php endif; ?>

                <div class="live-comments">
                    <h3>Live conversation</h3>
                    <div id="liveChatFeed" class="live-chat-feed" aria-live="polite">
                        <?php foreach ($comments as $comment): ?>
                            <div class="live-message-item">
                                <?php if (!empty($comment['profile_image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars('/TMZ%20PODCAST/' . ltrim($comment['profile_image_path'], '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                <?php else: ?>
                                    <span class="live-avatar-fallback"><?php echo htmlspecialchars(strtoupper(substr($comment['username'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <p><strong><?php echo htmlspecialchars($comment['username'], ENT_QUOTES, 'UTF-8'); ?></strong><span><?php echo htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($canChat): ?>
                        <form id="liveChatForm" method="post" action="/TMZ%20PODCAST/live-action.php">
                            <?php echo CSRF::getField(); ?>
                            <input type="hidden" name="live_id" value="<?php echo (int)$live['id']; ?>">
                            <input id="liveChatInput" name="comment" maxlength="500" required placeholder="Join the conversation">
                            <button class="btn btn-primary">Send</button>
                        </form>
                    <?php else: ?>
                        <p class="live-readonly-note">Watching as a guest. Sign in to post in the member chat.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>

<?php if ($live && $canJoin): ?>
    <section class="live-recordings-panel" id="publicLiveRecordings" data-live-id="<?php echo (int)$live['id']; ?>" data-api="/TMZ%20PODCAST/api/live-recordings.php">
        <div class="live-recordings-heading"><div><span class="eyebrow">DOWNLOADS</span><h2>Live show recordings</h2><p>Choose a completed video or audio file to download. Download links expire after 10 minutes; only the admin can delete recordings.</p></div></div>
        <div class="live-recordings-list" aria-live="polite"><p class="form-help">Loading available recordings…</p></div>
        <p class="live-recordings-status" role="status" aria-live="polite"></p>
    </section>
    <script src="/TMZ%20PODCAST/assets/js/live-recordings.js?v=live-recordings-2"></script>
    <script src="/TMZ%20PODCAST/assets/js/live-transcript-range.js?v=live-transcript-range-1"></script>
<?php endif; ?>

<?php if ($live && $live['status'] === 'live' && $canJoin): ?>
    <script src="/TMZ%20PODCAST/assets/js/livekit-client.umd.js"></script>
    <script src="/TMZ%20PODCAST/assets/js/live-viewer-controls.js?v=watch-controls-3"></script>
    <script src="/TMZ%20PODCAST/assets/js/live-room-viewer.js?v=device-settings-1"></script>
    <script src="/TMZ%20PODCAST/assets/js/live-captions.js?v=phase2-1"></script>
    <script src="/TMZ%20PODCAST/assets/js/live-extras.js?v=lx-1" defer></script>
<?php endif; ?>

<?php include __DIR__ . '/../partials/live-lobby.php'; ?>
