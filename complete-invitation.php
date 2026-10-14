<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token = trim((string) ($_POST['access_token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($token === '' || strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Use a valid invitation link and a password with at least 12 characters, uppercase and lowercase letters, a number, and a symbol.';
    } else {
        try {
            supabase_auth_set_password($token, $password);
            $authUser = supabase_auth_user($token);
            $stmt = db()->prepare('UPDATE users SET status = "Pending Approval", updated_at = NOW() WHERE auth_user_id = ? AND LOWER(email) = LOWER(?) AND status = "Pending Invitation"');
            $stmt->execute([(string) ($authUser['id'] ?? ''), (string) ($authUser['email'] ?? '')]);
            $profileStmt = db()->prepare('SELECT id FROM users WHERE auth_user_id = ? AND LOWER(email) = LOWER(?) AND status IN ("Pending Approval", "Active", "Inactive") AND deleted_at IS NULL LIMIT 1');
            $profileStmt->execute([(string) ($authUser['id'] ?? ''), (string) ($authUser['email'] ?? '')]);
            if (!$profileStmt->fetchColumn()) {
                throw new RuntimeException('No matching invited I-Record profile was found. Ask the administrator to verify the invitation.');
            }
            $done = true;
        } catch (Throwable $exception) {
            error_log('Invitation completion failed: ' . $exception->getMessage());
            $error = 'The invitation could not be completed. The link may have expired; ask the administrator to send a new one.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Complete Invitation | I-Record</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="login-body"><main class="login-shell"><section class="login-card"><h1>Complete your invitation</h1>
<?php if ($done): ?><p>Your password is set. Your profile is now awaiting administrator approval.</p><a class="primary-btn full-btn" href="index.php">Go to Sign in</a>
<?php else: ?><?php if ($error): ?><div class="notice error"><?php echo h($error); ?></div><?php endif; ?><p>Choose a password for your I-Record account.</p><form method="post" id="inviteForm"><?php echo csrf_field(); ?><input type="hidden" name="access_token" id="accessToken"><label class="field"><span>New password</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label><button class="primary-btn full-btn" type="submit">Set Password</button></form><p id="inviteError" class="notice error" hidden>This link is missing its invitation token. Open the complete link from your email.</p><script>const token=new URLSearchParams(location.hash.slice(1)).get('access_token');if(token){document.getElementById('accessToken').value=token;history.replaceState(null,'',location.pathname);}else{document.getElementById('inviteError').hidden=false;document.querySelector('#inviteForm button').disabled=true;}</script><?php endif; ?></section></main></body></html>
