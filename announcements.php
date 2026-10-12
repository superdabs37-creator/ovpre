<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff']);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        db()->prepare('INSERT INTO announcements (title, content, audience, status, posted_by, created_at) VALUES (?, ?, ?, "Published", ?, NOW())')
            ->execute([trim($_POST['title']), trim($_POST['content']), $_POST['audience'], $user['id']]);
        $announcementId = (int) db()->lastInsertId();
        foreach (db()->query('SELECT id FROM users WHERE status = "Active"') as $recipient) {
            notify_user((int) $recipient['id'], 'New announcement: ' . trim($_POST['title']));
        }
        log_action((int) $user['id'], "Published announcement #{$announcementId}");
        flash('success', 'Announcement published and notifications sent.');
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
<section class="module-header"><div class="module-icon"><i class="ri-megaphone-line"></i></div><div><p>Communication</p><h2>Announcements</h2><span>Create and publish updates for faculty, researchers, and staff.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel modal-launcher-panel"><div class="panel-heading"><div><h2><?php echo $archived ? 'Archived Announcements' : 'Announcements'; ?></h2><span><?php echo $archived ? 'Archived announcements.' : 'Create and publish an update for your audience.'; ?></span></div><div class="action-row"><?php if (!$archived): ?><button class="primary-btn" type="button" data-open-modal="createAnnouncementModal"><i class="ri-add-line"></i> Create Announcement</button><?php endif; ?></div></div></section>
<?php if (!$archived): ?>
<div class="modal" id="createAnnouncementModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createAnnouncementTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card simple-submit-modal">
        <button class="modal-close" type="button" data-close-modal aria-label="Close form"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head"><span class="category-badge">Announcement</span><h2 id="createAnnouncementTitle">Create Announcement</h2><p>Choose who can see this announcement.</p></div>
        <form class="announcement-form" method="post">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
            <label class="field"><span>Audience</span><select name="audience"><option>All</option><option>Faculty</option><option>Researchers</option><option>Staff</option></select></label>
            <label class="field"><span>Announcement Title</span><input name="title" required></label>
            <label class="field"><span>Announcement Content</span><textarea name="content" rows="6" required></textarea></label>
            <button class="primary-btn post-btn" type="submit"><i class="ri-send-plane-line"></i> Publish Announcement</button>
        </form>
    </article>
</div>
<?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2><?php echo $archived ? 'Archived Announcements' : 'Announcement List'; ?></h2><span><?php echo count($announcements); ?> total</span></div>
    <div class="announcement-list">
        <?php foreach ($announcements as $item): ?>
            <article class="list-card">
                <div><h3><?php echo h($item['title']); ?></h3><p><span class="dept-pill"><?php echo h($item['audience']); ?></span> <?php echo h($item['status']); ?> by <?php echo h($item['fullname']); ?> on <?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></p></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php renderFooter(); ?>
