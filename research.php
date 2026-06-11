<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator']);
verify_csrf();
$assignableUsers = assignable_users();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        db()->prepare('INSERT INTO research_projects (title, researchers, abstract, funding_source, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, "Pending", NOW())')
            ->execute([trim($_POST['title']), trim($_POST['researchers']), trim($_POST['abstract']), trim($_POST['funding_source'] ?? ''), $user['id']]);
        $id = (int) db()->lastInsertId();
        log_review('research', $id, (int) $user['id'], 'Submitted', trim($_POST['abstract']));
        log_action((int) $user['id'], 'Submitted research proposal: ' . trim($_POST['title']));
        flash('success', 'Research proposal submitted.');
    } elseif ($action === 'review') {
        $id = (int) $_POST['id'];
        $status = in_array($_POST['status'] ?? 'Pending', ['Pending', 'Under Review', 'Approved', 'Declined'], true) ? $_POST['status'] : 'Pending';
        $assignedTo = ($_POST['assigned_to'] ?? '') === '' ? null : (int) $_POST['assigned_to'];
        $remarks = trim($_POST['remarks'] ?? '');
        db()->prepare('UPDATE research_projects SET status = ?, assigned_to = ?, status_remarks = ? WHERE id = ?')->execute([$status, $assignedTo, $remarks, $id]);
        log_review('research', $id, (int) $user['id'], 'Status: ' . $status, $remarks);
        log_action((int) $user['id'], "Reviewed research #{$id}: {$status}");
        flash('success', 'Research review updated.');
    }
    redirect_to('research.php');
}

$notice = flash();
$statusFilter = $_GET['status'] ?? 'All';
$where = $statusFilter !== 'All' && in_array($statusFilter, ['Pending', 'Under Review', 'Approved', 'Declined'], true) ? 'WHERE r.status = ?' : '';
$stmt = db()->prepare("SELECT r.*, u.fullname, au.fullname AS assignee FROM research_projects r JOIN users u ON u.id = r.submitted_by LEFT JOIN users au ON au.id = r.assigned_to {$where} ORDER BY r.created_at DESC");
$stmt->execute($where ? [$statusFilter] : []);
$projects = $stmt->fetchAll();
renderHeader('Research Management', 'research');
?>
<section class="module-header"><div class="module-icon"><i class="ri-flask-line"></i></div><div><p>Research Repository</p><h2>Research Management</h2><span>Submit, assign, review, approve, reject, and track research proposals.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="tool-panel"><form method="get" class="action-row"><select class="filter-select" name="status" onchange="this.form.submit()"><option value="All">All Status</option><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status === 'Declined' ? 'Rejected' : $status); ?></option><?php endforeach; ?></select></form></section>
<section class="panel"><div class="panel-heading"><h2>Submit Research Proposal</h2><span>Creates a pending review item</span></div><form class="editor-panel" method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create"><div class="form-grid"><label class="field"><span>Title</span><input name="title" required></label><label class="field"><span>Researchers</span><input name="researchers" required></label><label class="field"><span>Funding Source</span><input name="funding_source"></label></div><label class="field"><span>Abstract</span><textarea rows="5" name="abstract" required></textarea></label><button class="primary-btn post-btn"><i class="ri-send-plane-line"></i> Submit Research</button></form></section>
<section class="panel"><div class="table-scroll"><table class="data-table"><thead><tr><th>Title</th><th>Researchers</th><th>Submitted By</th><th>Assigned To</th><th>Status</th><th>Remarks</th><th>Date</th><th>Manage</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr data-table-row><td><?php echo h($project['title']); ?></td><td><?php echo h($project['researchers']); ?></td><td><?php echo h($project['fullname']); ?></td><td><?php echo h($project['assignee'] ?? 'Unassigned'); ?></td><td><span class="status-pill status-<?php echo h(statusClass($project['status'])); ?>"><?php echo h($project['status'] === 'Declined' ? 'Rejected' : $project['status']); ?></span></td><td><?php echo h($project['status_remarks'] ?? ''); ?></td><td><?php echo h(date('M d, Y', strtotime($project['created_at']))); ?></td><td><button class="ghost-btn action-btn" type="button" data-open-modal="research-<?php echo (int) $project['id']; ?>"><i class="ri-edit-line"></i><span>Review</span></button></td></tr><?php endforeach; ?><?php if (!$projects): ?><tr><td colspan="8">No research proposals found.</td></tr><?php endif; ?></tbody></table></div></section>
<?php foreach ($projects as $project): ?><div class="modal" id="research-<?php echo (int) $project['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Review Research</h2><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>"><label class="field"><span>Status</span><select name="status"><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option <?php echo $project['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label><label class="field"><span>Assign Reviewer / Coordinator</span><select name="assigned_to"><option value="">Unassigned</option><?php foreach ($assignableUsers as $assignee): ?><option value="<?php echo (int) $assignee['id']; ?>" <?php echo (int) ($project['assigned_to'] ?? 0) === (int) $assignee['id'] ? 'selected' : ''; ?>><?php echo h($assignee['fullname'] . ' - ' . $assignee['role']); ?></option><?php endforeach; ?></select></label><label class="field"><span>Remarks</span><textarea name="remarks" rows="4"><?php echo h($project['status_remarks']); ?></textarea></label><div class="timeline-list"><?php foreach (review_timeline('research', (int) $project['id']) as $log): ?><div><strong><?php echo h($log['action']); ?></strong><p><?php echo h($log['remarks']); ?></p><small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small></div><?php endforeach; ?></div><button class="primary-btn"><i class="ri-save-line"></i> Save Review</button></form></div><?php endforeach; ?>
<?php renderFooter(); ?>
