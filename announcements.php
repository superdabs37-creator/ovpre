<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin']);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $audience = (string) ($_POST['audience'] ?? '');
        $audienceRoles = announcement_audience_roles($audience);
        if ($audienceRoles === null) {
            flash('error', 'Choose a valid announcement audience.');
            redirect_to('announcements.php');
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        if ($title === '' || $content === '') {
            flash('error', 'Enter an announcement title and content.');
            redirect_to('announcements.php');
        }
        db()->prepare('INSERT INTO announcements (title, content, audience, status, posted_by, created_at) VALUES (?, ?, ?, "Published", ?, NOW())')
            ->execute([$title, $content, $audience, $user['id']]);
        $announcementId = (int) db()->lastInsertId();
        if ($audienceRoles === []) {
            $recipients = db()->query('SELECT id FROM users WHERE status = "Active" AND deleted_at IS NULL');
        } else {
            $rolePlaceholders = implode(', ', array_fill(0, count($audienceRoles), '?'));
            $recipientStmt = db()->prepare('SELECT id FROM users WHERE status = "Active" AND deleted_at IS NULL AND role IN (' . $rolePlaceholders . ')');
            $recipientStmt->execute($audienceRoles);
            $recipients = $recipientStmt;
        }
        foreach ($recipients as $recipient) {
            notify_user((int) $recipient['id'], 'New announcement: ' . $title);
        }
        log_action((int) $user['id'], "Published announcement #{$announcementId}");
        flash('success', 'Announcement published and notifications sent to the selected audience.');
    } elseif ($action === 'archive') {
        db()->prepare('UPDATE announcements SET status = "Archived" WHERE id = ? AND status <> "Archived"')->execute([(int) $_POST['id']]);
        log_action((int) $user['id'], 'Archived announcement #' . (int) $_POST['id']);
        flash('success', 'Announcement archived.');
    } elseif ($action === 'restore') {
        db()->prepare('UPDATE announcements SET status = "Published" WHERE id = ? AND status = "Archived"')->execute([(int) $_POST['id']]);
        log_action((int) $user['id'], 'Restored announcement #' . (int) $_POST['id']);
        flash('success', 'Announcement restored.');
    }
    redirect_to('announcements.php' . (($_GET['archived'] ?? '') === '1' ? '?archived=1' : ''));
}

$notice = flash();
$archived = ($_GET['archived'] ?? '') === '1';
$announcementStatus = $archived ? 'Archived' : 'Published';
$announcementStmt = db()->prepare('SELECT a.*, u.fullname FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.status = ? ORDER BY a.created_at DESC');
$announcementStmt->execute([$announcementStatus]);
$announcements = $announcementStmt->fetchAll();
renderHeader($archived ? 'Archived Announcements' : 'Announcements', 'announcements');
?>
<section class="module-header"><div class="module-icon"><i class="ri-megaphone-line"></i></div><div><p>Communication</p><h2>Announcements</h2><span>Publish updates for directors, coordinators, faculty extensionists, OVPRE staff, or other authorized users.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<?php if (!$archived): ?>
<section class="panel modal-launcher-panel"><div class="panel-heading" style="display: flex; justify-content: flex-end;"><div class="action-row" style="margin-left: auto;"><button class="primary-btn" type="button" data-open-modal="createAnnouncementModal"><i class="ri-add-line"></i> Create Announcement</button></div></div></section>
<?php endif; ?>
<?php if (!$archived): ?>
<div class="modal" id="createAnnouncementModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createAnnouncementTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card simple-submit-modal">
        <button class="modal-close" type="button" data-close-modal aria-label="Close form"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head"><span class="category-badge">Announcement</span><h2 id="createAnnouncementTitle">Create Announcement</h2><p>Choose who can see this announcement.</p></div>
        <form class="announcement-form" method="post">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
            <label class="field"><span>Audience</span><select name="audience" required><option value="" selected disabled>Choose audience</option><?php foreach (['All', 'Directors', 'Coordinators', 'Faculty Extensionists', 'OVPRE Staff', 'Other Authorized Users'] as $audienceOption): ?><option value="<?php echo h($audienceOption); ?>"><?php echo h($audienceOption); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Announcement Title</span><input name="title" required></label>
            <label class="field"><span>Announcement Content</span><textarea name="content" rows="6" required></textarea></label>
            <button class="primary-btn post-btn" type="submit"><i class="ri-send-plane-line"></i> Publish Announcement</button>
        </form>
    </article>
</div>
<?php endif; ?>
<section class="panel">
    <div class="panel-heading"><span><?php echo count($announcements); ?> total</span></div>
    <div class="announcement-list">
        <?php foreach ($announcements as $item): ?>
            <article class="list-card">
                <div><h3><?php echo h($item['title']); ?></h3><p><span class="dept-pill"><?php echo h($item['audience']); ?></span> <?php echo h($item['status']); ?> by <?php echo h($item['fullname']); ?> on <?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></p></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php renderFooter(); ?>
