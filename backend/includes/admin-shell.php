<?php
require_once __DIR__ . '/portal-data.php';

if (!function_exists('renderModuleHeader')) {
function renderModuleHeader($title, $subtitle, $icon = 'ri-dashboard-line') {
?>
    <section class="module-header">
        <div class="module-icon"><i class="<?php echo h($icon); ?>"></i></div>
        <div>
            <p>College Document Management System</p>
            <h2><?php echo h($title); ?></h2>
            <span><?php echo h($subtitle); ?></span>
        </div>
    </section>
<?php
}
}

if (!function_exists('renderStatusPill')) {
function renderStatusPill($status) {
    echo '<span class="status-pill status-' . h(statusClass($status)) . '">' . h($status) . '</span>';
}
}
?>
