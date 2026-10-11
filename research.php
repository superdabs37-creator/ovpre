<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator']);
verify_csrf();
$assignableUsers = assignable_users();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $researchers = trim($_POST['researchers'] ?? '');
        $abstract = trim($_POST['abstract'] ?? '');
        if ($title === '' || $researchers === '' || $abstract === '') {
            flash('error', 'Title, researchers, and abstract are required.');
            redirect_to('research.php');
        }

        $documentId = null;
        $file = $_FILES['file'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The research document could not be uploaded.');
                }
                if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
                    throw new RuntimeException('The research document exceeds the 15 MB limit.');
                }
                $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['pdf', 'doc', 'docx', 'zip'];
                if (!in_array($extension, $allowedExtensions, true)) {
                    throw new RuntimeException('Upload a PDF, DOC, DOCX, or ZIP file.');
                }

                $categoryId = (int) (db()->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
                $departmentId = (int) ($user['department_id'] ?? 0);
                if ($departmentId <= 0) {
                    $departmentId = (int) (db()->query('SELECT id FROM departments ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
                }
                if ($categoryId <= 0 || $departmentId <= 0) {
                    throw new RuntimeException('A document category and department must be configured before uploading.');
                }

                $folder = UPLOAD_DIR . '/' . $categoryId;
                if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
                    throw new RuntimeException('The upload folder could not be created.');
                }
                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $relativePath = $categoryId . '/' . $storedName;
                if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $storedName)) {
                    throw new RuntimeException('The research document could not be saved.');
                }

                db()->beginTransaction();
                db()->prepare('INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", CURDATE(), NOW())')
                    ->execute([$title . ' - Research Document', $categoryId, 'Supporting document for research proposal.', $user['fullname'], $departmentId, $relativePath, basename((string) $file['name']), (int) $user['id']]);
                $documentId = (int) db()->lastInsertId();
                db()->prepare('INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, "Original Version", ?, ?, NOW())')
                    ->execute([$documentId, $relativePath, (int) $user['id']]);
            } catch (Throwable $error) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('error', $error->getMessage());
                redirect_to('research.php');
            }
        }

        try {
            db()->prepare('INSERT INTO research_projects (title, researchers, abstract, document_id, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, "Pending", NOW())')
                ->execute([$title, $researchers, $abstract, $documentId, (int) $user['id']]);
            $id = (int) db()->lastInsertId();
            if (db()->inTransaction()) {
                db()->commit();
            }
            log_review('research', $id, (int) $user['id'], 'Submitted', $abstract);
            log_action((int) $user['id'], 'Submitted research proposal: ' . $title);
            flash('success', 'Research proposal submitted successfully.');
        } catch (Throwable $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'The research proposal could not be saved.');
        }
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
$stmt = db()->prepare("SELECT r.*, u.fullname, au.fullname AS assignee, d.filename AS document_filename, d.original_filename AS document_original_filename FROM research_projects r JOIN users u ON u.id = r.submitted_by LEFT JOIN users au ON au.id = r.assigned_to LEFT JOIN documents d ON d.id = r.document_id {$where} ORDER BY r.created_at DESC");
$stmt->execute($where ? [$statusFilter] : []);
$projects = $stmt->fetchAll();
renderHeader('Research Management', 'research');
?>
<section class="module-header"><div class="module-icon"><i class="ri-flask-line"></i></div><div><p>Research Repository</p><h2>Research Management</h2><span>Submit, assign, review, approve, reject, and track research proposals with files.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="tool-panel"><form method="get" class="action-row"><select class="filter-select" name="status" onchange="this.form.submit()"><option value="All">All Status</option><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status === 'Declined' ? 'Rejected' : $status); ?></option><?php endforeach; ?></select></form></section>

<section class="panel modal-launcher-panel"><div class="panel-heading"><div><h2>Research Proposals</h2><span>Submit a proposal for review.</span></div><button class="primary-btn" type="button" data-open-modal="createResearchProposalModal"><i class="ri-add-line"></i> Submit Research Proposal</button></div></section>
<div class="modal" id="createResearchProposalModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createResearchProposalTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card simple-submit-modal">
        <button class="modal-close" type="button" data-close-modal aria-label="Close form"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head"><span class="category-badge">Research</span><h2 id="createResearchProposalTitle">Submit Research Proposal</h2><p>Submit a proposal for review. You can attach a supporting document.</p></div>
        <form class="editor-panel" method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create"><div class="form-grid"><label class="field"><span>Title</span><input name="title" maxlength="220" required></label><label class="field"><span>Researchers</span><input name="researchers" required></label></div><label class="field"><span>Abstract</span><textarea rows="5" name="abstract" required></textarea></label><label class="field"><span>Research Document (PDF, DOC, DOCX, ZIP; up to 15 MB)</span><input type="file" name="file" accept=".pdf,.doc,.docx,.zip"></label><button class="primary-btn post-btn" type="submit"><i class="ri-send-plane-line"></i> Submit Research</button></form>
    </article>
</div>

<section class="panel"><div class="table-scroll"><table class="data-table"><thead><tr><th>Title</th><th>Researchers</th><th>Submitted By</th><th>Assigned To</th><th>Status</th><th>File</th><th>Date</th><th>Manage</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr data-table-row><td><?php echo h($project['title']); ?></td><td><?php echo h($project['researchers']); ?></td><td><?php echo h($project['fullname']); ?></td><td><?php echo h($project['assignee'] ?? 'Unassigned'); ?></td><td><span class="status-pill status-<?php echo h(statusClass($project['status'])); ?>"><?php echo h($project['status'] === 'Declined' ? 'Rejected' : $project['status']); ?></span></td><td><?php if (!empty($project['document_filename'])): ?><a href="<?php echo h('uploads/records/' . $project['document_filename']); ?>" target="_blank" rel="noopener" class="ghost-btn mini-btn" aria-label="Open <?php echo h($project['document_original_filename'] ?? 'research document'); ?>"><i class="ri-file-download-line"></i></a><?php else: ?><span class="muted">None</span><?php endif; ?></td><td><?php echo h(date('M d, Y', strtotime($project['created_at']))); ?></td><td><button class="ghost-btn action-btn" type="button" data-open-modal="research-<?php echo (int) $project['id']; ?>"><i class="ri-edit-line"></i><span>Review</span></button></td></tr><?php endforeach; ?><?php if (!$projects): ?><tr><td colspan="8">No research proposals found.</td></tr><?php endif; ?></tbody></table></div></section>

<?php foreach ($projects as $project): ?><div class="modal" id="research-<?php echo (int) $project['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Review Research</h2><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?php echo (int) $project['id']; ?>"><label class="field"><span>Status</span><select name="status"><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option value="<?php echo h($status); ?>" <?php echo $project['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label><label class="field"><span>Assign Reviewer / Coordinator</span><select name="assigned_to"><option value="">Unassigned</option><?php foreach ($assignableUsers as $assignee): ?><option value="<?php echo (int) $assignee['id']; ?>" <?php echo (int) ($project['assigned_to'] ?? 0) === (int) $assignee['id'] ? 'selected' : ''; ?>><?php echo h($assignee['fullname'] . ' - ' . $assignee['role']); ?></option><?php endforeach; ?></select></label><label class="field"><span>Remarks</span><textarea name="remarks" rows="4"><?php echo h($project['status_remarks'] ?? ''); ?></textarea></label><?php if (!empty($project['document_filename'])): ?><p><a href="<?php echo h('uploads/records/' . $project['document_filename']); ?>" target="_blank" rel="noopener" class="primary-btn"><i class="ri-download-cloud-line"></i> Download Attached Document</a></p><?php endif; ?><div class="timeline-list"><?php foreach (review_timeline('research', (int) $project['id']) as $log): ?><div><strong><?php echo h($log['action']); ?></strong><p><?php echo h($log['remarks']); ?></p><small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small></div><?php endforeach; ?></div><button class="primary-btn"><i class="ri-save-line"></i> Save Review</button></form></div><?php endforeach; ?>
<?php renderFooter(); ?>
