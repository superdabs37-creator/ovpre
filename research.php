<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin', 'Research Director', 'Research Coordinator', 'Researcher']);
verify_csrf();
$assignableUsers = assignable_users();

$role = role_access_group((string) ($user['role'] ?? ''));
$isSuperAdmin = $role === ROLE_SUPER_ADMIN;
$isOVPREAdmin = $role === ROLE_OVPRE_ADMIN;
$isDirector = $role === ROLE_RESEARCH_DIRECTOR;
$isCoordinator = $role === ROLE_RESEARCH_COORDINATOR;
$isResearcher = $role === ROLE_RESEARCHER;
$isExecutive = $isSuperAdmin || $isOVPREAdmin;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        if ($isSuperAdmin) {
            http_response_code(403);
            exit('System Admin accounts cannot submit research proposals.');
        }
        $title = trim($_POST['title'] ?? '');
        $researchers = trim($_POST['researchers'] ?? '');
        $abstract = trim($_POST['abstract'] ?? '');
        $fundingSource = trim($_POST['funding_source'] ?? 'Institutional Research Grant');

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

                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $relativePath = 'storage:' . $categoryId . '/' . $storedName;
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
                supabase_storage_upload((string) $file['tmp_name'], substr($relativePath, 8), $mime);

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
            db()->prepare('INSERT INTO research_projects (title, researchers, abstract, funding_source, document_id, submitted_by, status, workflow_stage, created_at) VALUES (?, ?, ?, ?, ?, ?, "Pending Coordinator Review", "Proposal", NOW())')
                ->execute([$title, $researchers, $abstract, $fundingSource, $documentId, (int) $user['id']]);
            $id = (int) db()->lastInsertId();
            if (db()->inTransaction()) {
                db()->commit();
            }
            log_review('research', $id, (int) $user['id'], 'Stage 1: Proposal Submitted', 'Submitted proposal for College Coordinator screening.');
            log_action((int) $user['id'], 'Submitted research proposal: ' . $title);

            // Notify college coordinators
            $coordStmt = db()->prepare('SELECT id FROM users WHERE department_id = ? AND role = "Research Coordinator" AND status = "Active"');
            $coordStmt->execute([(int) ($user['department_id'] ?? 0)]);
            foreach ($coordStmt->fetchAll(PDO::FETCH_COLUMN) as $coordId) {
                notify_user((int) $coordId, 'New research proposal submitted for college screening: ' . $title, 'research.php#proposal-' . $id);
            }

            flash('success', 'Research proposal submitted successfully! Routed to College Coordinator.');
        } catch (Throwable $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'The research proposal could not be saved: ' . $error->getMessage());
        }
    } elseif ($action === 'coordinator_endorse') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Passed college-level screening.');
        db()->prepare('UPDATE research_projects SET status = "Pending Director Review", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $remarks, $id]);

        log_review('research', $id, (int) $user['id'], 'Stage 2: Endorsed by Coordinator', $remarks);
        log_action((int) $user['id'], "Coordinator endorsed research #{$id}");

        $owner = db()->query("SELECT submitted_by, title FROM research_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], 'Your proposal "' . $owner['title'] . '" was endorsed by the Coordinator to the Research Director.', 'research.php#proposal-' . $id);
        }
        flash('success', 'Proposal endorsed to Research Director.');
    } elseif ($action === 'coordinator_return') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Returned for revision by coordinator.');
        db()->prepare('UPDATE research_projects SET status = "Needs Revision", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $remarks, $id]);

        log_review('research', $id, (int) $user['id'], 'Returned for Revision by Coordinator', $remarks);
        $owner = db()->query("SELECT submitted_by, title FROM research_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], 'Your proposal requires revision: ' . $remarks, 'research.php#proposal-' . $id);
        }
        flash('success', 'Proposal returned to faculty for revision.');
    } elseif ($action === 'director_endorse') {
        $id = (int) $_POST['id'];
        $aihrStatus = trim($_POST['aihr_status'] ?? 'Passed AIHR');
        $aihrScore = ($_POST['aihr_score'] ?? '') !== '' ? (float) $_POST['aihr_score'] : null;
        $grantAmount = ($_POST['grant_amount'] ?? '') !== '' ? (float) $_POST['grant_amount'] : null;
        $remarks = trim($_POST['remarks'] ?? 'AIHR evaluation completed and grant recommended.');

        db()->prepare('UPDATE research_projects SET status = "Pending Executive Approval", director_id = ?, director_remarks = ?, aihr_status = ?, aihr_score = ?, grant_amount = ?, director_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $aihrStatus, $aihrScore, $grantAmount, $remarks, $id]);

        $logDetails = "AIHR Status: {$aihrStatus} | Score: " . ($aihrScore ?? 'N/A') . " | Rec. Grant: PHP " . number_format((float) $grantAmount, 2) . "\n" . $remarks;
        log_review('research', $id, (int) $user['id'], 'Stage 3: Endorsed by Research Director', $logDetails);
        log_action((int) $user['id'], "Research Director endorsed research #{$id}");

        $owner = db()->query("SELECT submitted_by, title FROM research_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], 'Your proposal passed AIHR review and is forwarded to OVPRE Executive for final approval.', 'research.php#proposal-' . $id);
        }
        flash('success', 'AIHR evaluation completed and endorsed to VP / OVPRE Executive.');
    } elseif ($action === 'executive_approve') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Granted Final Institutional Approval.');

        db()->prepare('UPDATE research_projects SET status = "Approved", executive_id = ?, executive_remarks = ?, executive_approved_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $remarks, $id]);

        log_review('research', $id, (int) $user['id'], 'Stage 4: Final Executive Approval Granted', $remarks);
        log_action((int) $user['id'], "Executive approved research #{$id}");

        $owner = db()->query("SELECT submitted_by, title FROM research_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], '🎉 Congratulations! Your research proposal "' . $owner['title'] . '" received Final Approval from OVPRE!', 'research.php#proposal-' . $id);
        }
        flash('success', 'Final Institutional Approval successfully granted!');
    } elseif ($action === 'decline') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Proposal declined.');
        db()->prepare('UPDATE research_projects SET status = "Declined", status_remarks = ? WHERE id = ?')->execute([$remarks, $id]);
        log_review('research', $id, (int) $user['id'], 'Declined', $remarks);
        flash('success', 'Proposal has been declined.');
    } elseif ($action === 'review') {
        // Super Admin manual review/assignment
        $id = (int) $_POST['id'];
        $status = in_array($_POST['status'] ?? 'Pending Coordinator Review', [
            'Pending Coordinator Review', 'Pending Director Review', 'Pending Executive Approval', 'Approved', 'Needs Revision', 'Declined',
            'Pending', 'Under Review'
        ], true) ? $_POST['status'] : 'Pending Coordinator Review';
        $assignedTo = ($_POST['assigned_to'] ?? '') === '' ? null : (int) $_POST['assigned_to'];
        $remarks = trim($_POST['remarks'] ?? '');
        db()->prepare('UPDATE research_projects SET status = ?, assigned_to = ?, status_remarks = ? WHERE id = ?')->execute([$status, $assignedTo, $remarks, $id]);
        log_review('research', $id, (int) $user['id'], 'Status: ' . $status, $remarks);
        flash('success', 'Research record updated.');
    }
    redirect_to('research.php');
}

$notice = flash();
$statusFilter = $_GET['status'] ?? 'All';
$departmentFilter = (int) ($_GET['department'] ?? 0);
$conditions = [];
$queryParams = [];

// Enforce role scoping:
if ($isResearcher) {
    $conditions[] = 'r.submitted_by = ?';
    $queryParams[] = (int) $user['id'];
} elseif ($isCoordinator) {
    if (!empty($user['department_id'])) {
        $conditions[] = 'u.department_id = ?';
        $queryParams[] = (int) $user['department_id'];
    }
}

if ($statusFilter !== 'All') {
    $conditions[] = 'r.status = ?';
    $queryParams[] = $statusFilter;
}
if ($departmentFilter > 0 && !$isCoordinator) {
    $conditions[] = 'u.department_id = ?';
    $queryParams[] = $departmentFilter;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$stmt = db()->prepare("
    SELECT r.*, u.fullname, dep.department_name, au.fullname AS assignee, 
           d.filename AS document_filename, d.original_filename AS document_original_filename,
           coord.fullname AS coordinator_name, dir.fullname AS director_name, exec.fullname AS executive_name
    FROM research_projects r 
    JOIN users u ON u.id = r.submitted_by 
    LEFT JOIN departments dep ON dep.id = u.department_id
    LEFT JOIN users au ON au.id = r.assigned_to 
    LEFT JOIN documents d ON d.id = r.document_id 
    LEFT JOIN users coord ON coord.id = r.coordinator_id
    LEFT JOIN users dir ON dir.id = r.director_id
    LEFT JOIN users exec ON exec.id = r.executive_id
    {$where} 
    ORDER BY r.created_at DESC
");
$stmt->execute($queryParams);
$projects = $stmt->fetchAll();

renderHeader('Research Management', 'research');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-flask-line"></i></div>
    <div>
        <p>Research Repository</p>
        <h2>Research Management & Workflow</h2>
        <span>Academic Pipeline: Proponent &rarr; College Coordinator &rarr; Research Director (AIHR) &rarr; Executive Final Approval</span>
    </div>
</section>

<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>

<!-- PIPELINE FLOW HIGHLIGHT -->
<section class="panel" style="margin-bottom: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border-left: 5px solid #16a34a;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="margin: 0; color: #166534;"><i class="ri-git-merge-line"></i> Multi-Tier Approval Workflow</h3>
            <p style="margin: 4px 0 0; color: #4b5563; font-size: 0.9rem;">
                Currently logged in as: <strong><?php echo h($user['fullname']); ?></strong> (<?php echo h($user['role']); ?>)
            </p>
        </div>
        <div>
            <span class="role-chip" style="font-size: 0.85rem; padding: 4px 10px; background: #dcfce7; color: #166534;">
                <?php
                if ($isCoordinator) echo 'College Coordinator Scope (Department Level)';
                elseif ($isDirector) echo 'Director AIHR Evaluation Scope (University Wide)';
                elseif ($isExecutive) echo 'OVPRE Executive Scope (Final Approval)';
                else echo 'Faculty Proponent Scope';
                ?>
            </span>
        </div>
    </div>
</section>

<!-- SUBMIT PROPOSAL PANEL (FOR RESEARCHERS AND COORDINATORS) -->
<?php if (!$isSuperAdmin): ?>
<section class="panel modal-launcher-panel">
    <div class="panel-heading">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: bold; color: #16a34a;">Research Proposals</h2>
            <span style="font-size: 1.1rem; color: #4b5563;">Submit a new research proposal for College Coordinator screening.</span>
        </div>
        <button class="primary-btn" type="button" data-open-modal="createResearchProposalModal"><i class="ri-add-line"></i> Submit Research Proposal</button>
    </div>
</section>

<div class="modal" id="createResearchProposalModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createResearchProposalTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card simple-submit-modal">
        <button class="modal-close" type="button" data-close-modal aria-label="Close form"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head">
            <span class="category-badge">Research</span>
            <h2 id="createResearchProposalTitle">Submit Research Proposal</h2>
            <p>Stage 1: Proposal Writing & Submission (Routed to College Coordinator for Initial Screening)</p>
        </div>
        <form class="editor-panel" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <label class="field"><span>Title</span><input name="title" maxlength="220" required></label>
                <label class="field"><span>Researchers</span><input name="researchers" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Target Funding Source</span><input name="funding_source" value="Institutional Research Grant"></label>
            </div>
            <label class="field"><span>Abstract</span><textarea rows="5" name="abstract" required></textarea></label>
            <label class="field"><span>Research Document (PDF, DOC, DOCX, ZIP; up to 15 MB)</span><input type="file" name="file" accept=".pdf,.doc,.docx,.zip" required></label>
            <button class="primary-btn post-btn" type="submit"><i class="ri-send-plane-line"></i> Submit to Research Coordinator</button>
        </form>
    </article>
</div>
<?php endif; ?>

<!-- FILTER TOOLBAR -->
<section class="tool-panel">
    <form method="get" class="action-row">
        <select class="filter-select" name="status" onchange="this.form.submit()">
            <option value="All">All Statuses</option>
            <?php foreach ([
                'Pending Coordinator Review' => '1. For Coordinator Screening',
                'Pending Director Review' => '2. For Director AIHR Evaluation',
                'Pending Executive Approval' => '3. For Executive Final Approval',
                'Approved' => 'Approved',
                'Needs Revision' => 'Needs Revision',
                'Declined' => 'Declined',
            ] as $stKey => $stLabel): ?>
                <option value="<?php echo h($stKey); ?>" <?php echo $statusFilter === $stKey ? 'selected' : ''; ?>><?php echo h($stLabel); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$isCoordinator): ?>
            <select class="filter-select" name="department" onchange="this.form.submit()">
                <option value="0">All Departments / Colleges</option>
                <?php foreach (departments() as $department): ?>
                    <option value="<?php echo (int) $department['id']; ?>" <?php echo $departmentFilter === (int) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </form>
</section>

<!-- PROJECTS TABLE -->
<section class="panel">
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Researchers</th>
                    <th>College / Dept</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>File</th>
                    <th>Date</th>
                    <th>Manage / Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php $pStatus = $project['status']; ?>
                    <tr id="proposal-<?php echo (int) $project['id']; ?>">
                        <td><strong><?php echo h($project['title']); ?></strong></td>
                        <td><?php echo h($project['researchers']); ?></td>
                        <td><span class="dept-pill"><?php echo h($project['department_name'] ?? 'College'); ?></span></td>
                        <td><span style="font-weight:600; color:#166534;"><?php echo h($project['workflow_stage'] ?? 'Proposal'); ?></span></td>
                        <td>
                            <span class="status-pill status-<?php echo h(statusClass($pStatus)); ?>">
                                <?php echo h($pStatus); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($project['document_filename'])): ?>
                                <a href="<?php echo h(record_access_url($project['document_filename'], '', (int) $project['document_id'])); ?>" target="_blank" rel="noopener" class="ghost-btn mini-btn" title="Download Document">
                                    <i class="ri-file-download-line"></i>
                                </a>
                            <?php else: ?>
                                <span class="muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h(date('M d, Y', strtotime($project['created_at']))); ?></td>
                        <td>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <?php if ($isCoordinator && $pStatus === 'Pending Coordinator Review'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="coord-modal-<?php echo (int) $project['id']; ?>" style="background: #b45309;">
                                        <i class="ri-check-line"></i> Screen
                                    </button>
                                <?php elseif ($isDirector && $pStatus === 'Pending Director Review'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="dir-modal-<?php echo (int) $project['id']; ?>" style="background: #1d4ed8;">
                                        <i class="ri-award-line"></i> AIHR
                                    </button>
                                <?php elseif ($isExecutive && $pStatus === 'Pending Executive Approval'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="exec-modal-<?php echo (int) $project['id']; ?>" style="background: #7e22ce;">
                                        <i class="ri-shield-check-line"></i> Approve
                                    </button>
                                <?php endif; ?>

                                <button class="ghost-btn action-btn" type="button" data-open-modal="research-<?php echo (int) $project['id']; ?>">
                                    <i class="ri-eye-line"></i><span>Details</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$projects): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 24px;">No research proposals found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- MODALS -->
<?php foreach ($projects as $project): ?>
    <?php $projId = (int) $project['id']; ?>

    <!-- VIEW & SUPER ADMIN REVIEW MODAL -->
    <div class="modal" id="research-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 650px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2>Research Proposal Details</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['title']); ?></strong></p>
            <p style="color: #6b7280; font-size: 0.9rem;">Researchers: <?php echo h($project['researchers']); ?> | Submitter: <?php echo h($project['fullname']); ?></p>

            <div style="background: #f9fafb; padding: 12px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 12px;">
                <strong>Abstract:</strong>
                <p style="margin: 4px 0 0;"><?php echo nl2br(h($project['abstract'])); ?></p>
            </div>

            <?php if (!empty($project['coordinator_remarks'])): ?>
                <div style="border-left: 3px solid #b45309; padding-left: 10px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #b45309;">Coordinator Endorsement:</strong>
                    <div><?php echo nl2br(h($project['coordinator_remarks'])); ?></div>
                    <small style="color: #6b7280;">By: <?php echo h($project['coordinator_name'] ?? 'Coordinator'); ?></small>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['director_remarks'])): ?>
                <div style="border-left: 3px solid #1d4ed8; padding-left: 10px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #1d4ed8;">Director AIHR Evaluation:</strong>
                    <div>Score: <?php echo h($project['aihr_score'] ?? 'N/A'); ?>/100 | Grant: PHP <?php echo number_format((float) ($project['grant_amount'] ?? 0), 2); ?></div>
                    <div><?php echo nl2br(h($project['director_remarks'])); ?></div>
                    <small style="color: #6b7280;">By: <?php echo h($project['director_name'] ?? 'Director'); ?></small>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['executive_remarks'])): ?>
                <div style="border-left: 3px solid #7e22ce; padding-left: 10px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #7e22ce;">Executive Final Approval:</strong>
                    <div><?php echo nl2br(h($project['executive_remarks'])); ?></div>
                    <small style="color: #6b7280;">By: <?php echo h($project['executive_name'] ?? 'Executive'); ?></small>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['document_filename'])): ?>
                <p><a href="<?php echo h(record_access_url($project['document_filename'], '', (int) $project['document_id'])); ?>" target="_blank" rel="noopener" class="primary-btn"><i class="ri-download-cloud-line"></i> Download Attached Document</a></p>
            <?php endif; ?>

            <?php if ($isSuperAdmin): ?>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 16px 0;">
                <input type="hidden" name="action" value="review">
                <h3>Super Admin Status Control</h3>
                <label class="field"><span>Status Override:</span>
                    <select name="status">
                        <?php foreach (['Pending Coordinator Review', 'Pending Director Review', 'Pending Executive Approval', 'Approved', 'Needs Revision', 'Declined'] as $st): ?>
                            <option value="<?php echo h($st); ?>" <?php echo $project['status'] === $st ? 'selected' : ''; ?>><?php echo h($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Remarks:</span>
                    <textarea name="remarks" rows="2"><?php echo h($project['status_remarks'] ?? ''); ?></textarea>
                </label>
                <button class="primary-btn"><i class="ri-save-line"></i> Update Record</button>
            <?php endif; ?>

            <div class="timeline-list" style="margin-top: 16px;">
                <?php foreach (review_timeline('research', $projId) as $log): ?>
                    <div>
                        <strong><?php echo h($log['action']); ?></strong>
                        <p><?php echo h($log['remarks']); ?></p>
                        <small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <!-- COORDINATOR MODAL -->
    <?php if ($isCoordinator || $isExecutive): ?>
    <div class="modal" id="coord-modal-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 580px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2 style="color: #b45309;"><i class="ri-shield-check-line"></i> College Coordinator Screening</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['title']); ?></strong></p>
            <label class="field"><span>Endorsement Remarks / Feedback:</span>
                <textarea name="remarks" rows="3" required placeholder="Enter initial screening remarks before endorsing to the Research Director..."></textarea>
            </label>
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px;">
                <button class="primary-btn" type="submit" name="action" value="coordinator_endorse" style="background: #15803d;">
                    <i class="ri-check-line"></i> Endorse to Director
                </button>
                <button class="ghost-btn" type="submit" name="action" value="coordinator_return" style="color: #c2410c;">
                    <i class="ri-arrow-go-back-line"></i> Return for Revision
                </button>
                <button class="ghost-btn danger-btn" type="submit" name="action" value="decline">Decline</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- DIRECTOR MODAL -->
    <?php if ($isDirector || $isExecutive): ?>
    <div class="modal" id="dir-modal-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 620px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2 style="color: #1d4ed8;"><i class="ri-award-line"></i> Director AIHR & Grant Evaluation</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['title']); ?></strong></p>
            <div class="form-grid">
                <label class="field"><span>AIHR Status:</span>
                    <select name="aihr_status">
                        <option value="Passed AIHR">Passed AIHR</option>
                        <option value="Passed with Conditions">Passed with Conditions</option>
                    </select>
                </label>
                <label class="field"><span>AIHR Score (1-100):</span>
                    <input type="number" step="0.01" min="0" max="100" name="aihr_score" placeholder="85.00" required>
                </label>
            </div>
            <label class="field"><span>Recommended Grant Amount (PHP):</span>
                <input type="number" step="0.01" min="0" name="grant_amount" value="50000.00">
            </label>
            <label class="field"><span>Evaluation & Recommendation Remarks:</span>
                <textarea name="remarks" rows="3" required placeholder="Evaluation feedback and recommendations regarding funding and national agenda alignment..."></textarea>
            </label>
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px;">
                <button class="primary-btn" type="submit" name="action" value="director_endorse" style="background: #1d4ed8;">
                    <i class="ri-send-plane-line"></i> Endorse to VP / Executive
                </button>
                <button class="ghost-btn" type="submit" name="action" value="director_return" style="color: #c2410c;">
                    <i class="ri-arrow-go-back-line"></i> Return for Revision
                </button>
                <button class="ghost-btn danger-btn" type="submit" name="action" value="decline">Decline</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- EXECUTIVE MODAL -->
    <?php if ($isExecutive): ?>
    <div class="modal" id="exec-modal-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 600px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2 style="color: #7e22ce;"><i class="ri-checkbox-circle-line"></i> OVPRE Executive Final Approval</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['title']); ?></strong></p>
            <label class="field"><span>Executive Resolution & Directives:</span>
                <textarea name="remarks" rows="4" required>Approved for implementation under the Institutional Research Program. Notice of Award issued.</textarea>
            </label>
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px;">
                <button class="primary-btn" type="submit" name="action" value="executive_approve" style="background: #16a34a;">
                    <i class="ri-check-double-line"></i> Grant Final Approval
                </button>
                <button class="ghost-btn danger-btn" type="submit" name="action" value="decline">Disapprove</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

<?php endforeach; ?>

<?php renderFooter(); ?>
