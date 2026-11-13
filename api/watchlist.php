<?php
/** Toggle a movie in the signed-in member's watchlist. POST: movie_id, csrf_token. */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Middleware/Auth.php';
require_once __DIR__ . '/../app/Middleware/CSRF.php';
require_once __DIR__ . '/../app/Models/SiteExtras.php';

function wl_out(array $data, $status = 200) { http_response_code($status); echo json_encode($data); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') wl_out(['error' => 'POST required.'], 405);
if (empty($_SESSION['user_id'])) wl_out(['error' => 'Sign in to save movies to your list.', 'login' => true], 401);
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CSRF::validateToken((string)$token)) wl_out(['error' => 'Security token expired. Refresh the page.'], 403);

$movieId = filter_var($_POST['movie_id'] ?? null, FILTER_VALIDATE_INT);
if (!$movieId) wl_out(['error' => 'Missing movie.'], 400);

$now = (new SiteExtras())->toggleWatchlist((int)$_SESSION['user_id'], $movieId);
if ($now === null) wl_out(['error' => 'Movie not found.'], 404);
wl_out(['ok' => true, 'saved' => $now]);
