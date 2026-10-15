<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin', 'Extension Director', 'Extension Coordinator', 'Faculty Extensionist']);
verify_csrf();
$assignableUsers = assignable_users();

$role = role_access_group((string) ($user['role'] ?? ''));
$isSuperAdmin = $role === ROLE_SUPER_ADMIN;
$isOVPREAdmin = $role === ROLE_OVPRE_ADMIN;
$isDirector = $role === ROLE_EXTENSION_DIRECTOR;
$isCoordinator = $role === ROLE_EXTENSION_COORDINATOR;
$isExtensionist = $role === ROLE_FACULTY_EXTENSIONIST;
$isExecutive = $isSuperAdmin || $isOVPREAdmin;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        if ($isSuperAdmin) {
            http_response_code(403);
            exit('System Admin accounts cannot submit extension projects.');
        }

        $title = trim($_POST['project_title'] ?? '');
        $coordinator = trim($_POST['coordinator'] ?? $user['fullname']);
        $partner = trim($_POST['community_partner'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $beneficiaries = trim($_POST['target_beneficiaries'] ?? '');
        $budget = (float) ($_POST['budget'] ?? 0);
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $endDate = $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'));

        if ($title === '' || $partner === '') {
            flash('error', 'Project title and community partner are required.');
            redirect_to('extension.php');
        }

        $documentId = null;
        $file = $_FILES['file'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('The document could not be uploaded.');
                }
                if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
                    throw new RuntimeException('The document exceeds the 15 MB limit.');
                }
                $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['pdf', 'doc', 'docx', 'zip'];
                if (!in_array($extension, $allowedExtensions, true)) {
                    throw new RuntimeException('Upload a PDF, DOC, DOCX, or ZIP file.');
                }

                $categoryId = (int) (db()->query("SELECT id FROM categories WHERE category_name = 'Extension Project' LIMIT 1")->fetchColumn() ?: 3);
                $departmentId = (int) ($user['department_id'] ?? 0);
                if ($departmentId <= 0) {
                    $departmentId = (int) (db()->query('SELECT id FROM departments ORDER BY id LIMIT 1')->fetchColumn() ?: 1);
                }

                $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
                $relativePath = 'storage:' . $categoryId . '/' . $storedName;
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
                supabase_storage_upload((string) $file['tmp_name'], substr($relativePath, 8), $mime);

                db()->beginTransaction();
                db()->prepare('INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", CURDATE(), NOW())')
                    ->execute([$title . ' - Extension Document', $categoryId, 'Supporting document for extension proposal.', $user['fullname'], $departmentId, $relativePath, basename((string) $file['name']), (int) $user['id']]);
                $documentId = (int) db()->lastInsertId();
                db()->prepare('INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, "Original Version", ?, ?, NOW())')
                    ->execute([$documentId, $relativePath, (int) $user['id']]);
            } catch (Throwable $error) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('error', $error->getMessage());
                redirect_to('extension.php');
            }
        }

        try {
            db()->prepare('INSERT INTO extension_projects (project_title, coordinator, community_partner, location, target_beneficiaries, budget, start_date, end_date, progress_status, status, workflow_stage, document_id, submitted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Planning", "Pending Coordinator Review", "Proposal", ?, ?, NOW())')
                ->execute([$title, $coordinator, $partner, $location, $beneficiaries, $budget, $startDate, $endDate, $documentId, (int) $user['id']]);
            $id = (int) db()->lastInsertId();
            if (db()->inTransaction()) {
                db()->commit();
            }
            log_review('extension', $id, (int) $user['id'], 'Stage 1: Proposal Submitted', 'Submitted proposal for College Coordinator screening.');
            log_action((int) $user['id'], 'Submitted extension project: ' . $title);

            // Notify college coordinator
            $coordStmt = db()->prepare('SELECT id FROM users WHERE department_id = ? AND role = "Extension Coordinator" AND status = "Active"');
            $coordStmt->execute([(int) ($user['department_id'] ?? 0)]);
            foreach ($coordStmt->fetchAll(PDO::FETCH_COLUMN) as $coordId) {
                notify_user((int) $coordId, 'New extension proposal submitted for college screening: ' . $title, 'extension.php#project-' . $id);
            }

            flash('success', 'Extension project proposal submitted successfully! Routed to College Extension Coordinator.');
        } catch (Throwable $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'The extension project could not be saved: ' . $error->getMessage());
        }
    } elseif ($action === 'coordinator_endorse') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Passed college screening.');
        db()->prepare('UPDATE extension_projects SET status = "Pending Director Review", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $remarks, $id]);

        log_review('extension', $id, (int) $user['id'], 'Stage 2: Endorsed by Extension Coordinator', $remarks);
        log_action((int) $user['id'], "Coordinator endorsed extension #{$id}");

        $owner = db()->query("SELECT submitted_by, project_title FROM extension_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], 'Your extension project "' . $owner['project_title'] . '" was endorsed by the Coordinator to the Extension Director.', 'extension.php#project-' . $id);
        }
        flash('success', 'Extension project endorsed to Extension Director.');
    } elseif ($action === 'coordinator_return') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Returned for revision by coordinator.');
        db()->prepare('UPDATE extension_projects SET status = "Needs Revision", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $remarks, $id]);

        log_review('extension', $id, (int) $user['id'], 'Returned for Revision by Extension Coordinator', $remarks);
        $owner = db()->query("SELECT submitted_by, project_title FROM extension_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], 'Your extension project requires revision: ' . $remarks, 'extension.php#project-' . $id);
        }
        flash('success', 'Extension project returned for revision.');
    } elseif ($action === 'director_endorse') {
        $id = (int) $_POST['id'];
        $rating = ($_POST['evaluation_rating'] ?? '') !== '' ? (float) $_POST['evaluation_rating'] : null;
        $approvedBudget = ($_POST['approved_budget'] ?? '') !== '' ? (float) $_POST['approved_budget'] : null;
        $remarks = trim($_POST['remarks'] ?? 'Extension evaluation completed and recommended for executive approval.');

        db()->prepare('UPDATE extension_projects SET status = "Pending Executive Approval", director_id = ?, director_remarks = ?, evaluation_rating = ?, approved_budget = ?, director_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $rating, $approvedBudget, $remarks, $id]);

        $logDetails = "Rating: " . ($rating ?? 'N/A') . "/100 | Budget: PHP " . number_format((float) $approvedBudget, 2) . "\n" . $remarks;
        log_review('extension', $id, (int) $user['id'], 'Stage 3: Endorsed by Extension Director', $logDetails);
        log_action((int) $user['id'], "Extension Director endorsed extension #{$id}");

        $owner = db()->query("SELECT submitted_by, project_title FROM extension_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], 'Your extension project was evaluated by the Director and forwarded to OVPRE Executive for final approval.', 'extension.php#project-' . $id);
        }
        flash('success', 'Extension evaluation completed and endorsed to VP / OVPRE Executive.');
    } elseif ($action === 'executive_approve') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Granted Final Institutional Approval.');

        db()->prepare('UPDATE extension_projects SET status = "Approved", progress_status = "Ongoing", executive_id = ?, executive_remarks = ?, executive_approved_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $remarks, $id]);

        log_review('extension', $id, (int) $user['id'], 'Stage 4: Final Executive Approval Granted', $remarks);
        log_action((int) $user['id'], "Executive approved extension #{$id}");

        $owner = db()->query("SELECT submitted_by, project_title FROM extension_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], '🎉 Congratulations! Your extension project "' . $owner['project_title'] . '" received Final Approval from OVPRE!', 'extension.php#project-' . $id);
        }
        flash('success', 'Final Institutional Approval successfully granted for extension project!');
    } elseif ($action === 'decline') {
        $id = (int) $_POST['id'];
        $remarks = trim($_POST['remarks'] ?? 'Project declined.');
        db()->prepare('UPDATE extension_projects SET status = "Declined", status_remarks = ? WHERE id = ?')->execute([$remarks, $id]);
        log_review('extension', $id, (int) $user['id'], 'Declined', $remarks);
        flash('success', 'Extension project has been declined.');
    } elseif ($action === 'review') {
        $id = (int) $_POST['id'];
        $status = in_array($_POST['status'] ?? 'Pending Coordinator Review', [
            'Pending Coordinator Review', 'Pending Director Review', 'Pending Executive Approval', 'Approved', 'Needs Revision', 'Declined', 'Planning', 'Ongoing', 'Completed'
        ], true) ? $_POST['status'] : 'Pending Coordinator Review';
        $progress = in_array($_POST['progress_status'] ?? 'Planning', ['Planning', 'Ongoing', 'Completed'], true) ? $_POST['progress_status'] : 'Planning';
        $assignedTo = ($_POST['assigned_to'] ?? '') === '' ? null : (int) $_POST['assigned_to'];
        $remarks = trim($_POST['remarks'] ?? '');
        db()->prepare('UPDATE extension_projects SET status = ?, progress_status = ?, assigned_to = ?, status_remarks = ? WHERE id = ?')
            ->execute([$status, $progress, $assignedTo, $remarks, $id]);
        log_review('extension', $id, (int) $user['id'], 'Status: ' . $status . ' / ' . $progress, $remarks);
        flash('success', 'Extension record updated.');
    }
    redirect_to('extension.php');
}

$notice = flash();
$statusFilter = $_GET['status'] ?? 'All';
$departmentFilter = (int) ($_GET['department'] ?? 0);
$conditions = [];
$queryParams = [];

if ($isExtensionist) {
    $conditions[] = 'e.submitted_by = ?';
    $queryParams[] = (int) $user['id'];
} elseif ($isCoordinator) {
    if (!empty($user['department_id'])) {
        $conditions[] = 'u.department_id = ?';
        $queryParams[] = (int) $user['department_id'];
    }
}

if ($statusFilter !== 'All') {
    $conditions[] = 'e.status = ?';
    $queryParams[] = $statusFilter;
}
if ($departmentFilter > 0 && !$isCoordinator) {
    $conditions[] = 'u.department_id = ?';
    $queryParams[] = $departmentFilter;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$stmt = db()->prepare("
    SELECT e.*, u.fullname, dep.department_name, au.fullname AS assignee,
           d.filename AS document_filename, d.original_filename AS document_original_filename,
           coord.fullname AS coordinator_name, dir.fullname AS director_name, exec.fullname AS executive_name
    FROM extension_projects e 
    JOIN users u ON u.id = e.submitted_by 
    LEFT JOIN departments dep ON dep.id = u.department_id
    LEFT JOIN users au ON au.id = e.assigned_to 
    LEFT JOIN documents d ON d.id = e.document_id 
    LEFT JOIN users coord ON coord.id = e.coordinator_id
    LEFT JOIN users dir ON dir.id = e.director_id
    LEFT JOIN users exec ON exec.id = e.executive_id
    {$where} 
    ORDER BY e.created_at DESC
");
$stmt->execute($queryParams);
$projects = $stmt->fetchAll();

renderHeader('Extension Management', 'extension');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-community-line"></i></div>
    <div>
        <p>Community Extension</p>
        <h2>Extension Projects & Workflow</h2>
        <span>Academic Pipeline: Faculty Extensionist &rarr; College Coordinator &rarr; Extension Director &rarr; Executive Final Approval</span>
    </div>
</section>

<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>

<!-- PIPELINE FLOW HIGHLIGHT -->
<section class="panel" style="margin-bottom: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border-left: 5px solid #16a34a;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="margin: 0; color: #166534;"><i class="ri-git-merge-line"></i> Extension Review & Approval Pipeline</h3>
            <p style="margin: 4px 0 0; color: #4b5563; font-size: 0.9rem;">
                Currently logged in as: <strong><?php echo h($user['fullname']); ?></strong> (<?php echo h($user['role']); ?>)
            </p>
        </div>
        <div>
            <span class="role-chip" style="font-size: 0.85rem; padding: 4px 10px; background: #dcfce7; color: #166534;">
                <?php
                if ($isCoordinator) echo 'College Extension Coordinator Scope';
                elseif ($isDirector) echo 'Extension Director Evaluation Scope (University Wide)';
                elseif ($isExecutive) echo 'OVPRE Executive Scope (Final Approval)';
                else echo 'Faculty Extensionist Scope';
                ?>
            </span>
        </div>
    </div>
</section>

<!-- SUBMIT EXTENSION PROJECT PANEL -->
<?php if (!$isSuperAdmin): ?>
<section class="panel modal-launcher-panel">
    <div class="panel-heading">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: bold; color: #16a34a;">Extension Proposals</h2>
            <span style="font-size: 1.1rem; color: #4b5563;">Submit a new community extension proposal for College Coordinator screening.</span>
        </div>
        <button class="primary-btn" type="button" data-open-modal="createExtensionProjectModal"><i class="ri-add-line"></i> Submit Extension Proposal</button>
    </div>
</section>

<div class="modal" id="createExtensionProjectModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="createExtensionProjectTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card simple-submit-modal">
        <button class="modal-close" type="button" data-close-modal aria-label="Close form"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head">
            <span class="category-badge">Extension</span>
            <h2 id="createExtensionProjectTitle">Submit Extension Project Proposal</h2>
            <p>Stage 1: Proposal Writing & Submission (Routed to College Coordinator for Initial Screening)</p>
        </div>
        <form class="editor-panel" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <label class="field"><span>Project Title</span><input name="project_title" required></label>
                <label class="field"><span>Coordinator / Lead</span><input name="coordinator" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Community Partner / LGU</span><input name="community_partner" required></label>
                <label class="field"><span>Location</span><input name="location" placeholder="e.g. Barangay San Jose" required></label>
                <label class="field"><span>Target Beneficiaries</span><input name="target_beneficiaries" placeholder="e.g. 50 OSY and women" required></label>
                <label class="field"><span>Target Budget (PHP)</span><input type="number" step="0.01" name="budget" required></label>
                <label class="field"><span>Start Date</span><input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required></label>
                <label class="field"><span>End Date</span><input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>" required></label>
            </div>
            <label class="field"><span>Proposal Document / MOA Draft (PDF, DOC, DOCX, ZIP up to 15 MB)</span><input type="file" name="file" accept=".pdf,.doc,.docx,.zip" required></label>
            <button class="primary-btn post-btn" type="submit"><i class="ri-send-plane-line"></i> Submit to Extension Coordinator</button>
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
                'Pending Director Review' => '2. For Director Evaluation',
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
                    <th>Project</th>
                    <th>Partner / Location</th>
                    <th>College / Dept</th>
                    <th>Budget</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>File</th>
                    <th>Manage / Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php $pStatus = $project['status'] ?? 'Approved'; ?>
                    <tr id="project-<?php echo (int) $project['id']; ?>">
                        <td>
                            <strong><?php echo h($project['project_title']); ?></strong>
                            <div style="font-size: 0.8rem; color: #6b7280; margin-top: 3px;">
                                <i class="ri-user-line"></i> Lead: <?php echo h($project['coordinator']); ?> | 
                                <i class="ri-calendar-line"></i> <?php echo date('M d, Y', strtotime($project['start_date'])); ?> to <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo h($project['community_partner']); ?></strong>
                            <?php if (!empty($project['location'])): ?>
                                <br><small style="color:#4b5563;"><i class="ri-map-pin-line"></i> <?php echo h($project['location']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="dept-pill"><?php echo h($project['department_name'] ?? 'College'); ?></span></td>
                        <td><strong>PHP <?php echo number_format((float) ($project['approved_budget'] ?: $project['budget']), 2); ?></strong></td>
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
                        <td>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <?php if ($isCoordinator && $pStatus === 'Pending Coordinator Review'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="coord-ext-modal-<?php echo (int) $project['id']; ?>" style="background: #b45309;">
                                        <i class="ri-check-line"></i> Screen
                                    </button>
                                <?php elseif ($isDirector && $pStatus === 'Pending Director Review'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="dir-ext-modal-<?php echo (int) $project['id']; ?>" style="background: #1d4ed8;">
                                        <i class="ri-award-line"></i> Evaluate
                                    </button>
                                <?php elseif ($isExecutive && $pStatus === 'Pending Executive Approval'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="exec-ext-modal-<?php echo (int) $project['id']; ?>" style="background: #7e22ce;">
                                        <i class="ri-shield-check-line"></i> Approve
                                    </button>
                                <?php endif; ?>

                                <button class="ghost-btn action-btn" type="button" data-open-modal="ext-detail-<?php echo (int) $project['id']; ?>">
                                    <i class="ri-eye-line"></i><span>Details</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$projects): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 24px;">No extension projects found.</td>
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
    <div class="modal" id="ext-detail-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 650px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2>Extension Project Details</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['project_title']); ?></strong></p>
            <p style="color: #6b7280; font-size: 0.9rem;">Lead: <?php echo h($project['coordinator']); ?> | Partner: <?php echo h($project['community_partner']); ?></p>

            <div style="background: #f9fafb; padding: 12px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 12px;">
                <div><strong>Location:</strong> <?php echo h($project['location'] ?? 'N/A'); ?></div>
                <div style="margin-top: 4px;"><strong>Target Beneficiaries:</strong> <?php echo h($project['target_beneficiaries'] ?? 'N/A'); ?></div>
                <div style="margin-top: 4px;"><strong>Budget:</strong> PHP <?php echo number_format((float) ($project['approved_budget'] ?: $project['budget']), 2); ?></div>
                <div style="margin-top: 4px;"><strong>Dates:</strong> <?php echo date('M d, Y', strtotime($project['start_date'])); ?> to <?php echo date('M d, Y', strtotime($project['end_date'])); ?></div>
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
                    <strong style="color: #1d4ed8;">Director Evaluation:</strong>
                    <div>Rating: <?php echo h($project['evaluation_rating'] ?? 'N/A'); ?>/100 | Approved Budget: PHP <?php echo number_format((float) ($project['approved_budget'] ?? $project['budget']), 2); ?></div>
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
                <div class="form-grid">
                    <label class="field"><span>Status Override:</span>
                        <select name="status">
                            <?php foreach (['Pending Coordinator Review', 'Pending Director Review', 'Pending Executive Approval', 'Approved', 'Needs Revision', 'Declined'] as $st): ?>
                                <option value="<?php echo h($st); ?>" <?php echo ($project['status'] ?? '') === $st ? 'selected' : ''; ?>><?php echo h($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field"><span>Progress Status:</span>
                        <select name="progress_status">
                            <?php foreach (['Planning', 'Ongoing', 'Completed'] as $st): ?>
                                <option value="<?php echo h($st); ?>" <?php echo ($project['progress_status'] ?? '') === $st ? 'selected' : ''; ?>><?php echo h($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="field"><span>Remarks:</span>
                    <textarea name="remarks" rows="2"><?php echo h($project['status_remarks'] ?? ''); ?></textarea>
                </label>
                <button class="primary-btn"><i class="ri-save-line"></i> Update Record</button>
            <?php endif; ?>

            <div class="timeline-list" style="margin-top: 16px;">
                <?php foreach (review_timeline('extension', $projId) as $log): ?>
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
    <div class="modal" id="coord-ext-modal-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 580px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2 style="color: #b45309;"><i class="ri-shield-check-line"></i> College Coordinator Screening</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['project_title']); ?></strong></p>
            <label class="field"><span>Endorsement Remarks / Feedback:</span>
                <textarea name="remarks" rows="3" required placeholder="Enter initial screening remarks before endorsing to the Extension Director..."></textarea>
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
    <div class="modal" id="dir-ext-modal-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 620px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2 style="color: #1d4ed8;"><i class="ri-award-line"></i> Extension Director Evaluation</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['project_title']); ?></strong></p>
            <div class="form-grid">
                <label class="field"><span>Evaluation Rating (Score out of 100):</span>
                    <input type="number" step="0.01" min="0" max="100" name="evaluation_rating" placeholder="85.00" required>
                </label>
                <label class="field"><span>Approved Extension Budget (PHP):</span>
                    <input type="number" step="0.01" min="0" name="approved_budget" value="<?php echo h($project['budget']); ?>" required>
                </label>
            </div>
            <label class="field"><span>Evaluation & Recommendation Remarks:</span>
                <textarea name="remarks" rows="3" required placeholder="Evaluation feedback regarding community partnership, safety protocols, and budget allocation..."></textarea>
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
    <div class="modal" id="exec-ext-modal-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post" style="max-width: 600px;">
            <?php echo csrf_field(); ?>
            <button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button>
            <h2 style="color: #7e22ce;"><i class="ri-checkbox-circle-line"></i> OVPRE Executive Final Approval</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['project_title']); ?></strong></p>
            <label class="field"><span>Executive Resolution & Directives:</span>
                <textarea name="remarks" rows="4" required>Approved for implementation under the Institutional Extension Program. Notice to Implement issued.</textarea>
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
