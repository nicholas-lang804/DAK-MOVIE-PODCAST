<?php
require_once __DIR__ . '/../../config/database.php';
class LivePodcast {
    private $pdo; private $hasProfileImageColumn = false;
    public function __construct(){ $this->pdo=getDB(); try { $q=$this->pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image_path'"); $this->hasProfileImageColumn=(bool)$q->fetch(); } catch (Throwable $e) { $this->hasProfileImageColumn=false; } }
    public function getCurrent(){ return $this->pdo->query("SELECT * FROM live_podcasts WHERE status IN ('scheduled','live') ORDER BY status='live' DESC, created_at DESC LIMIT 1")->fetch(); }
    public function getById($id){$s=$this->pdo->prepare("SELECT * FROM live_podcasts WHERE id=?");$s->execute([(int)$id]);return $s->fetch();}
    public function create($d){$room='tmz-live-'.bin2hex(random_bytes(12));$s=$this->pdo->prepare("INSERT INTO live_podcasts(title,description,stream_url,cover_image_path,room_name,started_by,access_type,price,currency,status) VALUES(?,?,?,?,?,?,?,?,?,?)");$s->execute([$d['title'],$d['description'],$d['stream_url'],$d['cover_image_path']??null,$room,$d['started_by']??null,$d['access_type'],$d['price'],$d['currency'],$d['status']]);return $this->pdo->lastInsertId();}
    public function updateShareDetails($id,$title,$description,$coverImagePath=null){$sql="UPDATE live_podcasts SET title=?,description=?";$params=[$title,$description];if($coverImagePath!==null){$sql.=",cover_image_path=?";$params[]=$coverImagePath;}$sql.=" WHERE id=?";$params[]=(int)$id;$s=$this->pdo->prepare($sql);return $s->execute($params);}
    public function setStatus($id,$status){$s=$this->pdo->prepare("UPDATE live_podcasts SET status=?,started_at=CASE WHEN ?='live' THEN NOW() ELSE started_at END,ended_at=CASE WHEN ?='ended' THEN NOW() ELSE ended_at END WHERE id=?");return $s->execute([$status,$status,$status,(int)$id]);}
    public function userCanAccess($userId,$liveId){$s=$this->pdo->prepare("SELECT 1 FROM user_podcast_access WHERE user_id=? AND podcast_id=? UNION SELECT 1 FROM payments WHERE user_id=? AND podcast_id=? AND status='completed' LIMIT 1");$s->execute([(int)$userId,(int)$liveId,(int)$userId,(int)$liveId]);return (bool)$s->fetchColumn();}
    public function participantName($userId){if(!$userId)return 'Guest';$s=$this->pdo->prepare("SELECT username FROM users WHERE id=? LIMIT 1");$s->execute([(int)$userId]);return (string)($s->fetchColumn()?:'Member');}
    public function ensureParticipant($liveId,$userId,$identity,$role,$canChat){$host=$role==='host';$s=$this->pdo->prepare("INSERT INTO live_participant_permissions(live_id,user_id,identity,role,can_publish_microphone,can_publish_camera,can_publish_screen_share,can_chat) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE identity=identity");$s->execute([(int)$liveId,$userId?(int)$userId:null,$identity,$role,1,1,$host?1:0,$canChat?1:0]);return $this->participantByIdentity($liveId,$identity);}
    /** Rooms opened before open-talk existed stored never-touched members with mic and camera off. Treat only those untouched rows as the new default; anything the host changed (updated_at moved) stays as the host set it. */
    public function upgradeLegacyTalkDefaults($liveId,$identity){try{$s=$this->pdo->prepare("UPDATE live_participant_permissions SET can_publish_microphone=1,can_publish_camera=1 WHERE live_id=? AND identity=? AND role<>'host' AND can_publish_microphone=0 AND can_publish_camera=0 AND updated_at=created_at AND removed_at IS NULL AND banned_at IS NULL");$s->execute([(int)$liveId,$identity]);return $s->rowCount()===1;}catch(Throwable $e){error_log('Live talk default upgrade skipped: '.$e->getMessage());return false;}}
    public function participantByIdentity($liveId,$identity){$s=$this->pdo->prepare("SELECT * FROM live_participant_permissions WHERE live_id=? AND identity=? LIMIT 1");$s->execute([(int)$liveId,$identity]);return $s->fetch()?:null;}
    public function participantByUser($liveId,$userId){$s=$this->pdo->prepare("SELECT * FROM live_participant_permissions WHERE live_id=? AND user_id=? AND banned_at IS NOT NULL LIMIT 1");$s->execute([(int)$liveId,(int)$userId]);return $s->fetch()?:null;}
    public function roomControls($liveId){$s=$this->pdo->prepare("INSERT IGNORE INTO live_room_controls(live_id) VALUES(?)");$s->execute([(int)$liveId]);$s=$this->pdo->prepare("SELECT * FROM live_room_controls WHERE live_id=?");$s->execute([(int)$liveId]);return $s->fetch()?:['room_locked'=>0,'mute_all'=>0,'disable_cameras'=>0];}
    public function setRoomControl($liveId,$name,$value){$allowed=['room_locked','mute_all','disable_cameras'];if(!in_array($name,$allowed,true))throw new InvalidArgumentException('Unsupported live-room control.');$this->roomControls($liveId);$s=$this->pdo->prepare("UPDATE live_room_controls SET {$name}=? WHERE live_id=?");return $s->execute([$value?1:0,(int)$liveId]);}
    public function updateParticipantPermission($liveId,$identity,$field,$value){$allowed=['role','can_publish_microphone','can_publish_camera','can_publish_screen_share','can_chat'];if(!in_array($field,$allowed,true))throw new InvalidArgumentException('Unsupported participant permission.');$s=$this->pdo->prepare("UPDATE live_participant_permissions SET {$field}=? WHERE live_id=? AND identity=? AND banned_at IS NULL");return $s->execute([$field==='role'?$value:($value?1:0),(int)$liveId,$identity]);}
    public function markParticipantRemoved($liveId,$identity,$banned=false){$column=$banned?'banned_at':'removed_at';$s=$this->pdo->prepare("UPDATE live_participant_permissions SET {$column}=NOW() WHERE live_id=? AND identity=?");return $s->execute([(int)$liveId,$identity]);}
    public function permissionRows($liveId){$s=$this->pdo->prepare("SELECT * FROM live_participant_permissions WHERE live_id=? ORDER BY created_at ASC");$s->execute([(int)$liveId]);return $s->fetchAll();}
    public function logPermissionAudit($liveId,$adminId,$identity,$action,$before,$after){$s=$this->pdo->prepare("INSERT INTO live_permission_audit(live_id,actor_admin_id,participant_identity,action,before_state,after_state) VALUES(?,?,?,?,?,?)");return $s->execute([(int)$liveId,$adminId===null?null:(int)$adminId,$identity,$action,$before===null?null:json_encode($before),$after===null?null:json_encode($after)]);}
    public function comments($id,$all=false){$avatar=$this->hasProfileImageColumn?'u.profile_image_path':'NULL AS profile_image_path';$sql="SELECT lc.*,u.username,$avatar FROM live_comments lc JOIN users u ON u.id=lc.user_id WHERE live_id=?".($all?'':" AND lc.status='visible'")." ORDER BY lc.created_at ASC LIMIT 200";$s=$this->pdo->prepare($sql);$s->execute([(int)$id]);return $s->fetchAll();}
    public function deleteAllComments($liveId){$s=$this->pdo->prepare("DELETE FROM live_comments WHERE live_id=?");$s->execute([(int)$liveId]);return $s->rowCount();}
    public function addComment($live,$user,$comment,$parentId=null){$s=$this->pdo->prepare("INSERT INTO live_comments(live_id,user_id,comment,parent_id) VALUES(?,?,?,?)");return $s->execute([(int)$live,(int)$user,trim($comment),$parentId?(int)$parentId:null]);}
    public function updateComment($id,$status){$s=$this->pdo->prepare("UPDATE live_comments SET status=? WHERE id=?");return $s->execute([$status,(int)$id]);}
    public function react($live,$user,$type){$s=$this->pdo->prepare("INSERT IGNORE INTO live_reactions(live_id,user_id,reaction_type) VALUES(?,?,?)");return $s->execute([(int)$live,(int)$user,$type]);}
    public function requestSpeaker($live,$user,$type,$identity=null){$s=$this->pdo->prepare("INSERT INTO live_speaker_requests(live_id,user_id,participant_identity,request_type) VALUES(?,?,?,?)");return $s->execute([(int)$live,(int)$user,$identity,$type]);}
    public function requests($id){$s=$this->pdo->prepare("SELECT r.*,u.username FROM live_speaker_requests r JOIN users u ON u.id=r.user_id WHERE live_id=? ORDER BY r.created_at DESC");$s->execute([(int)$id]);return $s->fetchAll();}
    public function deleteAllSpeakerRequests($liveId){$s=$this->pdo->prepare("DELETE FROM live_speaker_requests WHERE live_id=?");$s->execute([(int)$liveId]);return $s->rowCount();}
    public function speakerRequest($liveId,$requestId){$s=$this->pdo->prepare("SELECT r.*,u.username FROM live_speaker_requests r JOIN users u ON u.id=r.user_id WHERE r.live_id=? AND r.id=? LIMIT 1");$s->execute([(int)$liveId,(int)$requestId]);return $s->fetch()?:null;}
    public function userSpeakerInvitations($liveId,$userId,$identity){$s=$this->pdo->prepare("SELECT id,request_type,created_at FROM live_speaker_requests WHERE live_id=? AND user_id=? AND participant_identity=? AND status='invited' ORDER BY created_at ASC");$s->execute([(int)$liveId,(int)$userId,$identity]);return $s->fetchAll();}
    public function updateSpeakerRequest($liveId,$requestId,$status,$expectedStatus=null){$allowed=['pending','invited','approved','denied','accepted','declined','ended'];if(!in_array($status,$allowed,true))throw new InvalidArgumentException('Unsupported speaker request status.');$sql="UPDATE live_speaker_requests SET status=? WHERE live_id=? AND id=?";$params=[$status,(int)$liveId,(int)$requestId];if($expectedStatus!==null){$sql.=" AND status=?";$params[]=$expectedStatus;}$s=$this->pdo->prepare($sql);$s->execute($params);return $s->rowCount()===1;}
    public function updateRequest($id,$status){$s=$this->pdo->prepare("UPDATE live_speaker_requests SET status=? WHERE id=?");return $s->execute([$status,(int)$id]);}
    public function participantForUser($liveId,$userId){$s=$this->pdo->prepare("SELECT * FROM live_participant_permissions WHERE live_id=? AND user_id=? AND banned_at IS NULL AND removed_at IS NULL ORDER BY updated_at DESC,id DESC LIMIT 1");$s->execute([(int)$liveId,(int)$userId]);return $s->fetch()?:null;}
    public function addTranscriptLine($liveId,$identity,$speakerName,$text,$language,$startTimeMs){$s=$this->pdo->prepare("INSERT INTO live_transcript_lines(live_id,speaker_identity,speaker_name,caption_text,language,start_time_ms) VALUES(?,?,?,?,?,?)");$s->execute([(int)$liveId,$identity,$speakerName,$text,$language,(int)$startTimeMs]);return (int)$this->pdo->lastInsertId();}
    public function transcriptLines($liveId,$search='',$limit=100){$limit=max(1,min(20000,(int)$limit));$sql="SELECT id,speaker_identity,speaker_name,caption_text,language,start_time_ms,corrected_by,created_at FROM live_transcript_lines WHERE live_id=?";$params=[(int)$liveId];if($search!==''){$sql.=" AND caption_text LIKE ? ESCAPE '\\\\'";$escaped=addcslashes($search,'\\%_');$params[]='%'.$escaped.'%';}$sql.=" ORDER BY start_time_ms ASC,id ASC LIMIT ".$limit;$s=$this->pdo->prepare($sql);$s->execute($params);return $s->fetchAll();}
    public function correctTranscriptLine($liveId,$lineId,$text,$adminId){$s=$this->pdo->prepare("SELECT caption_text FROM live_transcript_lines WHERE live_id=? AND id=? LIMIT 1");$s->execute([(int)$liveId,(int)$lineId]);$current=$s->fetchColumn();if($current===false)return false;if($current===$text)return true;$s=$this->pdo->prepare("UPDATE live_transcript_lines SET caption_text=?,corrected_by=? WHERE live_id=? AND id=?");$s->execute([$text,(int)$adminId,(int)$liveId,(int)$lineId]);return $s->rowCount()===1;}
    public function createRecording($liveId,$egressId,$mediaType,$format,$objectKey,$adminId){$s=$this->pdo->prepare("INSERT INTO live_recordings(live_id,egress_id,media_type,format,object_key,status,created_by_admin_id) VALUES(?,?,?,?,?,'starting',?)");$s->execute([(int)$liveId,$egressId,$mediaType,$format,$objectKey,(int)$adminId]);return (int)$this->pdo->lastInsertId();}
    public function recordingByEgressId($liveId,$egressId){$s=$this->pdo->prepare("SELECT * FROM live_recordings WHERE live_id=? AND egress_id=? LIMIT 1");$s->execute([(int)$liveId,$egressId]);return $s->fetch()?:null;}
    public function recordingById($recordingId){$s=$this->pdo->prepare("SELECT * FROM live_recordings WHERE id=? LIMIT 1");$s->execute([(int)$recordingId]);return $s->fetch()?:null;}
    public function deleteRecording($liveId,$recordingId){$s=$this->pdo->prepare("DELETE FROM live_recordings WHERE live_id=? AND id=?");$s->execute([(int)$liveId,(int)$recordingId]);return $s->rowCount()===1;}
    public function updateRecordingFromEgress($liveId,array $egress){
        $egressId=(string)($egress['egressId']??'');
        if($egressId==='')return false;
        $statusMap=[0=>'starting',1=>'active',2=>'ending',3=>'complete',4=>'failed',5=>'aborted',6=>'failed','EGRESS_STARTING'=>'starting','EGRESS_ACTIVE'=>'active','EGRESS_ENDING'=>'ending','EGRESS_COMPLETE'=>'complete','EGRESS_FAILED'=>'failed','EGRESS_ABORTED'=>'aborted','EGRESS_LIMIT_REACHED'=>'failed'];
        $statusValue=$egress['status']??null;
        $status=$statusValue!==null&&array_key_exists($statusValue,$statusMap)?$statusMap[$statusValue]:null;
        if($statusValue!==null&&$status===null)error_log('Unrecognized LiveKit Egress status: '.(string)$statusValue);
        $file=$egress['fileResults'][0]??[];
        $location=(string)($file['location']??'');
        $objectKey='';
        if(strpos($location,'s3://')===0){
            $parsed=parse_url($location);
            $objectKey=ltrim((string)($parsed['path']??''),'/');
        }
        $startedAt=null;
        $endedAt=null;
        if(!empty($egress['startedAt'])){
            $timestamp=(int)$egress['startedAt'];
            if($timestamp>1000000000000000)$timestamp=intdiv($timestamp,1000000000);
            $startedAt=gmdate('Y-m-d H:i:s',$timestamp);
        }
        if(!empty($egress['endedAt'])){
            $timestamp=(int)$egress['endedAt'];
            if($timestamp>1000000000000000)$timestamp=intdiv($timestamp,1000000000);
            $endedAt=gmdate('Y-m-d H:i:s',$timestamp);
        }
        $duration=max(0,(int)($file['duration']??0));
        if($duration>100000000)$duration=intdiv($duration,1000000);
        $size=max(0,(int)($file['size']??0));
        $sql="UPDATE live_recordings SET status=COALESCE(?,status),duration_ms=GREATEST(duration_ms,?),file_size_bytes=GREATEST(file_size_bytes,?),started_at=COALESCE(started_at,?),ended_at=COALESCE(?,ended_at),object_key=CASE WHEN ?<>'' THEN ? ELSE object_key END WHERE live_id=? AND egress_id=?";
        $s=$this->pdo->prepare($sql);
        return $s->execute([$status,$duration,$size,$startedAt,$endedAt,$objectKey,$objectKey,(int)$liveId,$egressId]);
    }
    public function liveRecordings($liveId){$s=$this->pdo->prepare("SELECT id,live_id,media_type,format,status,duration_ms,file_size_bytes,started_at,ended_at,created_at FROM live_recordings WHERE live_id=? ORDER BY created_at DESC LIMIT 100");$s->execute([(int)$liveId]);return $s->fetchAll();}
    public function activeRecordingForLive($liveId){$s=$this->pdo->prepare("SELECT * FROM live_recordings WHERE live_id=? AND status IN ('starting','active','ending') ORDER BY id DESC LIMIT 1");$s->execute([(int)$liveId]);return $s->fetch()?:null;}
    public function claimRecordingStatusSync($liveId){$s=$this->pdo->prepare("UPDATE live_recordings SET last_synced_at=NOW() WHERE live_id=? AND status IN ('starting','active','ending') AND (last_synced_at IS NULL OR last_synced_at<DATE_SUB(NOW(),INTERVAL 10 SECOND)) LIMIT 1");$s->execute([(int)$liveId]);return $s->rowCount()===1;}
    public function transcriptLinesInRange($liveId,$fromMs,$toMs,$limit=20000){$limit=max(1,min(20000,(int)$limit));$s=$this->pdo->prepare("SELECT id,speaker_identity,speaker_name,caption_text,language,start_time_ms,corrected_by,created_at FROM live_transcript_lines WHERE live_id=? AND start_time_ms>=? AND start_time_ms<=? ORDER BY start_time_ms ASC,id ASC LIMIT ".$limit);$s->execute([(int)$liveId,(int)$fromMs,(int)$toMs]);return $s->fetchAll();}
    public function pastLives($limit=50){$limit=max(1,min(200,(int)$limit));$s=$this->pdo->query("SELECT id,title,status,started_at,ended_at,viewer_peak,cover_image_path FROM live_podcasts WHERE status='ended' ORDER BY ended_at DESC,id DESC LIMIT $limit");return $s->fetchAll();}
    /** Permanently delete a finished live show and every row it accumulated. Refuses while the show is live. */
    public function deleteLive($id){
        $id=(int)$id; $live=$this->getById($id); if(!$live) return false;
        if(($live['status']??'')==='live') return false;
        $this->pdo->beginTransaction();
        try{
            $simple=['live_comments','live_reactions','live_speaker_requests','live_participant_permissions','live_room_controls','live_permission_audit','live_transcript_lines','live_recordings','live_reminders','live_stage_state','live_presence','live_reaction_events'];
            foreach($simple as $t){ try{$this->pdo->prepare("DELETE FROM $t WHERE live_id=?")->execute([$id]);}catch(Throwable $e){} }
            try{$this->pdo->exec("DELETE v FROM live_poll_votes v JOIN live_polls p ON p.id=v.poll_id WHERE p.live_id=".$id);}catch(Throwable $e){}
            try{$this->pdo->exec("DELETE o FROM live_poll_options o JOIN live_polls p ON p.id=o.poll_id WHERE p.live_id=".$id);}catch(Throwable $e){}
            try{$this->pdo->prepare("DELETE FROM live_polls WHERE live_id=?")->execute([$id]);}catch(Throwable $e){}
            try{$this->pdo->exec("DELETE qv FROM live_question_votes qv JOIN live_questions q ON q.id=qv.question_id WHERE q.live_id=".$id);}catch(Throwable $e){}
            try{$this->pdo->prepare("DELETE FROM live_questions WHERE live_id=?")->execute([$id]);}catch(Throwable $e){}
            $this->pdo->prepare("DELETE FROM live_podcasts WHERE id=?")->execute([$id]);
            $this->pdo->commit(); return true;
        }catch(Throwable $e){ if($this->pdo->inTransaction())$this->pdo->rollBack(); error_log('Live delete failed: '.$e->getMessage()); return false; }
    }
}
