<?php
/** Download a scheduled live show as an .ics calendar event. */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/PublicUrl.php';
require_once __DIR__ . '/../app/Models/LiveExtras.php';

$liveId = filter_var($_GET['live_id'] ?? null, FILTER_VALIDATE_INT);
$row = $liveId ? (new LiveExtras())->liveRow($liveId) : null;
if (!$row || empty($row['scheduled_at'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This show has no scheduled time.');
}

$esc = function ($t) {
    $t = str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], (string)$t);
    return $t;
};
$fold = function ($line) {
    $out = '';
    while (strlen($line) > 74) { $out .= substr($line, 0, 74) . "\r\n "; $line = substr($line, 74); }
    return $out . $line;
};
$start = strtotime($row['scheduled_at'] . ' UTC');
$base = TmzPublicUrl::base();
if (substr($base, -4) === '/api') $base = substr($base, 0, -4); // this script lives in /api
$url = $base . '/live.php?live_id=' . (int)$row['id'];
$desc = trim((string)($row['description'] ?? ''));
$lines = [
    'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//THE MOVIE ZONE PODCAST 256//Live//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:live-' . (int)$row['id'] . '@tmz-podcast',
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . gmdate('Ymd\THis\Z', $start),
    'DTEND:' . gmdate('Ymd\THis\Z', $start + 3600),
    'SUMMARY:' . $esc('TMZ Live: ' . $row['title']),
    'DESCRIPTION:' . $esc(($desc !== '' ? $desc . "\n\n" : '') . 'Watch live: ' . $url),
    'URL:' . $url,
    'BEGIN:VALARM', 'TRIGGER:-PT15M', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($row['title'] . ' starts in 15 minutes'), 'END:VALARM',
    'END:VEVENT', 'END:VCALENDAR',
];
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="tmz-live-' . (int)$row['id'] . '.ics"');
echo implode("\r\n", array_map($fold, $lines)) . "\r\n";
