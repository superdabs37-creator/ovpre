<?php
require_once __DIR__ . '/includes/bootstrap.php';

$message = null;
$error = null;
$resetComplete = false;
$challenge = $_SESSION['local_password_reset'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'request');

    if ($action === 'request') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = db()->prepare('SELECT id, username, email, role FROM users WHERE LOWER(email) = LOWER(?) AND status = "Active" AND deleted_at IS NULL LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && $user['role'] === ROLE_SUPER_ADMIN) {
                    $limitStmt = db()->prepare('SELECT COUNT(*) FROM login_history WHERE user_id = ? AND status = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
                    $limitStmt->execute([(int) $user['id'], 'Local password reset code sent']);
                    if ((int) $limitStmt->fetchColumn() < 3) {
                        $code = (string) random_int(100000, 999999);
                        $sent = send_app_email(
                            (string) $user['email'],
                            APP_NAME . ' Super Admin password reset code',
                            "Your password reset code is {$code}. It expires in 10 minutes. If you did not request this code, ignore this email."
                        );
                        log_login_event((int) $user['id'], (string) $user['username'], $sent ? 'Local password reset code sent' : 'Local password reset email failed');
                        if ($sent) {
                            session_regenerate_id(true);
                            $_SESSION['local_password_reset'] = [
                                'user_id' => (int) $user['id'],
                                'email' => (string) $user['email'],
                                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                                'expires_at' => time() + 600,
                                'attempts' => 0,
                            ];
                            $challenge = $_SESSION['local_password_reset'];
                            $message = 'A reset code has been sent to the registered email, if the account is eligible.';
                        } else {
                            $error = 'We could not send the reset code. Check the email service configuration and try again.';
                        }
                    } else {
                        $error = 'Too many reset codes were requested. Please wait 15 minutes before trying again.';
                    }
                } elseif ($user) {
                    // Other roles continue to use their Supabase Auth recovery link.
                    supabase_auth_recover($email);
                    $message = 'If an active account uses this email, a secure password reset link has been sent.';
                } else {
                    $message = 'If an active account uses this email, a secure password reset link has been sent.';
                }
            } catch (Throwable $exception) {
                error_log('Password recovery request failed: ' . $exception->getMessage());
                $error = 'We could not process the request. Please try again later or contact the System Administrator.';
            }
        } else {
            $error = 'Enter a valid account email address.';
        }
    } elseif ($action === 'reset') {
        $challenge = $_SESSION['local_password_reset'] ?? null;
        $code = trim((string) ($_POST['code'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $strongPassword = strlen($password) >= 12
            && preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);

        if (!$challenge || (int) ($challenge['expires_at'] ?? 0) < time()) {
            unset($_SESSION['local_password_reset']);
            $challenge = null;
            $error = 'The reset code expired. Request a new code and try again.';
        } elseif ((int) ($challenge['attempts'] ?? 0) >= 5) {
            unset($_SESSION['local_password_reset']);
            $challenge = null;
            $error = 'Too many incorrect codes. Request a new reset code.';
        } elseif (!$strongPassword) {
            $error = 'Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.';
        } else {
            $_SESSION['local_password_reset']['attempts'] = (int) $challenge['attempts'] + 1;
            if (preg_match('/^\d{6}$/', $code) && password_verify($code, (string) $challenge['code_hash'])) {
                try {
                    $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ? AND LOWER(email) = LOWER(?) AND role = ? AND status = "Active" AND deleted_at IS NULL LIMIT 1');
                    $stmt->execute([(int) $challenge['user_id'], (string) $challenge['email'], ROLE_SUPER_ADMIN]);
                    $user = $stmt->fetch();
                    if (!$user) {
                        throw new RuntimeException('The Super Admin profile is no longer active.');
                    }

                    $update = db()->prepare('UPDATE users SET password = ?, login_attempts = 0, locked_until = NULL, reset_token = NULL, reset_expires = NULL, updated_at = NOW() WHERE id = ? AND role = ?');
                    $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id'], ROLE_SUPER_ADMIN]);
                    unset($_SESSION['local_password_reset']);
                    log_action((int) $user['id'], 'Super Admin password reset with email code');
                    log_login_event((int) $user['id'], (string) $user['username'], 'Local password reset completed');
                    $challenge = null;
                    $message = 'Password updated and account lock cleared. You can now sign in with your new password.';
                    $resetComplete = true;
                } catch (Throwable $exception) {
                    error_log('Super Admin password reset failed: ' . $exception->getMessage());
                    $error = 'The password reset could not be completed. Please try again or contact the System Administrator.';
                }
            } else {
                $challenge = $_SESSION['local_password_reset'];
                $error = 'The reset code is incorrect.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | I-Record</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-card">
        <h1>Forgot Password</h1>
        <?php if ($message): ?><p class="notice success"><?php echo h($message); ?></p><?php endif; ?>
        <?php if ($error): ?><p class="notice error"><?php echo h($error); ?></p><?php endif; ?>

        <?php if ($challenge && !$resetComplete): ?>
            <p>Enter the 6-digit code sent to your registered email and choose a new password. The code expires in 10 minutes.</p>
            <form class="login-form" method="post" autocomplete="off">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset">
                <label for="code">Email reset code</label>
                <div class="input-control"><input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div>
                <label for="password">New password</label>
                <div class="input-control"><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div>
                <button class="primary-btn full-btn" type="submit">Reset Password</button>
            </form>
        <?php else: ?>
            <form class="login-form" method="post" autocomplete="off">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="request">
                <label for="email">Account email</label>
                <div class="input-control"><input id="email" name="email" type="email" required autocomplete="email"></div>
                <button class="primary-btn full-btn" type="submit">Send Reset Code</button>
            </form>
        <?php endif; ?>
        <p class="portal-link full-btn"><a href="index.php">Back to Sign in</a></p>
    </section>
</main>
</body>
</html>
