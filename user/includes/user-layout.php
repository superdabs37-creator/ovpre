<?php
declare(strict_types=1);

require_once __DIR__ . '/components.php';

function user_active(string $page, string $current): string {
    return $page === $current ? 'active' : '';
}

function user_render_header(string $title, string $current): array {
    $user = require_end_user();
    $unread = user_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = "Unread"');
    $unread->execute([(int) $user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($title); ?> | I-Record User</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="<?php echo h($_SESSION['user_theme'] ?? 'light-mode'); ?>">
    <div class="user-shell">
        <aside class="sidebar" id="userSidebar">
            <a class="brand" href="dashboard.php"><span><i class="ri-folder-cloud-line"></i></span><strong>I-Record</strong></a>
            <nav class="nav">
                <a class="<?php echo user_active('dashboard', $current); ?>" href="dashboard.php"><i class="ri-home-5-line"></i><span>Home</span></a>
                <a class="<?php echo user_active('files', $current); ?>" href="my-files.php"><i class="ri-folder-3-line"></i><span>My Files</span></a>
                <a class="<?php echo user_active('upload', $current); ?>" href="upload.php"><i class="ri-upload-cloud-line"></i><span>Upload</span></a>
                <a class="<?php echo user_active('track', $current); ?>" href="track.php"><i class="ri-route-line"></i><span>Track</span></a>
                <a class="<?php echo user_active('announcements', $current); ?>" href="announcements.php"><i class="ri-megaphone-line"></i><span>News</span></a>
                <a class="<?php echo user_active('notifications', $current); ?>" href="notifications.php"><i class="ri-notification-3-line"></i><span>Notifications</span><?php if ((int) $unread->fetchColumn() > 0): ?><b></b><?php endif; ?></a>
                <a class="<?php echo user_active('profile', $current); ?>" href="profile.php"><i class="ri-user-line"></i><span>Profile</span></a>
                <a class="<?php echo user_active('settings', $current); ?>" href="settings.php"><i class="ri-settings-3-line"></i><span>Settings</span></a>
            </nav>
        </aside>
        <div class="page">
            <header class="topbar">
                <button class="icon-btn" type="button" data-sidebar-toggle aria-label="Open menu"><i class="ri-menu-line"></i></button>
                <div><p><?php echo h(APP_ORG); ?></p><h1><?php echo h($title); ?></h1></div>
                <a class="notify-btn" href="notifications.php"><i class="ri-notification-3-line"></i></a>
                <a class="avatar" href="profile.php"><?php echo h(user_initials($user['fullname'])); ?></a>
            </header>
            <main class="content">
<?php
    return $user;
}

function user_render_footer(): void {
?>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
<?php
}
