<?php
require_once 'includes/user-layout.php';
$user = require_end_user();
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        user_save_upload($_POST, $_FILES['document'] ?? [], $user);
        flash('success', 'File uploaded successfully. It is now pending review.');
        redirect_to('my-files.php');
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}
$user = user_render_header('Upload File', 'upload');
?>
<section class="simple-header"><div><h2>Upload File</h2><p>Add a document for OVPRE review in a few simple steps.</p></div><a class="soft-btn" href="my-files.php"><i class="ri-arrow-left-line"></i> Cancel</a></section>
<?php user_notice($notice); ?>
<form class="upload-panel" id="uploadForm" method="post" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <label class="dropzone" id="dropzone"><i class="ri-upload-cloud-2-line"></i><strong>Drop your file here</strong><span>or tap to browse PDF, DOC, DOCX, XLS, JPG, PNG</span><input id="documentFile" type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required></label>
    <div class="progress"><span id="uploadProgress"></span></div>
    <div class="form-grid">
        <label class="field"><span>Title</span><input name="title" required></label>
        <label class="field"><span>Category</span><select name="category_id" required><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="field"><span>Description</span><textarea name="description" rows="4" placeholder="Add a short note for the office"></textarea></label>
    <div class="actions"><button class="big-action" type="submit"><i class="ri-upload-cloud-line"></i> Upload File</button><a class="soft-btn" href="my-files.php">Cancel</a></div>
</form>
<?php user_render_footer(); ?>
