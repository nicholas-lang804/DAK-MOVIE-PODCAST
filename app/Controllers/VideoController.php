<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Models/MediaAsset.php';
require_once __DIR__ . '/../Models/SiteSetting.php';

class VideoController extends Controller {
    private $mediaModel;

    public function __construct() {
        parent::__construct();
        $this->mediaModel = new MediaAsset();
    }

    public function index() {
        $query = $this->getSearchQuery();
        [$limit, $offset, $page] = $this->getPagination(12);
        $videos = $this->mediaModel->getPublicVideos($limit, $offset, $query, 'video_centre');
        $data = [
            'title' => 'Video Centre',
            'description' => 'Watch the latest THE DAK FILM PODCAST MEDIA videos and entertainment features.',
            'videos' => $videos,
            'searchQuery' => $query,
            'currentPage' => $page,
            'downloadsEnabled' => SiteSetting::downloadsEnabled()
        ];
        $this->render('videos', $data);
    }
}
