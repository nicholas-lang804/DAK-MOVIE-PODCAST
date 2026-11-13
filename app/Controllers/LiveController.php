<?php
require_once __DIR__ . '/Controller.php'; require_once __DIR__ . '/../Models/LivePodcast.php'; require_once __DIR__ . '/../Models/Payment.php'; require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Helpers/PublicUrl.php';
class LiveController extends Controller {
 public function show(){
  $model=new LivePodcast(); $requestedId=filter_var($_GET['live_id']??null,FILTER_VALIDATE_INT); $live=$requestedId?$model->getById($requestedId):$model->getCurrent();
  if($live && !in_array($live['status'],['scheduled','live','ended'],true)) $live=null;
  $userId=$_SESSION['user_id']??0;
  $canJoin = false;
  if ($live) {
   $canJoin = $live['access_type']==='free' || ($userId && (
    $model->userCanAccess($userId, $live['id']) ||
    (new Payment())->hasActiveSubscription($userId)
   ));
  }
  $chatUser=$userId?(new User())->findById($userId):null;
  $data=['title'=>$live?($live['title'].' — Live Podcast'):'Live Podcast','description'=>$live?trim((string)($live['description']??'')):'Watch live shows from THE DAK FILM PODCAST MEDIA.','live'=>$live,'canJoin'=>$canJoin,'canChat'=>(bool)$userId && $live && $live['status']==='live','chatUser'=>$chatUser,'comments'=>$live?$model->comments($live['id']):[]];
  if($live){
   $shareUrl=TmzPublicUrl::to('/live.php?live_id='.(int)$live['id']);
   $imagePath=ltrim((string)($live['cover_image_path']??''),'/');
   $data['socialShare']=['title'=>(string)$live['title'],'description'=>trim((string)($live['description']??''))?:'Watch this live show from THE DAK FILM PODCAST MEDIA.','url'=>$shareUrl,'image'=>$imagePath!==''?TmzPublicUrl::to('/'.$imagePath):''];
  }
  $this->render('live',$data);
 }
}
