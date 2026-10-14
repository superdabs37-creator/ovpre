<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Secure Login';
$error = null;
if (($_GET['restart'] ?? '') === '1') {
    unset($_SESSION['login_otp']);
}
$otpChallenge = $_SESSION['login_otp'] ?? null;

$currentUser = current_user();
if ($currentUser) {
    $destination = role_dashboard_path((string) ($currentUser['role'] ?? ''));
    if ($destination !== null) {
        redirect_to($destination);
    }
    log_action((int) $currentUser['id'], 'Blocked active account with unrecognized role');
    log_login_event((int) $currentUser['id'], (string) $currentUser['username'], 'Denied: invalid role');
    $_SESSION = [];
    session_destroy();
    $error = 'This account is not configured for access. Contact the System Administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $authAction = (string) ($_POST['auth_action'] ?? 'login');

    if ($authAction === 'verify_otp') {
        $challenge = $_SESSION['login_otp'] ?? null;
        $code = trim((string) ($_POST['otp'] ?? ''));
        if (!$challenge || (int) ($challenge['expires_at'] ?? 0) < time()) {
            unset($_SESSION['login_otp']);
            $otpChallenge = null;
            $error = 'Your verification code expired. Sign in again to get a new code.';
        } elseif ((int) ($challenge['attempts'] ?? 0) >= 5) {
            unset($_SESSION['login_otp']);
            $otpChallenge = null;
            $error = 'Too many incorrect codes. Sign in again to request a new code.';
        } else {
            $_SESSION['login_otp']['attempts'] = (int) $challenge['attempts'] + 1;
            $userStmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "Active" AND deleted_at IS NULL LIMIT 1');
            $userStmt->execute([(int) $challenge['user_id']]);
            $user = $userStmt->fetch();
            $isLocked = $user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
            if (preg_match('/^\d{6}$/', $code) && password_verify($code, (string) $challenge['code_hash']) && $user && !$isLocked) {
                $destination = role_dashboard_path((string) $user['role']);
                if ($destination === null) {
                    unset($_SESSION['login_otp']);
                    $otpChallenge = null;
                    $error = 'This account is not configured for access. Contact the System Administrator.';
                } else {
                    unset($_SESSION['login_otp']);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['supabase_access_token'] = (string) ($challenge['supabase_access_token'] ?? '');
                    $_SESSION['supabase_refresh_token'] = (string) ($challenge['supabase_refresh_token'] ?? '');
                    $_SESSION['last_activity'] = time();
                    db()->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
                    log_action((int) $user['id'], 'Login completed with email OTP');
                    log_login_event((int) $user['id'], (string) $user['username'], 'Success: email OTP');
                    redirect_to($destination);
                }
            } else {
                if ($user) {
                    $attempts = (int) $user['login_attempts'] + 1;
                    $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + 900) : null;
                    db()->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?')->execute([$attempts, $lockedUntil, $user['id']]);
                    log_action((int) $user['id'], 'Failed email OTP verification');
                    log_login_event((int) $user['id'], (string) $user['username'], 'Failed: email OTP');
                }
                $otpChallenge = $_SESSION['login_otp'];
                $error = 'The verification code is incorrect or the account is no longer active.';
                if ((int) $_SESSION['login_otp']['attempts'] >= 5 || ($user && $attempts >= MAX_LOGIN_ATTEMPTS)) {
                    unset($_SESSION['login_otp']);
                    $otpChallenge = null;
                    $error = 'Too many incorrect codes. Sign in again to request a new code.';
                }
            }
        }
    } elseif ($authAction === 'resend_otp') {
        $challenge = $_SESSION['login_otp'] ?? null;
        if (!$challenge || (int) ($challenge['expires_at'] ?? 0) < time()) {
            unset($_SESSION['login_otp']);
            $otpChallenge = null;
            $error = 'Your verification session expired. Sign in again to get a new code.';
        } elseif ((int) ($challenge['resends'] ?? 0) >= 3 || time() - (int) ($challenge['sent_at'] ?? 0) < 60 || login_otp_rate_limited((int) $challenge['user_id'])) {
            $otpChallenge = $challenge;
            $error = 'Please wait before requesting another code. Codes are limited to four per 10 minutes.';
        } else {
            $code = (string) random_int(100000, 999999);
            $sent = send_app_email((string) $challenge['email'], APP_NAME . ' sign-in verification code', "Your sign-in verification code is {$code}. It expires in 5 minutes. If you did not try to sign in, secure your account by changing your password.");
            if ($sent) {
                $_SESSION['login_otp']['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
                $_SESSION['login_otp']['sent_at'] = time();
                $_SESSION['login_otp']['expires_at'] = time() + 300;
                $_SESSION['login_otp']['attempts'] = 0;
                $_SESSION['login_otp']['resends'] = (int) $challenge['resends'] + 1;
                $otpChallenge = $_SESSION['login_otp'];
                $error = null;
                $userStmt = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                $userStmt->execute([(int) $challenge['user_id']]);
                log_login_event((int) $challenge['user_id'], (string) ($userStmt->fetchColumn() ?: ''), 'OTP sent');
                flash('success', 'A new verification code was sent to your email.');
            } else {
                $otpChallenge = $challenge;
                $userStmt = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                $userStmt->execute([(int) $challenge['user_id']]);
                log_login_event((int) $challenge['user_id'], (string) ($userStmt->fetchColumn() ?: ''), 'OTP delivery failed');
                $error = 'We could not send a verification code. Check the email service configuration and try again.';
            }
        }
    } else {
        unset($_SESSION['login_otp']);
        $otpChallenge = null;
    $usernameValue = $_POST['username'] ?? '';
    $passwordValue = $_POST['password'] ?? '';
    $username = is_string($usernameValue) ? trim($usernameValue) : '';
    $password = is_string($passwordValue) ? $passwordValue : '';
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    $auth = null;
    $passwordValid = false;
    if ($user && $password !== '') {
        // The Super Admin profile authenticates against its local password hash.
        // All other roles continue to authenticate through Supabase Auth.
        if (($user['role'] ?? '') === ROLE_SUPER_ADMIN) {
            $passwordValid = password_verify($password, (string) ($user['password'] ?? ''));
        } else {
        try {
            $auth = supabase_auth_password_login((string) $user['email'], $password);
            $authProfile = supabase_auth_user((string) ($auth['access_token'] ?? ''));
            $authId = (string) ($authProfile['id'] ?? '');
            if ($authId === '' || strcasecmp((string) ($authProfile['email'] ?? ''), (string) $user['email']) !== 0
                || (!empty($user['auth_user_id']) && strcasecmp((string) $user['auth_user_id'], $authId) !== 0)) {
                $auth = null;
            } else {
                db()->prepare('UPDATE users SET auth_user_id = ? WHERE id = ? AND (auth_user_id IS NULL OR auth_user_id = ?)')->execute([$authId, $user['id'], $authId]);
            }
        } catch (Throwable $authError) {
            $auth = null;
        }
            $passwordValid = $auth !== null;
        }
    }
    $isLocked = $user && !empty($user['locked_until']) && strtotime($user['locked_until']) > time();

    if (!$user || !$passwordValid || $isLocked) {
        if ($user) {
            $attempts = ((int) $user['login_attempts']) + 1;
            $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS ? date('Y-m-d H:i:s', time() + 900) : null;
            db()->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?')->execute([$attempts, $lockedUntil, $user['id']]);
            log_action((int) $user['id'], 'Failed login attempt');
            log_login_event((int) $user['id'], $username, 'Failed');
        }
        if (!$user) {
            log_login_event(null, $username, 'Failed');
        }
        $error = 'Invalid credentials or temporarily locked account.';
    } elseif ($user['role'] !== ROLE_SUPER_ADMIN && in_array($user['status'], ['Pending', 'Pending Invitation', 'Pending Approval'], true)) {
        log_action((int) $user['id'], 'Login blocked: pending account');
        log_login_event((int) $user['id'], $username, 'Denied: Pending');
        $error = 'Your account is awaiting invitation acceptance or administrator approval.';
    } elseif ($user['status'] !== 'Active') {
        log_login_event((int) $user['id'], $username, 'Denied: inactive');
        $error = 'This account is inactive or locked. Contact the System Administrator.';
    } elseif (($destination = role_dashboard_path((string) $user['role'])) === null) {
        log_action((int) $user['id'], 'Login denied: unrecognized role');
        log_login_event((int) $user['id'], $username, 'Denied: invalid role');
        $error = 'This account is not configured for access. Contact the System Administrator.';
    } else {
        $sent = false;
        $rateLimited = login_otp_rate_limited((int) $user['id']);
        if (!$rateLimited) {
            $code = (string) random_int(100000, 999999);
            $sent = send_app_email((string) $user['email'], APP_NAME . ' sign-in verification code', "Your sign-in verification code is {$code}. It expires in 5 minutes. If you did not try to sign in, secure your account by changing your password.");
        }
        if ($sent) {
            $_SESSION['login_otp'] = [
                'user_id' => (int) $user['id'],
                'email' => (string) $user['email'],
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'expires_at' => time() + 300,
                'sent_at' => time(),
                'attempts' => 0,
                'resends' => 0,
                'supabase_access_token' => (string) $auth['access_token'],
                'supabase_refresh_token' => (string) ($auth['refresh_token'] ?? ''),
            ];
            $otpChallenge = $_SESSION['login_otp'];
            log_action((int) $user['id'], 'Email OTP sent for login');
            log_login_event((int) $user['id'], $username, 'OTP sent');
        } else {
            if (!$rateLimited) {
                log_login_event((int) $user['id'], $username, 'OTP delivery failed');
            }
            $error = $rateLimited ? 'Too many verification codes were requested. Please wait 10 minutes before trying again.' : 'We could not send a verification code to your registered email. Please try again later or contact the System Administrator.';
        }
    }
    }
}
$notice = flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> | <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261012">
    <link rel="stylesheet" href="assets/css/style.css?v=20261015-simple-login-background">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-card">
            <div class="login-logo">
                <img src="assets/img/ovpre-logo.jpg" alt="OVPRE Logo">
            </div>
            <div class="login-copy">
                <p><?php echo h(APP_ORG); ?></p>
                <h1><?php echo APP_NAME; ?></h1>
            </div>
            <?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-information-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice error"><i class="ri-error-warning-line"></i><?php echo h($error); ?></div><?php endif; ?>
            <?php if ($otpChallenge): ?>
                <form class="login-form" method="post" autocomplete="off">
                    <?php echo csrf_field(); ?><input type="hidden" name="auth_action" value="verify_otp">
                    <p class="otp-expiry" id="otpExpiry" data-expires-at="<?php echo (int) ($otpChallenge['expires_at'] ?? 0); ?>">
                        We sent a code to <?php echo h(preg_replace('/(?<=.).(?=[^@]*?@)/', '*', (string) $otpChallenge['email'])); ?>.
                        <span>Expires in <time id="otpCountdown">05:00</time>.</span>
                    </p>
                    <label for="otp">Email verification code</label>
                    <div class="input-control"><i class="ri-shield-keyhole-line"></i><input type="text" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Enter 6-digit code" required autofocus></div>
                    <button class="primary-btn full-btn" id="verifyOtpButton" type="submit"><i class="ri-shield-check-line"></i> Verify and Login</button>
                </form>
                <form class="login-form" method="post">
                    <?php echo csrf_field(); ?><input type="hidden" name="auth_action" value="resend_otp">
                    <button class="ghost-btn full-btn" type="submit">Resend Code</button>
                </form>
                <p class="portal-link full-btn"><a href="index.php?restart=1">Back to Login</a></p>
            <?php else: ?>
                <form class="login-form" method="post">
                    <?php echo csrf_field(); ?><input type="hidden" name="auth_action" value="login">
                    <label for="username">Username or email</label>
                    <div class="input-control"><i class="ri-user-line"></i><input type="text" id="username" name="username" placeholder="Enter username or email" required></div>
                    <label for="password">Password</label>
                    <div class="input-control"><i class="ri-lock-line"></i><input type="password" id="password" name="password" placeholder="Enter password" required></div>
                    <div class="login-tools"><label class="check-control"><input type="checkbox" name="remember"><span>Remember me</span></label><a href="forgot-password.php">Forgot Password?</a></div>
                    <button class="primary-btn full-btn" type="submit"><i class="ri-login-circle-line"></i> Sign in</button>
                    <p class="portal-link full-btn"><a href="landing.php"><i class="ri-arrow-left-line"></i> Back to Home</a></p>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <?php if ($otpChallenge): ?>
        <script>
            (() => {
                const message = document.getElementById('otpExpiry');
                const countdown = document.getElementById('otpCountdown');
                const codeInput = document.getElementById('otp');
                const verifyButton = document.getElementById('verifyOtpButton');
                const expiresAt = Number(message?.dataset.expiresAt || 0) * 1000;

                const updateCountdown = () => {
                    const secondsLeft = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
                    if (secondsLeft === 0) {
                        message.textContent = 'Your code has expired. Sign in again to get a new one.';
                        message.classList.add('is-expired');
                        codeInput.disabled = true;
                        verifyButton.disabled = true;
                        return;
                    }

                    const minutes = Math.floor(secondsLeft / 60);
                    const seconds = secondsLeft % 60;
                    countdown.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    window.setTimeout(updateCountdown, 1000);
                };

                updateCountdown();
            })();
        </script>
    <?php endif; ?>
</body>
</html>
