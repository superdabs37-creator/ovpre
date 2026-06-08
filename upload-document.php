<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Staff', 'Research Coordinator', 'Extension Coordinator', 'Faculty Researcher']);
verify_csrf();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        save_uploaded_document($_POST, $_FILES['document'] ?? [], (int) $user['id']);
        flash('success', 'Record uploaded and queued for review.');
        redirect_to('admin-documents.php');
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}

renderHeader('Upload Record', 'documents');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-upload-cloud-line"></i></div>
    <div><p>Secure File Upload</p><h2>Upload Record</h2><span>Accepted files: PDF, DOCX, XLSX, and PPTX up to 15 MB.</span></div>
</section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-error-warning-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel">
    <form class="editor-panel" method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="form-grid">
            <label class="field"><span>Document Title</span><input name="title" required></label>
            <label class="field"><span>Author</span><input name="author" value="<?php echo h($user['fullname']); ?>" required></label>
            <label class="field"><span>Category</span><select name="category_id" required><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Department</span><select name="department_id" required><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>"><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Submission Date</span><input type="date" name="submission_date" value="<?php echo date('Y-m-d'); ?>" required></label>
            <label class="field"><span>Upload File</span><input type="file" name="document" accept=".pdf,.docx,.xlsx,.pptx" required></label>
        </div>
        <label class="field"><span>Description</span><textarea name="description" rows="5"></textarea></label>
        <button class="primary-btn post-btn" type="submit"><i class="ri-save-line"></i> Save Record</button>
    </form>
</section>
<?php renderFooter(); ?>
