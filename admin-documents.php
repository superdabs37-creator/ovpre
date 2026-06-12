<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator', 'Extension Coordinator']);
verify_csrf();

function record_file_url(string $filename): string {
    return 'uploads/records/' . implode('/', array_map('rawurlencode', explode('/', $filename)));
}

function record_is_previewable(string $filename): bool {
    return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $bulkAction = $_POST['bulk_action'] ?? '';
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'approve') {
                db()->prepare("UPDATE documents SET status = 'Approved', status_remarks = ?, updated_at = NOW() WHERE id IN ($placeholders)")
                    ->execute(array_merge([trim($_POST['bulk_remarks'] ?? 'Bulk approved.')], $ids));
                foreach ($ids as $docId) {
                    log_review('document', $docId, (int) $user['id'], 'Bulk Approved', trim($_POST['bulk_remarks'] ?? ''));
                }
                flash('success', 'Selected records approved.');
            } elseif ($bulkAction === 'archive') {
                db()->prepare("UPDATE documents SET archived_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)")
                    ->execute($ids);
                foreach ($ids as $docId) {
                    db()->prepare('INSERT INTO archived_documents (document_id, archived_by, reason, created_at) VALUES (?, ?, ?, NOW())')
                        ->execute([$docId, (int) $user['id'], trim($_POST['bulk_remarks'] ?? 'Bulk archive')]);
                    log_review('document', $docId, (int) $user['id'], 'Bulk Archived', trim($_POST['bulk_remarks'] ?? ''));
                }
                flash('success', 'Selected records archived.');
            } elseif ($bulkAction === 'delete') {
                $stmt = db()->prepare("SELECT id, filename FROM documents WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                foreach ($stmt->fetchAll() as $doc) {
                    db()->prepare('DELETE FROM documents WHERE id = ?')->execute([(int) $doc['id']]);
                    $path = UPLOAD_DIR . '/' . $doc['filename'];
                    if (is_file($path)) {
                        unlink($path);
                    }
                    log_action((int) $user['id'], 'Bulk deleted document #' . (int) $doc['id']);
                }
                flash('success', 'Selected records deleted.');
            }
        }
    } elseif ($action === 'status') {
        $status = $_POST['status'] ?? 'Pending';
        if (in_array($status, ['Pending', 'Under Review', 'Approved', 'Declined'], true)) {
            $remarks = trim($_POST['remarks'] ?? '');
            $assignedTo = ($_POST['assigned_to'] ?? '') === '' ? null : (int) $_POST['assigned_to'];
            db()->prepare('UPDATE documents SET status = ?, status_remarks = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$status, $remarks, $assignedTo, $id]);
            log_review('document', $id, (int) $user['id'], 'Status: ' . $status, $remarks);
            log_action((int) $user['id'], "Set document #{$id} to {$status}");
            flash('success', 'Document status updated with remarks.');
        }
    } elseif ($action === 'archive') {
        $reason = trim($_POST['reason'] ?? '');
        db()->prepare('UPDATE documents SET archived_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$id]);
        db()->prepare('INSERT INTO archived_documents (document_id, archived_by, reason, created_at) VALUES (?, ?, ?, NOW())')->execute([$id, $user['id'], $reason]);
        log_review('document', $id, (int) $user['id'], 'Archived', $reason);
        log_action((int) $user['id'], "Archived document #{$id}");
        flash('success', 'Document archived.');
    } elseif ($action === 'restore') {
        db()->prepare('UPDATE documents SET archived_at = NULL, updated_at = NOW() WHERE id = ?')->execute([$id]);
        log_review('document', $id, (int) $user['id'], 'Restored', '');
        log_action((int) $user['id'], "Restored document #{$id}");
        flash('success', 'Document restored.');
    } elseif ($action === 'delete') {
        $stmt = db()->prepare('SELECT filename FROM documents WHERE id = ?');
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if ($doc) {
            db()->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
            $path = UPLOAD_DIR . '/' . $doc['filename'];
            if (is_file($path)) {
                unlink($path);
            }
            log_action((int) $user['id'], "Permanently deleted document #{$id}");
            flash('success', 'Document permanently deleted.');
        }
    } elseif ($action === 'announcement_restore') {
        db()->prepare('UPDATE announcements SET status = "Published" WHERE id = ?')->execute([$id]);
        log_action((int) $user['id'], "Restored announcement #{$id}");
        flash('success', 'Announcement restored to published announcements.');
    }
    redirect_to('admin-documents.php' . (isset($_GET['archived']) ? '?archived=1' : ''));
}

$archived = ($_GET['archived'] ?? '') === '1';
$statusFilter = $_GET['status'] ?? 'All';
$documents = document_list($archived, $statusFilter);
$archivedAnnouncements = [];
if ($archived) {
    $archivedAnnouncements = db()->query('SELECT a.*, u.fullname FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.status = "Archived" ORDER BY a.created_at DESC')->fetchAll();
}
$researchSubmissions = [];
$extensionSubmissions = [];
$activitySubmissions = [];
if (!$archived) {
    $researchSubmissions = db()->query('SELECT r.*, u.fullname, au.fullname AS assignee FROM research_projects r JOIN users u ON u.id = r.submitted_by LEFT JOIN users au ON au.id = r.assigned_to ORDER BY r.created_at DESC')->fetchAll();
    $extensionSubmissions = db()->query('SELECT e.*, u.fullname, au.fullname AS assignee FROM extension_projects e JOIN users u ON u.id = e.submitted_by LEFT JOIN users au ON au.id = e.assigned_to ORDER BY e.created_at DESC')->fetchAll();
    $activitySubmissions = db()->query('SELECT a.*, u.fullname FROM activities a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.date DESC, a.created_at DESC')->fetchAll();
}
$assignableUsers = assignable_users();
$notice = flash();
$categoryAnalytics = db()->query('SELECT c.category_name, COUNT(d.id) total FROM categories c LEFT JOIN documents d ON d.category_id = c.id AND d.archived_at IS NULL GROUP BY c.id ORDER BY c.category_name')->fetchAll();
$departmentAnalytics = db()->query('SELECT dep.department_name, COUNT(d.id) total FROM departments dep LEFT JOIN documents d ON d.department_id = dep.id AND d.archived_at IS NULL GROUP BY dep.id ORDER BY dep.department_name')->fetchAll();
renderHeader('Record Management', 'documents');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-file-search-line"></i></div>
    <div><p><?php echo APP_NAME; ?></p><h2>Record Management</h2><span>Review records with remarks, preview files, assign reviewers, archive with reasons, and track timelines.</span></div>
</section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-checkbox-circle-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>

<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search by title, author, category, date, or status"></div>
    <form class="action-row" method="get">
        <?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
        <select class="filter-select" name="status" onchange="this.form.submit()"><option value="All">All Status</option><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
    </form>
    <a class="ghost-btn" href="admin-documents.php<?php echo $archived ? '' : '?archived=1'; ?>"><i class="ri-inbox-archive-line"></i> <?php echo $archived ? 'Active Records' : 'Archives'; ?></a>
    <a class="primary-btn" href="upload-document.php"><i class="ri-upload-cloud-line"></i> Upload Record</a>
</section>

<?php if (!$archived): ?>
<section class="panel submission-folders">
    <div class="panel-heading"><h2>Submission Folders</h2><span>Research, extension, and activity submissions</span></div>

    <details class="submission-folder" open>
        <summary><span><i class="ri-flask-line"></i> Research Management Submissions</span><strong><?php echo count($researchSubmissions); ?></strong></summary>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>ID</th><th>Research Title</th><th>Researchers</th><th>Submitted By</th><th>Assigned To</th><th>Status</th><th>Date</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($researchSubmissions as $research): ?>
                    <tr data-table-row data-status="<?php echo h($research['status']); ?>">
                        <td>RES-<?php echo str_pad((string) $research['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo h($research['title']); ?><br><small><?php echo h($research['funding_source'] ?: 'No funding source'); ?></small></td>
                        <td><?php echo h($research['researchers']); ?></td>
                        <td><?php echo h($research['fullname']); ?></td>
                        <td><?php echo h($research['assignee'] ?? 'Unassigned'); ?></td>
                        <td><span class="status-pill status-<?php echo h(statusClass($research['status'])); ?>"><?php echo h($research['status'] === 'Declined' ? 'Rejected' : $research['status']); ?></span></td>
                        <td><?php echo h(date('M d, Y', strtotime($research['created_at']))); ?></td>
                        <td><a class="ghost-btn action-btn" href="research.php?status=<?php echo urlencode($research['status']); ?>"><i class="ri-arrow-right-line"></i><span>Open</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$researchSubmissions): ?><tr><td colspan="8">No research submissions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    </details>

    <details class="submission-folder">
        <summary><span><i class="ri-community-line"></i> Extension Project Submissions</span><strong><?php echo count($extensionSubmissions); ?></strong></summary>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>ID</th><th>Project</th><th>Coordinator</th><th>Partner</th><th>Budget</th><th>Assigned To</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($extensionSubmissions as $extension): ?>
                    <tr data-table-row data-status="<?php echo h($extension['progress_status']); ?>">
                        <td>EXT-<?php echo str_pad((string) $extension['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo h($extension['project_title']); ?><br><small><?php echo h(date('M d, Y', strtotime($extension['start_date'])) . ' - ' . date('M d, Y', strtotime($extension['end_date']))); ?></small></td>
                        <td><?php echo h($extension['coordinator']); ?></td>
                        <td><?php echo h($extension['community_partner']); ?></td>
                        <td><?php echo h(number_format((float) $extension['budget'], 2)); ?></td>
                        <td><?php echo h($extension['assignee'] ?? 'Unassigned'); ?></td>
                        <td><span class="status-pill status-<?php echo h(statusClass($extension['progress_status'])); ?>"><?php echo h($extension['progress_status']); ?></span></td>
                        <td><a class="ghost-btn action-btn" href="extension.php?status=<?php echo urlencode($extension['progress_status']); ?>"><i class="ri-arrow-right-line"></i><span>Open</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$extensionSubmissions): ?><tr><td colspan="8">No extension project submissions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    </details>

    <details class="submission-folder">
        <summary><span><i class="ri-calendar-event-line"></i> Activity Submissions</span><strong><?php echo count($activitySubmissions); ?></strong></summary>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>ID</th><th>Activity</th><th>Type</th><th>Venue</th><th>Date</th><th>Submitted By</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($activitySubmissions as $activity): ?>
                    <tr data-table-row>
                        <td>ACT-<?php echo str_pad((string) $activity['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo h($activity['title']); ?><br><small><?php echo h($activity['description'] ?: 'No description'); ?></small></td>
                        <td><span class="dept-pill"><?php echo h($activity['activity_type']); ?></span></td>
                        <td><?php echo h($activity['venue']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($activity['date']))); ?></td>
                        <td><?php echo h($activity['fullname'] ?? 'System'); ?></td>
                        <td><a class="ghost-btn action-btn" href="activities.php"><i class="ri-arrow-right-line"></i><span>Open</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$activitySubmissions): ?><tr><td colspan="7">No activity submissions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    </details>
</section>
<?php endif; ?>

<section class="summary-grid">
    <article class="summary-card category-filter-card active" role="button" tabindex="0" data-category-filter="All" aria-pressed="true"><div class="summary-icon"><i class="ri-folder-open-line"></i></div><div><p>All Records</p><h3><?php echo array_sum(array_map(fn($row) => (int) $row['total'], $categoryAnalytics)); ?></h3><span>Open all records</span></div></article>
    <?php foreach ($categoryAnalytics as $row): ?>
        <article class="summary-card category-filter-card" role="button" tabindex="0" data-category-filter="<?php echo h($row['category_name']); ?>" aria-pressed="false"><div class="summary-icon"><i class="ri-folder-3-line"></i></div><div><p><?php echo h($row['category_name']); ?></p><h3><?php echo (int) $row['total']; ?></h3><span>Open category records</span></div></article>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-heading"><h2 id="recordsPanelTitle"><?php echo $archived ? 'Archived Records' : 'All Records'; ?></h2><span id="recordsPanelSubtitle">Open a category card above to view its documents.</span></div>
    <div class="table-scroll">
        <table class="data-table portal-table" id="recordsTable">
            <thead><tr><th>ID</th><th>Document</th><th>Author</th><th>Department</th><th>Date</th><th>Status</th><th>Assigned</th><th>Uploaded By</th><th>Manage</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr data-table-row data-status="<?php echo h($doc['status']); ?>" data-category="<?php echo h($doc['category_name']); ?>">
                    <td>REC-<?php echo str_pad((string) $doc['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo h($doc['title']); ?><br><small><?php echo h($doc['category_name']); ?> | <?php echo h($doc['original_filename']); ?></small><?php if ($doc['status_remarks']): ?><br><small>Remarks: <?php echo h($doc['status_remarks']); ?></small><?php endif; ?></td>
                    <td><?php echo h($doc['author']); ?></td>
                    <td><?php echo h($doc['department_name']); ?></td>
                    <td><?php echo h(date('M d, Y', strtotime($doc['submission_date']))); ?></td>
                    <td><span class="status-pill status-<?php echo h(statusClass($doc['status'])); ?>"><?php echo h($doc['status'] === 'Declined' ? 'Rejected' : $doc['status']); ?></span></td>
                    <td><?php echo h($doc['assignee'] ?? 'Unassigned'); ?></td>
                    <td><?php echo h($doc['uploader']); ?></td>
                    <td>
                        <div class="record-action-panel">
                            <?php if (record_is_previewable($doc['filename'])): ?><button class="ghost-btn action-btn" type="button" data-open-modal="preview-<?php echo (int) $doc['id']; ?>"><i class="ri-eye-line"></i><span>Preview</span></button><?php endif; ?>
                            <a class="ghost-btn action-btn" href="<?php echo h(record_file_url($doc['filename'])); ?>" title="Download"><i class="ri-download-line"></i><span>Download</span></a>
                            <button class="ghost-btn action-btn" type="button" data-open-modal="timeline-<?php echo (int) $doc['id']; ?>"><i class="ri-time-line"></i><span>Timeline</span></button>
                            <button class="ghost-btn action-btn" type="button" data-open-modal="versions-<?php echo (int) $doc['id']; ?>"><i class="ri-git-branch-line"></i><span>Versions</span></button>
                            <?php if (!$archived): ?>
                                <button class="primary-btn action-btn" type="button" data-open-modal="status-<?php echo (int) $doc['id']; ?>"><i class="ri-checkbox-circle-line"></i><span>Review</span></button>
                                <button class="ghost-btn action-btn" type="button" data-open-modal="archive-<?php echo (int) $doc['id']; ?>"><i class="ri-inbox-archive-line"></i><span>Archive</span></button>
                            <?php else: ?>
                                <button class="ghost-btn action-btn" type="button" data-open-modal="restore-<?php echo (int) $doc['id']; ?>"><i class="ri-arrow-go-back-line"></i><span>Restore</span></button>
                                <button class="danger-btn action-btn" type="button" data-open-modal="delete-<?php echo (int) $doc['id']; ?>"><i class="ri-delete-bin-line"></i><span>Delete</span></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documents): ?><tr><td colspan="9">No records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>


<?php if ($archived): ?>
<section class="panel">
    <div class="panel-heading"><h2>Archived Announcements</h2><span><?php echo count($archivedAnnouncements); ?> archived announcement<?php echo count($archivedAnnouncements) === 1 ? '' : 's'; ?></span></div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>ID</th><th>Announcement</th><th>Audience</th><th>Archived Status</th><th>Posted By</th><th>Date Posted</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($archivedAnnouncements as $announcement): ?>
                    <tr data-table-row data-status="Archived">
                        <td>ANN-<?php echo str_pad((string) $announcement['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo h($announcement['title']); ?><br><small><?php echo h($announcement['content']); ?></small></td>
                        <td><span class="dept-pill"><?php echo h($announcement['audience']); ?></span></td>
                        <td><span class="status-pill status-archived">Archived</span></td>
                        <td><?php echo h($announcement['fullname']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($announcement['created_at']))); ?></td>
                        <td>
                            <form method="post">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="announcement_restore">
                                <input type="hidden" name="id" value="<?php echo (int) $announcement['id']; ?>">
                                <button class="ghost-btn action-btn"><i class="ri-arrow-go-back-line"></i><span>Restore</span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$archivedAnnouncements): ?><tr><td colspan="7">No archived announcements found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php foreach ($documents as $doc): ?>
    <div class="modal" id="status-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Review Record</h2><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><label class="field"><span>Status</span><select name="status"><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option <?php echo $doc['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label><label class="field"><span>Assign Reviewer / Coordinator</span><select name="assigned_to"><option value="">Unassigned</option><?php foreach ($assignableUsers as $assignee): ?><option value="<?php echo (int) $assignee['id']; ?>" <?php echo (int) ($doc['assigned_to'] ?? 0) === (int) $assignee['id'] ? 'selected' : ''; ?>><?php echo h($assignee['fullname'] . ' - ' . $assignee['role']); ?></option><?php endforeach; ?></select></label><label class="field"><span>Approval / Rejection Remarks</span><textarea name="remarks" rows="4"><?php echo h($doc['status_remarks']); ?></textarea></label><button class="primary-btn"><i class="ri-save-line"></i> Save Review</button></form></div>
    <div class="modal" id="archive-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Archive Record</h2><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><label class="field"><span>Archive Reason</span><textarea name="reason" rows="4" required></textarea></label><button class="primary-btn"><i class="ri-inbox-archive-line"></i> Archive Record</button></form></div>
    <div class="modal" id="restore-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Restore Record</h2><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><p>Restore this archived record to the active workflow?</p><button class="primary-btn"><i class="ri-arrow-go-back-line"></i> Restore Record</button></form></div>
    <div class="modal" id="delete-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Delete Record</h2><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><p>This permanently deletes the record and its stored file.</p><button class="danger-btn"><i class="ri-delete-bin-line"></i> Permanently Delete</button></form></div>
    <div class="modal" id="timeline-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Review Timeline</h2><div class="timeline-list"><?php foreach (review_timeline('document', (int) $doc['id']) as $log): ?><div><strong><?php echo h($log['action']); ?></strong><p><?php echo h($log['remarks'] ?: 'No remarks.'); ?></p><small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small></div><?php endforeach; ?><div><strong>Uploaded</strong><p><?php echo h($doc['description'] ?: 'Record submitted.'); ?></p><small><?php echo h(date('M d, Y h:i A', strtotime($doc['created_at']))); ?></small></div></div></article></div>
    <div class="modal" id="versions-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Version History</h2><div class="timeline-list"><?php $versions = db()->prepare('SELECT v.*, u.fullname FROM document_versions v LEFT JOIN users u ON u.id = v.modified_by WHERE v.document_id = ? ORDER BY v.created_at DESC'); $versions->execute([(int) $doc['id']]); foreach ($versions->fetchAll() as $version): ?><div><strong><?php echo h($version['version_label']); ?></strong><p><a href="<?php echo h(record_file_url($version['filename'])); ?>">Download version</a></p><small><?php echo h(($version['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($version['created_at']))); ?></small></div><?php endforeach; ?></div></article></div>
    <?php if (record_is_previewable($doc['filename'])): ?><div class="modal" id="preview-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card preview-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2><?php echo h($doc['title']); ?></h2><?php $ext = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION)); if ($ext === 'pdf'): ?><iframe class="document-preview" src="<?php echo h(record_file_url($doc['filename'])); ?>"></iframe><?php else: ?><img class="document-preview-image" src="<?php echo h(record_file_url($doc['filename'])); ?>" alt="<?php echo h($doc['title']); ?>"><?php endif; ?></article></div><?php endif; ?>
<?php endforeach; ?>
<?php renderFooter(); ?>
