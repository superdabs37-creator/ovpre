<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();
if ($user) {
    log_action((int) $user['id'], 'Logout');
}
$_SESSION = [];
session_destroy();
redirect_to('index.php');
