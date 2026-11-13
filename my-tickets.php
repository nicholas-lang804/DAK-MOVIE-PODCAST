<?php
/** A logged-in customer's purchased tickets. */
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/app/Models/Event.php';

tmzRequireCustomerLogin();
$tickets = (new Event())->getUserTickets($_SESSION['user_id']);

ob_start();
include __DIR__ . '/resources/views/partials/tickets-list.php';
$content = '<section class="user-dashboard"><div class="section-header"><div><span class="member-eyebrow">THE DAK FILM PODCAST MEDIA</span><h1>My Tickets</h1></div><a href="/events.php" class="btn btn-primary">Browse events</a></div>'
    . (isset($_GET['new']) ? '<div class="alert alert-success">Your ticket is ready!</div>' : '')
    . ob_get_clean() . '</section>';
tmzRenderPage('My Tickets', $content);
