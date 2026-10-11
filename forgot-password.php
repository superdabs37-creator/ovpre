<?php
require_once __DIR__ . '/includes/bootstrap.php';

$message = null;
$error = null;
$submittedEmail = '';
$challenge = $_SESSION['password_reset_challenge'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'send_code');

    if ($action === 'send_code') {
        $submittedEmail = trim((string) ($_POST['email'] ?? ''));
        unset($_SESSION['password_reset_challenge'], $_SESSION['password_reset_authorization']);

        if (!filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $userStmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND status = "Active" AND deleted_at IS NULL LIMIT 1');
            $userStmt->execute([$submittedEmail]);
            $user = $userStmt->fetch();
            $eligible = (bool) $user;
            $code = (string) random_int(100000, 999999);
            $challenge = [
                'user_id' => $eligible ? (int) $user['id'] : 0,
                'email' => $submittedEmail,
                'eligible' => $eligible,
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires_at' => time() + 600,
                'sent_at' => time(),
                'attempts' => 0,
                'resends' => 0,
            ];

            if ($eligible) {
                db()->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?')
                    ->execute([$challenge['code_hash'], $user['id']]);
                $body = "Your I-Record password reset code is {$code}. Enter this code on the password recovery page within 10 minutes. If you did not request a password reset, ignore this email.";
                if (!send_app_email((string) $user['email'], APP_NAME . ' password reset code', $body)) {
                    db()->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?')->execute([$user['id']]);
                    error_log('Password reset OTP email could not be sent for user #' . (int) $user['id']);
                    $error = 'We could not send the verification email right now. Please try again later.';
                    $challenge = null;
                } else {
                    log_action((int) $user['id'], 'Password reset OTP sent');
                    $_SESSION['password_reset_challenge'] = $challenge;
                }
            } else {
                // Keep the response and next screen consistent for addresses without an active account.
                $_SESSION['password_reset_challenge'] = $challenge;
            }

            if ($challenge) {
                $message = 'If an active account uses this email, a 6-digit code has been sent. Enter it below to continue.';
            }
        }
    } elseif ($action === 'verify_code') {
        $challenge = $_SESSION['password_reset_challenge'] ?? null;
        $code = trim((string) ($_POST['otp'] ?? ''));

        if (!$challenge || (int) ($challenge['expires_at'] ?? 0) < time()) {
            unset($_SESSION['password_reset_challenge']);
            $challenge = null;
            $error = 'That code expired. Request a new code to continue.';
        } else {
            $_SESSION['password_reset_challenge']['attempts'] = (int) $challenge['attempts'] + 1;
            $isCorrect = preg_match('/^\d{6}$/', $code)
                && password_verify($code, (string) $challenge['code_hash']);

            if ($isCorrect && !empty($challenge['eligible'])) {
                $userStmt = db()->prepare('SELECT id, reset_token FROM users WHERE id = ? AND status = "Active" AND deleted_at IS NULL AND reset_expires > NOW() AND reset_token IS NOT NULL LIMIT 1');
                $userStmt->execute([(int) $challenge['user_id']]);
                $resetUser = $userStmt->fetch();
                $userId = (int) ($resetUser['id'] ?? 0);
                if ($userId > 0 && password_verify($code, (string) $resetUser['reset_token'])) {
                    db()->prepare('UPDATE users SET reset_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?')->execute([$userId]);
                    $_SESSION['password_reset_authorization'] = [
                        'user_id' => $userId,
                        'expires_at' => time() + 600,
                    ];
                    unset($_SESSION['password_reset_challenge']);
                    redirect_to('reset-password.php');
                }
            }

            if ((int) $_SESSION['password_reset_challenge']['attempts'] >= 5) {
                if (!empty($challenge['user_id'])) {
                    db()->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?')->execute([(int) $challenge['user_id']]);
                }
                unset($_SESSION['password_reset_challenge']);
                $challenge = null;
                $error = 'Too many incorrect codes. Request a new code to continue.';
            } else {
                $challenge = $_SESSION['password_reset_challenge'];
                $error = 'That verification code is incorrect or expired.';
            }
        }
    } elseif ($action === 'resend_code') {
        $challenge = $_SESSION['password_reset_challenge'] ?? null;
        if (!$challenge || (int) ($challenge['expires_at'] ?? 0) < time()) {
            unset($_SESSION['password_reset_challenge']);
            $challenge = null;
            $error = 'Your verification session expired. Enter your email to request a new code.';
        } elseif ((int) ($challenge['resends'] ?? 0) >= 3 || time() - (int) ($challenge['sent_at'] ?? 0) < 60) {
            $error = 'Wait one minute before resending. You can request up to three additional codes.';
        } elseif (!empty($challenge['eligible'])) {
            $userId = (int) $challenge['user_id'];
            $userStmt = db()->prepare('SELECT email FROM users WHERE id = ? AND status = "Active" AND deleted_at IS NULL LIMIT 1');
            $userStmt->execute([$userId]);
            $activeEmail = $userStmt->fetchColumn();
            if (!$activeEmail) {
                unset($_SESSION['password_reset_challenge']);
                $challenge = null;
                $error = 'Your verification session expired. Enter your email to request a new code.';
            } else {
            $code = (string) random_int(100000, 999999);
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ? AND status = "Active" AND deleted_at IS NULL')
                ->execute([$codeHash, $userId]);
            $body = "Your I-Record password reset code is {$code}. Enter this code on the password recovery page within 10 minutes. If you did not request a password reset, ignore this email.";
            if (send_app_email((string) $activeEmail, APP_NAME . ' password reset code', $body)) {
                $_SESSION['password_reset_challenge']['code_hash'] = $codeHash;
                $_SESSION['password_reset_challenge']['expires_at'] = time() + 600;
                $_SESSION['password_reset_challenge']['sent_at'] = time();
                $_SESSION['password_reset_challenge']['attempts'] = 0;
                $_SESSION['password_reset_challenge']['resends'] = (int) $challenge['resends'] + 1;
                $challenge = $_SESSION['password_reset_challenge'];
                $message = 'If an active account uses this email, a new 6-digit code has been sent.';
                log_action($userId, 'Password reset OTP resent');
            } else {
                db()->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?')->execute([$userId]);
                unset($_SESSION['password_reset_challenge']);
                $challenge = null;
                $error = 'We could not send the verification email right now. Please request a new code later.';
                error_log('Password reset OTP resend failed for user #' . $userId);
            }
            }
        } else {
            $error = 'That code expired. Enter your email to request a new code.';
            unset($_SESSION['password_reset_challenge']);
            $challenge = null;
        }
    }
}

$challenge = $_SESSION['password_reset_challenge'] ?? null;
$maskedEmail = '';
if ($challenge && !empty($challenge['email'])) {
    $emailParts = explode('@', (string) $challenge['email'], 2);
    $localPart = $emailParts[0] ?? '';
    $maskedEmail = ($localPart !== '' ? substr($localPart, 0, 1) . str_repeat('*', max(1, strlen($localPart) - 1)) : '')
        . (isset($emailParts[1]) ? '@' . $emailParts[1] : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | <?php echo h(APP_NAME); ?></title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261012">
    <link rel="stylesheet" href="assets/css/style.css?v=20261006-functional-password-reset">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-card">
        <div class="school-mark"><i class="ri-key-2-line"></i></div>
        <div class="login-copy"><p>Account Recovery</p><h1>Forgot Password</h1></div>
        <?php if ($message): ?><div class="notice success"><?php echo h($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice error"><?php echo h($error); ?></div><?php endif; ?>

        <?php if ($challenge): ?>
            <form class="login-form" method="post" autocomplete="off">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="verify_code">
                <p class="portal-link full-btn">Enter the 6-digit code sent to <?php echo h($maskedEmail); ?>. It expires in 10 minutes.</p>
                <label for="reset-otp">Email verification code</label>
                <div class="input-control"><i class="ri-shield-keyhole-line"></i><input type="text" id="reset-otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Enter 6-digit code" required autofocus></div>
                <button class="primary-btn full-btn" type="submit"><i class="ri-shield-check-line"></i> Verify Code</button>
            </form>
            <form class="login-form" method="post">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="resend_code">
                <button class="ghost-btn full-btn" type="submit">Resend Code</button>
            </form>
        <?php else: ?>
            <form class="login-form" method="post">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="send_code">
                <label for="recovery-email">Email</label>
                <div class="input-control"><i class="ri-mail-line"></i><input id="recovery-email" type="email" name="email" value="<?php echo h($submittedEmail); ?>" autocomplete="email" required></div>
                <button class="primary-btn full-btn" type="submit"><i class="ri-mail-send-line"></i> Send Verification Code</button>
            </form>
        <?php endif; ?>
        <a class="portal-link full-btn" href="index.php">Back to Login</a>
    </section>
</main>
</body>
</html>
