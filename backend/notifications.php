<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all'])) {
        db()->prepare('UPDATE notifications SET status = "Read" WHERE user_id = ? AND status = "Unread"')->execute([(int) $user['id']]);
        flash('success', 'All notifications marked as read.');
    } elseif (isset($_POST['mark_selected']) && !empty($_POST['selected_ids'])) {
        $ids = array_map('intval', (array) $_POST['selected_ids']);
        if (!empty($ids)) {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([(int) $user['id']], $ids);
            db()->prepare("UPDATE notifications SET status = 'Read' WHERE user_id = ? AND id IN ($inClause)")->execute($params);
            flash('success', count($ids) . ' notification(s) marked as read.');
        }
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

function get_notif_category($message, $link = '') {
    $text = strtolower($message . ' ' . $link);
    if (strpos($text, 'research') !== false) return 'Research';
    if (strpos($text, 'extension') !== false) return 'Extension';
    if (strpos($text, 'announcement') !== false) return 'Announcements';
    if (strpos($text, 'activit') !== false || strpos($text, 'colloquium') !== false) return 'Activities';
    if (strpos($text, 'document') !== false || strpos($text, 'file') !== false || strpos($text, 'upload') !== false) return 'Records';
    if (strpos($text, 'user') !== false || strpos($text, 'account') !== false || strpos($text, 'security') !== false) return 'User Security';
    return 'General';
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

<section class="panel" style="margin-bottom: 22px;">
    <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <div>
            <h2>File and Submission Alerts</h2>
            <span style="color: var(--muted); font-size: 0.9rem;">Updates about uploads, reviews, endorsements, and coordinator submissions.</span>
        </div>
        <div class="notif-header-actions">
            <?php if ($unreadCount > 0): ?>
                <form method="post" style="display: inline-flex; margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mark_all" value="1">
                    <button class="btn-mark-all notification-btn" type="submit" id="markAllRead">
                        <i class="ri-check-double-line"></i>
                        <span>Mark All as Read (<?php echo $unreadCount; ?>)</span>
                    </button>
                </form>
            <?php endif; ?>
            <a class="btn-view-files notification-btn" href="submitted-files.php">
                <i class="ri-folder-open-line"></i>
                <span>View All Files</span>
            </a>
        </div>
    </div>
</section>

<section class="panel">
    <div class="table-selection-toolbar is-hidden" data-selection-toolbar="notificationsTable" id="notificationsSelectionToolbar" hidden style="display: none; align-items: center; justify-content: space-between; padding: 8px 0; margin-bottom: 12px; background: transparent;">
        <span data-selection-count id="notificationsSelectedCount" style="font-weight: 700; color: #0f172a; font-size: 0.88rem;">0 selected</span>
        <form method="post" id="bulkNotificationsForm" style="margin: 0; display: inline-flex; gap: 8px; align-items: center;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="mark_selected" value="1">
            <button class="action-btn" type="submit" data-bulk-submit style="background: #15803d; color: #ffffff; border: none; font-weight: 700; padding: 7px 14px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <i class="ri-check-line"></i><span>Mark Selected as Read</span>
            </button>
        </form>
    </div>
    <div class="table-scroll">
        <table class="data-table" id="notificationsTable">
            <thead>
                <tr>
                    <th class="table-checkbox-col" style="width: 44px; min-width: 44px; text-align: center;">
                        <label class="select-all-control">
                            <input form="bulkNotificationsForm" type="checkbox" aria-label="Select all notifications" data-select-all="notificationsTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Notification Details</th>
                    <th>Category</th>
                    <th>Received Date</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $isUnread = ($notification['status'] ?? '') === 'Unread';
                    $link = trim((string) ($notification['link'] ?? ''));
                    $category = get_notif_category($notification['message'] ?? '', $link);
                    $createdAt = $notification['created_at'] ?? '';
                    $timeFormatted = $createdAt ? date('M d, Y • h:i A', strtotime($createdAt)) : '';
                    ?>
                    <tr data-table-row data-status="<?php echo $isUnread ? 'Unread' : 'Read'; ?>" data-category="<?php echo h($category); ?>" style="<?php echo $isUnread ? 'background: #f0fdf4;' : ''; ?>">
                        <td class="table-checkbox-col" style="width: 44px; min-width: 44px; text-align: center; vertical-align: middle;">
                            <input form="bulkNotificationsForm" type="checkbox" name="selected_ids[]" value="<?php echo (int) $notification['id']; ?>" aria-label="Select notification" data-row-select="notificationsTable">
                        </td>
                        <td style="vertical-align: middle;">
                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                <div style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo $isUnread ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $isUnread ? '#15803d' : '#64748b'; ?>; display: inline-grid; place-items: center; font-size: 1.15rem; flex-shrink: 0; margin-top: 2px;">
                                    <i class="<?php echo $isUnread ? 'ri-notification-3-fill' : 'ri-notification-3-line'; ?>"></i>
                                </div>
                                <div>
                                    <div style="font-size: 0.92rem; font-weight: <?php echo $isUnread ? '700' : '500'; ?>; color: #0f172a; line-height: 1.45;">
                                        <?php echo h($notification['message']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="vertical-align: middle;">
                            <span class="badge" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; font-weight: 700; font-size: 0.76rem; padding: 3px 10px; border-radius: 999px;">
                                <?php echo h($category); ?>
                            </span>
                        </td>
                        <td style="vertical-align: middle; white-space: nowrap; color: #64748b; font-size: 0.84rem;" data-sort-value="<?php echo h($createdAt); ?>">
                            <i class="ri-time-line"></i> <?php echo h($timeFormatted); ?>
                        </td>
                        <td style="vertical-align: middle;">
                            <span class="badge" style="background: <?php echo $isUnread ? '#dcfce7' : '#f1f5f9'; ?>; color: <?php echo $isUnread ? '#15803d' : '#64748b'; ?>; border: 1px solid <?php echo $isUnread ? '#86efac' : '#cbd5e1'; ?>; font-weight: 700; font-size: 0.74rem; padding: 3px 9px; border-radius: 999px;">
                                <i class="<?php echo $isUnread ? 'ri-record-circle-line' : 'ri-checkbox-circle-line'; ?>"></i>
                                <?php echo $isUnread ? 'Unread' : 'Read'; ?>
                            </span>
                        </td>
                        <td style="vertical-align: middle; text-align: right; white-space: nowrap;">
                            <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end;">
                                <?php if ($link !== ''): ?>
                                    <a class="ghost-btn action-btn notification-btn-open" href="<?php echo h($link); ?>" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 700; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #1e293b; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="ri-arrow-right-up-line"></i> Open
                                    </a>
                                <?php endif; ?>
                                <?php if ($isUnread): ?>
                                    <form method="post" style="display: inline-flex; margin: 0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo (int) $notification['id']; ?>">
                                        <button class="ghost-btn action-btn notification-btn-mark" type="submit" title="Mark as Read" style="padding: 6px 12px; font-size: 0.8rem; font-weight: 700; border-radius: 8px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #15803d; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="ri-check-line"></i> Mark as Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$notifications): ?>
                    <tr data-pagination-empty>
                        <td colspan="6" style="text-align: center; padding: 48px 24px;">
                            <div style="width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 50%; background: #f1f5f9; display: grid; place-items: center; color: #94a3b8; font-size: 1.75rem;">
                                <i class="ri-notification-off-line"></i>
                            </div>
                            <h3 style="margin: 0 0 6px; color: var(--green-900); font-size: 1.15rem; font-weight: 700;">No notifications yet</h3>
                            <p style="margin: 0; color: var(--muted); font-size: 0.92rem;">You're all caught up! New alerts and updates will appear here.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter(); ?>
