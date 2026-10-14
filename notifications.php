<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare('UPDATE notifications SET status = "Read" WHERE id = ? AND user_id = ?')->execute([$id, (int) $user['id']]);
    redirect_to('notifications.php');
}
$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([(int) $user['id']]);
$notifications = $stmt->fetchAll();
$notice = flash();
renderHeader('Notifications', 'notifications');
?>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel"><div class="panel-heading"><div><h2>File and Submission Alerts</h2><span>Updates about uploads and coordinator submissions.</span></div><a class="primary-btn" href="submitted-files.php"><i class="ri-folder-open-line"></i> View All Files</a></div></section>
<section class="notification-list" data-paginate="10">
    <?php foreach ($notifications as $notification): ?><article class="panel notification-card <?php echo h(strtolower($notification['status'])); ?>"><i class="ri-notification-3-line"></i><div><h3><?php echo h($notification['status']); ?></h3><p><?php echo h($notification['message']); ?></p><small><?php echo h(date('M d, Y h:i A', strtotime($notification['created_at']))); ?></small></div><?php if (!empty($notification['link'])): ?><a class="ghost-btn" href="<?php echo h($notification['link']); ?>"><i class="ri-eye-line"></i> View File</a><?php endif; ?><?php if ($notification['status'] === 'Unread'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $notification['id']; ?>"><button class="ghost-btn" type="submit">Mark as Read</button></form><?php endif; ?></article><?php endforeach; ?>
    <?php if (!$notifications): ?><article class="panel" data-pagination-empty><p>No notifications yet.</p></article><?php endif; ?>
</section>
<?php renderFooter(); ?>
