<?php
require_once 'includes/user-layout.php';
$user = require_end_user();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'read') {
        user_db()->prepare('UPDATE notifications SET status = "Read" WHERE id = ? AND user_id = ?')->execute([$id, (int) $user['id']]);
    } elseif (($_POST['action'] ?? '') === 'delete') {
        user_db()->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')->execute([$id, (int) $user['id']]);
    }
    redirect_to('notifications.php');
}
$notes = user_notifications((int) $user['id']);
$user = user_render_header('Notifications', 'notifications');
?>
<section class="simple-header"><div><h2>Notifications</h2><p>Clean updates about your files and system messages.</p></div></section>
<section class="note-list full">
    <?php foreach ($notes as $note): $text = strtolower($note['message']); $type = strpos($text, 'announcement') !== false ? 'Announcement' : (strpos($text, 'document') !== false || strpos($text, 'file') !== false ? 'Document update' : 'System message'); ?>
    <article class="note <?php echo h(strtolower($note['status'])); ?>">
        <span></span><div><strong><?php echo h($type); ?></strong><p><?php echo h($note['message']); ?></p><small><?php echo h(date('M d, Y h:i A', strtotime($note['created_at']))); ?></small></div>
        <form method="post" class="note-actions"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $note['id']; ?>"><?php if ($note['status'] === 'Unread'): ?><button class="soft-btn" name="action" value="read">Mark as read</button><?php endif; ?><button class="danger-btn" name="action" value="delete">Delete</button></form>
    </article>
    <?php endforeach; ?>
    <?php if (!$notes): ?><article class="empty-card"><i class="ri-notification-off-line"></i><h3>No notifications</h3><p>You are all caught up.</p></article><?php endif; ?>
</section>
<?php user_render_footer(); ?>
