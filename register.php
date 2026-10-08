<?php
require_once __DIR__ . '/includes/bootstrap.php';

$error = null;
$notice = flash();
$form = ['fullname' => '', 'username' => '', 'email' => '', 'role' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($form) as $field) {
        $value = $_POST[$field] ?? '';
        $form[$field] = is_string($value) ? trim($value) : '';
    }
    $passwordValue = $_POST['password'] ?? '';
    $confirmValue = $_POST['confirm_password'] ?? '';
    $password = is_string($passwordValue) ? $passwordValue : '';
    $confirmPassword = is_string($confirmValue) ? $confirmValue : '';

    if (!in_array($form['role'], SELF_REGISTERABLE_ROLES, true)) {
        $error = 'Choose a valid account type.';
    } elseif ($form['fullname'] === '' || strlen($form['fullname']) > 160) {
        $error = 'Enter your full name (up to 160 characters).';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $form['username'])) {
        $error = 'Username must be 3–80 characters and use only letters, numbers, dots, dashes, or underscores.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 190) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Use at least 12 characters with an uppercase letter, lowercase letter, number, and symbol.';
    } elseif (!hash_equals($password, $confirmPassword)) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $duplicate = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
            $duplicate->execute([$form['username'], $form['email']]);
            $existing = $duplicate->fetch();
            if ($existing) {
                $error = strcasecmp((string) $existing['username'], $form['username']) === 0
                    ? 'That username is already in use.'
                    : 'That email address is already registered.';
            } else {
                db()->beginTransaction();
                $insert = db()->prepare('INSERT INTO users (fullname, email, username, password, role, status, login_attempts, created_at) VALUES (?, ?, ?, ?, ?, "Pending", 0, NOW())');
                $insert->execute([
                    $form['fullname'],
                    $form['email'],
                    $form['username'],
                    password_hash($password, PASSWORD_DEFAULT),
                    $form['role'],
                ]);
                $newUserId = (int) db()->lastInsertId();
                log_action($newUserId, 'Submitted registration for ' . $form['role']);
                db()->commit();
                flash('success', 'Registration successful. Your account is now pending approval by the System Administrator.');
                redirect_to('index.php');
            }
        } catch (PDOException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $error = 'That username or email address is already registered.';
            } else {
                error_log('Registration failed: ' . $exception->getMessage());
                $error = 'Registration could not be completed. Please try again later.';
            }
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('Registration failed: ' . $exception->getMessage());
            $error = 'Registration could not be completed. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | <?php echo h(APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261009-registration">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-card">
            <div class="login-logo"><img src="assets/img/ovpre-logo.jpg" alt="OVPRE Logo"></div>
            <div class="login-copy"><p><?php echo h(APP_ORG); ?></p><h1>Create Account</h1></div>
            <?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="notice error"><i class="ri-error-warning-line"></i><?php echo h($error); ?></div><?php endif; ?>
            <form class="login-form" method="post" autocomplete="on">
                <?php echo csrf_field(); ?>
                <label for="fullname">Full Name</label>
                <div class="input-control"><i class="ri-user-line"></i><input id="fullname" name="fullname" maxlength="160" autocomplete="name" value="<?php echo h($form['fullname']); ?>" required></div>
                <label for="username">Username</label>
                <div class="input-control"><i class="ri-at-line"></i><input id="username" name="username" maxlength="80" autocomplete="username" value="<?php echo h($form['username']); ?>" required></div>
                <label for="email">Email</label>
                <div class="input-control"><i class="ri-mail-line"></i><input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?php echo h($form['email']); ?>" required></div>
                <label for="role">Account Type</label>
                <div class="input-control"><i class="ri-shield-user-line"></i><select id="role" name="role" required><option value="">Choose account type</option><?php foreach (SELF_REGISTERABLE_ROLES as $role): ?><option value="<?php echo h($role); ?>" <?php echo $form['role'] === $role ? 'selected' : ''; ?>><?php echo h($role); ?></option><?php endforeach; ?></select></div>
                <label for="password">Password</label>
                <div class="input-control"><i class="ri-lock-line"></i><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required></div>
                <label for="confirm-password">Confirm Password</label>
                <div class="input-control"><i class="ri-lock-password-line"></i><input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="12" required></div>
                <p class="muted">Use at least 12 characters, including uppercase and lowercase letters, a number, and a symbol.</p>
                <button class="primary-btn full-btn" type="submit"><i class="ri-user-add-line"></i> Create Account</button>
                <a class="portal-link full-btn" href="index.php">Back to Login</a>
            </form>
        </section>
    </main>
</body>
</html>
