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
<section class="panel"><div class="panel-heading"><div><h2>File and Submission Alerts</h2><span>Updates about uploads and coordinator submissions.</span></div><a class="primary-btn" href="../submitted-files.php"><i class="ri-folder-open-line"></i> View All Files</a></div></section>
<section class="notification-list" data-paginate="10"><?php foreach ($notifications as $note): ?><article class="panel notification-card <?php echo h(strtolower($note['status'])); ?>"><i class="ri-notification-3-line"></i><div><h3><?php echo h($note['status']); ?></h3><p><?php echo h($note['message']); ?></p><small><?php echo h(date('M d, Y h:i A', strtotime($note['created_at']))); ?></small></div><?php if (!empty($note['link'])): ?><a class="ghost-btn" href="../<?php echo h(ltrim($note['link'], '/')); ?>" target="_blank" rel="noopener"><i class="ri-eye-line"></i> View File</a><?php endif; ?><?php if ($note['status'] === 'Unread'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $note['id']; ?>"><button class="ghost-btn">Mark as Read</button></form><?php endif; ?></article><?php endforeach; ?><?php if (!$notifications): ?><article class="panel" data-pagination-empty><p>No notifications yet.</p></article><?php endif; ?></section>
<?php portal_footer(); ?>
