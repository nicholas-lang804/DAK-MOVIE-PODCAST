<?php
/**
 * MusicController: the Music Zone (browse, search, artist, album, genre, track, videos, library, playlists).
 * Listening happens through the persistent player; media itself is served by music-stream.php.
 */

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Models/Music.php';
require_once __DIR__ . '/../Models/Payment.php';

class MusicController extends Controller {
    private $music;
    private $viewer = null;

    public function __construct() {
        parent::__construct();
        $this->music = new Music();
    }

    private function viewer() {
        if ($this->viewer === null) {
            $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
            $sub = false;
            if ($uid) { try { $sub = (new Payment())->hasActiveSubscription($uid); } catch (Throwable $e) { $sub = false; } }
            $this->viewer = ['user_id' => $uid, 'sub' => $sub, 'admin' => function_exists('isAdmin') && isAdmin()];
        }
        return $this->viewer;
    }

    /** Export rows for the player with one query for "liked" state. */
    private function exportList(array $rows) {
        $v = $this->viewer();
        $liked = $v['user_id'] ? $this->music->likedIds($v['user_id'], array_map(function ($r) { return (int)$r['id']; }, $rows)) : [];
        return array_map(function ($r) use ($v, $liked) { return $this->music->export($r, $v, $liked); }, $rows);
    }

    public function index() {
        $v = $this->viewer();
        $data = [
            'musicView' => 'home',
            'title' => 'Music Zone',
            'description' => 'Listen, watch music videos and download tracks from African and global artists.',
            'canLibrary' => (bool)$v['user_id'],
            'genreList' => $this->music->genres(),
            'search' => trim($_GET['q'] ?? ''),
        ];

        if (!empty($_GET['track'])) {
            $row = $this->music->trackBySlug((string)$_GET['track']);
            if (!$row) return $this->missing();
            $tracks = $this->exportList([$row]);
            $more = $this->exportList(array_values(array_filter($this->music->byArtist($row['artist_id'], 12), function ($r) use ($row) { return (int)$r['id'] !== (int)$row['id']; })));
            return $this->render('music', array_merge($data, [
                'musicView' => 'track', 'title' => $row['title'] . ' · ' . $row['artist_name'],
                'description' => 'Listen to ' . $row['title'] . ' by ' . $row['artist_name'] . ' on THE DAK FILM PODCAST MEDIA.',
                'track' => $tracks[0], 'trackRow' => $row, 'moreTracks' => $more,
            ]));
        }
        if (!empty($_GET['artist'])) {
            $artist = $this->music->artistBySlug((string)$_GET['artist']);
            if (!$artist) return $this->missing();
            $tracks = $this->exportList($this->music->byArtist($artist['id'], 100));
            return $this->render('music', array_merge($data, [
                'musicView' => 'artist', 'title' => $artist['name'], 'description' => 'Songs and videos by ' . $artist['name'] . '.',
                'artist' => $artist, 'tracks' => $tracks, 'albums' => $this->music->newestAlbums(20, $artist['id']),
            ]));
        }
        if (!empty($_GET['album'])) {
            $album = $this->music->albumBySlug((string)$_GET['album']);
            if (!$album) return $this->missing();
            return $this->render('music', array_merge($data, [
                'musicView' => 'album', 'title' => $album['title'] . ' · ' . $album['artist_name'], 'description' => 'Album by ' . $album['artist_name'] . '.',
                'album' => $album, 'tracks' => $this->exportList($this->music->byAlbum($album['id'], 100)),
            ]));
        }
        if (!empty($_GET['genre'])) {
            $genre = trim((string)$_GET['genre']);
            return $this->render('music', array_merge($data, [
                'musicView' => 'genre', 'title' => $genre . ' music', 'genre' => $genre,
                'tracks' => $this->exportList($this->music->byGenre($genre, 100)),
            ]));
        }
        if (($_GET['view'] ?? '') === 'videos') {
            return $this->render('music', array_merge($data, [
                'musicView' => 'videos', 'title' => 'Music videos',
                'tracks' => $this->exportList($this->music->videos(60)),
            ]));
        }
        if ($data['search'] !== '') {
            return $this->render('music', array_merge($data, [
                'musicView' => 'search', 'title' => 'Search: ' . $data['search'],
                'tracks' => $this->exportList($this->music->search($data['search'], 60)),
            ]));
        }
        if (!empty($_GET['playlist'])) {
            if (!$v['user_id']) $this->redirect('/TMZ%20PODCAST/login.php');
            $pl = $this->music->playlist((int)$_GET['playlist'], $v['user_id']);
            if (!$pl) return $this->missing();
            return $this->render('music', array_merge($data, [
                'musicView' => 'playlist', 'title' => $pl['name'], 'playlist' => $pl,
                'tracks' => $this->exportList($this->music->playlistTracks($pl['id'])),
            ]));
        }
        if (($_GET['view'] ?? '') === 'library') {
            if (!$v['user_id']) $this->redirect('/TMZ%20PODCAST/login.php');
            return $this->render('music', array_merge($data, [
                'musicView' => 'library', 'title' => 'Your Library',
                'playlists' => $this->music->playlists($v['user_id']),
                'likedTracks' => $this->exportList($this->music->likedTracks($v['user_id'], 200)),
                'recentTracks' => $this->exportList($this->music->history($v['user_id'], 20)),
            ]));
        }

        // Home
        $featured = $this->music->featured(1);
        $trending = $this->music->trending(10);
        $recent = $v['user_id'] ? $this->music->history($v['user_id'], 10) : [];
        return $this->render('music', array_merge($data, [
            'featured' => $featured ? $this->exportList($featured)[0] : null,
            'trending' => $this->exportList($trending),
            'newTracks' => $this->exportList($this->music->newest(12)),
            'videoTracks' => $this->exportList($this->music->videos(8)),
            'recentTracks' => $this->exportList($recent),
            'artists' => $this->music->topArtists(12),
            'albums' => $this->music->newestAlbums(12),
        ]));
    }

    private function missing() {
        http_response_code(404);
        $this->render('404', ['title' => 'Not found']);
    }
}
?>
