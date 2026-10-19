<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all'])) {
        db()->prepare('UPDATE notifications SET status = "Read" WHERE user_id = ? AND status = "Unread"')->execute([(int) $user['id']]);
        flash('success', 'All notifications marked as read.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE notifications SET status = "Read" WHERE id = ? AND user_id = ?')->execute([$id, (int) $user['id']]);
        flash('success', 'Notification marked as read.');
    }
    redirect_to('notifications.php');
}

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([(int) $user['id']]);
$notifications = $stmt->fetchAll();

$unreadCount = 0;
foreach ($notifications as $n) {
    if (($n['status'] ?? '') === 'Unread') {
        $unreadCount++;
    }
}

$notice = flash();
renderHeader('Notifications', 'notifications');
?>
<?php if ($notice): ?>
    <div class="notice <?php echo h($notice['type']); ?>">
        <i class="ri-information-line"></i>
        <span><?php echo h($notice['message']); ?></span>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div>
            <h2>File and Submission Alerts</h2>
            <span style="color: var(--muted); font-size: 0.9rem;">Updates about uploads, reviews, and coordinator submissions.</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-left: auto;">
            <?php if ($unreadCount > 0): ?>
                <form method="post" style="display: inline-flex; margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mark_all" value="1">
                    <button class="ghost-btn" type="submit" id="markAllRead" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ri-check-double-line"></i> Mark All as Read (<?php echo $unreadCount; ?>)
                    </button>
                </form>
            <?php endif; ?>
            <a class="primary-btn" href="submitted-files.php" style="display: inline-flex; align-items: center; gap: 6px;">
                <i class="ri-folder-open-line"></i> View All Files
            </a>
        </div>
    </div>
</section>

<section class="notification-list" data-paginate="10">
    <?php foreach ($notifications as $notification): ?>
        <?php
        $isUnread = ($notification['status'] ?? '') === 'Unread';
        $link = trim((string) ($notification['link'] ?? ''));
        ?>
        <article class="panel notification-card <?php echo $isUnread ? 'unread' : 'read'; ?>">
            <div class="notification-icon-wrap <?php echo $isUnread ? 'unread' : 'read'; ?>">
                <i class="<?php echo $isUnread ? 'ri-notification-3-fill' : 'ri-notification-3-line'; ?>"></i>
            </div>
            <div class="notification-body">
                <p class="notification-message"><?php echo h($notification['message']); ?></p>
                <div class="notification-meta">
                    <span class="notification-time">
                        <i class="ri-time-line"></i> <?php echo h(date('M d, Y • h:i A', strtotime($notification['created_at']))); ?>
                    </span>
                    <span class="notification-badge <?php echo $isUnread ? 'unread' : 'read'; ?>">
                        <i class="<?php echo $isUnread ? 'ri-record-circle-line' : 'ri-checkbox-circle-line'; ?>"></i>
                        <?php echo $isUnread ? 'Unread' : 'Read'; ?>
                    </span>
                </div>
            </div>
            <div class="notification-actions">
                <?php if ($link !== ''): ?>
                    <a class="ghost-btn notification-btn-open" href="<?php echo h($link); ?>">
                        <i class="ri-arrow-right-up-line"></i> Open
                    </a>
                <?php endif; ?>
                <?php if ($isUnread): ?>
                    <form method="post" class="notification-read-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $notification['id']; ?>">
                        <button class="ghost-btn notification-btn-mark" type="submit" title="Mark as Read">
                            <i class="ri-check-line"></i> Mark as Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$notifications): ?>
        <article class="panel" data-pagination-empty style="text-align: center; padding: 48px 24px;">
            <div style="width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 50%; background: #f1f5f9; display: grid; place-items: center; color: #94a3b8; font-size: 1.75rem;">
                <i class="ri-notification-off-line"></i>
            </div>
            <h3 style="margin: 0 0 6px; color: var(--green-900); font-size: 1.15rem; font-weight: 700;">No notifications yet</h3>
            <p style="margin: 0; color: var(--muted); font-size: 0.92rem;">You're all caught up! New alerts and updates will appear here.</p>
        </article>
    <?php endif; ?>
</section>
<?php renderFooter(); ?>
