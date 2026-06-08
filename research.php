<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator', 'Faculty Researcher', 'Evaluator/Reviewer']);
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('INSERT INTO research_projects (title, researchers, abstract, funding_source, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, "Pending", NOW())')
        ->execute([trim($_POST['title']), trim($_POST['researchers']), trim($_POST['abstract']), trim($_POST['funding_source'] ?? ''), $user['id']]);
    log_action((int) $user['id'], 'Submitted research proposal: ' . trim($_POST['title']));
    flash('success', 'Research proposal submitted.');
    redirect_to('research.php');
}
$notice = flash();
$projects = db()->query('SELECT r.*, u.fullname FROM research_projects r JOIN users u ON u.id = r.submitted_by ORDER BY r.created_at DESC')->fetchAll();
renderHeader('Research Management', 'research');
?>
<section class="module-header"><div class="module-icon"><i class="ri-flask-line"></i></div><div><p>Research Repository</p><h2>Research Management</h2><span>Submit proposals and track pending, under review, approved, and declined studies.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel"><form class="editor-panel" method="post"><?php echo csrf_field(); ?><div class="form-grid"><label class="field"><span>Title</span><input name="title" required></label><label class="field"><span>Researchers</span><input name="researchers" required></label><label class="field"><span>Funding Source</span><input name="funding_source"></label></div><label class="field"><span>Abstract</span><textarea rows="5" name="abstract" required></textarea></label><button class="primary-btn post-btn"><i class="ri-send-plane-line"></i> Submit Research</button></form></section>
<section class="panel"><div class="table-scroll"><table class="data-table"><thead><tr><th>Title</th><th>Researchers</th><th>Submitted By</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr><td><?php echo h($project['title']); ?></td><td><?php echo h($project['researchers']); ?></td><td><?php echo h($project['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($project['status'])); ?>"><?php echo h($project['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($project['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php renderFooter(); ?>
