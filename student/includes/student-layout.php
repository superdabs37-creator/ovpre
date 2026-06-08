<?php
declare(strict_types=1);

require_once __DIR__ . '/components.php';

function student_active(string $page, string $currentPage): string {
    return $page === $currentPage ? 'active' : '';
}

function student_render_header(string $pageTitle, string $currentPage): array {
    $user = require_student();
    $name = $user['fullname'] ?? 'Student';
    $initials = student_initials($name);
    $unreadStmt = student_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = "Unread"');
    $unreadStmt->execute([(int) $user['id']]);
    $unread = (int) $unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> | Student Portal | <?php echo h(APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body class="<?php echo h($_SESSION['student_theme'] ?? 'theme-light'); ?>">
    <div class="student-shell">
        <aside class="student-sidebar" id="studentSidebar">
            <a class="student-brand" href="dashboard.php">
                <span><i class="ri-graduation-cap-line"></i></span>
                <strong>I-Record</strong>
            </a>
            <nav class="student-nav">
                <a class="<?php echo student_active('dashboard', $currentPage); ?>" href="dashboard.php"><i class="ri-dashboard-line"></i><span>Dashboard</span></a>
                <a class="<?php echo student_active('documents', $currentPage); ?>" href="documents.php"><i class="ri-folder-3-line"></i><span>My Documents</span></a>
                <a class="<?php echo student_active('upload', $currentPage); ?>" href="upload.php"><i class="ri-upload-cloud-line"></i><span>Upload</span></a>
                <a class="<?php echo student_active('track', $currentPage); ?>" href="track.php"><i class="ri-route-line"></i><span>Track Status</span></a>
                <a class="<?php echo student_active('announcements', $currentPage); ?>" href="announcements.php"><i class="ri-megaphone-line"></i><span>Announcements</span></a>
                <a class="<?php echo student_active('notifications', $currentPage); ?>" href="notifications.php"><i class="ri-notification-3-line"></i><span>Notifications</span><?php if ($unread): ?><b><?php echo $unread; ?></b><?php endif; ?></a>
                <a class="<?php echo student_active('history', $currentPage); ?>" href="history.php"><i class="ri-history-line"></i><span>History</span></a>
                <a class="<?php echo student_active('profile', $currentPage); ?>" href="profile.php"><i class="ri-user-line"></i><span>Profile</span></a>
                <a class="<?php echo student_active('settings', $currentPage); ?>" href="settings.php"><i class="ri-settings-3-line"></i><span>Settings</span></a>
            </nav>
            <div class="student-sidebar-note">
                <i class="ri-shield-check-line"></i>
                <span>Secure OVPRE academic record management</span>
            </div>
        </aside>
        <div class="student-page">
            <header class="student-topbar">
                <button class="icon-btn menu-toggle" type="button" aria-label="Toggle menu" data-student-sidebar-toggle><i class="ri-menu-line"></i></button>
                <div class="student-title">
                    <p><?php echo h(APP_ORG); ?></p>
                    <h1><?php echo h($pageTitle); ?></h1>
                </div>
                <a class="topbar-notification" href="notifications.php" aria-label="Notifications">
                    <i class="ri-notification-3-line"></i>
                    <?php if ($unread): ?><span><?php echo $unread; ?></span><?php endif; ?>
                </a>
                <div class="profile-menu">
                    <button class="profile-button" type="button" data-profile-toggle>
                        <span class="avatar"><?php echo h($initials); ?></span>
                        <span><?php echo h($name); ?></span>
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="profile-dropdown" data-profile-dropdown>
                        <a href="profile.php"><i class="ri-user-line"></i> Profile</a>
                        <a href="settings.php"><i class="ri-settings-3-line"></i> Settings</a>
                        <a href="../logout.php"><i class="ri-logout-circle-line"></i> Logout</a>
                    </div>
                </div>
            </header>
            <main class="student-content">
<?php
    return $user;
}

function student_render_footer(): void {
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
