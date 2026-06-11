<?php
if (!portal_can('research')) { http_response_code(403); exit('Research module is not available for this role.'); }
$user = portal_require_user();
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $documentId = portal_save_document($_POST, $_FILES['supporting_document'] ?? [], $user);
        db()->prepare('INSERT INTO research_projects (title, researchers, abstract, funding_source, document_id, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "Pending", NOW())')
            ->execute([trim($_POST['title']), trim($_POST['researchers']), trim($_POST['abstract']), trim($_POST['funding_source'] ?? ''), $documentId, $user['id']]);
        log_action((int) $user['id'], 'Submitted research proposal: ' . trim($_POST['title']));
        flash('success', 'Research proposal submitted for Super Admin review.');
        redirect_to('research.php');
    } catch (Throwable $error) { $notice = ['type' => 'error', 'message' => $error->getMessage()]; }
}
$user = portal_header('Research Submission', 'research');
$projects = portal_research_projects();
?>
<?php portal_notice($notice); ?>
<section class="panel"><div class="panel-heading"><h2>Submit Research Proposal</h2><span>Visible to Super Admin</span></div><form class="portal-form" method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><div class="form-grid"><label class="field"><span>Proposal Title</span><input name="title" required></label><label class="field"><span>Researchers</span><input name="researchers" value="<?php echo h($user['fullname']); ?>" required></label><label class="field"><span>Funding Source</span><input name="funding_source"></label><label class="field"><span>Document Category</span><select name="category_id"><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label></div><label class="field"><span>Abstract</span><textarea name="abstract" rows="5" required></textarea></label><label class="field"><span>Supporting Document</span><input type="file" name="supporting_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"></label><input type="hidden" name="document_title" value="Research Supporting Document"><input type="hidden" name="document_description" value="Research proposal supporting document"><button class="primary-btn"><i class="ri-send-plane-line"></i> Submit Research Proposal</button></form></section>
<section class="panel"><div class="panel-heading"><h2>Research Submissions</h2><span>Pending | Under Review | Approved | Rejected</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Title</th><th>Researchers</th><th>Submitted By</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr><td><?php echo h($project['title']); ?></td><td><?php echo h($project['researchers']); ?></td><td><?php echo h($project['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($project['status'])); ?>"><?php echo h($project['status'] === 'Declined' ? 'Rejected' : $project['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($project['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php portal_footer(); ?>
