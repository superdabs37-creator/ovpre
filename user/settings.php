<?php
require_once 'includes/user-layout.php';
$user = require_end_user();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['user_notify'] = isset($_POST['notify']);
    $_SESSION['user_theme'] = ($_POST['theme'] ?? 'light-mode') === 'dark-mode' ? 'dark-mode' : 'light-mode';
    flash('success', 'Settings saved.');
    redirect_to('settings.php');
}
$notice = flash();
$user = user_render_header('Settings', 'settings');
?>
<section class="simple-header"><div><h2>Settings</h2><p>Adjust the friendly portal to your preference.</p></div></section>
<?php user_notice($notice); ?>
<form class="settings-list" method="post">
    <?php echo csrf_field(); ?>
    <article class="setting-row"><div><h3>Notifications</h3><p>Receive document, announcement, and system updates.</p></div><input type="checkbox" name="notify" <?php echo ($_SESSION['user_notify'] ?? true) ? 'checked' : ''; ?>></article>
    <article class="setting-row"><div><h3>Theme</h3><p>Choose a light or dark display.</p></div><select name="theme"><option value="light-mode" <?php echo ($_SESSION['user_theme'] ?? 'light-mode') === 'light-mode' ? 'selected' : ''; ?>>Light</option><option value="dark-mode" <?php echo ($_SESSION['user_theme'] ?? '') === 'dark-mode' ? 'selected' : ''; ?>>Dark</option></select></article>
    <article class="setting-row"><div><h3>Password</h3><p>Change your password from your profile page.</p></div><a class="soft-btn" href="profile.php">Open Profile</a></article>
    <div class="actions"><button class="primary-btn">Save settings</button><a class="danger-btn" href="../logout.php"><i class="ri-logout-circle-line"></i> Logout</a></div>
</form>
<?php user_render_footer(); ?>
