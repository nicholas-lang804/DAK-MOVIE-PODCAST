<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/livekit.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isAdmin()) { http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Administrator login required.']); exit; }
try {
    $config = livekitConfig();
    if (!$config['url'] || !$config['api_key'] || !$config['api_secret']) throw new RuntimeException('Enter the LiveKit URL, API key and API secret first.');
    $jwt = Firebase\JWT\JWT::encode(['iss'=>$config['api_key'],'sub'=>'tmz-settings-test','nbf'=>time()-5,'exp'=>time()+60,'video'=>['roomList'=>true]], $config['api_secret'], 'HS256');
    $endpoint = preg_replace('/^wss:/', 'https:', $config['url']) . '/twirp/livekit.RoomService/ListRooms';
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>'{}', CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$jwt,'Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
    $body = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
    if ($curlError) throw new RuntimeException('Could not reach LiveKit: '.$curlError);
    if ($http < 200 || $http >= 300) throw new RuntimeException('LiveKit rejected the credentials (HTTP '.$http.'). Check that the URL, API key and API secret belong to the same LiveKit project.');
    echo json_encode(['ok'=>true,'message'=>'LiveKit connection verified.']);
} catch (Throwable $e) { http_response_code(400); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); }
