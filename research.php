<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin', 'Research Director', 'Research Coordinator', 'Researcher']);
verify_csrf();
ensure_research_archived_at_column();
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
            $coordStmt = db()->prepare("SELECT id FROM users WHERE department_id = ? AND role = 'Research Coordinator' AND status = 'Active'");
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
        $aihrStatus = trim($_POST['aihr_status'] ?? 'Passed RIUH');
        $aihrScore = ($_POST['aihr_score'] ?? '') !== '' ? (float) $_POST['aihr_score'] : null;
        $grantAmount = ($_POST['grant_amount'] ?? '') !== '' ? (float) $_POST['grant_amount'] : null;
        $remarks = trim($_POST['remarks'] ?? 'RIUH evaluation completed and grant recommended.');

        db()->prepare('UPDATE research_projects SET status = "Pending Executive Approval", director_id = ?, director_remarks = ?, aihr_status = ?, aihr_score = ?, grant_amount = ?, director_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
            ->execute([(int) $user['id'], $remarks, $aihrStatus, $aihrScore, $grantAmount, $remarks, $id]);

        $logDetails = "RIUH Status: {$aihrStatus} | Score: " . ($aihrScore ?? 'N/A') . " | Rec. Grant: PHP " . number_format((float) $grantAmount, 2) . "\n" . $remarks;
        log_review('research', $id, (int) $user['id'], 'Stage 3: Endorsed by Research Director', $logDetails);
        log_action((int) $user['id'], "Research Director endorsed research #{$id}");

        $owner = db()->query("SELECT submitted_by, title FROM research_projects WHERE id = {$id}")->fetch();
        if ($owner) {
            notify_user((int) $owner['submitted_by'], 'Your proposal passed RIUH review and is forwarded to OVPRE Executive for final approval.', 'research.php#proposal-' . $id);
        }
        flash('success', 'RIUH evaluation completed and endorsed to VP / OVPRE Executive.');
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
        if (get_system_setting('require_admin_remarks', '1') === '1' && $remarks === '') {
            flash('error', 'Remarks are required when declining a research proposal.');
            redirect_to('research.php');
        }
        db()->prepare('UPDATE research_projects SET status = "Declined", status_remarks = ? WHERE id = ?')->execute([$remarks, $id]);
        log_review('research', $id, (int) $user['id'], 'Declined', $remarks);

        $owner = db()->query("SELECT submitted_by, title FROM research_projects WHERE id = {$id}")->fetch();
        if ($owner && !empty($owner['submitted_by'])) {
            notify_user((int) $owner['submitted_by'], 'Your research proposal "' . ($owner['title'] ?? 'Proposal') . '" was declined. Remarks: ' . $remarks, 'research.php#proposal-' . $id);
        }
        flash('success', 'Proposal has been declined.');
    } elseif ($action === 'review') {
        // Super Admin manual review/assignment
        $id = (int) $_POST['id'];
        $orig = db()->query("SELECT submitted_by, title, assigned_to, status FROM research_projects WHERE id = {$id}")->fetch();
        $status = isset($_POST['status']) && in_array($_POST['status'], [
            'Pending Coordinator Review', 'Pending Director Review', 'Pending Executive Approval', 'Approved', 'Needs Revision', 'Declined',
            'Pending', 'Under Review'
        ], true) ? $_POST['status'] : ($orig['status'] ?? 'Pending Coordinator Review');
        $assignedTo = ($_POST['assigned_to'] ?? '') === '' ? null : (int) $_POST['assigned_to'];
        $remarks = trim($_POST['remarks'] ?? '');

        if (get_system_setting('require_admin_remarks', '1') === '1' && in_array($status, ['Declined', 'Needs Revision'], true) && $remarks === '') {
            flash('error', 'Remarks are required when setting status to ' . $status . '.');
            redirect_to('research.php');
        }

        db()->prepare('UPDATE research_projects SET status = ?, assigned_to = ?, status_remarks = ? WHERE id = ?')->execute([$status, $assignedTo, $remarks, $id]);
        log_review('research', $id, (int) $user['id'], 'Status: ' . $status, $remarks);

        if ($orig) {
            $pTitle = $orig['title'] ?? 'Research Proposal';
            if (!empty($orig['submitted_by']) && get_system_setting('notify_on_approval', '1') === '1') {
                notify_user((int) $orig['submitted_by'], "Research proposal '{$pTitle}' status updated to {$status}." . ($remarks ? " Remarks: {$remarks}" : ''), 'research.php#proposal-' . $id);
            }
            if ($assignedTo && $assignedTo !== (int) ($orig['assigned_to'] ?? 0) && $assignedTo !== (int) $user['id']) {
                notify_user($assignedTo, "You have been assigned to review research proposal: '{$pTitle}'", 'research.php#proposal-' . $id);
            }
        }
        flash('success', 'Research record updated.');
    } elseif ($action === 'archive') {
        $id = (int) ($_POST['id'] ?? 0);
        $archivedView = !empty($_POST['archived_view']);
        $stmt = db()->prepare('UPDATE research_projects SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_action((int) $user['id'], 'Archived research proposal #' . $id);
            log_review('research', $id, (int) $user['id'], 'Proposal Archived', 'Research proposal moved to archive.');
            flash('success', 'Research proposal archived.');
        } else {
            flash('error', 'Research proposal could not be found or is already archived.');
        }
        redirect_to('research.php' . ($archivedView ? '?archived=1' : ''));
    } elseif ($action === 'restore') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE research_projects SET archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_action((int) $user['id'], 'Restored research proposal #' . $id);
            log_review('research', $id, (int) $user['id'], 'Proposal Restored', 'Research proposal restored from archive.');
            flash('success', 'Research proposal restored to active pipeline.');
        } else {
            flash('error', 'Research proposal could not be found or is already active.');
        }
        redirect_to('research.php?archived=1');
    } elseif ($action === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), static fn($id) => $id > 0)));
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');
        $isArchivedRedirect = !empty($_POST['archived_view']) || (($_GET['archived'] ?? '') === '1');

        if (!$ids) {
            flash('error', 'No research proposals selected.');
            redirect_to('research.php' . ($isArchivedRedirect ? '?archived=1' : ''));
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));

        if ($bulkAction === 'archive') {
            $stmt = db()->prepare("UPDATE research_projects SET archived_at = NOW() WHERE archived_at IS NULL AND id IN ($marks)");
            $stmt->execute($ids);
            foreach ($ids as $pId) {
                log_action((int) $user['id'], 'Bulk archived research proposal #' . $pId);
                log_review('research', $pId, (int) $user['id'], 'Bulk Archived', 'Proposal archived via bulk selection.');
            }
            flash('success', count($ids) . ' research proposal(s) archived.');
            redirect_to('research.php');
        } elseif ($bulkAction === 'restore') {
            $stmt = db()->prepare("UPDATE research_projects SET archived_at = NULL WHERE archived_at IS NOT NULL AND id IN ($marks)");
            $stmt->execute($ids);
            foreach ($ids as $pId) {
                log_action((int) $user['id'], 'Bulk restored research proposal #' . $pId);
                log_review('research', $pId, (int) $user['id'], 'Bulk Restored', 'Proposal restored via bulk selection.');
            }
            flash('success', count($ids) . ' research proposal(s) restored.');
            redirect_to('research.php?archived=1');
        }
    }
    redirect_to('research.php');
}

$notice = flash();
$archived = ($_GET['archived'] ?? '') === '1';
$statusFilter = $_GET['status'] ?? 'All';
$departmentFilter = (int) ($_GET['department'] ?? 0);
$conditions = [];
$queryParams = [];

// Archive filter
$conditions[] = $archived ? 'r.archived_at IS NOT NULL' : 'r.archived_at IS NULL';

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

// Active and Archived counts scoped by role
$scopeConditions = [];
$scopeParams = [];
if ($isResearcher) {
    $scopeConditions[] = 'r.submitted_by = ?';
    $scopeParams[] = (int) $user['id'];
} elseif ($isCoordinator && !empty($user['department_id'])) {
    $scopeConditions[] = 'u.department_id = ?';
    $scopeParams[] = (int) $user['department_id'];
}
$scopeWhere = $scopeConditions ? ' AND ' . implode(' AND ', $scopeConditions) : '';

$activeCountStmt = db()->prepare("SELECT COUNT(*) FROM research_projects r JOIN users u ON u.id = r.submitted_by WHERE r.archived_at IS NULL {$scopeWhere}");
$activeCountStmt->execute($scopeParams);
$activeCount = (int) $activeCountStmt->fetchColumn();

$archivedCountStmt = db()->prepare("SELECT COUNT(*) FROM research_projects r JOIN users u ON u.id = r.submitted_by WHERE r.archived_at IS NOT NULL {$scopeWhere}");
$archivedCountStmt->execute($scopeParams);
$archivedCount = (int) $archivedCountStmt->fetchColumn();

renderHeader('Research Management', 'research');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-flask-line"></i></div>
    <div>
        <p>Research Repository</p>
        <h2>Research Management</h2>
        <span>Academic Pipeline: Proponent &rarr; College Coordinator &rarr; Research Director (RIUH) &rarr; Executive Final Approval</span>
    </div>
</section>

<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>

<?php if (!$isSuperAdmin): ?>
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

<!-- PROJECTS TABLE -->
<section class="panel">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
        <div class="portal-table-tabs">
            <a class="portal-table-tab <?php echo !$archived ? 'active' : ''; ?>" href="research.php">
                <i class="ri-flask-line"></i>
                <span>Active Proposals</span>
                <span class="tab-count"><?php echo $activeCount; ?></span>
            </a>
            <a class="portal-table-tab <?php echo $archived ? 'active' : ''; ?>" href="research.php?archived=1">
                <i class="ri-archive-line"></i>
                <span>Archived</span>
                <span class="tab-count"><?php echo $archivedCount; ?></span>
            </a>
        </div>
    </div>

    <!-- Bulk Selection Toolbar for Research -->
    <div class="table-selection-toolbar" data-selection-toolbar="researchTable" hidden>
        <span data-selection-count>0 selected</span>
        <form id="bulk-researchTable" method="post" data-bulk-selection-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk">
            <?php if ($archived): ?>
                <input type="hidden" name="archived_view" value="1">
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="restore" data-bulk-submit>
                    <i class="ri-arrow-go-back-line"></i><span>Restore Selected</span>
                </button>
            <?php else: ?>
                <button class="action-btn btn-archive-natural" type="submit" name="bulk_action" value="archive" data-bulk-submit onclick="return confirm('Archive selected research proposal(s)?');">
                    <i class="ri-archive-line"></i><span>Archive Selected</span>
                </button>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="researchTable">
            <thead>
                <tr>
                    <th class="table-checkbox-col">
                        <label class="select-all-control">
                            <input form="bulk-researchTable" type="checkbox" aria-label="Select all research proposals" data-select-all="researchTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Title</th>
                    <th>Researchers</th>
                    <th>College</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Manage / Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php $pStatus = $project['status']; ?>
                    <tr id="proposal-<?php echo (int) $project['id']; ?>">
                        <td class="table-checkbox-col">
                            <input form="bulk-researchTable" type="checkbox" name="ids[]" aria-label="Select research proposal <?php echo (int) $project['id']; ?>" value="<?php echo (int) $project['id']; ?>" data-row-select="researchTable">
                        </td>
                        <td><strong><?php echo h($project['title']); ?></strong></td>
                        <td><?php echo h($project['researchers']); ?></td>
                        <td><span class="dept-pill"><?php echo h($project['department_name'] ?? 'College'); ?></span></td>
                        <td><span style="font-weight:600; color:#166534;"><?php echo h($project['workflow_stage'] ?? 'Proposal'); ?></span></td>
                        <td>
                            <span class="status-pill status-<?php echo h(statusClass($pStatus)); ?>">
                                <?php echo h($pStatus); ?>
                            </span>
                        </td>
                        <td><?php echo h(date('M d, Y', strtotime($project['created_at']))); ?></td>
                        <td>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <?php if ($isCoordinator && in_array($pStatus, ['Pending Coordinator Review', 'Pending', 'Under Review'], true)): ?>
                                    <button class="primary-btn action-btn" type="button" data-open-modal="coord-modal-<?php echo (int) $project['id']; ?>" style="background: #15803d; color: #fff;" title="Endorse proposal to Research Director">
                                        <i class="ri-check-double-line"></i><span>Endorse</span>
                                    </button>
                                    <button class="ghost-btn action-btn" type="button" data-open-modal="coord-modal-<?php echo (int) $project['id']; ?>" style="color: #c2410c; border-color: #fed7aa; background: #fff7ed;" title="Return to faculty for revision">
                                        <i class="ri-arrow-go-back-line"></i><span>Revision</span>
                                    </button>
                                <?php elseif ($isDirector && in_array($pStatus, ['Pending Director Review', 'Under Review'], true)): ?>
                                    <button class="primary-btn action-btn" type="button" data-open-modal="dir-modal-<?php echo (int) $project['id']; ?>" style="background: #1d4ed8; color: #fff;" title="Director RIUH & Policy Evaluation">
                                        <i class="ri-award-line"></i><span>RIUH</span>
                                    </button>
                                <?php elseif ($isExecutive && in_array($pStatus, ['Pending Executive Approval'], true)): ?>
                                    <button class="primary-btn action-btn" type="button" data-open-modal="exec-modal-<?php echo (int) $project['id']; ?>" style="background: #7e22ce; color: #fff;" title="Executive Institutional Approval">
                                        <i class="ri-shield-check-line"></i><span>Approve</span>
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
                        <td colspan="8" style="text-align: center; padding: 24px; color: #64748b;"><?php echo $archived ? 'No archived research proposals found.' : 'No research proposals found.'; ?></td>
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
                    <strong style="color: #1d4ed8;">Director RIUH Evaluation:</strong>
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

            <h3 style="margin-top: 18px; margin-bottom: 8px; font-size: 0.95rem; color: #15803d; display: flex; align-items: center; gap: 6px;">
                <i class="ri-history-line"></i> Audit Trail &amp; Workflow History
            </h3>
            <div class="timeline-list" style="margin-top: 4px; max-height: 280px; overflow-y: auto; padding-right: 6px; scrollbar-width: thin;">
                <?php foreach (review_timeline('research', $projId) as $log): ?>
                    <div>
                        <strong><?php echo h($log['action']); ?></strong>
                        <p><?php echo h($log['remarks']); ?></p>
                        <small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($archived): ?>
                <input type="hidden" name="archived_view" value="1">
            <?php endif; ?>
            <div style="display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-top: 18px; padding-top: 14px; border-top: 1px solid #e5e7eb;">
                <div>
                    <?php if (!empty($project['document_filename'])): ?>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <button class="primary-btn action-btn" type="button" data-open-modal="preview-research-<?php echo $projId; ?>">
                                <i class="ri-eye-line"></i> <span>Preview</span>
                            </button>
                            <a href="<?php echo h(record_access_url($project['document_filename'], '', (int) $project['document_id'])); ?>" download class="ghost-btn action-btn" title="Download Document">
                                <i class="ri-download-line"></i> <span>Download</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($archived): ?>
                        <button class="ghost-btn action-btn" type="submit" name="action" value="restore" style="color: #15803d; border: 1px solid #bbf7d0; background: #f0fdf4;" onclick="return confirm('Restore this research proposal to active?');">
                            <i class="ri-arrow-go-back-line"></i><span>Restore Proposal</span>
                        </button>
                    <?php else: ?>
                        <button class="ghost-btn action-btn" type="submit" name="action" value="archive" style="color: #b45309; border: 1px solid #fed7aa; background: #fff7ed;" onclick="return confirm('Archive this research proposal?');">
                            <i class="ri-archive-line"></i><span>Archive Proposal</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- RESEARCH DOCUMENT PREVIEW MODAL -->
    <?php if (!empty($project['document_filename'])): ?>
        <div class="modal preview-modal" id="preview-research-<?php echo $projId; ?>" aria-hidden="true">
            <div class="modal-backdrop" data-close-modal></div>
            <article class="modal-card preview-card">
                <button class="modal-close" type="button" data-close-modal title="Close Preview"><i class="ri-close-line"></i></button>
                <h2><?php echo h($project['title']); ?></h2>
                <?php 
                $docFilename = (string) $project['document_filename'];
                $ext = strtolower(pathinfo(preg_replace('/^storage:/', '', $docFilename), PATHINFO_EXTENSION));
                $fileUrl = record_access_url($docFilename, '', (int) $project['document_id']);
                if ($ext === 'pdf'): 
                ?>
                    <iframe class="document-preview" data-src="<?php echo h($fileUrl); ?>" src="about:blank"></iframe>
                <?php elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)): ?>
                    <img class="document-preview-image" data-src="<?php echo h($fileUrl); ?>" alt="<?php echo h($project['title']); ?>">
                <?php else: ?>
                    <div style="padding: 32px 20px; text-align: center; background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0;">
                        <i class="ri-file-info-line" style="font-size: 2.5rem; color: #64748b;"></i>
                        <p style="margin: 12px 0 16px; color: #334155;">Preview is not supported for <strong>.<?php echo h($ext); ?></strong> files. Please download the file to view.</p>
                        <a class="primary-btn action-btn" href="<?php echo h($fileUrl); ?>" download style="display: inline-flex; align-items: center; gap: 6px;">
                            <i class="ri-download-line"></i> Download Document
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    <?php endif; ?>

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
            <?php if (!empty($project['document_filename'])): ?>
                <div style="margin-bottom: 12px;">
                    <button class="ghost-btn action-btn" type="button" data-open-modal="preview-research-<?php echo $projId; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ri-file-search-line"></i> Preview Attached Document
                    </button>
                </div>
            <?php endif; ?>
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
            <h2 style="color: #1d4ed8;"><i class="ri-award-line"></i> Director RIUH & Grant Evaluation</h2>
            <input type="hidden" name="id" value="<?php echo $projId; ?>">
            <p><strong><?php echo h($project['title']); ?></strong></p>
            <?php if (!empty($project['document_filename'])): ?>
                <div style="margin-bottom: 12px;">
                    <button class="ghost-btn action-btn" type="button" data-open-modal="preview-research-<?php echo $projId; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ri-file-search-line"></i> Preview Attached Document
                    </button>
                </div>
            <?php endif; ?>
            <div class="form-grid">
                <label class="field"><span>RIUH Status:</span>
                    <select name="aihr_status">
                        <option value="Passed RIUH">Passed RIUH</option>
                        <option value="Passed with Conditions">Passed with Conditions</option>
                    </select>
                </label>
                <label class="field"><span>RIUH Score (1-100):</span>
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
            <?php if (!empty($project['document_filename'])): ?>
                <div style="margin-bottom: 12px;">
                    <button class="ghost-btn action-btn" type="button" data-open-modal="preview-research-<?php echo $projId; ?>" style="display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ri-file-search-line"></i> Preview Attached Document
                    </button>
                </div>
            <?php endif; ?>
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
