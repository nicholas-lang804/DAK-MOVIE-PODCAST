<section class="admin-section live-studio" data-live-id="<?php echo $live ? (int)$live['id'] : 0; ?>" data-live-status="<?php echo $live ? htmlspecialchars($live['status'], ENT_QUOTES, 'UTF-8') : 'offline'; ?>">
  <div class="studio-topbar"><div class="studio-brand"><span class="studio-brand-mark">DAK</span><div><strong>THE DAK FILM PODCAST MEDIA</strong><small>LIVE PRODUCTION STUDIO</small></div></div><div class="studio-top-metrics"><span class="studio-metric"><b id="studioDuration">00:00:00</b><small>DURATION</small></span><span class="studio-metric"><b id="liveViewerCount">0</b><small>VIEWERS</small></span><span class="studio-metric"><b id="studioHealth">READY</b><small>STREAM HEALTH</small></span></div></div>
  <div class="section-header"><div><span class="eyebrow">BROADCAST CONTROL</span><h1>Live Podcast Studio</h1><p>Control the stream, audience, speakers, comments and reactions from one room.</p></div><button type="button" class="btn btn-outline" id="cameraButton">Enable camera and microphone</button></div>
  <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?></div><?php endif; ?>
  <?php if(isset($_SESSION['error'])): ?><div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div><?php endif; ?>
  <form id="liveStudioCsrf" hidden><?php echo CSRF::getField(); ?></form>
  <details class="studio-audio-panel" id="studioAudioPanel" open>
    <summary><span><strong>Microphone sound &amp; isolation</strong><small>Browser-side processing · no paid audio SDKs</small></span><span class="audio-panel-status" id="audioPresetLabel">Podcast</span></summary>
    <div class="audio-panel-body">
      <div class="audio-preset-buttons" aria-label="One-click audio presets">
        <button type="button" class="btn btn-outline" data-audio-preset="studio">Studio</button>
        <button type="button" class="btn btn-outline" data-audio-preset="podcast">Podcast</button>
        <button type="button" class="btn btn-outline" data-audio-preset="interview">Interview</button>
        <button type="button" class="btn btn-outline" data-audio-preset="noisy-room">Noisy Room</button>
        <button type="button" class="btn btn-outline" data-audio-preset="music">Music Mode</button>
      </div>
      <div class="audio-control-grid">
        <label>Voice EQ preset
          <select id="audioEqPreset">
            <option value="natural">Natural</option><option value="podcast" selected>Podcast</option>
            <option value="warm">Warm Voice</option><option value="bright">Bright</option>
            <option value="phone">Phone Clarity</option><option value="deep">Deep Voice</option><option value="custom">Custom</option>
          </select>
        </label>
        <label>RNNoise suppression
          <select id="noiseSuppressionLevel"><option value="off">Off</option><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select>
        </label>
        <label>Hum notch
          <select id="humFrequency"><option value="50" selected>50 Hz</option><option value="60">60 Hz</option></select>
        </label>
        <label>Noise gate threshold <span id="audioGateThresholdValue">-55 dB</span>
          <input type="range" id="audioGateThreshold" min="-70" max="-20" value="-55">
        </label>
        <label>Clarity <span id="audioClarityValue">0 dB</span>
          <input type="range" id="audioClarity" min="-6" max="6" value="0">
        </label>
        <label>Warmth <span id="audioWarmthValue">0 dB</span>
          <input type="range" id="audioWarmth" min="-6" max="6" value="0">
        </label>
        <label>Studio reverb <span id="audioReverbValue">0%</span>
          <input type="range" id="audioReverb" min="0" max="20" value="0">
        </label>
      </div>
      <div class="audio-feature-toggles">
        <label><input type="checkbox" id="browserProcessing" checked><span>Browser echo cancellation, noise suppression &amp; auto gain</span></label>
        <label><input type="checkbox" id="voiceIsolation"><span>Voice focus <small>(RNNoise-assisted, experimental)</small></span></label>
        <label><input type="checkbox" id="noiseGateEnabled" checked><span>Noise gate</span></label>
        <label><input type="checkbox" id="highPassEnabled" checked><span>High-pass rumble filter</span></label>
        <label><input type="checkbox" id="equalizerEnabled" checked><span>9-band equaliser</span></label>
        <label><input type="checkbox" id="deEsserEnabled" checked><span>De-esser</span></label>
        <label><input type="checkbox" id="dePlosiveEnabled" checked><span>De-plosive helper</span></label>
        <label><input type="checkbox" id="autoLevelEnabled" checked><span>Auto level / leveller</span></label>
        <label><input type="checkbox" id="compressorEnabled" checked><span>Compressor</span></label>
        <label><input type="checkbox" id="limiterEnabled" checked><span>Peak limiter</span></label>
        <label><input type="checkbox" id="windHumEnabled" checked><span>Wind &amp; hum reduction</span></label>
        <label><input type="checkbox" id="dryRoom" checked><span>Dry room (reverb off)</span></label>
        <label><input type="checkbox" id="musicMode"><span>Music Mode <small>(lighter cleanup, stereo target, higher bitrate)</small></span></label>
      </div>
      <div class="audio-equalizer"><strong>9-band equalizer</strong><div id="audioEqualizerBands"></div></div>
      <div class="audio-gate-calibration">
        <button type="button" class="btn btn-outline" id="calibrateNoiseGate">Calibrate — stay quiet for 3 seconds</button>
        <span id="noiseGateCalibrationStatus" aria-live="polite"></span>
      </div>
      <div class="audio-pipeline-monitor">
        <span>MIC LEVEL</span><span class="audio-meter"><i id="audioPipelineMeterFill"></i></span>
        <strong id="audioPipelineMeterLabel">WAITING</strong><span>Voice activity <b id="audioVadValue">—</b></span><span id="browserProcessingStatus">Browser mic processing: waiting</span>
      </div>
      <p class="audio-feature-note" id="audioFeatureStatus" aria-live="polite">Starting browser audio feature checks…</p>
      <p class="audio-feature-note audio-echo-warning">If another computer nearby is playing the live audio through speakers, its microphone can pick your voice up again and create echo. Use headphones on each computer, mute other microphones, or move to separate rooms; browser echo cancellation cannot reliably remove a nearby open-speaker loop.</p>
      <p class="audio-feature-note">RNNoise and all filters run on this device. Voice focus is best-effort and cannot fully separate overlapping speakers. Music Mode can only upmix a mono microphone; it cannot create true stereo. AudioWorklet and screen/mic access require a supported browser and a secure context.</p>
    </div>
  </details>
  <div class="studio-preview-frame"><div class="studio-preview-label"><span>PROGRAM PREVIEW</span><span id="previewResolution">1280 × 720 · 30 FPS TARGET</span></div><video id="cameraPreview" class="camera-preview" autoplay muted playsinline hidden></video><div class="studio-preview-empty">Enable your camera and microphone to preview the program feed.</div><button type="button" class="preview-fullscreen" id="previewFullscreen" title="Full-screen preview" aria-label="Full-screen preview">⛶</button></div><div class="studio-device-controls"><label>Camera<select id="cameraSelect"><option value="">Default camera</option></select></label><label>Microphone<select id="microphoneSelect"><option value="">Default microphone</option></select></label><label>Video quality<select id="videoQuality"><option value="hd">HD · 720p30</option><option value="fullhd">Full HD · 1080p30</option><option value="sd">Standard · 480p30</option><option value="data">Data saver · 360p24</option></select></label><span id="connectionQuality" class="livekit-quality">Quality: waiting</span><span class="audio-meter-wrap">MIC <span class="audio-meter"><i id="audioMeterFill"></i></span></span></div><div id="livekitStatus" class="livekit-status">OFFLINE</div>
  <?php if($live && $live['status']==='live'): ?><div class="live-status"><strong>● LIVE NOW</strong><span><?php echo htmlspecialchars($live['title']); ?></span><span id="liveViewerInline">0 viewers</span><div class="live-host-controls"><button type="button" class="btn btn-outline" id="muteMic">Mute microphone</button><button type="button" class="btn btn-outline" id="toggleCamera">Turn camera off</button><button type="button" class="btn btn-outline live-share-screen-btn" id="screenShare">📺 Share screen</button></div><form method="post"><input type="hidden" name="live_action" value="status"><input type="hidden" name="status" value="ended"><?php echo CSRF::getField(); ?><button class="btn btn-danger" type="submit">End Live</button></form></div>
  <?php elseif($live && $live['status']==='scheduled'): ?><div class="live-status"><strong>READY TO START</strong><span><?php echo htmlspecialchars($live['title']); ?></span><span class="form-help">Click Enable camera and microphone. The room becomes LIVE after the connection succeeds.</span></div>
  <?php else: ?><form method="post" enctype="multipart/form-data" class="admin-form live-form"><?php echo CSRF::getField(); ?><input type="hidden" name="live_action" value="create"><label>Podcast title<input name="title" required></label><label>Description<textarea name="description" rows="3"></textarea></label><label>Share cover image (JPEG, PNG, or WebP; max 8 MB)<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"></label><label>Optional external stream URL<input type="url" name="stream_url" placeholder="Only needed for an external player"></label><label>Access<select name="access_type" id="liveAccess"><option value="free">Free — account required</option><option value="paid">Paid</option></select></label><div id="livePrice" hidden><label>Price<input type="number" name="price" min="0.01" step="0.01"></label><label>Currency<input name="currency" value="USD" maxlength="3"></label></div><button class="btn btn-primary" type="submit">Prepare Live Room</button></form><?php endif; ?>
</section>
<?php if($live && in_array($live['status'], ['live','scheduled'], true)): ?>
<?php $shareUrl=TmzPublicUrl::to('/live.php?live_id='.(int)$live['id']); ?>
<section class="admin-section live-share-tools" id="liveShareTools" data-share-url="<?php echo htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>" data-share-title="<?php echo htmlspecialchars($live['title'], ENT_QUOTES, 'UTF-8'); ?>">
  <div class="live-share-heading"><div><span class="eyebrow">INVITE YOUR AUDIENCE</span><h2>Share this live show</h2><p>The link preview uses this show’s title, description, and cover image in messaging and social apps.</p></div></div>
  <div class="live-share-preview">
    <?php if(!empty($live['cover_image_path'])): ?><img src="/TMZ%20PODCAST/<?php echo htmlspecialchars(ltrim($live['cover_image_path'], '/'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($live['title'], ENT_QUOTES, 'UTF-8'); ?>"><?php else: ?><div class="live-share-image-empty">Add a cover image for a rich preview</div><?php endif; ?>
    <div><strong><?php echo htmlspecialchars($live['title'], ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo nl2br(htmlspecialchars($live['description'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p></div>
  </div>
  <form method="post" enctype="multipart/form-data" action="/TMZ%20PODCAST/admin/dashboard.php?tab=live&amp;live_id=<?php echo (int)$live['id']; ?>" class="live-share-edit-form">
    <?php echo CSRF::getField(); ?><input type="hidden" name="live_action" value="update_share">
    <label>Share title<input name="title" maxlength="255" required value="<?php echo htmlspecialchars($live['title'], ENT_QUOTES, 'UTF-8'); ?>"></label>
    <label>Share description<textarea name="description" rows="3" maxlength="5000"><?php echo htmlspecialchars($live['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></label>
    <label>Cover image (JPEG, PNG, or WebP; max 8 MB)<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"><small>Leave empty to keep the current image.</small></label>
    <button class="btn btn-outline" type="submit">Save share preview</button>
  </form>
  <label class="live-share-link-label">Public show link<input id="liveShareLink" type="url" readonly value="<?php echo htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>"></label>
  <div class="live-share-actions"><button type="button" class="btn btn-primary" id="shareLiveShow">Share…</button><button type="button" class="btn btn-outline" id="copyLiveShowLink">Copy link</button><a class="btn btn-outline" id="whatsappLiveShow" target="_blank" rel="noopener noreferrer">WhatsApp</a><a class="btn btn-outline" id="emailLiveShow">Email</a><span id="liveShareStatus" role="status" aria-live="polite"></span></div>
</section>
<script src="/TMZ%20PODCAST/assets/js/live-share.js?v=live-share-1"></script>
<?php endif; ?>
<?php if($live): ?>
<section class="admin-section live-control-panel">
  <h2>Live control room</h2>
  <div class="live-control-grid">
    <div class="live-request-section">
      <div class="live-request-heading">
        <div><h3>Speaker requests</h3><p class="form-help"><?php echo count($speakerRequests??[]); ?> request<?php echo count($speakerRequests??[])===1?'':'s'; ?> for this show</p></div>
        <div class="live-request-actions">
          <button type="button" class="btn btn-outline" id="toggleLiveRequests" aria-expanded="false" aria-controls="liveRequestsList">Open Requests</button>
          <form method="post" class="live-delete-requests-form" onsubmit="return confirm('Delete every speaker request for this show? This cannot be undone.');">
            <input type="hidden" name="live_action" value="delete_all_speaker_requests">
            <?php echo CSRF::getField(); ?>
            <button type="submit" class="btn btn-danger" <?php echo empty($speakerRequests)?'disabled':''; ?>>Delete All Requests</button>
          </form>
        </div>
      </div>
      <div id="liveRequestsList" class="live-requests-list" hidden>
        <?php foreach(($speakerRequests??[]) as $request): ?>
          <div class="live-status">
            <strong><?php echo htmlspecialchars($request['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars(ucfirst($request['request_type']).' · '.ucfirst($request['status']), ENT_QUOTES, 'UTF-8'); ?></span>
            <form method="post">
              <input type="hidden" name="live_action" value="speaker">
              <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
              <?php echo CSRF::getField(); ?>
              <button name="speaker_status" value="approved" class="btn btn-primary">Allow</button>
              <button name="speaker_status" value="denied" class="btn btn-outline">Deny</button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if(empty($speakerRequests)): ?><p class="form-help">No speaker requests yet.</p><?php endif; ?>
      </div>
    </div>
    <div class="live-comment-section">
      <div class="live-request-heading">
        <div><h3>Comments and replies</h3><p class="form-help"><?php echo count($liveComments??[]); ?> comment<?php echo count($liveComments??[])===1?'':'s'; ?> for this show</p></div>
        <div class="live-request-actions">
          <button type="button" class="btn btn-outline" id="toggleLiveComments" aria-expanded="false" aria-controls="liveCommentsList">Open Comments</button>
          <form method="post" class="live-delete-requests-form" onsubmit="return confirm('Delete every comment and reply for this show? This cannot be undone.');">
            <input type="hidden" name="live_action" value="delete_all_live_comments">
            <?php echo CSRF::getField(); ?>
            <button type="submit" class="btn btn-danger" <?php echo empty($liveComments)?'disabled':''; ?>>Delete All Comments</button>
          </form>
        </div>
      </div>
      <div id="liveCommentsList" class="live-comments-list" hidden>
        <?php foreach(($liveComments??[]) as $comment): ?>
          <div class="admin-live-comment">
            <strong><?php echo htmlspecialchars($comment['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <p><?php echo htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
            <form method="post"><input type="hidden" name="live_action" value="comment_reply"><input type="hidden" name="parent_id" value="<?php echo (int)$comment['id']; ?>"><?php echo CSRF::getField(); ?><input name="comment" placeholder="Reply to this comment"><button class="btn btn-small btn-primary">Reply</button></form>
            <form method="post"><input type="hidden" name="live_action" value="comment_hide"><input type="hidden" name="comment_id" value="<?php echo (int)$comment['id']; ?>"><?php echo CSRF::getField(); ?><button class="btn btn-small btn-outline">Hide</button></form>
          </div>
        <?php endforeach; ?>
        <?php if(empty($liveComments)): ?><p class="form-help">No comments yet.</p><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
<?php if($live): ?><section class="admin-section live-soundboard" id="liveSoundboard" data-live-id="<?php echo (int)$live['id']; ?>">
  <div class="live-recordings-heading"><div><span class="eyebrow">PRESENTER SOUNDS</span><h2>Applause &amp; reactions</h2><p>Thank a great presentation — each button plays a sound on your device and sends the matching emoji reaction to everyone watching.</p></div></div>
  <div class="live-soundboard-buttons">
    <button type="button" class="btn btn-outline" data-sound="applause">👏 Applause</button>
    <button type="button" class="btn btn-outline" data-sound="cheer">🎉 Cheer</button>
    <button type="button" class="btn btn-outline" data-sound="clap">👏 Clap clap</button>
    <button type="button" class="btn btn-outline" data-sound="whistle">📣 Whistle</button>
    <button type="button" class="btn btn-outline" data-sound="fanfare">🏆 Fanfare</button>
    <button type="button" class="btn btn-outline" data-sound="laugh">😂 Laugh</button>
  </div>
  <?php if($live['status']!=='live'): ?><p class="form-help">Sounds play now; the emoji reactions appear for viewers while the show is live.</p><?php endif; ?>
</section><?php endif; ?>
<?php if($live){ include __DIR__ . '/../partials/live-extras-admin.php'; } ?>
<?php if($live): ?><section class="admin-section live-caption-admin" id="adminLiveCaptions" data-live-id="<?php echo (int)$live['id']; ?>">
  <div class="live-caption-admin-heading"><div><span class="eyebrow">LIVE ACCESSIBLE AUDIO</span><h2>Captions &amp; transcript</h2><p>Browser speech recognition runs on this device. Depending on the browser, speech may be processed by its own recognition service.</p></div><div class="live-caption-admin-controls"><label>Caption language<select id="adminCaptionLanguage"><option value="en-US">English</option><option value="lg-UG">Luganda</option><option value="sw-KE">Swahili</option><option value="fr-FR">French</option></select></label><button type="button" class="btn btn-primary" id="toggleAdminCaptions">Start live captions</button><span id="adminCaptionStatus" role="status">Captions off</span></div></div>
  <div id="adminLiveCaptionLines" class="live-caption-lines" aria-live="polite" aria-atomic="true"><p class="live-caption-placeholder">Live captions are off.</p></div>
  <form id="adminTranscriptSearch" class="live-transcript-search"><label>Search transcript <input id="adminTranscriptQuery" type="search" maxlength="100"></label><button type="submit" class="btn btn-outline">Search</button></form>
  <form class="live-transcript-range" data-live-id="<?php echo (int)$live['id']; ?>">
    <label>From (HH:MM:SS)<input name="from" inputmode="numeric" placeholder="00:00:00" pattern="[0-9]{1,5}:[0-5][0-9]:[0-5][0-9]"></label>
    <label>To (HH:MM:SS)<input name="to" inputmode="numeric" placeholder="01:00:00" pattern="[0-9]{1,5}:[0-5][0-9]:[0-5][0-9]"></label>
    <button type="submit" class="btn btn-outline">Apply time range</button><button type="reset" class="btn btn-outline">Full transcript</button>
  </form>
  <nav class="live-transcript-downloads" aria-label="Download live transcript"><?php foreach(['txt'=>'TXT','srt'=>'SRT','vtt'=>'VTT'] as $format=>$label): ?><a class="btn btn-outline" href="/TMZ%20PODCAST/api/live-captions.php?live_id=<?php echo (int)$live['id']; ?>&amp;format=<?php echo $format; ?>"><?php echo $label; ?></a><?php endforeach; ?></nav>
  <div id="adminTranscriptResults" class="live-transcript-results" aria-live="polite"></div>
</section><?php endif; ?>
<?php if($live): ?><section class="admin-section live-recordings-panel" id="adminLiveRecordings" data-live-id="<?php echo (int)$live['id']; ?>" data-api="/TMZ%20PODCAST/api/live-recordings.php">
  <div class="live-recordings-heading"><div><span class="eyebrow">LIVE MEDIA ARCHIVE</span><h2>Recordings &amp; downloads</h2><p>While the show is LIVE, choose Start video (MP4) or select an audio format and choose Start audio. Press Stop recording to finish; completed files appear below with Download and Delete controls. A recording only captures content from when you press Start onward.</p></div></div>
  <p class="live-recording-consent">Viewers are informed before joining that recording may begin at any time during the live show. Starting a recording does not prompt viewers to approve it. Video is saved as MP4; audio is saved as MP3 or OGG.</p>
  <?php if($live['status']==='live'): ?>
    <div class="live-recording-controls"><button type="button" class="btn btn-primary" id="startVideoRecording">Start video (MP4)</button><label>Audio format<select id="liveAudioRecordingFormat"><option value="mp3">MP3</option><option value="ogg">OGG</option></select></label><button type="button" class="btn btn-outline" id="startAudioRecording">Start audio</button><button type="button" class="btn btn-danger" id="stopLiveRecording" disabled>Stop recording</button></div>
  <?php else: ?><p class="form-help">Recording controls are available while a show is live.</p><?php endif; ?>
  <p id="adminLiveRecordingStatus" class="live-recordings-status" role="status" aria-live="polite">Loading recording status…</p>
  <div class="live-recordings-list" aria-live="polite"><p class="form-help">Loading recordings…</p></div>
</section><?php endif; ?>
<?php if($live && $live['status']==='live'): ?><section class="admin-section live-chat-console"><div class="live-chat-heading"><div><span class="eyebrow">REAL-TIME CONVERSATION</span><h2>Live chat monitor</h2></div><span class="live-chat-state">LOW-LATENCY DATA CHANNEL</span></div><div id="adminLiveChat" class="live-chat-feed" aria-live="polite"><p class="form-help">Waiting for viewer messages…</p></div><form id="adminChatForm" class="live-chat-form"><input id="adminChatInput" maxlength="500" placeholder="Reply to the audience…"><button class="btn btn-primary" type="submit">Send reply</button></form></section><?php endif; ?>
<?php if($live && in_array($live['status'], ['live','scheduled'], true)): ?><section class="admin-section live-participant-admin" id="liveParticipantAdmin" data-live-id="<?php echo (int)$live['id']; ?>">
  <div class="live-participant-heading"><div><span class="eyebrow">LIVE ROOM ACCESS CONTROL</span><h2>Participants &amp; permissions</h2><p>Viewers join with mic, camera, and screen share disabled. Grant each capability explicitly.</p></div><span id="liveParticipantSyncStatus" role="status">Waiting for host connection</span></div>
  <div class="live-room-actions" aria-label="Room-wide controls">
    <button type="button" class="btn btn-outline" data-room-control="mute_all">Mute all participants</button>
    <button type="button" class="btn btn-outline" data-room-control="disable_cameras">Disable all cameras</button>
    <button type="button" class="btn btn-outline" data-room-control="room_locked">Lock room to new participants</button>
    <button type="button" class="btn btn-danger" data-room-action="end_for_all">End for all</button>
  </div>
  <section class="live-admin-video-monitor" aria-labelledby="liveAdminVideoHeading"><div class="live-admin-video-heading"><div><h3 id="liveAdminVideoHeading">Participant video monitor</h3><p>Spotlight a speaker for everyone or leave each viewer in control of their own layout.</p></div><button type="button" class="btn btn-outline" id="clearLiveSpotlight">Show gallery to viewers</button></div><div id="liveAdminVideoGrid" class="live-admin-video-grid" aria-live="polite"><p class="form-help">Waiting for participants to publish audio or video.</p></div></section>
  <div id="liveParticipantList" class="live-participant-list" aria-live="polite"><p class="form-help">Connect the studio to load participants.</p></div>
</section><?php endif; ?>
<script src="/TMZ%20PODCAST/assets/js/livekit-client.umd.js"></script><script src="/TMZ%20PODCAST/assets/js/audio-pipeline.js?v=browser-audio-2"></script><script src="/TMZ%20PODCAST/assets/js/live-studio-controls.js?v=audio-controls-2"></script><script src="/TMZ%20PODCAST/assets/js/live-chat-profile.js"></script><script src="/TMZ%20PODCAST/assets/js/live-room-admin.js?v=open-talk-1"></script>
<script>(async function(){if(!navigator.mediaDevices?.enumerateDevices)return;const fill=async()=>{try{const devices=await navigator.mediaDevices.enumerateDevices(),camera=document.getElementById('cameraSelect'),mic=document.getElementById('microphoneSelect');if(!camera||!mic)return;const camPrev=camera.value,micPrev=mic.value;camera.textContent='';mic.textContent='';const co=document.createElement('option');co.value='';co.textContent='Default camera';camera.appendChild(co);const mo=document.createElement('option');mo.value='';mo.textContent='Default microphone';mic.appendChild(mo);devices.forEach(d=>{if(d.kind!=='videoinput'&&d.kind!=='audioinput')return;const o=document.createElement('option');o.value=d.deviceId;const l=(d.label||'').toLowerCase();const tag=d.kind==='videoinput'?(/back|rear|environment/.test(l)?' · Back camera':(/front|user|face/.test(l)?' · Front camera':'')):'';o.textContent=(d.label||(d.kind==='videoinput'?'Camera':'Microphone'))+tag;(d.kind==='videoinput'?camera:mic).appendChild(o);});if(camPrev&&[...camera.options].some(o=>o.value===camPrev))camera.value=camPrev;if(micPrev&&[...mic.options].some(o=>o.value===micPrev))mic.value=micPrev;}catch(e){}};await fill();if(window.isSecureContext&&navigator.mediaDevices?.getUserMedia){try{const s=await navigator.mediaDevices.getUserMedia({video:true,audio:false});s.getTracks().forEach(t=>t.stop());await fill();}catch(e){}}})();</script>
<script src="/TMZ%20PODCAST/assets/js/live-studio-host.js?v=device-settings-1"></script>
<?php if($live): ?><script src="/TMZ%20PODCAST/assets/js/live-captions.js?v=phase2-1"></script><script src="/TMZ%20PODCAST/assets/js/live-recordings.js?v=live-recordings-2"></script><script src="/TMZ%20PODCAST/assets/js/live-transcript-range.js?v=live-transcript-range-1"></script><?php endif; ?>
<?php if($live): ?><script src="/TMZ%20PODCAST/assets/js/live-requests.js?v=live-requests-2"></script><script src="/TMZ%20PODCAST/assets/js/live-soundboard.js?v=soundboard-1"></script><?php endif; ?>
<?php $pastLives = $pastLives ?? []; ?>
<section class="admin-section live-past-lives">
  <div class="live-recordings-heading">
    <div><span class="eyebrow">ARCHIVE CLEANUP</span><h2>Past live shows</h2><p>Delete old broadcasts to clear their comments, requests, transcripts, recordings and all accumulated data. Recording files stored in external storage are not removed from the bucket — only the site's records.</p></div>
    <?php if(!empty($pastLives)): ?>
    <form method="post" onsubmit="return confirm('Delete ALL past live shows and their data? This cannot be undone.');">
      <input type="hidden" name="live_action" value="delete_all_past">
      <?php echo CSRF::getField(); ?>
      <button type="submit" class="btn btn-danger">Delete all past lives (<?php echo count($pastLives); ?>)</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if(empty($pastLives)): ?>
    <p class="form-help">No past live shows to clean up.</p>
  <?php else: ?>
  <div class="table-container"><table class="admin-table">
    <thead><tr><th>Show</th><th>Ended</th><th>Peak viewers</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($pastLives as $past): ?>
      <tr>
        <td><?php echo htmlspecialchars($past['title'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo !empty($past['ended_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($past['ended_at'])), ENT_QUOTES, 'UTF-8') : '—'; ?></td>
        <td><?php echo (int)($past['viewer_peak'] ?? 0); ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this past live show and all of its data? This cannot be undone.');">
            <input type="hidden" name="live_action" value="delete_live">
            <input type="hidden" name="past_live_id" value="<?php echo (int)$past['id']; ?>">
            <?php echo CSRF::getField(); ?>
            <button type="submit" class="btn btn-small btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
