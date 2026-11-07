<?php
require_once 'includes/layout.php';
require_once 'includes/admin-shell.php';
$user = require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $strongPasswords = isset($_POST['strong_passwords']) ? '1' : '0';
    $sessionTimeoutEnabled = isset($_POST['session_timeout_enabled']) ? '1' : '0';
    $sessionTimeoutMinutes = max(5, min(480, (int) ($_POST['session_timeout_minutes'] ?? 30)));
    $maxFileSizeMb = max(1, min(100, (int) ($_POST['max_file_size_mb'] ?? 25)));
    $allowedTypes = trim((string) ($_POST['allowed_file_types'] ?? 'PDF, DOCX, XLSX, PPTX'));
    if ($allowedTypes === '') {
        $allowedTypes = 'PDF, DOCX, XLSX, PPTX';
    }
    $requireAdminRemarks = isset($_POST['require_admin_remarks']) ? '1' : '0';
    $notifyOnApproval = isset($_POST['notify_on_approval']) ? '1' : '0';

    set_system_setting('strong_passwords', $strongPasswords);
    set_system_setting('session_timeout_enabled', $sessionTimeoutEnabled);
    set_system_setting('session_timeout_minutes', (string) $sessionTimeoutMinutes);
    set_system_setting('max_file_size_mb', (string) $maxFileSizeMb);
    set_system_setting('allowed_file_types', $allowedTypes);
    set_system_setting('require_admin_remarks', $requireAdminRemarks);
    set_system_setting('notify_on_approval', $notifyOnApproval);

    log_action((int) $user['id'], 'Updated system settings');
    flash('success', 'System settings saved and applied across all modules.');
    redirect_to('admin-settings.php');
}

$notice = flash();
$strongPasswords = get_system_setting('strong_passwords', '1') === '1';
$sessionTimeoutEnabled = get_system_setting('session_timeout_enabled', '1') === '1';
$sessionTimeoutMinutes = (int) get_system_setting('session_timeout_minutes', 30);
$maxFileSizeMb = (int) get_system_setting('max_file_size_mb', 25);
$allowedTypes = (string) get_system_setting('allowed_file_types', 'PDF, DOCX, XLSX, PPTX');
$requireAdminRemarks = get_system_setting('require_admin_remarks', '1') === '1';
$notifyOnApproval = get_system_setting('notify_on_approval', '1') === '1';

renderHeader('System Settings', 'settings');
renderModuleHeader('System Settings', 'Configure security rules, upload limits, notifications, and workflow defaults.', 'ri-settings-3-line');
?>

<?php if ($notice): ?>
    <div class="flash flash-<?php echo h($notice['type']); ?>" style="margin-bottom: 20px;">
        <i class="ri-<?php echo $notice['type'] === 'success' ? 'checkbox-circle' : 'error-warning'; ?>-line"></i>
        <span><?php echo h($notice['message']); ?></span>
    </div>
<?php endif; ?>

<section class="panel" style="margin-bottom: 20px;">
    <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 style="margin: 0 0 4px; font-size: 1.25rem;">Database Protection & Snapshots</h2>
            <p style="margin: 0; color: var(--muted); font-size: 0.9rem;">Create system backups or merge historical data records.</p>
        </div>
        <a class="primary-btn" href="system-backup.php"><i class="ri-database-2-line"></i> Backup and Recovery</a>
    </div>
</section>

<form method="post" action="admin-settings.php">
    <?php echo csrf_field(); ?>
    <section class="settings-grid">
        <!-- Security Settings -->
        <article class="panel setting-card">
            <i class="ri-shield-keyhole-line"></i>
            <div>
                <h3 style="margin: 0 0 4px;">Security Settings</h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--muted);">Password rules and active session protection.</p>
            </div>
            
            <label class="toggle-row">
                <span>Require strong passwords<br><small style="font-weight: 400; color: var(--muted);">Require letters & numbers on registration</small></span>
                <input type="checkbox" name="strong_passwords" value="1" <?php echo $strongPasswords ? 'checked' : ''; ?>>
            </label>
            
            <label class="toggle-row">
                <span>Enable session timeout<br><small style="font-weight: 400; color: var(--muted);">Automatically end idle sessions</small></span>
                <input type="checkbox" name="session_timeout_enabled" value="1" <?php echo $sessionTimeoutEnabled ? 'checked' : ''; ?>>
            </label>

            <label class="field" style="margin-top: 6px;">
                <span>Session Timeout (Minutes)</span>
                <input type="number" name="session_timeout_minutes" min="5" max="480" value="<?php echo h($sessionTimeoutMinutes); ?>" required>
            </label>
        </article>

        <!-- Upload Settings -->
        <article class="panel setting-card">
            <i class="ri-upload-cloud-line"></i>
            <div>
                <h3 style="margin: 0 0 4px;">Upload & Storage</h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--muted);">Limits for file uploads across documents & projects.</p>
            </div>
            
            <label class="field">
                <span>Max File Size (MB)</span>
                <input type="number" name="max_file_size_mb" min="1" max="100" value="<?php echo h($maxFileSizeMb); ?>" required>
            </label>
            
            <label class="field">
                <span>Allowed File Extensions</span>
                <input type="text" name="allowed_file_types" value="<?php echo h($allowedTypes); ?>" placeholder="e.g. PDF, DOCX, XLSX, PPTX" required>
            </label>

            <div style="font-size: 0.82rem; color: var(--muted); padding: 8px 12px; background: var(--surface); border-radius: 10px;">
                <i class="ri-information-line"></i> Separate extensions with commas. Case-insensitive.
            </div>
        </article>

        <!-- Workflow Defaults -->
        <article class="panel setting-card">
            <i class="ri-route-line"></i>
            <div>
                <h3 style="margin: 0 0 4px;">Workflow Defaults</h3>
                <p style="margin: 0; font-size: 0.85rem; color: var(--muted);">Reviewer feedback requirements & notifications.</p>
            </div>
            
            <label class="toggle-row">
                <span>Require admin remarks<br><small style="font-weight: 400; color: var(--muted);">Mandatory remarks on rejection / revision</small></span>
                <input type="checkbox" name="require_admin_remarks" value="1" <?php echo $requireAdminRemarks ? 'checked' : ''; ?>>
            </label>
            
            <label class="toggle-row">
                <span>Notify on review & approval<br><small style="font-weight: 400; color: var(--muted);">Alert proponent on status updates</small></span>
                <input type="checkbox" name="notify_on_approval" value="1" <?php echo $notifyOnApproval ? 'checked' : ''; ?>>
            </label>

            <div style="font-size: 0.82rem; color: var(--muted); padding: 8px 12px; background: var(--surface); border-radius: 10px;">
                <i class="ri-notification-3-line"></i> Creates in-app alerts in real-time notification feed.
            </div>
        </article>
    </section>

    <div style="margin-top: 24px; display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
        <button type="submit" class="primary-btn" style="padding: 12px 24px; font-size: 0.95rem;">
            <i class="ri-save-line"></i> Save System Settings
        </button>
    </div>
</form>

<?php renderFooter(); ?>
