<?php
if (!portal_can('extension')) { http_response_code(403); exit('Extension module is not available for this role.'); }
$user = portal_require_user();
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $documentId = portal_save_document($_POST, $_FILES['required_document'] ?? [], $user);
        db()->prepare('INSERT INTO extension_projects (project_title, coordinator, community_partner, budget, start_date, end_date, progress_status, document_id, submitted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([trim($_POST['project_title']), trim($_POST['coordinator']), trim($_POST['community_partner']), (float) $_POST['budget'], $_POST['start_date'], $_POST['end_date'], $_POST['progress_status'], $documentId, $user['id']]);
        log_action((int) $user['id'], 'Submitted extension project: ' . trim($_POST['project_title']));
        flash('success', 'Extension project submitted. Please wait for approval.');
        redirect_to('extension.php');
    } catch (Throwable $error) { $notice = ['type' => 'error', 'message' => $error->getMessage()]; }
}
$user = portal_header('Extension Submission', 'extension');
$projects = portal_extension_projects();
?>
<?php portal_notice($notice); ?>
<section class="panel proposal-launcher">
    <div class="panel-heading">
        <div><h2>Extension Projects</h2><span>Create a project submission visible to the Super Admin.</span></div>
        <button class="primary-btn" type="button" data-open-modal="extensionProjectModal"><i class="ri-add-line"></i> Submit Extension Project</button>
    </div>
</section>
<div class="modal" id="extensionProjectModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <section class="modal-card proposal-modal-card" role="dialog" aria-modal="true" aria-labelledby="extensionProjectModalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close extension project form"><i class="ri-close-line"></i></button>
        <div class="panel-heading"><div><h2 id="extensionProjectModalTitle">Submit Extension Project</h2><span>Visible to Super Admin</span></div></div>
        <form class="portal-form" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <label class="field"><span>Project Title</span><input name="project_title" required></label>
                <label class="field"><span>Coordinator</span><input name="coordinator" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Community Partner</span><input name="community_partner" required></label>
                <label class="field"><span>Budget</span><input type="number" step="0.01" name="budget" required></label>
                <label class="field"><span>Start Date</span><input type="date" name="start_date" required></label>
                <label class="field"><span>End Date</span><input type="date" name="end_date" required></label>
                <label class="field"><span>Status</span><select name="progress_status"><option>Planning</option><option>Ongoing</option><option>Completed</option></select></label>
                <label class="field"><span>Document Category</span><select name="category_id"><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
            </div>
            <label class="field"><span>Required Document</span><input type="file" name="required_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"></label>
            <input type="hidden" name="document_title" value="Extension Required Document">
            <input type="hidden" name="document_description" value="Extension project required document">
            <div class="proposal-modal-actions"><button class="primary-btn" type="submit"><i class="ri-save-line"></i> Submit Extension Project</button><button class="ghost-btn" type="button" data-close-modal>Cancel</button></div>
        </form>
    </section>
</div>
<section class="panel"><div class="panel-heading"><h2>Extension Projects</h2><label class="table-search"><span class="sr-only">Search extension projects</span><i class="ri-search-line"></i><input type="search" data-table-search="extensionProjectTable" placeholder="Search extension projects"></label></div><div class="table-scroll"><table class="data-table" id="extensionProjectTable"><thead><tr><th>Project</th><th>Coordinator</th><th>Partner</th><th>Budget</th><th>Status</th><th>File</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr><td><?php echo h($project['project_title']); ?></td><td><?php echo h($project['coordinator']); ?></td><td><?php echo h($project['community_partner']); ?></td><td><?php echo h(number_format((float) $project['budget'], 2)); ?></td><td><span class="status-pill status-<?php echo h(statusClass($project['progress_status'])); ?>"><?php echo h($project['progress_status']); ?></span></td><td><?php if (!empty($project['attachment_filename'])): ?><div class="file-actions"><a class="ghost-btn mini-btn" href="<?php echo h('../uploads/records/' . $project['attachment_filename']); ?>" target="_blank" rel="noopener"><i class="ri-download-line"></i> File</a><button class="ghost-btn mini-btn" type="button" data-print-file="<?php echo h('../uploads/records/' . $project['attachment_filename']); ?>"><i class="ri-printer-line"></i> Print</button></div><?php else: ?><span class="muted">None</span><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$projects): ?><tr><td colspan="6">No extension projects yet.</td></tr><?php endif; ?></tbody></table><p class="search-empty" data-search-empty="extensionProjectTable" hidden>No matching extension projects.</p></div></section>
<?php portal_footer(); ?>
