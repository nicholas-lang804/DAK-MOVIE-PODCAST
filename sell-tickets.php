<?php
/**
 * "Sale Tickets With Us" — organiser entry point.
 * Guests are sent to login/register and returned to the event creation form.
 */
session_start();
require_once __DIR__ . '/config/database.php';

$target = '/TMZ%20PODCAST/user/dashboard.php?tab=event-create';

if (empty($_SESSION['user_id'])) {
    $_SESSION['after_login'] = $target;
    header('Location: /TMZ%20PODCAST/login.php');
    exit;
}
header('Location: ' . $target);
exit;
