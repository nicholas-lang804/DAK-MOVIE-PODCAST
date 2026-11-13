<?php
/**
s * Downloads on this site are limited to music tracks (audio and music videos),
 * which are served exclusively through music-download.php with per-track access rules.
 * Movies, videos, episodes and article media are stream-only.
 */
session_start();
http_response_code(403);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
   . '<title>Download unavailable</title>'
   . '<body style="font-family:system-ui,sans-serif;background:#050a1a;color:#eef3ff;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:1rem">'
   . '<main><h1>Downloads are only available for music</h1>'
   . '<p>Movies, videos and episodes can be streamed but not downloaded.</p>'
   . '<p><a style="color:#8fb8ff" href="/music.php">Go to Music</a></p></main>';
exit;
