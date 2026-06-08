<?php
$user = portal_require_user();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('UPDATE notifications SET status = "Read" WHERE id = ? AND user_id = ?')->execute([(int) $_POST['id'], (int) $user['id']]);
    redirect_to('notifications.php');
}
$user = portal_header('Notifications', 'notifications');
$notifications = portal_notifications((int) $user['id']);
?>
<section class="notification-list"><?php foreach ($notifications as $note): ?><article class="panel notification-card <?php echo h(strtolower($note['status'])); ?>"><i class="ri-notification-3-line"></i><div><h3><?php echo h($note['status']); ?></h3><p><?php echo h($note['message']); ?></p><small><?php echo h(date('M d, Y h:i A', strtotime($note['created_at']))); ?></small></div><?php if ($note['status'] === 'Unread'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $note['id']; ?>"><button class="ghost-btn">Mark as Read</button></form><?php endif; ?></article><?php endforeach; ?><?php if (!$notifications): ?><article class="panel"><p>No notifications yet.</p></article><?php endif; ?></section>
<?php portal_footer(); ?>
