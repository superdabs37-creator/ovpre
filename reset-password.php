<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$authorization = $_SESSION['password_reset_authorization'] ?? null;
$userId = (int) ($authorization['user_id'] ?? 0);
$error = null;
$success = null;
$user = null;

if ($userId > 0 && (int) ($authorization['expires_at'] ?? 0) >= time()) {
    $userStmt = db()->prepare('SELECT id FROM users WHERE id = ? AND status = "Active" AND deleted_at IS NULL AND reset_token IS NOT NULL AND reset_expires > NOW() LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
}

$valid = $user !== false && $user !== null && $userId > 0
    && (int) ($authorization['expires_at'] ?? 0) >= time();
if (!$valid) {
    unset($_SESSION['password_reset_authorization']);
    $error = 'Verify a valid email code before resetting your password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$valid) {
        $error = 'Your verification expired. Request a new email code to continue.';
    } elseif (strlen((string) ($_POST['password'] ?? '')) < 8 || $_POST['password'] !== ($_POST['confirm_password'] ?? null)) {
        $error = 'Passwords must match and contain at least 8 characters.';
    } else {
        $update = db()->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ? AND status = "Active" AND deleted_at IS NULL AND reset_expires > NOW()');
        $update->execute([password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $userId]);
        if ($update->rowCount() === 1) {
            log_action($userId, 'Password reset completed after email OTP');
            unset($_SESSION['password_reset_authorization']);
            $success = 'Password reset complete. You can now sign in.';
        } else {
            unset($_SESSION['password_reset_authorization']);
            $valid = false;
            $error = 'Your verification expired. Request a new email code to continue.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?php echo h(APP_NAME); ?></title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261012">
    <link rel="stylesheet" href="assets/css/style.css?v=20261012-email-reset-otp">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-card">
        <div class="school-mark"><i class="ri-lock-password-line"></i></div>
        <div class="login-copy"><p>Account Recovery</p><h1>Reset Password</h1></div>
        <?php if ($success): ?>
            <div class="notice success"><?php echo h($success); ?></div>
            <a class="portal-link full-btn" href="index.php">Back to Login</a>
        <?php elseif (!$valid): ?>
            <div class="notice error"><?php echo h($error); ?></div>
            <a class="primary-btn full-btn" href="forgot-password.php"><i class="ri-mail-send-line"></i> Request Email Code</a>
        <?php else: ?>
            <?php if ($error): ?><div class="notice error"><?php echo h($error); ?></div><?php endif; ?>
            <form class="login-form" method="post">
                <?php echo csrf_field(); ?>
                <label for="new-password">New Password</label>
                <div class="input-control"><i class="ri-lock-line"></i><input id="new-password" type="password" name="password" minlength="8" autocomplete="new-password" required></div>
                <label for="confirm-password">Confirm Password</label>
                <div class="input-control"><i class="ri-lock-line"></i><input id="confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
                <button class="primary-btn full-btn" type="submit"><i class="ri-lock-password-line"></i> Reset Password</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
