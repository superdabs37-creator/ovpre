<?php
if (!portal_can('extension')) { http_response_code(403); exit('Extension module is not available for this role.'); }
$user = portal_require_user();
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        portal_save_document($_POST, $_FILES['required_document'] ?? [], $user);
        db()->prepare('INSERT INTO extension_projects (project_title, coordinator, community_partner, budget, start_date, end_date, progress_status, submitted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([trim($_POST['project_title']), trim($_POST['coordinator']), trim($_POST['community_partner']), (float) $_POST['budget'], $_POST['start_date'], $_POST['end_date'], $_POST['progress_status'], $user['id']]);
        log_action((int) $user['id'], 'Submitted extension project: ' . trim($_POST['project_title']));
        flash('success', 'Extension project submitted for Super Admin review.');
        redirect_to('extension.php');
    } catch (Throwable $error) { $notice = ['type' => 'error', 'message' => $error->getMessage()]; }
}
$user = portal_header('Extension Submission', 'extension');
$projects = portal_extension_projects();
?>
<?php portal_notice($notice); ?>
<section class="panel"><div class="panel-heading"><h2>Submit Extension Project</h2><span>Visible to Super Admin</span></div><form class="portal-form" method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><div class="form-grid"><label class="field"><span>Project Title</span><input name="project_title" required></label><label class="field"><span>Coordinator</span><input name="coordinator" value="<?php echo h($user['fullname']); ?>" required></label><label class="field"><span>Community Partner</span><input name="community_partner" required></label><label class="field"><span>Budget</span><input type="number" step="0.01" name="budget" required></label><label class="field"><span>Start Date</span><input type="date" name="start_date" required></label><label class="field"><span>End Date</span><input type="date" name="end_date" required></label><label class="field"><span>Status</span><select name="progress_status"><option>Planning</option><option>Ongoing</option><option>Completed</option></select></label><label class="field"><span>Document Category</span><select name="category_id"><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label></div><label class="field"><span>Required Document</span><input type="file" name="required_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"></label><input type="hidden" name="document_title" value="Extension Required Document"><input type="hidden" name="document_description" value="Extension project required document"><button class="primary-btn"><i class="ri-save-line"></i> Submit Extension Project</button></form></section>
<section class="panel"><div class="panel-heading"><h2>Extension Projects</h2><span>Planning | Ongoing | Completed</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Project</th><th>Coordinator</th><th>Partner</th><th>Budget</th><th>Status</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr><td><?php echo h($project['project_title']); ?></td><td><?php echo h($project['coordinator']); ?></td><td><?php echo h($project['community_partner']); ?></td><td><?php echo h(number_format((float) $project['budget'], 2)); ?></td><td><span class="status-pill"><?php echo h($project['progress_status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php portal_footer(); ?>
