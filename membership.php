<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Models/SubscriptionPlan.php';
require_once __DIR__ . '/app/Models/Payment.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Services/PesapalGateway.php';
if (!isLoggedIn()) { header('Location: /login.php'); exit; }
$plans=(new SubscriptionPlan())->getActive(); $message=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $plan=(new SubscriptionPlan())->findById((int)($_POST['plan_id']??0));
    if ($plan) {
        $payment=(new Payment())->createPendingSubscriptionPayment($_SESSION['user_id'],$plan['id'],$plan['price'],$plan['currency']);
        try { $result=(new PesapalGateway())->submitOrder($payment,$plan,(new User())->findById($_SESSION['user_id'])); if(!empty($result['success'])){(new Payment())->setGatewayReference($payment['id'],$result['tracking_id']??'');header('Location: '.$result['redirect_url']);exit;} $message=$result['message']; } catch(Throwable $e){$message='Pesapal could not be reached. Please contact support.';}
    } else $message='Please choose a valid subscription plan.';
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>All Access Membership</title><link rel="stylesheet" href="/assets/css/style.css"></head><body><main class="membership-page"><div class="membership-heading"><span class="eyebrow">TMZ PODCAST MEMBERSHIP</span><h1>Unlock the whole screening room</h1><p>One subscription opens paid movies and premium releases for a fixed period. There is no lifetime plan.</p></div><?php if($message): ?><div class="alert alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><div class="membership-grid"><?php foreach($plans as $plan): ?><article class="membership-card <?php echo $plan['plan_key']==='yearly'?'featured':''; ?>"><span class="membership-duration"><?php echo htmlspecialchars($plan['name']); ?></span><h2><?php echo htmlspecialchars($plan['currency']); ?> <?php echo number_format((float)$plan['price'],2); ?></h2><p><?php echo (int)$plan['duration_months']; ?> month<?php echo (int)$plan['duration_months']===1?'':'s'; ?> of all-access viewing and downloads</p><?php if((float)$plan['discount_percent']>0): ?><strong class="membership-discount"><?php echo rtrim(rtrim(number_format((float)$plan['discount_percent'],2), '0'), '.'); ?>% longer-term saving</strong><?php endif; ?><form method="post"><input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>"><button class="btn btn-primary" type="submit">Choose plan</button></form></article><?php endforeach; ?></div><a class="btn btn-outline" href="/dashboard.php">Back to dashboard</a></main></body></html>
