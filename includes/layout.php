<?php
require_once __DIR__ . '/bootstrap.php';

function activePage($page, $currentPage) {
    return $page === $currentPage ? 'active' : '';
}

function renderHeader($pageTitle, $currentPage) {
    $user = current_user();
    $name = $user['fullname'] ?? 'Guest';
    $initials = implode('', array_map(fn($part) => strtoupper(substr($part, 0, 1)), array_slice(preg_split('/\s+/', trim($name)), 0, 2)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260613-record-folders">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="dashboard.php">
                <span><i class="ri-graduation-cap-line"></i></span>
                <strong><?php echo APP_NAME; ?></strong>
            </a>
            <nav class="sidebar-nav">
                <a class="<?php echo activePage('dashboard', $currentPage); ?>" href="dashboard.php"><i class="ri-dashboard-line"></i><span>Dashboard</span></a>
                <a class="<?php echo activePage('documents', $currentPage); ?>" href="admin-documents.php"><i class="ri-file-search-line"></i><span>Records</span></a>
                <?php if (($user['role'] ?? '') === 'Super Admin'): ?>
                    <a class="<?php echo activePage('users', $currentPage); ?>" href="admin-users.php"><i class="ri-team-line"></i><span>Users</span></a>
                <?php endif; ?>
                <a class="<?php echo activePage('announcements', $currentPage); ?>" href="announcements.php"><i class="ri-megaphone-line"></i><span>Announcements</span></a>
                <a class="<?php echo activePage('research', $currentPage); ?>" href="research.php"><i class="ri-flask-line"></i><span>Research</span></a>
                <a class="<?php echo activePage('extension', $currentPage); ?>" href="extension.php"><i class="ri-community-line"></i><span>Extension</span></a>
                <a class="<?php echo activePage('activities', $currentPage); ?>" href="activities.php"><i class="ri-calendar-event-line"></i><span>Activities</span></a>
                <a class="<?php echo activePage('reports', $currentPage); ?>" href="admin-reports.php"><i class="ri-file-chart-line"></i><span>Reports</span></a>
                <a class="<?php echo activePage('audit', $currentPage); ?>" href="system-activity.php"><i class="ri-history-line"></i><span>System Activity</span></a>
                <a class="<?php echo activePage('settings', $currentPage); ?>" href="admin-settings.php"><i class="ri-settings-3-line"></i><span>Settings</span></a>
            </nav>
            <div class="sidebar-footer">
                <i class="ri-shield-check-line"></i>
                <span>Secure academic records</span>
            </div>
        </aside>
        <div class="page-wrap">
            <header class="topbar">
                <button class="icon-btn menu-toggle" type="button" aria-label="Toggle sidebar" data-toggle-sidebar>
                    <i class="ri-menu-line"></i>
                </button>
                <div class="topbar-title">
                    <p><?php echo h(APP_ORG); ?></p>
                    <h1><?php echo h($pageTitle); ?></h1>
                </div>
                <form class="topbar-search" role="search" data-global-search-form>
                    <i class="ri-search-line"></i>
                    <input type="search" placeholder="Search this page" aria-label="Search visible page content" data-global-search>
                    <button class="topbar-search-clear" type="button" aria-label="Clear search" data-global-search-clear><i class="ri-close-line"></i></button>
                    <button class="topbar-search-submit" type="submit" aria-label="Run search"><i class="ri-arrow-right-line"></i></button>
                    <span class="topbar-search-count" data-global-search-count></span>
                </form>
                <button class="topbar-icon" type="button" aria-label="Notifications">
                    <i class="ri-notification-3-line"></i>
                    <span></span>
                </button>
                <div class="profile-menu">
                    <button class="profile-button" type="button" data-profile-toggle>
                        <span class="avatar"><?php echo h($initials ?: 'IR'); ?></span>
                        <span><?php echo h($name); ?></span>
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="profile-dropdown" data-profile-dropdown>
                        <a href="admin-settings.php"><i class="ri-user-line"></i> Profile</a>
                        <a href="#"><i class="ri-settings-3-line"></i> Settings</a>
                        <a href="logout.php"><i class="ri-logout-circle-line"></i> Logout</a>
                    </div>
                </div>
            </header>
            <main class="content-area">
<?php
}

function renderFooter() {
?>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/app.js?v=20260613-record-folders"></script>
</body>
</html>
<?php
}
?>
