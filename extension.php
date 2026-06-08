<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Extension Coordinator']);
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('INSERT INTO extension_projects (project_title, coordinator, community_partner, budget, start_date, end_date, progress_status, submitted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([trim($_POST['project_title']), trim($_POST['coordinator']), trim($_POST['community_partner']), (float) $_POST['budget'], $_POST['start_date'], $_POST['end_date'], $_POST['progress_status'], $user['id']]);
    log_action((int) $user['id'], 'Submitted extension project: ' . trim($_POST['project_title']));
    flash('success', 'Extension project saved.');
    redirect_to('extension.php');
}
$notice = flash();
$projects = db()->query('SELECT e.*, u.fullname FROM extension_projects e JOIN users u ON u.id = e.submitted_by ORDER BY e.created_at DESC')->fetchAll();
renderHeader('Extension Management', 'extension');
?>
<section class="module-header"><div class="module-icon"><i class="ri-community-line"></i></div><div><p>Community Extension</p><h2>Extension Projects</h2><span>Submit projects and monitor planning, ongoing, and completed progress.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel"><form class="editor-panel" method="post"><?php echo csrf_field(); ?><div class="form-grid"><label class="field"><span>Project Title</span><input name="project_title" required></label><label class="field"><span>Coordinator</span><input name="coordinator" value="<?php echo h($user['fullname']); ?>" required></label><label class="field"><span>Community Partner</span><input name="community_partner" required></label><label class="field"><span>Budget</span><input type="number" step="0.01" name="budget" required></label><label class="field"><span>Start Date</span><input type="date" name="start_date" required></label><label class="field"><span>End Date</span><input type="date" name="end_date" required></label><label class="field"><span>Status</span><select name="progress_status"><option>Planning</option><option>Ongoing</option><option>Completed</option></select></label></div><button class="primary-btn post-btn"><i class="ri-save-line"></i> Save Project</button></form></section>
<section class="panel"><div class="table-scroll"><table class="data-table"><thead><tr><th>Project</th><th>Coordinator</th><th>Partner</th><th>Budget</th><th>Status</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr><td><?php echo h($project['project_title']); ?></td><td><?php echo h($project['coordinator']); ?></td><td><?php echo h($project['community_partner']); ?></td><td><?php echo h(number_format((float) $project['budget'], 2)); ?></td><td><span class="status-pill"><?php echo h($project['progress_status']); ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php renderFooter(); ?>
