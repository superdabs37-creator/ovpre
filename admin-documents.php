<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator', 'Extension Coordinator', 'Evaluator/Reviewer']);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'status') {
        $status = $_POST['status'] ?? 'Pending';
        if (in_array($status, ['Pending', 'Under Review', 'Approved', 'Declined'], true)) {
            db()->prepare('UPDATE documents SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $id]);
            log_action((int) $user['id'], "Set document #{$id} to {$status}");
            flash('success', 'Document status updated.');
        }
    } elseif ($action === 'archive') {
        db()->prepare('UPDATE documents SET archived_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$id]);
        db()->prepare('INSERT INTO archived_documents (document_id, archived_by, reason, created_at) VALUES (?, ?, ?, NOW())')->execute([$id, $user['id'], trim($_POST['reason'] ?? '')]);
        log_action((int) $user['id'], "Archived document #{$id}");
        flash('success', 'Document archived.');
    } elseif ($action === 'restore') {
        db()->prepare('UPDATE documents SET archived_at = NULL, updated_at = NOW() WHERE id = ?')->execute([$id]);
        log_action((int) $user['id'], "Restored document #{$id}");
        flash('success', 'Document restored.');
    } elseif ($action === 'delete') {
        $stmt = db()->prepare('SELECT filename FROM documents WHERE id = ? AND archived_at IS NOT NULL');
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        if ($doc) {
            db()->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
            $path = UPLOAD_DIR . '/' . $doc['filename'];
            if (is_file($path)) {
                unlink($path);
            }
            log_action((int) $user['id'], "Permanently deleted document #{$id}");
            flash('success', 'Archived document permanently deleted.');
        }
    }
    redirect_to('admin-documents.php' . (isset($_GET['archived']) ? '?archived=1' : ''));
}

$archived = ($_GET['archived'] ?? '') === '1';
$documents = document_list($archived);
$notice = flash();
renderHeader('Record Management', 'documents');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-file-search-line"></i></div>
    <div><p><?php echo APP_NAME; ?></p><h2>Record Management</h2><span>Upload, search, classify, approve, archive, restore, and audit OVPRE documents.</span></div>
</section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-checkbox-circle-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search by title, author, category, date, or status"></div>
    <select class="filter-select" data-status-filter><option value="All">All Status</option><option>Pending</option><option>Under Review</option><option>Approved</option><option>Declined</option></select>
    <a class="ghost-btn" href="admin-documents.php<?php echo $archived ? '' : '?archived=1'; ?>"><i class="ri-inbox-archive-line"></i> <?php echo $archived ? 'Active Records' : 'Archives'; ?></a>
    <a class="primary-btn" href="upload-document.php"><i class="ri-upload-cloud-line"></i> Upload Record</a>
</section>
<section class="panel">
    <div class="panel-heading"><h2><?php echo $archived ? 'Archived Records' : 'Workflow Queue'; ?></h2><span>Pending -> Under Review -> Approved/Declined</span></div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>ID</th><th>Document</th><th>Author</th><th>Department</th><th>Date</th><th>Status</th><th>Uploaded By</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr data-table-row data-status="<?php echo h($doc['status']); ?>">
                    <td>REC-<?php echo str_pad((string) $doc['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo h($doc['title']); ?><br><small><?php echo h($doc['category_name']); ?> | <?php echo h($doc['original_filename']); ?></small></td>
                    <td><?php echo h($doc['author']); ?></td>
                    <td><?php echo h($doc['department_name']); ?></td>
                    <td><?php echo h(date('M d, Y', strtotime($doc['submission_date']))); ?></td>
                    <td><span class="status-pill status-<?php echo h(statusClass($doc['status'])); ?>"><?php echo h($doc['status']); ?></span></td>
                    <td><?php echo h($doc['uploader']); ?></td>
                    <td>
                        <div class="action-row compact-actions">
                            <a class="ghost-btn" href="uploads/records/<?php echo h(str_replace('%2F', '/', rawurlencode($doc['filename']))); ?>" title="Download"><i class="ri-download-line"></i></a>
                            <?php if (!$archived): ?>
                                <form method="post" class="action-row">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                    <input type="hidden" name="action" value="status">
                                    <select class="filter-select" name="status">
                                        <?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option <?php echo $doc['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?>
                                    </select>
                                    <button class="primary-btn mini-btn" title="Save"><i class="ri-save-line"></i></button>
                                </form>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                    <input type="hidden" name="action" value="archive">
                                    <button class="ghost-btn" title="Archive"><i class="ri-inbox-archive-line"></i></button>
                                </form>
                            <?php else: ?>
                                <form method="post">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <button class="ghost-btn" title="Restore"><i class="ri-arrow-go-back-line"></i></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Permanently delete this archived record?')">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="danger-btn" title="Delete"><i class="ri-delete-bin-line"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documents): ?><tr><td colspan="8">No records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter(); ?>
