<?php
require_once 'includes/student-layout.php';
$user = require_student();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'mark_all') {
        student_db()->prepare('UPDATE notifications SET status = "Read" WHERE user_id = ?')->execute([(int) $user['id']]);
        flash('success', 'All notifications marked as read.');
    } elseif (($_POST['action'] ?? '') === 'mark_one') {
        student_db()->prepare('UPDATE notifications SET status = "Read" WHERE id = ? AND user_id = ?')->execute([(int) $_POST['id'], (int) $user['id']]);
    }
    redirect_to('notifications.php');
}
$notice = flash();
$notifications = student_notifications((int) $user['id']);
$user = student_render_header('Notifications', 'notifications');
?>
<section class="module-header"><div class="module-icon"><i class="ri-notification-3-line"></i></div><div><p>Notification Center</p><h2>Notifications</h2><span>Document, announcement, and system updates for your account.</span></div></section>
<?php student_notice($notice); ?>
<section class="tool-panel"><div class="search-control"><i class="ri-search-line"></i><input data-card-search type="search" placeholder="Search notifications"></div><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="mark_all"><button class="primary-btn" id="markAllRead"><i class="ri-check-double-line"></i> Mark All as Read</button></form></section>
<section class="notification-stack">
    <?php foreach ($notifications as $note): $messageText = strtolower($note['message']); $type = strpos($messageText, 'document') !== false ? 'Document' : (strpos($messageText, 'announcement') !== false ? 'Announcement' : 'System'); ?>
    <article class="notification-card <?php echo h(strtolower($note['status'])); ?>" data-card>
        <span class="notification-dot"></span>
        <div><span class="category-badge"><?php echo h($type); ?></span><h3><?php echo h($type); ?> Update</h3><p><?php echo h($note['message']); ?></p><small><?php echo h(date('M d, Y h:i A', strtotime($note['created_at']))); ?></small></div>
        <?php if ($note['status'] === 'Unread'): ?><form method="post" class="push-right"><?php echo csrf_field(); ?><input type="hidden" name="action" value="mark_one"><input type="hidden" name="id" value="<?php echo (int) $note['id']; ?>"><button class="ghost-btn"><i class="ri-check-line"></i> Read</button></form><?php endif; ?>
    </article>
    <?php endforeach; ?>
    <?php if (!$notifications): ?><article class="panel empty-state"><i class="ri-notification-off-line"></i><h2>No notifications</h2><p>You are all caught up.</p></article><?php endif; ?>
</section>
<?php student_render_footer(); ?>
