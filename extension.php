<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Extension Coordinator']);
verify_csrf();
$assignableUsers = assignable_users();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        db()->prepare('INSERT INTO extension_projects (project_title, coordinator, community_partner, budget, start_date, end_date, progress_status, submitted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
            ->execute([trim($_POST['project_title']), trim($_POST['coordinator']), trim($_POST['community_partner']), (float) $_POST['budget'], $_POST['start_date'], $_POST['end_date'], $_POST['progress_status'], $user['id']]);
        $id = (int) db()->lastInsertId();
        log_review('extension', $id, (int) $user['id'], 'Submitted', trim($_POST['project_title']));
        log_action((int) $user['id'], 'Submitted extension project: ' . trim($_POST['project_title']));
        flash('success', 'Extension project saved.');
    } elseif ($action === 'review') {
        $id = (int) $_POST['id'];
        $progress = in_array($_POST['progress_status'] ?? 'Planning', ['Planning', 'Ongoing', 'Completed'], true) ? $_POST['progress_status'] : 'Planning';
        $assignedTo = ($_POST['assigned_to'] ?? '') === '' ? null : (int) $_POST['assigned_to'];
        $remarks = trim($_POST['remarks'] ?? '');
        db()->prepare('UPDATE extension_projects SET progress_status = ?, assigned_to = ?, status_remarks = ? WHERE id = ?')->execute([$progress, $assignedTo, $remarks, $id]);
        log_review('extension', $id, (int) $user['id'], 'Progress: ' . $progress, $remarks);
        log_action((int) $user['id'], "Reviewed extension #{$id}: {$progress}");
        flash('success', 'Extension project updated.');
    }
    redirect_to('extension.php');
}

$notice = flash();
$statusFilter = $_GET['status'] ?? 'All';
$where = $statusFilter !== 'All' && in_array($statusFilter, ['Planning', 'Ongoing', 'Completed'], true) ? 'WHERE e.progress_status = ?' : '';
$stmt = db()->prepare("SELECT e.*, u.fullname, au.fullname AS assignee FROM extension_projects e JOIN users u ON u.id = e.submitted_by LEFT JOIN users au ON au.id = e.assigned_to {$where} ORDER BY e.created_at DESC");
$stmt->execute($where ? [$statusFilter] : []);
$projects = $stmt->fetchAll();
renderHeader('Extension Management', 'extension');
?>
<section class="module-header"><div class="module-icon"><i class="ri-community-line"></i></div><div><p>Community Extension</p><h2>Extension Projects</h2><span>Submit, assign, monitor, and report extension project progress.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="tool-panel"><form method="get" class="action-row"><select class="filter-select" name="status" onchange="this.form.submit()"><option value="All">All Progress</option><?php foreach (['Planning', 'Ongoing', 'Completed'] as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></form></section>
<section class="panel"><div class="panel-heading"><h2>Submit Extension Project</h2><span>Creates a monitored project</span></div><form class="editor-panel" method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create"><div class="form-grid"><label class="field"><span>Project Title</span><input name="project_title" required></label><label class="field"><span>Coordinator</span><input name="coordinator" value="<?php echo h($user['fullname']); ?>" required></label><label class="field"><span>Community Partner</span><input name="community_partner" required></label><label class="field"><span>Budget</span><input type="number" step="0.01" name="budget" required></label><label class="field"><span>Start Date</span><input type="date" name="start_date" required></label><label class="field"><span>End Date</span><input type="date" name="end_date" required></label><label class="field"><span>Status</span><select name="progress_status"><option>Planning</option><option>Ongoing</option><option>Completed</option></select></label></div><button class="primary-btn post-btn"><i class="ri-save-line"></i> Save Project</button></form></section>
<section class="panel"><div class="table-scroll"><table class="data-table"><thead><tr><th>Project</th><th>Coordinator</th><th>Partner</th><th>Budget</th><th>Assigned To</th><th>Status</th><th>Remarks</th><th>Manage</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr data-table-row><td><?php echo h($project['project_title']); ?></td><td><?php echo h($project['coordinator']); ?></td><td><?php echo h($project['community_partner']); ?></td><td><?php echo h(number_format((float) $project['budget'], 2)); ?></td><td><?php echo h($project['assignee'] ?? 'Unassigned'); ?></td><td><span class="status-pill"><?php echo h($project['progress_status']); ?></span></td><td><?php echo h($project['status_remarks'] ?? ''); ?></td><td><button class="ghost-btn action-btn" type="button" data-open-modal="extension-<?php echo (int) $project['id']; ?>"><i class="ri-edit-line"></i><span>Review</span></button></td></tr><?php endforeach; ?><?php if (!$projects): ?><tr><td colspan="8">No extension projects found.</td></tr><?php endif; ?></tbody></table></div></section>
<?php foreach ($projects as $project): ?><div class="modal" id="extension-<?php echo (int) $project['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Review Extension Project</h2><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>"><label class="field"><span>Progress Status</span><select name="progress_status"><?php foreach (['Planning', 'Ongoing', 'Completed'] as $status): ?><option <?php echo $project['progress_status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label><label class="field"><span>Assign Coordinator</span><select name="assigned_to"><option value="">Unassigned</option><?php foreach ($assignableUsers as $assignee): ?><option value="<?php echo (int) $assignee['id']; ?>" <?php echo (int) ($project['assigned_to'] ?? 0) === (int) $assignee['id'] ? 'selected' : ''; ?>><?php echo h($assignee['fullname'] . ' - ' . $assignee['role']); ?></option><?php endforeach; ?></select></label><label class="field"><span>Remarks</span><textarea name="remarks" rows="4"><?php echo h($project['status_remarks']); ?></textarea></label><div class="timeline-list"><?php foreach (review_timeline('extension', (int) $project['id']) as $log): ?><div><strong><?php echo h($log['action']); ?></strong><p><?php echo h($log['remarks']); ?></p><small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small></div><?php endforeach; ?></div><button class="primary-btn"><i class="ri-save-line"></i> Save Review</button></form></div><?php endforeach; ?>
<?php renderFooter(); ?>
