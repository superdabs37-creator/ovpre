<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Secure Login';
$error = null;
if (($_GET['restart'] ?? '') === '1' || ($_GET['logout'] ?? '') === '1') {
    if (($_GET['logout'] ?? '') === '1') {
        if (!empty($_SESSION['supabase_access_token'])) {
            try { supabase_auth_logout((string) $_SESSION['supabase_access_token']); } catch (Throwable $e) {}
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
            session_start();
        }
    }
}

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
    $error = 'This account is not configured for access. Contact the administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $usernameValue = $_POST['username'] ?? '';
    $passwordValue = $_POST['password'] ?? '';
    $username = is_string($usernameValue) ? trim($usernameValue) : '';
    $password = is_string($passwordValue) ? $passwordValue : '';

    $stmt = db()->prepare("SELECT * FROM users WHERE (username = ? OR LOWER(email) = LOWER(?) OR (role = 'Super Admin' AND ? = 'admin')) AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$username, $username, $username]);
    $user = $stmt->fetch();
    $auth = null;
    $passwordValid = false;

    if ($user && $password !== '') {
        if (!empty($user['password']) && password_verify($password, (string) $user['password'])) {
            $passwordValid = true;
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
        } else {
            log_login_event(null, $username, 'Failed');
        }
        $error = 'Invalid credentials or temporarily locked account.';
    } else {
        if ($user['role'] !== ROLE_SUPER_ADMIN && in_array($user['status'], ['Pending', 'Pending Invitation', 'Pending Approval'], true)) {
            // Auto-activate account since administrator approval is no longer required
            db()->prepare("UPDATE users SET status = 'Active', approved_at = NOW(), login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?")->execute([(int) $user['id']]);
            $user['status'] = 'Active';
        }

        if ($user['status'] !== 'Active') {
            log_login_event((int) $user['id'], $username, 'Denied: inactive');
            $error = 'This account is inactive or locked. Contact the administrator.';
        } elseif (($destination = role_dashboard_path((string) $user['role'])) === null) {
            log_action((int) $user['id'], 'Login denied: unrecognized role');
            log_login_event((int) $user['id'], $username, 'Denied: invalid role');
            $error = 'This account is not configured for access. Contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['supabase_access_token'] = (string) ($auth['access_token'] ?? '');
            $_SESSION['supabase_refresh_token'] = (string) ($auth['refresh_token'] ?? '');
            $_SESSION['last_activity'] = time();
            db()->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
            log_action((int) $user['id'], 'Login completed');
            log_login_event((int) $user['id'], (string) $user['username'], 'Success');
            redirect_to($destination);
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
            <?php if (isset($_GET['registered'])): ?>
                <div class="notice success" style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 14px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    <i class="ri-checkbox-circle-line" style="font-size: 1.2rem;"></i> Your account has been created and activated! You can now sign in immediately.
                </div>
            <?php endif; ?>
            <?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-information-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice error"><i class="ri-error-warning-line"></i><?php echo h($error); ?></div><?php endif; ?>
                <form class="login-form" method="post">
                    <?php echo csrf_field(); ?><input type="hidden" name="auth_action" value="login">
                    <label for="username">Username or email</label>
                    <div class="input-control"><i class="ri-user-line"></i><input type="text" id="username" name="username" placeholder="Enter username or email" required></div>
                    <label for="password">Password</label>
                    <div class="input-control">
                        <i class="ri-lock-line"></i>
                        <input type="password" id="password" name="password" placeholder="Enter password" required>
                        <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Show password" style="background:none;border:none;padding:0;cursor:pointer;color:var(--muted);display:flex;align-items:center;margin-left:auto;">
                            <i class="ri-eye-line" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                    <div class="login-tools"><label class="check-control"><input type="checkbox" name="remember"><span>Remember me</span></label><a href="forgot-password.php">Forgot Password?</a></div>
                    <button class="primary-btn full-btn" type="submit"><i class="ri-login-circle-line"></i> Sign in</button>
                    <div style="text-align: center; margin: 16px 0 10px; font-size: 0.92rem; color: #64748b;">
                        Don't have an account? <a href="register.php" style="color: #15803d; font-weight: 700; text-decoration: underline;">Create an account</a>
                    </div>
                    <p class="portal-link full-btn"><a href="landing.php"><i class="ri-arrow-left-line"></i> Back to Home</a></p>
                </form>
        </section>
    </main>
    <script>
        (() => {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (toggleBtn && passwordInput && toggleIcon) {
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    toggleIcon.className = isPassword ? 'ri-eye-off-line' : 'ri-eye-line';
                    toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                    toggleIcon.style.color = isPassword ? '#15803d' : '#6f7b73';
                });
            }
        })();
    </script>
</body>
</html>
