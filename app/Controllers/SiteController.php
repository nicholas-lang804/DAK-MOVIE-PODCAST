<?php
/**
 * SiteController: search, member watchlist ("My List") and legal pages.
 */

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Models/SiteExtras.php';

class SiteController extends Controller {
    public function search() {
        $q = $this->getSearchQuery();
        $results = (new SiteExtras())->search($q, 8);
        $total = 0;
        foreach ($results as $group) $total += count($group);
        $this->render('search', [
            'title' => $q !== '' ? 'Search: ' . $q : 'Search',
            'description' => 'Search movies, actors and actresses, episodes, news and live shows.',
            'query' => $q,
            'results' => $results,
            'resultCount' => $total
        ]);
    }

    public function myList() {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login.php');
        }
        $this->render('my-list', [
            'title' => 'My List',
            'listMovies' => (new SiteExtras())->watchlist((int)$_SESSION['user_id'])
        ]);
    }

    public function legal($doc) {
        $titles = ['privacy' => 'Privacy Policy', 'terms' => 'Terms of Use', 'cookies' => 'Cookie Notice'];
        if (!isset($titles[$doc])) {
            http_response_code(404);
            $this->render('404', ['title' => 'Page Not Found']);
            return;
        }
        $this->render('legal', [
            'title' => $titles[$doc],
            'description' => $titles[$doc] . ' for THE DAK FILM PODCAST MEDIA.',
            'legalDoc' => $doc,
            'legalTitle' => $titles[$doc]
        ]);
    }
}
?>
