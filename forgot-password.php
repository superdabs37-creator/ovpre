<?php
require_once __DIR__ . '/includes/bootstrap.php';

$message = null;
$error = null;
$resetComplete = false;

if (isset($_GET['restart']) && $_GET['restart'] === '1') {
    unset($_SESSION['password_reset']);
    redirect_to('forgot-password.php');
}

$challenge = $_SESSION['password_reset'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'request');

    if ($action === 'request' || $action === 'resend') {
        $email = $action === 'resend' && !empty($challenge['email'])
            ? (string) $challenge['email']
            : trim((string) ($_POST['email'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = db()->prepare("SELECT id, username, fullname, email, role, auth_user_id FROM users WHERE LOWER(email) = LOWER(?) AND status = 'Active' AND deleted_at IS NULL LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $userId = (int) $user['id'];
                    $limitStmt = db()->prepare("SELECT COUNT(*) FROM login_history WHERE user_id = ? AND status = ? AND created_at >= (NOW() - INTERVAL '15 MINUTE')");
                    $limitStmt->execute([$userId, 'Password reset code sent']);
                    if ((int) $limitStmt->fetchColumn() >= 5) {
                        $error = 'Too many reset requests. Please wait 15 minutes before requesting another code.';
                    } else {
                        $code = (string) random_int(100000, 999999);
                        $body = "Hello " . ($user['fullname'] ?: $user['username']) . ",\n\n"
                              . "We received a request to reset your password for your " . APP_NAME . " account.\n\n"
                              . "Your 6-digit verification code is:\n\n"
                              . "    " . $code . "\n\n"
                              . "This code is valid for 10 minutes.\n"
                              . "If you did not request a password reset, please ignore this email or inform your administrator immediately.\n\n"
                              . "Regards,\n"
                              . "Office of the Vice President for Research and Extension (OVPRE)\n"
                              . "Pamantasan ng Lungsod ng San Pablo";

                        $sent = send_app_email((string) $user['email'], APP_NAME . ' - Password Reset Verification Code', $body);
                        log_login_event($userId, (string) $user['username'], $sent ? 'Password reset code sent' : 'Password reset email failed');

                        if ($sent) {
                            session_regenerate_id(true);
                            $codeHash = password_hash($code, PASSWORD_DEFAULT);

                            try {
                                $tokenUpdate = db()->prepare("UPDATE users SET reset_token = ?, reset_expires = (NOW() + INTERVAL '10 MINUTE') WHERE id = ?");
                                $tokenUpdate->execute([$codeHash, $userId]);
                            } catch (Throwable $ignore) {}

                            $_SESSION['password_reset'] = [
                                'user_id' => $userId,
                                'email' => (string) $user['email'],
                                'username' => (string) $user['username'],
                                'fullname' => (string) $user['fullname'],
                                'code_hash' => $codeHash,
                                'expires_at' => time() + 600,
                                'attempts' => 0,
                            ];
                            $challenge = $_SESSION['password_reset'];
                            $message = 'A 6-digit verification code has been sent to ' . h($email) . '. Please enter it below to set your new password.';
                        } else {
                            $error = 'We were unable to deliver the reset code to your email. Please check your email configuration or contact the administrator.';
                        }
                    }
                } else {
                    // Generic feedback to prevent email enumeration
                    $message = 'If an active account is registered with this email, a 6-digit verification code has been sent.';
                }
            } catch (Throwable $e) {
                error_log('Password reset request error: ' . $e->getMessage());
                $error = 'Unable to process your request. Please try again later.';
            }
        } else {
            $error = 'Please enter a valid email address.';
        }
    } elseif ($action === 'reset') {
        $challenge = $_SESSION['password_reset'] ?? null;
        $code = trim((string) ($_POST['code'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!$challenge || (int) ($challenge['expires_at'] ?? 0) < time()) {
            unset($_SESSION['password_reset']);
            $challenge = null;
            $error = 'Your verification code has expired. Please request a new code.';
        } elseif ((int) ($challenge['attempts'] ?? 0) >= 5) {
            unset($_SESSION['password_reset']);
            $challenge = null;
            $error = 'Too many failed verification attempts. Please request a new code.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (get_system_setting('strong_passwords', '1') === '1' && (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password))) {
            $error = 'Password must contain both letters and numbers for system security.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            $_SESSION['password_reset']['attempts'] = (int) $challenge['attempts'] + 1;
            $userId = (int) $challenge['user_id'];

            if (preg_match('/^\d{6}$/', $code) && password_verify($code, (string) $challenge['code_hash'])) {
                try {
                    $stmt = db()->prepare("SELECT id, username, auth_user_id FROM users WHERE id = ? AND LOWER(email) = LOWER(?) AND status = 'Active' AND deleted_at IS NULL LIMIT 1");
                    $stmt->execute([$userId, (string) $challenge['email']]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        throw new RuntimeException('This account is no longer active.');
                    }

                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $update = db()->prepare('UPDATE users SET password = ?, login_attempts = 0, locked_until = NULL, reset_token = NULL, reset_expires = NULL, updated_at = NOW() WHERE id = ?');
                    $update->execute([$newHash, $userId]);

                    // Synchronize with Supabase Auth if linked
                    if (!empty($user['auth_user_id'])) {
                        supabase_auth_admin_set_password((string) $user['auth_user_id'], $password);
                    }

                    unset($_SESSION['password_reset']);
                    log_action($userId, 'User password reset with email verification code');
                    log_login_event($userId, (string) $user['username'], 'Password reset completed');
                    notify_user($userId, 'Your account password was successfully reset.');

                    $challenge = null;
                    $message = 'Your password has been successfully reset! You can now sign in with your new password.';
                    $resetComplete = true;
                } catch (Throwable $e) {
                    error_log('Password reset execution failed: ' . $e->getMessage());
                    $error = 'The password reset could not be completed: ' . $e->getMessage();
                }
            } else {
                $challenge = $_SESSION['password_reset'];
                $error = 'The 6-digit verification code is incorrect. Please check the code and try again.';
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
    <title>Forgot Password | <?php echo h(APP_NAME); ?></title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261105-logo">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20261105-clean">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-card" style="max-width: 440px; width: 100%;">
        <!-- OVPRE Seal Logo Header -->
        <div style="text-align: center; margin-bottom: 18px;">
            <img src="assets/img/ovpre-logo.jpg" alt="OVPRE Seal" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid var(--yellow-500, #eab308); box-shadow: 0 4px 14px rgba(0,0,0,0.12);">
            <h1 style="margin: 12px 0 4px; font-size: 1.55rem; font-weight: 800; color: var(--green-900, #0f3d24);">Reset Password</h1>
            <p style="margin: 0; color: var(--muted, #64748b); font-size: 0.88rem;">Office of the Vice President for Research and Extension</p>
        </div>

        <?php if ($message): ?>
            <div class="notice success" style="margin-bottom: 16px; padding: 12px 14px; border-radius: 10px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 8px;">
                <i class="ri-checkbox-circle-fill" style="color: #22c55e; font-size: 1.1rem; flex-shrink: 0; margin-top: 1px;"></i>
                <span><?php echo h($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="notice error" style="margin-bottom: 16px; padding: 12px 14px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 8px;">
                <i class="ri-error-warning-fill" style="color: #ef4444; font-size: 1.1rem; flex-shrink: 0; margin-top: 1px;"></i>
                <span><?php echo h($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($resetComplete): ?>
            <div style="text-align: center; padding: 10px 0 16px;">
                <div style="width: 58px; height: 58px; border-radius: 50%; background: #dcfce7; color: #16a34a; font-size: 1.8rem; display: grid; place-items: center; margin: 0 auto 14px;">
                    <i class="ri-check-line"></i>
                </div>
                <h3 style="margin: 0 0 6px; color: #0f172a; font-size: 1.15rem; font-weight: 700;">Password Updated!</h3>
                <p style="margin: 0 0 20px; color: #64748b; font-size: 0.88rem;">Your new password is now active. You can proceed to sign in with your new credentials.</p>
                <a class="primary-btn full-btn" href="index.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; padding: 12px; font-weight: 700;">
                    <i class="ri-login-box-line"></i> Proceed to Sign In
                </a>
            </div>
        <?php elseif ($challenge): ?>
            <!-- Step 2: Verification Code & New Password -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.84rem; color: #475569;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                    <span>Resetting account for: <strong><?php echo h($challenge['email']); ?></strong></span>
                    <a href="forgot-password.php?restart=1" style="color: #15803d; text-decoration: none; font-weight: 700; font-size: 0.8rem;">Change</a>
                </div>
            </div>

            <form class="login-form" method="post" autocomplete="off">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset">
                
                <label for="code" style="font-weight: 700; font-size: 0.85rem; color: #334155;">6-Digit Verification Code</label>
                <div class="input-control" style="margin-bottom: 14px;">
                    <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="------" style="letter-spacing: 8px; text-align: center; font-size: 1.35rem; font-weight: 800; color: #15803d;" required autofocus>
                </div>

                <label for="password" style="font-weight: 700; font-size: 0.85rem; color: #334155;">New Password</label>
                <div class="input-control" style="position: relative; margin-bottom: 14px;">
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Enter new password" style="padding-right: 40px;" required>
                    <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Show password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.1rem; display: grid; place-items: center;">
                        <i class="ri-eye-line" id="togglePasswordIcon"></i>
                    </button>
                </div>

                <label for="confirm_password" style="font-weight: 700; font-size: 0.85rem; color: #334155;">Confirm New Password</label>
                <div class="input-control" style="position: relative; margin-bottom: 18px;">
                    <input id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" placeholder="Confirm new password" style="padding-right: 40px;" required>
                    <button type="button" class="password-toggle-btn" id="toggleConfirmPassword" aria-label="Show password" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.1rem; display: grid; place-items: center;">
                        <i class="ri-eye-line" id="toggleConfirmPasswordIcon"></i>
                    </button>
                </div>

                <button class="primary-btn full-btn" type="submit" style="padding: 12px; font-weight: 700; font-size: 0.95rem; margin-bottom: 10px;">
                    <i class="ri-lock-password-line"></i> Set New Password
                </button>
            </form>

            <form method="post" style="text-align: center; margin-top: 10px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="resend">
                <span style="font-size: 0.84rem; color: #64748b;">Didn't receive the code? </span>
                <button type="submit" style="background: none; border: none; padding: 0; color: #15803d; font-weight: 700; font-size: 0.84rem; cursor: pointer; text-decoration: underline;">Resend code</button>
            </form>
        <?php else: ?>
            <!-- Step 1: Request Code by Email -->
            <p style="margin: 0 0 16px; font-size: 0.88rem; color: #475569; line-height: 1.5;">
                Enter your institutional email address below. We'll send you a 6-digit verification code to reset your account password.
            </p>

            <form class="login-form" method="post" autocomplete="off">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="request">
                
                <label for="email" style="font-weight: 700; font-size: 0.85rem; color: #334155;">Account Email</label>
                <div class="input-control" style="margin-bottom: 18px;">
                    <input id="email" name="email" type="email" placeholder="name@plsp.edu.ph" required autocomplete="email" autofocus>
                </div>

                <button class="primary-btn full-btn" type="submit" style="padding: 12px; font-weight: 700; font-size: 0.95rem;">
                    <i class="ri-mail-send-line"></i> Send Verification Code
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--line, #e2e8f0);">
            <a href="index.php" style="color: var(--green-800, #166534); text-decoration: none; font-size: 0.88rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ri-arrow-left-line"></i> Back to Sign In
            </a>
        </div>
    </section>
</main>

<script>
    (() => {
        function setupToggle(btnId, inputId, iconId) {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (!btn || !input || !icon) return;

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const isPassword = input.getAttribute('type') === 'password';
                input.setAttribute('type', isPassword ? 'text' : 'password');
                icon.className = isPassword ? 'ri-eye-off-line' : 'ri-eye-line';
                btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                icon.style.color = isPassword ? '#15803d' : '#64748b';
            });
        }

        setupToggle('togglePassword', 'password', 'togglePasswordIcon');
        setupToggle('toggleConfirmPassword', 'confirm_password', 'toggleConfirmPasswordIcon');
    })();
</script>
</body>
</html>
