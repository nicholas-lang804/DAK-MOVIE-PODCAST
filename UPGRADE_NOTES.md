# THE MOVIE ZONE PODCAST 256: upgrade notes

This project = your latest upload + all upgrades. Your own later edits were preserved (LiveKit reconnect
and join-status changes in live.php, live-room-viewer.js, live-studio-host.js, style.css, home.php).
`live.php` was merged line by line so both your edits and the new live features are in it.

## What was added
- **Music Zone** (`/music.php`): listen, download, music videos, lyrics, playlists, likes, search, artists, albums,
  genres, library, persistent player on every page. Admin: **Admin > Music** (upload songs, videos, covers, lyrics,
  artist bios, per-song listen/download rules).
- **Live studio**: scheduling + countdown + Remind me + calendar file, "now talking about", pinned message, shout-outs,
  live polls, Q&A with upvotes, viewer count + attendance report (CSV), floating reactions, lobby + past shows.
  Admin: **Admin > Live > Audience engagement**.
- **Site**: stars (actors/actresses), cast links, watchlist (My List), search page, legal pages (templates, have a lawyer
  review), cookie notice, installable app (manifest + service worker), always-filled advert slots, blue/red cinematic theme.
- **Renamed** to THE MOVIE ZONE PODCAST 256 everywhere it is shown (pages, emails, tickets, manifest, SQL seeds).

## After copying the project
1. Existing database: run `database/migrations/016_rename_site.sql` (or change Admin > Settings > Site Name).
2. New tables create themselves on first use. `database/migrations/014_*`, `015_*` are optional.
3. Music uploads: in php.ini raise `upload_max_filesize` and `post_max_size` (e.g. 512M, or 2G for videos).
   Media is stored in `storage/music/` (blocked from direct access; streamed by `music-stream.php` with seeking).
4. Phones may keep the old name cached for a short while (service worker cache was bumped to refresh it).

## Test checklist
1. Admin > Music > Upload a song (audio + cover + lyrics). Open /music.php: play, seek, next, shuffle, repeat, queue.
2. Click the title bar to open Now Playing: lyrics, queue (drag to reorder), Sound (equaliser, speed, sleep timer).
3. Add a music video; use "Watch video". Navigate inside Music Zone: playback continues without restarting.
4. Like a song, add it to a playlist, open Your Library. Try a download as guest vs signed-in.
5. Admin > Live: schedule a show, then check countdown, reminder, poll, Q&A, shout-out on /live.php.

## Open talk in the Live room (Teams-style)
- Anyone who joins the live room (members and guests) hears the room and can tap **Unmute** to talk, or **Start video** to switch to camera. No host approval is needed. They join with mic and camera off; the browser only asks for access when they tap.
- The host can **Mute** (or **Stop video**) any person from Admin > Live > participants. That person is blocked: their button shows "Muted by host" and they cannot talk until the host taps **Allow to talk** (or **Allow video**). Members who are blocked can tap the button once to ask the host.
- Host-wide controls (Mute all / Disable all cameras / Lock room / End for all) work as before.
- Screen share is still host-only.
- No database change is needed. Rooms already open when this is installed: people who joined and were never touched by the host are treated as allowed.
- Fixed: muting a participant used a LiveKit call that does not exist (RemoveTrack), so the mute could fail and roll back. It now uses MutePublishedTrack and the permission change always applies.
- Fixed: live captions for speakers needed the page to expose the room; the viewer page now does.
- Needs HTTPS (or localhost) for browsers to allow the microphone and camera.

### Test checklist (two browsers or a phone)
1. Host: Admin > Live > Start. Viewer: open /live.php, tap Watch live show. The bar shows Unmute / Start video.
2. Viewer taps Unmute: host hears them with no approval. Tap Start video: host sees them.
3. Host taps Mute on that person: they go silent at once and see "Muted by host". Their Unmute no longer works.
4. Host taps Allow to talk: viewer sees "The host allowed you to talk again", taps Unmute, talks again.
