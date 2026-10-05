<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Secure Login';
$error = null;

function login_redirect_for_role(string $role): string {
    return match ($role) {
        'Vice President' => 'vice-president/dashboard.php',
        'OVPRE Staff' => 'ovpre-staff/dashboard.php',
        'Research Coordinator' => 'research-coordinator/dashboard.php',
        'Extension Coordinator' => 'extension-coordinator/dashboard.php',
        default => 'dashboard.php',
    };
}

if (current_user()) {
    redirect_to(login_redirect_for_role((string) current_user()['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'Active' || ($user['locked_until'] && strtotime($user['locked_until']) > time()) || !password_verify($password, $user['password'])) {
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
    } else {
        db()->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        log_action((int) $user['id'], 'Login completed');
        log_login_event((int) $user['id'], $username, 'Success');
        redirect_to(login_redirect_for_role((string) $user['role']));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> | <?php echo APP_NAME; ?></title>
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
                <div class="demo-accounts" aria-label="Example accounts">
                    <strong>Seed Accounts</strong>
                    <span>Username: admin</span>
                    <span>Password: admin123</span>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
