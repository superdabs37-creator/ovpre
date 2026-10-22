<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin']);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isArchivedRedirect = (($_GET['archived'] ?? '') === '1') || (($_POST['archived_view'] ?? '') === '1');

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
            $recipients = db()->query("SELECT id FROM users WHERE status = 'Active' AND deleted_at IS NULL");
        } else {
            $rolePlaceholders = implode(', ', array_fill(0, count($audienceRoles), '?'));
            $recipientStmt = db()->prepare("SELECT id FROM users WHERE status = 'Active' AND deleted_at IS NULL AND role IN (" . $rolePlaceholders . ")");
            $recipientStmt->execute($audienceRoles);
            $recipients = $recipientStmt;
        }
        foreach ($recipients as $recipient) {
            notify_user((int) $recipient['id'], 'New announcement: ' . $title);
        }
        log_action((int) $user['id'], "Published announcement #{$announcementId}");
        flash('success', 'Announcement published and notifications sent to the selected audience.');
        redirect_to('announcements.php');
    } elseif ($action === 'archive') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE announcements SET status = "Archived" WHERE id = ? AND status <> "Archived"');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_action((int) $user['id'], 'Archived announcement #' . $id);
            flash('success', 'Announcement archived.');
        } else {
            flash('error', 'Announcement could not be found or is already archived.');
        }
    } elseif ($action === 'restore') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE announcements SET status = "Published" WHERE id = ? AND status = "Archived"');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_action((int) $user['id'], 'Restored announcement #' . $id);
            flash('success', 'Announcement restored to published.');
        } else {
            flash('error', 'Announcement could not be found or is already published.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT title FROM announcements WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $ann = $stmt->fetch();
        if (!$ann) {
            flash('error', 'Announcement could not be found.');
        } else {
            db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
            log_action((int) $user['id'], "Permanently deleted announcement #{$id}: {$ann['title']}");
            flash('success', 'Announcement permanently deleted.');
        }
    } elseif ($action === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), static fn($id) => $id > 0)));
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');

        if (!$ids) {
            flash('error', 'No announcements selected.');
            redirect_to('announcements.php' . ($isArchivedRedirect ? '?archived=1' : ''));
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));

        if ($bulkAction === 'archive') {
            db()->prepare("UPDATE announcements SET status = 'Archived' WHERE status <> 'Archived' AND id IN ($marks)")->execute($ids);
            foreach ($ids as $annId) {
                log_action((int) $user['id'], 'Bulk archived announcement #' . $annId);
            }
            flash('success', count($ids) . ' announcement(s) archived.');
            redirect_to('announcements.php');
        } elseif ($bulkAction === 'restore') {
            db()->prepare("UPDATE announcements SET status = 'Published' WHERE status = 'Archived' AND id IN ($marks)")->execute($ids);
            foreach ($ids as $annId) {
                log_action((int) $user['id'], 'Bulk restored announcement #' . $annId);
            }
            flash('success', count($ids) . ' announcement(s) restored.');
            redirect_to('announcements.php?archived=1');
        } elseif ($bulkAction === 'delete') {
            db()->prepare("DELETE FROM announcements WHERE id IN ($marks)")->execute($ids);
            foreach ($ids as $annId) {
                log_action((int) $user['id'], 'Bulk permanently deleted announcement #' . $annId);
            }
            flash('success', count($ids) . ' announcement(s) permanently deleted.');
            redirect_to('announcements.php' . ($isArchivedRedirect ? '?archived=1' : ''));
        }
    }

    redirect_to('announcements.php' . ($isArchivedRedirect ? '?archived=1' : ''));
}

$notice = flash();
$archived = ($_GET['archived'] ?? '') === '1';
$announcementStatus = $archived ? 'Archived' : 'Published';

$publishedCount = (int) db()->query('SELECT count(*) FROM announcements WHERE status = "Published"')->fetchColumn();
$archivedCount = (int) db()->query('SELECT count(*) FROM announcements WHERE status = "Archived"')->fetchColumn();

$announcementStmt = db()->prepare('SELECT a.*, u.fullname FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.status = ? ORDER BY a.created_at DESC');
$announcementStmt->execute([$announcementStatus]);
$announcements = $announcementStmt->fetchAll();

renderHeader($archived ? 'Archived Announcements' : 'Announcements', 'announcements');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-megaphone-line"></i></div>
    <div>
        <p>Communication</p>
        <h2><?php echo $archived ? 'Archived Announcements' : 'Announcements'; ?></h2>
        <span>Publish and manage updates for directors, coordinators, faculty extensionists, OVPRE staff, or faculty researchers.</span>
    </div>
</section>

<?php if ($notice): ?>
    <div class="notice <?php echo h($notice['type']); ?>">
        <i class="ri-<?php echo $notice['type'] === 'success' ? 'checkbox-circle' : 'error-warning'; ?>-line"></i>
        <?php echo h($notice['message']); ?>
    </div>
<?php endif; ?>

<?php if (!$archived): ?>
    <section class="panel modal-launcher-panel">
        <div class="panel-heading" style="display: flex; justify-content: flex-end;">
            <div class="action-row" style="margin-left: auto;">
                <button class="primary-btn" type="button" data-open-modal="createAnnouncementModal">
                    <i class="ri-add-line"></i> Create Announcement
                </button>
            </div>
        </div>
    </section>

    <div class="modal" id="createAnnouncementModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createAnnouncementTitle">
        <div class="modal-backdrop" data-close-modal></div>
        <article class="modal-card simple-submit-modal">
            <button class="modal-close" type="button" data-close-modal aria-label="Close form"><i class="ri-close-line"></i></button>
            <div class="activity-modal-head">
                <span class="category-badge">Announcement</span>
                <h2 id="createAnnouncementTitle">Create Announcement</h2>
                <p>Choose who can see this announcement.</p>
            </div>
            <form class="announcement-form" method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <label class="field">
                    <span>Audience</span>
                    <select name="audience" required>
                        <option value="" selected disabled>Choose audience</option>
                        <?php foreach (['All', 'Directors', 'Coordinators', 'Faculty Extensionists', 'OVPRE Staff', 'Faculty Researchers'] as $audienceOption): ?>
                            <option value="<?php echo h($audienceOption); ?>"><?php echo h($audienceOption); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Announcement Title</span>
                    <input name="title" required placeholder="Enter announcement headline">
                </label>
                <label class="field">
                    <span>Announcement Content</span>
                    <textarea name="content" rows="6" required placeholder="Write the announcement body here..."></textarea>
                </label>
                <button class="primary-btn post-btn" type="submit">
                    <i class="ri-send-plane-line"></i> Publish Announcement
                </button>
            </form>
        </article>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2><?php echo $archived ? 'Archived Announcements' : 'Published Announcements'; ?></h2>
            <span><?php echo count($announcements); ?> total announcement<?php echo count($announcements) === 1 ? '' : 's'; ?></span>
        </div>
        <div class="panel-heading-actions" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-left: auto;">
            <div class="portal-table-tabs">
                <a class="portal-table-tab <?php echo !$archived ? 'active' : ''; ?>" href="announcements.php">
                    <i class="ri-megaphone-line"></i>
                    <span>Published</span>
                    <span class="tab-count"><?php echo $publishedCount; ?></span>
                </a>
                <a class="portal-table-tab <?php echo $archived ? 'active' : ''; ?>" href="announcements.php?archived=1">
                    <i class="ri-archive-line"></i>
                    <span>Archived</span>
                    <span class="tab-count"><?php echo $archivedCount; ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Bulk Selection Toolbar for Announcements -->
    <div class="table-selection-toolbar" data-selection-toolbar="announcementsTable" hidden>
        <span data-selection-count>0 selected</span>
        <form id="bulk-announcementsTable" method="post" data-bulk-selection-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk">
            <?php if ($archived): ?>
                <input type="hidden" name="archived_view" value="1">
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="restore" data-bulk-submit>
                    <i class="ri-arrow-go-back-line"></i><span>Restore Selected</span>
                </button>
            <?php else: ?>
                <button class="action-btn btn-archive-natural" type="submit" name="bulk_action" value="archive" data-bulk-submit onclick="return confirm('Archive selected announcement(s)?');">
                    <i class="ri-archive-line"></i><span>Archive Selected</span>
                </button>
            <?php endif; ?>
            <button class="action-btn btn-delete-natural" type="submit" name="bulk_action" value="delete" data-bulk-submit data-confirm-delete>
                <i class="ri-delete-bin-line"></i><span>Delete Selected</span>
            </button>
        </form>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="announcementsTable">
            <thead>
                <tr>
                    <th class="table-checkbox-col">
                        <label class="select-all-control">
                            <input form="bulk-announcementsTable" type="checkbox" aria-label="Select all announcements" data-select-all="announcementsTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Audience</th>
                    <th>Announcement</th>
                    <th>Date Published</th>
                    <th>Posted By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($announcements as $item): ?>
                    <tr data-table-row>
                        <td class="table-checkbox-col">
                            <input form="bulk-announcementsTable" type="checkbox" name="ids[]" aria-label="Select announcement <?php echo (int) $item['id']; ?>" value="<?php echo (int) $item['id']; ?>" data-row-select="announcementsTable">
                        </td>
                        <td>
                            <span class="dept-pill"><?php echo h($item['audience'] === 'Other Authorized Users' ? 'Faculty Researchers' : $item['audience']); ?></span>
                        </td>
                        <td>
                            <strong style="color: #0f172a; font-size: 0.95rem; display: block;"><?php echo h($item['title']); ?></strong>
                            <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b; line-height: 1.4;">
                                <?php echo h(mb_strimwidth($item['content'] ?? '', 0, 95, '...')); ?>
                            </p>
                        </td>
                        <td style="white-space: nowrap; color: #475569; font-size: 0.88rem;">
                            <?php echo h(date('M d, Y', strtotime($item['created_at']))); ?>
                        </td>
                        <td style="white-space: nowrap; color: #475569; font-size: 0.88rem;">
                            <?php echo h($item['fullname'] ?? 'System Administrator'); ?>
                        </td>
                        <td>
                            <div class="archive-record-actions" style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                                <button class="ghost-btn action-btn" type="button" data-open-modal="viewAnnouncementModal<?php echo (int) $item['id']; ?>" title="View announcement details">
                                    <i class="ri-eye-line"></i><span>View</span>
                                </button>
                                <?php if ($archived): ?>
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('Restore this announcement to published?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                        <button class="ghost-btn action-btn" type="submit" title="Restore Announcement">
                                            <i class="ri-arrow-go-back-line"></i><span>Restore</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('Archive this announcement?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                        <button class="action-btn btn-archive-natural" type="submit" title="Archive Announcement">
                                            <i class="ri-archive-line"></i><span>Archive</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="margin: 0;" onsubmit="return confirm('Permanently delete this announcement? This cannot be undone.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                    <?php if ($archived): ?><input type="hidden" name="archived_view" value="1"><?php endif; ?>
                                    <button class="action-btn btn-delete-natural" type="submit" title="Permanently Delete Announcement">
                                        <i class="ri-delete-bin-line"></i><span>Delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$announcements): ?>
                    <tr data-pagination-empty>
                        <td colspan="6" style="text-align: center; padding: 36px 16px; color: #64748b;">
                            <i class="ri-inbox-line" style="font-size: 2.2rem; display: block; margin-bottom: 8px; color: #94a3b8;"></i>
                            <?php echo $archived ? 'No archived announcements found.' : 'No announcements published yet.'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($announcements as $item): ?>
    <div class="modal" id="viewAnnouncementModal<?php echo (int) $item['id']; ?>" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="viewAnnouncementTitle<?php echo (int) $item['id']; ?>">
        <div class="modal-backdrop" data-close-modal></div>
        <article class="modal-card simple-submit-modal" style="max-width: 600px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close modal"><i class="ri-close-line"></i></button>
            <div class="activity-modal-head">
                <span class="category-badge"><?php echo h($item['audience'] === 'Other Authorized Users' ? 'Faculty Researchers' : $item['audience']); ?></span>
                <h2 id="viewAnnouncementTitle<?php echo (int) $item['id']; ?>"><?php echo h($item['title']); ?></h2>
                <p style="margin-top: 6px; font-size: 0.85rem; color: #64748b;">
                    <i class="ri-user-line"></i> Posted by <?php echo h($item['fullname'] ?? 'System Administrator'); ?> &bull;
                    <i class="ri-calendar-line"></i> <?php echo h(date('F d, Y', strtotime($item['created_at']))); ?> &bull;
                    <span class="status-pill status-<?php echo h(strtolower($item['status'])); ?>"><?php echo h($item['status']); ?></span>
                </p>
            </div>
            <div style="margin-top: 16px; padding: 16px 0; border-top: 1px solid #e2e8f0; font-size: 0.95rem; line-height: 1.6; color: #334155; white-space: pre-wrap;">
                <?php echo nl2br(h($item['content'])); ?>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button class="ghost-btn" type="button" data-close-modal>Close</button>
            </div>
        </article>
    </div>
<?php endforeach; ?>

<?php renderFooter(); ?>
