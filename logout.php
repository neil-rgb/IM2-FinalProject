<?php
require_once __DIR__ . '/includes/auth.php';

if (!is_post_request()) {
    redirect('dashboard.php');
}

if (!verify_csrf_token(isset($_POST['_token']) && is_string($_POST['_token']) ? $_POST['_token'] : null)) {
    http_response_code(403);
    exit('Invalid logout request. Please return to the dashboard and try again.');
}

logout_user();
redirect('login.php?notice=signed-out');
