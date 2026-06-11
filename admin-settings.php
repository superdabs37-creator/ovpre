<?php
require_once 'includes/layout.php';
require_once 'includes/admin-shell.php';
renderHeader('System Settings', 'settings');
renderModuleHeader('System Settings', 'Configure security rules, upload limits, notifications, and workflow defaults.', 'ri-settings-3-line');
?>
<section class="settings-grid">
    <article class="panel setting-card"><i class="ri-shield-keyhole-line"></i><h3>Security Settings</h3><label class="toggle-row"><span>Require strong passwords</span><input checked type="checkbox"></label><label class="toggle-row"><span>Enable session timeout</span><input checked type="checkbox"></label></article>
    <article class="panel setting-card"><i class="ri-upload-cloud-line"></i><h3>Upload Settings</h3><label class="field"><span>Max File Size</span><input value="25MB"></label><label class="field"><span>Allowed Types</span><input value="PDF, DOCX, XLSX, PPTX"></label></article>
    <article class="panel setting-card"><i class="ri-route-line"></i><h3>Workflow Defaults</h3><label class="toggle-row"><span>Require admin remarks</span><input checked type="checkbox"></label><label class="toggle-row"><span>Notify on approval</span><input checked type="checkbox"></label></article>
</section>
<?php renderFooter(); ?>
