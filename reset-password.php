<?php
require_once __DIR__ . '/includes/bootstrap.php';
$error = null;
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token = trim((string) ($_POST['access_token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($token === '' || strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Use a valid reset link and a password with at least 12 characters, uppercase and lowercase letters, a number, and a symbol.';
    } else {
        try {
            $authUser = supabase_auth_user($token);
            $stmt = db()->prepare('SELECT id FROM users WHERE auth_user_id = ? AND email = ? AND status = "Active" AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([(string) ($authUser['id'] ?? ''), (string) ($authUser['email'] ?? '')]);
            $userId = (int) ($stmt->fetchColumn() ?: 0);
            if ($userId <= 0) {
                throw new RuntimeException('No active profile is linked to the recovery request.');
            }
            supabase_auth_set_password($token, $password);
            log_action($userId, 'Password reset completed with Supabase Auth');
            $done = true;
        } catch (Throwable $exception) {
            error_log('Password reset failed: ' . $exception->getMessage());
            $error = 'The reset could not be completed. The link may have expired; request another reset email.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset Password | I-Record</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="login-body"><main class="login-shell"><section class="login-card"><h1>Reset Password</h1>
<?php if ($done): ?><p class="notice success">Your password was updated.</p><a class="primary-btn full-btn" href="index.php">Go to Sign in</a><?php else: ?><?php if ($error): ?><p class="notice error"><?php echo h($error); ?></p><?php endif; ?><form class="login-form" method="post" id="resetForm"><?php echo csrf_field(); ?><input type="hidden" name="access_token" id="accessToken"><label for="password">New password</label><div class="input-control"><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div><button class="primary-btn full-btn" type="submit">Update Password</button></form><p id="resetError" class="notice error" hidden>Open the secure reset link from your email.</p><script>const token=new URLSearchParams(location.hash.slice(1)).get('access_token');if(token){document.getElementById('accessToken').value=token;history.replaceState(null,'',location.pathname);}else{document.getElementById('resetError').hidden=false;document.querySelector('#resetForm button').disabled=true;}</script><?php endif; ?></section></main></body></html>
