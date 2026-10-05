<?php
require_once __DIR__ . '/includes/bootstrap.php';
$message = null;
$resetLink = null;
$submittedEmail = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submittedEmail = trim((string) ($_POST['email'] ?? ''));
    if (filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND status = "Active" AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$submittedEmail]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?')
                ->execute([password_hash($token, PASSWORD_DEFAULT), $user['id']]);
            $link = rtrim(APP_BASE_URL, '/') . '/reset-password.php?user=' . (int) $user['id'] . '&token=' . urlencode($token);
            $subject = APP_NAME . ' password reset';
            $body = "We received a request to reset your password. Open this link within 30 minutes:\n\n{$link}\n\nIf you did not request this, you can ignore this email.";
            $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $emailSent = function_exists('mail') && @mail($user['email'], $subject, $body, $headers);

            // Local XAMPP does not usually have outbound mail configured, so expose a usable local link.
            $host = strtolower((string) parse_url(APP_BASE_URL, PHP_URL_HOST));
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            if (!$emailSent || $isLocal) {
                $logDir = __DIR__ . '/storage';
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0775, true);
                }
                file_put_contents($logDir . '/mail.log', '[' . date('c') . "] {$user['email']}: Password reset {$link}\n", FILE_APPEND | LOCK_EX);
                if ($isLocal) {
                    $resetLink = $link;
                }
            }
            log_action((int) $user['id'], 'Password reset requested');
        }
    }
    $message = 'If an active account uses that email, password reset instructions are ready.';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Forgot Password | <?php echo h(APP_NAME); ?></title><link rel="stylesheet" href="assets/css/style.css?v=20261006-functional-password-reset"><link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet"></head><body class="login-body"><main class="login-shell"><section class="login-card"><div class="school-mark"><i class="ri-key-2-line"></i></div><div class="login-copy"><p>Account Recovery</p><h1>Forgot Password</h1></div><?php if ($message): ?><div class="notice success"><?php echo h($message); ?></div><?php endif; ?><?php if ($resetLink): ?><a class="primary-btn full-btn" href="<?php echo h($resetLink); ?>"><i class="ri-lock-password-line"></i> Open Reset Form</a><?php endif; ?><form class="login-form" method="post"><?php echo csrf_field(); ?><label for="recovery-email">Email</label><div class="input-control"><i class="ri-mail-line"></i><input id="recovery-email" type="email" name="email" value="<?php echo h($submittedEmail); ?>" autocomplete="email" required></div><button class="primary-btn full-btn" type="submit">Send Reset Link</button><a class="portal-link full-btn" href="index.php">Back to Login</a></form></section></main></body></html>
