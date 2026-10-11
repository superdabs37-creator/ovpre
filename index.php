<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Secure Login';
$error = null;

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
    $usernameValue = $_POST['username'] ?? '';
    $passwordValue = $_POST['password'] ?? '';
    $username = is_string($usernameValue) ? trim($usernameValue) : '';
    $password = is_string($passwordValue) ? $passwordValue : '';
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $passwordValid = $user && password_verify($password, (string) $user['password']);
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
    } elseif ($user['status'] === 'Pending') {
        log_action((int) $user['id'], 'Login blocked: pending account');
        log_login_event((int) $user['id'], $username, 'Denied: Pending');
        $error = 'Your account is still pending approval by the System Administrator.';
    } elseif ($user['status'] === 'Rejected') {
        log_action((int) $user['id'], 'Login blocked: rejected account');
        log_login_event((int) $user['id'], $username, 'Denied: Rejected');
        $error = 'Your account registration was rejected.';
    } elseif ($user['status'] === 'Suspended') {
        log_action((int) $user['id'], 'Login blocked: suspended account');
        log_login_event((int) $user['id'], $username, 'Denied: Suspended');
        $error = 'Your account has been suspended. Please contact the System Administrator.';
    } elseif ($user['status'] !== 'Active') {
        log_login_event((int) $user['id'], $username, 'Denied: inactive');
        $error = 'This account is inactive or locked. Contact the System Administrator.';
    } elseif (($destination = role_dashboard_path((string) $user['role'])) === null) {
        log_action((int) $user['id'], 'Login denied: unrecognized role');
        log_login_event((int) $user['id'], $username, 'Denied: invalid role');
        $error = 'This account is not configured for access. Contact the System Administrator.';
    } else {
        db()->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        log_action((int) $user['id'], 'Login completed');
        log_login_event((int) $user['id'], $username, 'Success');
        redirect_to($destination);
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
    <link rel="stylesheet" href="assets/css/style.css?v=20261006-login-office-highlight">
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
            <form class="login-form" method="post">
                <?php echo csrf_field(); ?>
                <label for="username">Username</label>
                <div class="input-control">
                    <i class="ri-user-line"></i>
                    <input type="text" id="username" name="username" placeholder="Enter username" required>
                </div>

                <label for="password">Password</label>
                <div class="input-control">
                    <i class="ri-lock-line"></i>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>

                <div class="login-tools">
                    <label class="check-control">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>

                <button class="primary-btn full-btn" type="submit">
                    <i class="ri-login-circle-line"></i>
                    Login
                </button>
                <p class="portal-link full-btn">Don't have an account? <a href="register.php">Register here</a></p>
            </form>
        </section>
    </main>
</body>
</html>
