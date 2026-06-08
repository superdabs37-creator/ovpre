<?php
require_once 'includes/student-layout.php';
$user = require_student();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $doc = student_document($id, (int) $user['id']);
    if ($doc && $action === 'delete' && in_array($doc['status'], ['Pending', 'Declined'], true)) {
        student_db()->prepare('UPDATE documents SET archived_at = NOW(), updated_at = NOW() WHERE id = ? AND uploaded_by = ?')->execute([$id, (int) $user['id']]);
        student_log_document((int) $user['id'], $id, 'Deleted', 'Student removed document from active list.');
        flash('success', 'Document removed from your active list.');
    } elseif ($doc && $action === 'edit' && in_array($doc['status'], ['Pending', 'Declined'], true)) {
        student_db()->prepare('UPDATE documents SET title = ?, description = ?, category_id = ?, submission_date = ?, updated_at = NOW() WHERE id = ? AND uploaded_by = ?')
            ->execute([trim($_POST['title'] ?? ''), trim($_POST['description'] ?? ''), (int) $_POST['category_id'], $_POST['submission_date'] ?: date('Y-m-d'), $id, (int) $user['id']]);
        student_log_document((int) $user['id'], $id, 'Edited', 'Student updated document details.');
        flash('success', 'Document updated.');
    }
    redirect_to('documents.php');
}

$notice = flash();
$documents = student_documents((int) $user['id']);
$user = student_render_header('My Documents', 'documents');
?>
<section class="module-header"><div class="module-icon"><i class="ri-folder-3-line"></i></div><div><p>Personal Records</p><h2>My Documents</h2><span>Search, preview, download, edit, delete, and track your submitted records.</span></div></section>
<?php student_notice($notice); ?>
<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search documents"></div>
    <select class="filter-select" data-status-filter><option value="All">All Status</option><option>Pending</option><option>Under Review</option><option>Approved</option><option>Rejected</option></select>
    <a class="primary-btn" href="upload.php"><i class="ri-upload-cloud-line"></i> Upload</a>
</section>
<section class="panel">
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr data-table-row data-status="<?php echo h(student_status_label($doc['status'])); ?>">
                    <td><?php echo h(student_tracking_number((int) $doc['id'])); ?></td>
                    <td><?php echo h($doc['title']); ?><br><small><?php echo h($doc['original_filename']); ?></small></td>
                    <td><?php echo h($doc['category_name']); ?></td>
                    <td><?php echo h(date('M d, Y', strtotime($doc['submission_date']))); ?></td>
                    <td><?php echo student_badge($doc['status']); ?></td>
                    <td>
                        <div class="action-row compact-actions">
                            <button class="ghost-btn" type="button" title="View" data-preview-title="<?php echo h($doc['title']); ?>" data-preview-meta="<?php echo h($doc['category_name'] . ' | ' . student_tracking_number((int) $doc['id'])); ?>" data-preview-body="<?php echo h($doc['description'] ?: 'No description provided.'); ?>"><i class="ri-eye-line"></i></button>
                            <a class="ghost-btn" title="Download" href="<?php echo h(student_file_url($doc['filename'])); ?>" download><i class="ri-download-line"></i></a>
                            <?php if (in_array($doc['status'], ['Pending', 'Declined'], true)): ?>
                                <button class="ghost-btn" type="button" title="Edit" data-open-modal="edit-<?php echo (int) $doc['id']; ?>"><i class="ri-edit-line"></i></button>
                                <form method="post" onsubmit="return confirm('Delete this document from your active list?')"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><input type="hidden" name="action" value="delete"><button class="danger-btn" title="Delete"><i class="ri-delete-bin-line"></i></button></form>
                            <?php endif; ?>
                            <a class="ghost-btn" title="Track" href="track.php?id=<?php echo (int) $doc['id']; ?>"><i class="ri-route-line"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documents): ?><tr><td colspan="6">No documents found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php foreach ($documents as $doc): ?>
<div class="modal" id="edit-<?php echo (int) $doc['id']; ?>" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <form class="modal-card" method="post">
        <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
        <h2>Edit Document</h2>
        <?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><input type="hidden" name="action" value="edit">
        <label class="field"><span>Title</span><input name="title" value="<?php echo h($doc['title']); ?>" required></label>
        <label class="field"><span>Category</span><select name="category_id"><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $doc['category_id'] === (int) $category['id'] ? 'selected' : ''; ?>><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Submission Date</span><input type="date" name="submission_date" value="<?php echo h($doc['submission_date']); ?>"></label>
        <label class="field"><span>Description</span><textarea name="description" rows="5"><?php echo h($doc['description']); ?></textarea></label>
        <button class="primary-btn" type="submit"><i class="ri-save-line"></i> Save Changes</button>
    </form>
</div>
<?php endforeach; ?>
<div class="modal" id="previewModal" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><span class="category-badge" id="previewMeta"></span><h2 id="previewTitle"></h2><p id="previewBody"></p></article></div>
<?php student_render_footer(); ?>
