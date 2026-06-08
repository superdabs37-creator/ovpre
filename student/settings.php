<?php
require_once 'includes/student-layout.php';
$user = require_student();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['student_theme'] = in_array($_POST['theme'] ?? 'theme-light', ['theme-light', 'theme-contrast'], true) ? $_POST['theme'] : 'theme-light';
    $_SESSION['student_notify_document'] = isset($_POST['notify_document']);
    $_SESSION['student_notify_announcement'] = isset($_POST['notify_announcement']);
    $_SESSION['student_notify_system'] = isset($_POST['notify_system']);
    flash('success', 'Settings saved.');
    redirect_to('settings.php');
}

$notice = flash();
$user = student_render_header('Settings', 'settings');
?>
<section class="module-header"><div class="module-icon"><i class="ri-settings-3-line"></i></div><div><p>Preferences</p><h2>Settings</h2><span>Manage security visibility, notification choices, and student portal theme.</span></div></section>
<?php student_notice($notice); ?>
<form method="post" class="settings-grid">
    <?php echo csrf_field(); ?>
    <article class="panel setting-card">
        <i class="ri-shield-keyhole-line"></i><h3>Security Settings</h3>
        <label class="toggle-row"><span>Session protected login</span><input type="checkbox" checked disabled></label>
        <label class="toggle-row"><span>CSRF form protection</span><input type="checkbox" checked disabled></label>
        <label class="toggle-row"><span>Role-based access</span><input type="checkbox" checked disabled></label>
        <a class="danger-btn" href="profile.php"><i class="ri-lock-password-line"></i> Change Password</a>
    </article>
    <article class="panel setting-card">
        <i class="ri-notification-badge-line"></i><h3>Notification Preferences</h3>
        <label class="toggle-row"><span>Document updates</span><input type="checkbox" name="notify_document" <?php echo ($_SESSION['student_notify_document'] ?? true) ? 'checked' : ''; ?>></label>
        <label class="toggle-row"><span>Announcements</span><input type="checkbox" name="notify_announcement" <?php echo ($_SESSION['student_notify_announcement'] ?? true) ? 'checked' : ''; ?>></label>
        <label class="toggle-row"><span>System notices</span><input type="checkbox" name="notify_system" <?php echo ($_SESSION['student_notify_system'] ?? true) ? 'checked' : ''; ?>></label>
    </article>
    <article class="panel setting-card">
        <i class="ri-palette-line"></i><h3>Theme Settings</h3>
        <label class="field"><span>Portal Theme</span><select name="theme"><option value="theme-light" <?php echo ($_SESSION['student_theme'] ?? 'theme-light') === 'theme-light' ? 'selected' : ''; ?>>Green Academic</option><option value="theme-contrast" <?php echo ($_SESSION['student_theme'] ?? '') === 'theme-contrast' ? 'selected' : ''; ?>>High Contrast</option></select></label>
        <button class="primary-btn" type="submit"><i class="ri-save-line"></i> Save Settings</button>
    </article>
</form>
<?php student_render_footer(); ?>
