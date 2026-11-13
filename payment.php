<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/Movie.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Models/SiteSetting.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Services/PesapalGateway.php';

if (!isLoggedIn()) { header('Location: /TMZ%20PODCAST/login.php'); exit; }
$movie = (new Movie())->findById((int)($_GET['movie_id'] ?? $_POST['movie_id'] ?? 0));
if (!$movie || $movie['access_type'] !== 'paid') { header('Location: /TMZ%20PODCAST/movies.php'); exit; }
$payment = null;
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment = (new Payment())->createPendingMoviePayment($_SESSION['user_id'], $movie['id'], $movie['price'], $movie['price_currency']);
    try {
        $gatewayResult = (new PesapalGateway())->submitOrder($payment, $movie, (new User())->findById($_SESSION['user_id']));
        if (!empty($gatewayResult['success'])) {
            (new Payment())->setGatewayReference($payment['id'], $gatewayResult['tracking_id'] ?? '');
            header('Location: ' . $gatewayResult['redirect_url']); exit;
        }
        $message = $gatewayResult['message'];
    } catch (Throwable $e) {
        $message = 'Pesapal could not be reached: ' . $e->getMessage();
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment - THE DAK FILM PODCAST MEDIA</title><link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/style.css"></head><body><main class="payment-page"><div class="payment-card"><span class="eyebrow">SECURE SCREENING ACCESS</span><h1>Unlock <?php echo htmlspecialchars($movie['title']); ?></h1><p>Pay once to watch and download this movie.</p><div class="payment-total"><?php echo htmlspecialchars($movie['price_currency']); ?> <?php echo number_format((float)$movie['price'], 2); ?></div><?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><p class="form-help">Payment reference: <?php echo htmlspecialchars($payment['reference']); ?></p><?php else: ?><form method="post"><input type="hidden" name="movie_id" value="<?php echo (int)$movie['id']; ?>"><button class="btn btn-primary" type="submit">Proceed with Pesapal</button></form><?php endif; ?><a class="btn btn-outline" href="/TMZ%20PODCAST/movies.php?slug=<?php echo urlencode($movie['slug']); ?>">Back to movie</a></div></main></body></html>
