<?php
$user = portal_require_user();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $isArchivedView = !empty($_POST['archived_view']);

    if ($action === 'archive') {
        db()->prepare("UPDATE announcements SET status = 'Archived' WHERE id = ?")->execute([$id]);
        log_action((int) $user['id'], 'Archived announcement #' . $id);
        flash('success', 'Announcement archived.');
        redirect_to('announcements.php');
    } elseif ($action === 'restore') {
        db()->prepare("UPDATE announcements SET status = 'Published' WHERE id = ?")->execute([$id]);
        log_action((int) $user['id'], 'Restored announcement #' . $id);
        flash('success', 'Announcement restored to published announcements.');
        redirect_to('announcements.php?archived=1');
    } elseif ($action === 'bulk_archive') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $bulkAction = (string) ($_POST['bulk_action'] ?? 'archive');
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'restore') {
                db()->prepare("UPDATE announcements SET status = 'Published' WHERE id IN ($marks)")->execute($ids);
                foreach ($ids as $annId) {
                    log_action((int) $user['id'], 'Bulk restored announcement #' . $annId);
                }
                flash('success', count($ids) . ' announcement(s) restored.');
                redirect_to('announcements.php?archived=1');
            } else {
                db()->prepare("UPDATE announcements SET status = 'Archived' WHERE id IN ($marks)")->execute($ids);
                foreach ($ids as $annId) {
                    log_action((int) $user['id'], 'Bulk archived announcement #' . $annId);
                }
                flash('success', count($ids) . ' announcement(s) archived.');
                redirect_to('announcements.php');
            }
        } else {
            flash('error', 'No announcements selected.');
            redirect_to('announcements.php' . ($isArchivedView ? '?archived=1' : ''));
        }
    }
}

$notice = flash();
$archived = ($_GET['archived'] ?? '') === '1';
$user = portal_header('Announcements', 'announcements');
$announcements = portal_announcements($archived);
$counts = portal_announcement_counts();
?>
<?php portal_notice($notice); ?>

<section class="panel">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div class="panel-heading-actions" style="display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-left:auto;">
            <div class="portal-table-tabs" style="margin-left:auto;">
                <a class="portal-table-tab <?php echo !$archived ? 'active' : ''; ?>" href="announcements.php">
                    <i class="ri-megaphone-line"></i>
                    <span>Published</span>
                    <span class="tab-count"><?php echo $counts['active']; ?></span>
                </a>
                <a class="portal-table-tab <?php echo $archived ? 'active' : ''; ?>" href="announcements.php?archived=1">
                    <i class="ri-archive-line"></i>
                    <span>Archived</span>
                    <span class="tab-count"><?php echo $counts['archived']; ?></span>
                </a>
            </div>
            <label class="table-search" style="margin-left:auto;">
                <span class="sr-only">Search announcements</span>
                <i class="ri-search-line"></i>
                <input type="search" data-table-search="portalAnnouncementsTable" placeholder="Search announcements...">
            </label>
        </div>
    </div>

    <!-- Bulk Selection Toolbar for Announcements -->
    <div class="table-selection-toolbar" data-selection-toolbar="portalAnnouncementsTable" hidden>
        <span data-selection-count>0 selected</span>
        <form id="bulk-portalAnnouncementsTable" method="post" data-bulk-selection-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_archive">
            <?php if ($archived): ?>
                <input type="hidden" name="archived_view" value="1">
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="restore" data-bulk-submit>
                    <i class="ri-arrow-go-back-line"></i><span>Restore Selected</span>
                </button>
            <?php else: ?>
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="archive" data-bulk-submit onclick="return confirm('Archive selected announcements?');">
                    <i class="ri-archive-line"></i><span>Archive Selected</span>
                </button>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="portalAnnouncementsTable" data-paginate="10">
            <thead>
                <tr>
                    <th class="table-checkbox-col">
                        <label class="select-all-control">
                            <input form="bulk-portalAnnouncementsTable" type="checkbox" aria-label="Select all announcements" data-select-all="portalAnnouncementsTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Audience</th>
                    <th>Announcement</th>
                    <th>Published</th>
                    <th>Posted By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($announcements as $item): ?>
                    <tr>
                        <td class="table-checkbox-col">
                            <input form="bulk-portalAnnouncementsTable" type="checkbox" name="ids[]" aria-label="Select announcement <?php echo (int) $item['id']; ?>" value="<?php echo (int) $item['id']; ?>" data-row-select="portalAnnouncementsTable">
                        </td>
                        <td><span class="tag"><?php echo h($item['audience'] === 'Other Authorized Users' ? 'Faculty Researchers' : $item['audience']); ?></span></td>
                        <td><?php echo h($item['title']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></td>
                        <td><?php echo h($item['fullname']); ?></td>
                        <td>
                            <div class="archive-record-actions">
                                <button class="ghost-btn mini-btn announcement-detail-btn" type="button" data-open-modal="announcement-detail-<?php echo (int) $item['id']; ?>">
                                    <i class="ri-eye-line"></i> View Details
                                </button>
                                <?php if ($archived): ?>
                                    <form method="post" style="margin:0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit">
                                            <i class="ri-arrow-go-back-line"></i> Restore
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Archive this announcement?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit" style="color:#b45309;">
                                            <i class="ri-archive-line"></i> Archive
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$announcements): ?>
                    <tr data-pagination-empty>
                        <td colspan="6" style="text-align:center; padding: 20px; color:#6b7280;"><?php echo $archived ? 'No archived announcements.' : 'No announcements yet.'; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p class="search-empty" data-search-empty="portalAnnouncementsTable" hidden>No matching announcements.</p>
    </div>
</section>

<?php foreach ($announcements as $item): ?>
    <div class="modal" id="announcement-detail-<?php echo (int) $item['id']; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="announcement-title-<?php echo (int) $item['id']; ?>">
            <button class="modal-close" type="button" data-close-modal aria-label="Close announcement details"><i class="ri-close-line"></i></button>
            <span class="tag"><?php echo h($item['audience']); ?></span>
            <h2 id="announcement-title-<?php echo (int) $item['id']; ?>"><?php echo h($item['title']); ?></h2>
            <p><?php echo nl2br(h($item['content'])); ?></p>
            <p><small><?php echo h(date('M d, Y', strtotime($item['created_at'])) . ' | ' . $item['fullname']); ?></small></p>
        </section>
    </div>
<?php endforeach; ?>
<?php portal_footer(); ?>
