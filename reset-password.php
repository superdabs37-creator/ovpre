<?php
require_once __DIR__ . '/includes/bootstrap.php';
$userId = (int) ($_GET['user'] ?? $_POST['user'] ?? 0);
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$success = null;
$stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND reset_expires > NOW() LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
$valid = $user && $user['reset_token'] && password_verify($token, $user['reset_token']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$valid) {
        $error = 'Reset link is invalid or expired.';
    } elseif (strlen((string) $_POST['password']) < 8 || $_POST['password'] !== $_POST['confirm_password']) {
        $error = 'Passwords must match and contain at least 8 characters.';
    } else {
        db()->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')->execute([password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $userId]);
        log_action($userId, 'Password reset completed');
        $success = 'Password reset complete. You can now sign in.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset Password | <?php echo APP_NAME; ?></title><link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261012"><link rel="stylesheet" href="assets/css/style.css"><link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet"></head><body class="login-body"><main class="login-shell"><section class="login-card"><div class="school-mark"><i class="ri-lock-password-line"></i></div><div class="login-copy"><p>Account Recovery</p><h1>Reset Password</h1></div><?php if ($error): ?><div class="notice error"><?php echo h($error); ?></div><?php endif; ?><?php if ($success): ?><div class="notice success"><?php echo h($success); ?></div><a class="portal-link full-btn" href="index.php">Back to Login</a><?php else: ?><form class="login-form" method="post"><?php echo csrf_field(); ?><input type="hidden" name="user" value="<?php echo $userId; ?>"><input type="hidden" name="token" value="<?php echo h($token); ?>"><label>New Password</label><div class="input-control"><i class="ri-lock-line"></i><input type="password" name="password" minlength="8" required></div><label>Confirm Password</label><div class="input-control"><i class="ri-lock-line"></i><input type="password" name="confirm_password" minlength="8" required></div><button class="primary-btn full-btn">Reset Password</button></form><?php endif; ?></section></main></body></html>
