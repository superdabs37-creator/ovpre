<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();
if ($user) {
    log_action((int) $user['id'], 'Logout');
}
if (!empty($_SESSION['supabase_access_token'])) {
    supabase_auth_logout((string) $_SESSION['supabase_access_token']);
}
$_SESSION = [];
session_destroy();
redirect_to('index.php');
