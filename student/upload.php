<?php
require_once 'includes/student-layout.php';
$user = require_student();
verify_csrf();
$notice = flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['save_mode'] ?? '') === 'draft') {
        $_SESSION['student_upload_draft'] = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'submission_date' => $_POST['submission_date'] ?? date('Y-m-d'),
        ];
        flash('success', 'Draft saved in this session.');
        redirect_to('upload.php');
    }
    try {
        $documentId = student_save_uploaded_document($_POST, $_FILES['document'] ?? [], $user);
        flash('success', 'Document submitted. Tracking number: ' . student_tracking_number($documentId));
        redirect_to('track.php?id=' . $documentId);
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}

$draft = $_SESSION['student_upload_draft'] ?? [];
$user = student_render_header('Upload Document', 'upload');
?>
<section class="module-header"><div class="module-icon"><i class="ri-upload-cloud-line"></i></div><div><p>Secure Submission</p><h2>Upload Document</h2><span>Accepted files: PDF, DOC, DOCX, XLS, XLSX, JPG, and PNG up to 15 MB.</span></div></section>
<?php student_notice($notice); ?>
<section class="upload-workspace">
    <form class="upload-dropzone" id="uploadForm" method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <label class="dropzone-area" id="dropzone">
            <i class="ri-file-upload-line"></i>
            <strong>Drag a file here or browse</strong>
            <span>PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</span>
            <input id="documentFile" type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
        </label>
        <div class="upload-progress"><div id="uploadProgressBar"></div></div>
        <div class="form-grid">
            <label class="field"><span>Document Title</span><input name="title" value="<?php echo h($draft['title'] ?? ''); ?>" required></label>
            <label class="field"><span>Author</span><input name="author" value="<?php echo h($user['fullname']); ?>" required></label>
            <label class="field"><span>Category</span><select name="category_id" required><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>" <?php echo (int) ($draft['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : ''; ?>><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Department</span><select name="department_id" required><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>" <?php echo (int) ($user['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Submission Date</span><input type="date" name="submission_date" value="<?php echo h($draft['submission_date'] ?? date('Y-m-d')); ?>" required></label>
        </div>
        <label class="field"><span>Description</span><textarea name="description" rows="5"><?php echo h($draft['description'] ?? ''); ?></textarea></label>
        <div class="action-row"><button class="primary-btn" type="submit" name="save_mode" value="submit"><i class="ri-send-plane-line"></i> Submit Document</button><button class="ghost-btn" type="submit" name="save_mode" value="draft" formnovalidate><i class="ri-draft-line"></i> Save Draft</button></div>
    </form>
    <aside class="panel upload-rules">
        <div class="panel-heading"><h2>Upload Checks</h2></div>
        <p>Files are renamed before storage, validated by extension and MIME type, and queued as Pending for OVPRE review.</p>
        <div class="check-list"><span><i class="ri-check-line"></i> 15 MB maximum</span><span><i class="ri-check-line"></i> Prepared statements</span><span><i class="ri-check-line"></i> CSRF protected</span></div>
    </aside>
</section>
<?php student_render_footer(); ?>
