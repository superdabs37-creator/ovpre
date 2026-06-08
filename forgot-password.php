<?php
require_once __DIR__ . '/includes/bootstrap.php';
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($_POST['email'] ?? '')]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        db()->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?')->execute([password_hash($token, PASSWORD_DEFAULT), $user['id']]);
        $link = APP_BASE_URL . '/reset-password.php?user=' . $user['id'] . '&token=' . urlencode($token);
        $logDir = __DIR__ . '/storage';
        if (!is_dir($logDir)) { mkdir($logDir, 0775, true); }
        file_put_contents($logDir . '/mail.log', '[' . date('c') . "] {$user['email']}: Password reset {$link}\n", FILE_APPEND);
        log_action((int) $user['id'], 'Password reset requested');
    }
    $message = 'If the email exists, a reset link has been sent. Local fallback: storage/mail.log.';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Forgot Password | <?php echo APP_NAME; ?></title><link rel="stylesheet" href="assets/css/style.css"><link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet"></head><body class="login-body"><main class="login-shell"><section class="login-card"><div class="school-mark"><i class="ri-key-2-line"></i></div><div class="login-copy"><p>Account Recovery</p><h1>Forgot Password</h1></div><?php if ($message): ?><div class="notice success"><?php echo h($message); ?></div><?php endif; ?><form class="login-form" method="post"><?php echo csrf_field(); ?><label>Email</label><div class="input-control"><i class="ri-mail-line"></i><input type="email" name="email" required></div><button class="primary-btn full-btn">Send Reset Link</button><a class="portal-link full-btn" href="index.php">Back to Login</a></form></section></main></body></html>
