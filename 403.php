<?php
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(403);

$user = current_user();
$dashboardUrl = $user ? (role_dashboard_path((string)$user['role']) ?? 'dashboard.php') : 'index.php';
$roleName = $user ? ($user['role'] ?? 'User') : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Clearance Required | I-Record</title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261105-logo">
    <link rel="stylesheet" href="assets/css/style.css?v=20261111-error">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
    <div class="error-page-wrapper">
        <article class="error-card error-403">
            <a href="<?php echo h($dashboardUrl); ?>" class="error-brand" title="Go to Dashboard">
                <img src="assets/img/ovpre-logo.jpg" alt="OVPRE Logo">
                <div class="error-brand-text">
                    <strong>I-Record Portal</strong>
                    <span>Office of the Vice President for Research &amp; Extension</span>
                </div>
            </a>

            <div class="error-illustration">
                <i class="ri-shield-keyhole-line"></i>
            </div>

            <span class="error-code-badge">403 FORBIDDEN</span>
            <h1 class="error-title">Access Clearance Required</h1>
            <p class="error-description">
                Your current account role (<strong><?php echo h($roleName); ?></strong>) does not have clearance or administrative privileges to access this specific module.
            </p>

            <div class="error-actions">
                <a href="<?php echo h($dashboardUrl); ?>" class="error-btn-primary">
                    <i class="ri-dashboard-line"></i> Return to Authorized Dashboard
                </a>
                <a href="logout.php" class="error-btn-secondary">
                    <i class="ri-logout-box-line"></i> Switch Account
                </a>
                <a href="index.php" class="error-btn-secondary">
                    <i class="ri-home-4-line"></i> Home
                </a>
            </div>

            <div class="error-quick-links">
                <p>Authorized Portals</p>
                <div class="error-links-grid">
                    <a href="research.php" class="error-link-pill"><i class="ri-flask-line"></i> Research</a>
                    <a href="extension.php" class="error-link-pill"><i class="ri-community-line"></i> Extension</a>
                    <a href="announcements.php" class="error-link-pill"><i class="ri-megaphone-line"></i> Announcements</a>
                    <a href="activities.php" class="error-link-pill"><i class="ri-calendar-event-line"></i> Activities</a>
                </div>
            </div>

            <div class="error-footer">
                <span>Please contact the <strong>OVPRE Secretariat</strong> or your designated College Coordinator if you require role elevation.</span>
            </div>
        </article>
    </div>
</body>
</html>
