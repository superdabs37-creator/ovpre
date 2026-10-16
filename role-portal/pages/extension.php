<?php
if (!portal_can('extension')) {
    http_response_code(403);
    exit('Extension module is not available for this role.');
}
$user = portal_require_user();
verify_csrf();
$notice = flash();

$isExtensionist = PORTAL_ROLE === 'Faculty Extensionist';
$isCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isDirector = PORTAL_ROLE === 'Extension Director';
$isExecutive = in_array(PORTAL_ROLE, ['Vice President', 'OVPRE Admin'], true) || ($user['role'] ?? '') === ROLE_SUPER_ADMIN;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    try {
        if ($action === 'create' || $action === 'create_proposal') {
            $documentId = portal_save_document($_POST, $_FILES['required_document'] ?? [], $user);
            $title = trim((string) ($_POST['project_title'] ?? ''));
            $coordinatorName = trim((string) ($_POST['coordinator'] ?? $user['fullname']));
            $partner = trim((string) ($_POST['community_partner'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $beneficiaries = trim((string) ($_POST['target_beneficiaries'] ?? ''));
            $budget = (float) ($_POST['budget'] ?? 0);
            $startDate = $_POST['start_date'] ?? date('Y-m-d');
            $endDate = $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'));

            if ($title === '' || $partner === '') {
                throw new InvalidArgumentException('Project title and community partner are required.');
            }

            db()->prepare('INSERT INTO extension_projects (project_title, coordinator, community_partner, location, target_beneficiaries, budget, start_date, end_date, progress_status, status, workflow_stage, document_id, submitted_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Planning", "Pending Coordinator Review", "Proposal", ?, ?, NOW())')
                ->execute([$title, $coordinatorName, $partner, $location, $beneficiaries, $budget, $startDate, $endDate, $documentId, (int) $user['id']]);
            $projectId = (int) db()->lastInsertId();

            log_review('extension', $projectId, (int) $user['id'], 'Stage 1: Proposal Submitted', 'Submitted extension project proposal for College Coordinator initial screening.');
            log_action((int) $user['id'], 'Submitted extension project: ' . $title);

            // Notify college extension coordinators
            $coordStmt = db()->prepare('SELECT id FROM users WHERE department_id = ? AND role = "Extension Coordinator" AND status = "Active"');
            $coordStmt->execute([(int) ($user['department_id'] ?? 0)]);
            foreach ($coordStmt->fetchAll(PDO::FETCH_COLUMN) as $coordId) {
                notify_user((int) $coordId, 'New extension proposal submitted for your college screening: ' . $title, 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension project proposal submitted successfully! Routed to College Extension Coordinator.');
            redirect_to('extension.php');

        } elseif ($action === 'resubmit_proposal') {
            $projectId = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['project_title'] ?? ''));
            $partner = trim((string) ($_POST['community_partner'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $beneficiaries = trim((string) ($_POST['target_beneficiaries'] ?? ''));
            $budget = (float) ($_POST['budget'] ?? 0);
            $revisionNotes = trim((string) ($_POST['revision_notes'] ?? ''));

            $existingStmt = db()->prepare('SELECT * FROM extension_projects WHERE id = ? AND submitted_by = ?');
            $existingStmt->execute([$projectId, (int) $user['id']]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                throw new RuntimeException('Extension project not found or access denied.');
            }

            $documentId = $existing['document_id'];
            if (!empty($_FILES['required_document']['name'])) {
                $newDocId = portal_save_document($_POST, $_FILES['required_document'], $user);
                if ($newDocId) {
                    $documentId = $newDocId;
                }
            }

            db()->prepare('UPDATE extension_projects SET project_title = ?, community_partner = ?, location = ?, target_beneficiaries = ?, budget = ?, document_id = ?, status = "Pending Coordinator Review", status_remarks = ? WHERE id = ?')
                ->execute([$title, $partner, $location, $beneficiaries, $budget, $documentId, 'Resubmitted with revisions: ' . $revisionNotes, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Proposal Resubmitted', $revisionNotes);
            log_action((int) $user['id'], 'Resubmitted extension proposal #' . $projectId);

            flash('success', 'Extension proposal resubmitted for coordinator review.');
            redirect_to('extension.php');

        } elseif ($action === 'submit_progress_report') {
            $projectId = (int) ($_POST['id'] ?? 0);
            $reportType = trim((string) ($_POST['report_type'] ?? 'Milestone Report'));
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));

            if ($title === '') {
                throw new InvalidArgumentException('Report title is required.');
            }

            $documentId = null;
            if (!empty($_FILES['progress_file']['name'])) {
                $_POST['document_title'] = $reportType . ' - ' . $title;
                $_POST['document_description'] = $description;
                $documentId = portal_save_document($_POST, $_FILES['progress_file'], $user);
            }

            db()->prepare('INSERT INTO extension_progress_reports (project_id, report_type, title, description, document_id, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "Submitted", NOW())')
                ->execute([$projectId, $reportType, $title, $description, $documentId, (int) $user['id']]);

            $stage = match ($reportType) {
                'Liquidation Report' => 'Liquidation',
                'Community Impact & Output' => 'Community Impact',
                default => 'Implementation'
            };
            db()->prepare('UPDATE extension_projects SET workflow_stage = ? WHERE id = ?')->execute([$stage, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Submitted ' . $reportType, $title . ': ' . $description);
            log_action((int) $user['id'], 'Submitted ' . $reportType . ' for extension #' . $projectId);

            flash('success', $reportType . ' submitted successfully!');
            redirect_to('extension.php');

        } elseif ($action === 'coordinator_endorse') {
            if (!$isCoordinator && !$isExecutive) {
                throw new RuntimeException('Only Extension Coordinators can endorse at college level.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Passed college screening and aligned with community extension agenda.'));

            db()->prepare('UPDATE extension_projects SET status = "Pending Director Review", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Stage 2: Endorsed by Extension Coordinator', $remarks);
            log_action((int) $user['id'], 'Coordinator endorsed extension #' . $projectId);

            $owner = db()->query('SELECT submitted_by, project_title FROM extension_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your extension project "' . $owner['project_title'] . '" was endorsed by your Coordinator to the Extension Director.', 'extension.php#project-' . $projectId);
            }
            // Notify Directors
            $directors = db()->query('SELECT id FROM users WHERE role = "Extension Director" AND status = "Active"')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($directors as $dirId) {
                notify_user((int) $dirId, 'New extension proposal awaiting director evaluation: ' . ($owner['project_title'] ?? ''), 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension project endorsed to Extension Director!');
            redirect_to('extension.php');

        } elseif ($action === 'coordinator_return') {
            if (!$isCoordinator && !$isExecutive) {
                throw new RuntimeException('Unauthorized.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Returned for revision by coordinator.'));

            db()->prepare('UPDATE extension_projects SET status = "Needs Revision", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Returned for Revision by Extension Coordinator', $remarks);
            $owner = db()->query('SELECT submitted_by, project_title FROM extension_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your extension project "' . $owner['project_title'] . '" requires revision: ' . $remarks, 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension project returned to faculty for revision.');
            redirect_to('extension.php');

        } elseif ($action === 'director_endorse') {
            if (!$isDirector && !$isExecutive) {
                throw new RuntimeException('Only Extension Directors can evaluate at institutional level.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $rating = ($_POST['evaluation_rating'] ?? '') !== '' ? (float) $_POST['evaluation_rating'] : null;
            $approvedBudget = ($_POST['approved_budget'] ?? '') !== '' ? (float) $_POST['approved_budget'] : null;
            $remarks = trim((string) ($_POST['remarks'] ?? 'Extension proposal evaluated and recommended for executive approval.'));

            db()->prepare('UPDATE extension_projects SET status = "Pending Executive Approval", director_id = ?, director_remarks = ?, evaluation_rating = ?, approved_budget = ?, director_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $rating, $approvedBudget, $remarks, $projectId]);

            $logDetails = "Evaluation Rating: " . ($rating ?? 'N/A') . "/100 | Recommended Budget: PHP " . number_format((float) $approvedBudget, 2) . "\n" . $remarks;
            log_review('extension', $projectId, (int) $user['id'], 'Stage 3: Endorsed by Extension Director', $logDetails);
            log_action((int) $user['id'], 'Extension Director endorsed extension #' . $projectId);

            $owner = db()->query('SELECT submitted_by, project_title FROM extension_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your extension project was evaluated by the Extension Director and endorsed to OVPRE Executive for final approval.', 'extension.php#project-' . $projectId);
            }
            // Notify System Admin / VP
            $admins = db()->query('SELECT id FROM users WHERE role IN ("Super Admin", "OVPRE Admin", "Vice President") AND status = "Active"')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                notify_user((int) $adminId, 'Extension project awaiting Final Executive Approval: ' . ($owner['project_title'] ?? ''), 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension evaluation completed and endorsed to VP / OVPRE Executive!');
            redirect_to('extension.php');

        } elseif ($action === 'director_return') {
            if (!$isDirector && !$isExecutive) {
                throw new RuntimeException('Unauthorized.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Returned by Extension Director for amendment.'));

            db()->prepare('UPDATE extension_projects SET status = "Needs Revision", director_id = ?, director_remarks = ?, director_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Returned for Revision by Extension Director', $remarks);
            $owner = db()->query('SELECT submitted_by, project_title FROM extension_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your extension project was returned by the Extension Director for revision: ' . $remarks, 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension project returned for revision.');
            redirect_to('extension.php');

        } elseif ($action === 'executive_approve') {
            if (!$isExecutive) {
                throw new RuntimeException('Only the Vice President / Super Admin can grant final executive approval.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Granted Final Institutional Approval for community implementation.'));

            db()->prepare('UPDATE extension_projects SET status = "Approved", progress_status = "Ongoing", executive_id = ?, executive_remarks = ?, executive_approved_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Stage 4: Final Executive Approval Granted', $remarks);
            log_action((int) $user['id'], 'Executive approved extension #' . $projectId);

            $owner = db()->query('SELECT submitted_by, project_title FROM extension_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], '🎉 Congratulations! Your extension project "' . $owner['project_title'] . '" has received FINAL APPROVAL from the Office of the Vice President.', 'extension.php#project-' . $projectId);
            }

            flash('success', 'Final Institutional Approval successfully granted for extension project!');
            redirect_to('extension.php');

        } elseif ($action === 'decline') {
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Project declined.'));

            db()->prepare('UPDATE extension_projects SET status = "Declined", status_remarks = ? WHERE id = ?')
                ->execute([$remarks, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Declined', $remarks);
            $owner = db()->query('SELECT submitted_by, project_title FROM extension_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your extension project was declined: ' . $remarks, 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension project declined.');
            redirect_to('extension.php');
        }
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}

$user = portal_header('Extension Management & Workflow', 'extension');
$projects = portal_extension_projects();
?>

<?php portal_notice($notice); ?>

<!-- WORKFLOW PROGRESS BANNER -->
<section class="panel" style="margin-bottom: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border-left: 5px solid #16a34a;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="margin: 0; color: #166534; font-size: 1.25rem;">
                <i class="ri-community-line"></i> Community Extension Review & Approval Pipeline
            </h2>
            <p style="margin: 4px 0 0; color: #4b5563; font-size: 0.9rem;">
                Hierarchical Academic Flow: <strong>Faculty Extensionist</strong> &rarr; <strong>Extension Coordinator</strong> &rarr; <strong>Extension Director</strong> &rarr; <strong>Vice President / Admin</strong>
            </p>
        </div>
        <div>
            <span class="role-chip" style="font-size: 0.9rem; padding: 6px 12px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                <i class="ri-user-star-line"></i> Active Role: <?php echo h(PORTAL_ROLE); ?>
            </span>
        </div>
    </div>

    <!-- PIPELINE STEPPER -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-top: 18px;">
        <div style="background: #ffffff; padding: 12px 14px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #15803d;">
                <span style="background: #dcfce7; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">1</span>
                Faculty Extensionist
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">Proposal Submission, Community Milestones, Liquidation, Impact</small>
        </div>

        <div style="background: #ffffff; padding: 12px 14px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #b45309;">
                <span style="background: #fef3c7; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">2</span>
                Extension Coordinator
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">College Liaison, Initial Screening & Endorsement, Local Monitoring</small>
        </div>

        <div style="background: #ffffff; padding: 12px 14px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #1d4ed8;">
                <span style="background: #dbeafe; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">3</span>
                Extension Director
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">Community Policies, Proposal Evaluation, Resource & Budget Allocation</small>
        </div>

        <div style="background: #ffffff; padding: 12px 14px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #7e22ce;">
                <span style="background: #f3e8ff; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">4</span>
                VP for Research & Ext.
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">Strategic Direction, LGU/Partner Linkages, Final Institutional Approval</small>
        </div>
    </div>
</section>

<!-- FACULTY EXTENSIONIST SUBMIT BUTTON -->
<?php if ($isExtensionist || $isExecutive): ?>
<section class="panel proposal-launcher" style="margin-bottom: 20px;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-add-box-line"></i> Submit Community Extension Proposal</h2>
            <span>Submit a community extension project proposal for College Extension Coordinator screening.</span>
        </div>
        <button class="primary-btn" type="button" data-open-modal="extensionProposalModal"><i class="ri-add-line"></i> New Extension Project</button>
    </div>
</section>

<!-- MODAL: SUBMIT EXTENSION PROPOSAL -->
<div class="modal" id="extensionProposalModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <section class="modal-card proposal-modal-card" role="dialog" aria-modal="true" aria-labelledby="extensionProposalModalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close proposal form"><i class="ri-close-line"></i></button>
        <div class="panel-heading">
            <div>
                <h2 id="extensionProposalModalTitle">Submit New Extension Proposal</h2>
                <small style="color: #6b7280;">Stage 1: Proponent Submission (Routed to College Extension Coordinator)</small>
            </div>
        </div>
        <form class="portal-form" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_proposal">
            <div class="form-grid">
                <label class="field"><span>Project Title</span><input name="project_title" placeholder="e.g. Barangay Digital Literacy and Livelihood Program" required></label>
                <label class="field"><span>Project Coordinator / Lead</span><input name="coordinator" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Community Partner / LGU / Beneficiary Group</span><input name="community_partner" placeholder="e.g. Barangay San Jose / Women's Association" required></label>
                <label class="field"><span>Target Location / Community</span><input name="location" placeholder="e.g. San Pablo City, Laguna" required></label>
                <label class="field"><span>Target Beneficiaries (Count and Description)</span><input name="target_beneficiaries" placeholder="e.g. 50 Out-of-school youths and solo parents" required></label>
                <label class="field"><span>Target Budget (PHP)</span><input type="number" step="0.01" name="budget" placeholder="e.g. 35000.00" required></label>
                <label class="field"><span>Start Date</span><input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required></label>
                <label class="field"><span>End Date</span><input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>" required></label>
                <label class="field"><span>Document Category</span>
                    <select name="category_id">
                        <?php foreach (categories() as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="field"><span>Proposal Document / Draft MOA (PDF, DOCX, ZIP up to 15 MB)</span><input type="file" name="required_document" accept=".pdf,.doc,.docx,.zip" required></label>
            <input type="hidden" name="document_title" value="Extension Proposal Document">
            <input type="hidden" name="document_description" value="Supporting document for community extension project.">
            <div class="proposal-modal-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                <button class="primary-btn" type="submit"><i class="ri-send-plane-line"></i> Submit to Extension Coordinator</button>
                <button class="ghost-btn" type="button" data-close-modal>Cancel</button>
            </div>
        </form>
    </section>
</div>
<?php endif; ?>

<!-- EXTENSION PROJECTS TABLE -->
<section class="panel">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <h2>
                <?php
                if ($isCoordinator) echo 'College Extension Projects & Screening';
                elseif ($isDirector) echo 'Institutional Extension Evaluation & Budget Review';
                elseif ($isExecutive) echo 'Executive Extension Oversight & Approvals';
                else echo 'My Extension Projects';
                ?>
            </h2>
            <small style="color: #6b7280;">
                <?php
                if ($isCoordinator) echo 'Proposals and outreach projects from your College/Department.';
                elseif ($isDirector) echo 'Proposals endorsed by College Coordinators for institutional review and budget allocation.';
                elseif ($isExecutive) echo 'Projects ready for Final Executive Approval by the OVPRE.';
                else echo 'Track proposal status from College Coordinator screening to Final Executive Approval.';
                ?>
            </small>
        </div>
        <label class="table-search">
            <span class="sr-only">Search extension projects</span>
            <i class="ri-search-line"></i>
            <input type="search" data-table-search="extensionProjectTable" placeholder="Search project, partner, or status...">
        </label>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="extensionProjectTable" data-paginate="10">
            <thead>
                <tr>
                    <th>Project Title</th>
                    <th>Partner / Location</th>
                    <th>College / Dept</th>
                    <th>Budget</th>
                    <th>Workflow Stage</th>
                    <th>Status</th>
                    <th>Document</th>
                    <th>Manage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php
                    $pStatus = $project['status'] ?? 'Approved';
                    $stage = $project['workflow_stage'] ?? 'Proposal';
                    $progressReports = portal_extension_progress_reports((int) $project['id']);
                    $attachmentUrl = !empty($project['attachment_filename'])
                        ? record_access_url($project['attachment_filename'], '../', (int) $project['document_id'])
                        : '';
                    ?>
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
                        <td>
                            <strong>PHP <?php echo number_format((float) ($project['approved_budget'] ?: $project['budget']), 2); ?></strong>
                        </td>
                        <td>
                            <span style="font-weight: 600; font-size: 0.85rem; color: #166534;">
                                <i class="ri-bookmark-3-line"></i> <?php echo h($stage); ?>
                            </span>
                            <?php if ($progressReports): ?>
                                <br><small style="color:#0284c7;"><?php echo count($progressReports); ?> report(s) logged</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-pill status-<?php echo h(statusClass($pStatus)); ?>">
                                <?php echo h($pStatus); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($attachmentUrl): ?>
                                <div class="file-actions">
                                    <a class="ghost-btn mini-btn" href="<?php echo h($attachmentUrl); ?>" target="_blank" rel="noopener" title="Download Document">
                                        <i class="ri-download-line"></i> File
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="muted">No file</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <details class="user-actions-dropdown">
                                <summary class="user-actions-trigger">
                                    <i class="ri-more-2-fill"></i>
                                    <span>Manage</span>
                                    <i class="ri-arrow-down-s-line user-actions-chevron"></i>
                                </summary>
                                <div class="user-action-menu">
                                    <?php if ($isCoordinator && in_array($pStatus, ['Pending Coordinator Review', 'Pending', 'Under Review'], true)): ?>
                                        <button class="primary-btn action-btn" type="button" data-open-modal="coordinator-review-<?php echo (int) $project['id']; ?>" style="background: #15803d; color: #fff;">
                                            <i class="ri-check-double-line"></i><span>Endorse to Director</span>
                                        </button>
                                        <button class="ghost-btn action-btn" type="button" data-open-modal="coordinator-review-<?php echo (int) $project['id']; ?>" style="color: #c2410c; background: #fff7ed; border: 1px solid #fed7aa;">
                                            <i class="ri-arrow-go-back-line"></i><span>Return for Revision</span>
                                        </button>
                                    <?php elseif ($isDirector && in_array($pStatus, ['Pending Director Review', 'Under Review'], true)): ?>
                                        <button class="primary-btn action-btn" type="button" data-open-modal="director-review-<?php echo (int) $project['id']; ?>" style="background: #1d4ed8; color: #fff;">
                                            <i class="ri-medal-line"></i><span>Evaluate Project</span>
                                        </button>
                                    <?php elseif ($isExecutive && in_array($pStatus, ['Pending Executive Approval'], true)): ?>
                                        <button class="primary-btn action-btn" type="button" data-open-modal="executive-review-<?php echo (int) $project['id']; ?>" style="background: #7e22ce; color: #fff;">
                                            <i class="ri-shield-check-line"></i><span>Final Approval</span>
                                        </button>
                                    <?php endif; ?>

                                    <button class="ghost-btn action-btn" type="button" data-open-modal="project-details-<?php echo (int) $project['id']; ?>">
                                        <i class="ri-eye-line"></i><span>Details & History</span>
                                    </button>

                                    <?php if ($isExtensionist && $pStatus === 'Approved'): ?>
                                        <button class="ghost-btn action-btn" type="button" data-open-modal="progress-report-<?php echo (int) $project['id']; ?>" style="color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0;">
                                            <i class="ri-upload-cloud-line"></i><span>Submit Output / Liquidation</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($isExtensionist && $pStatus === 'Needs Revision'): ?>
                                        <button class="ghost-btn action-btn" type="button" data-open-modal="resubmit-<?php echo (int) $project['id']; ?>" style="color: #c2410c; background: #fff7ed; border: 1px solid #fed7aa;">
                                            <i class="ri-edit-line"></i><span>Resubmit Project</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($attachmentUrl): ?>
                                        <a class="ghost-btn action-btn" href="<?php echo h($attachmentUrl); ?>" target="_blank" rel="noopener">
                                            <i class="ri-download-line"></i><span>Download Document</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$projects): ?>
                    <tr data-pagination-empty>
                        <td colspan="8" style="text-align: center; padding: 24px; color: #6b7280;">No extension projects found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- MODALS SECTION -->
<?php foreach ($projects as $project): ?>
    <?php
    $projId = (int) $project['id'];
    $logs = review_timeline('extension', $projId);
    $progressReports = portal_extension_progress_reports($projId);
    ?>

    <!-- 1. DETAILS & TIMELINE MODAL -->
    <div class="modal" id="project-details-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 680px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2><?php echo h($project['project_title']); ?></h2>
            <div style="margin-bottom: 12px;">
                <span class="status-pill status-<?php echo h(statusClass($project['status'] ?? 'Approved')); ?>"><?php echo h($project['status'] ?? 'Approved'); ?></span>
                <span style="margin-left: 8px; color: #6b7280; font-size: 0.85rem;"><i class="ri-community-line"></i> Partner: <?php echo h($project['community_partner']); ?> | <i class="ri-user-line"></i> <?php echo h($project['fullname']); ?></span>
            </div>

            <div style="background: #f9fafb; padding: 12px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 16px;">
                <div><strong>Location:</strong> <?php echo h($project['location'] ?? 'Not specified'); ?></div>
                <div style="margin-top: 4px;"><strong>Target Beneficiaries:</strong> <?php echo h($project['target_beneficiaries'] ?? 'Not specified'); ?></div>
                <div style="margin-top: 4px;"><strong>Proposed Budget:</strong> PHP <?php echo number_format((float) $project['budget'], 2); ?></div>
                <div style="margin-top: 4px;"><strong>Dates:</strong> <?php echo date('M d, Y', strtotime($project['start_date'])); ?> to <?php echo date('M d, Y', strtotime($project['end_date'])); ?></div>
            </div>

            <!-- REVIEW INFORMATION HIGHLIGHTS -->
            <?php if (!empty($project['coordinator_remarks'])): ?>
                <div style="border-left: 3px solid #b45309; padding-left: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                    <strong style="color: #b45309;">College Coordinator Endorsement:</strong>
                    <div><?php echo nl2br(h($project['coordinator_remarks'])); ?></div>
                    <small style="color: #6b7280;">Endorsed by: <?php echo h($project['coordinator_name'] ?? 'Coordinator'); ?> | <?php echo !empty($project['coordinator_reviewed_at']) ? date('M d, Y', strtotime($project['coordinator_reviewed_at'])) : ''; ?></small>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['director_remarks']) || !empty($project['evaluation_rating'])): ?>
                <div style="border-left: 3px solid #1d4ed8; padding-left: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                    <strong style="color: #1d4ed8;">Extension Director Evaluation:</strong>
                    <div>Rating / Score: <strong><?php echo h($project['evaluation_rating'] ?? 'N/A'); ?>/100</strong></div>
                    <?php if (!empty($project['approved_budget'])): ?>
                        <div>Approved Extension Budget: <strong>PHP <?php echo number_format((float) $project['approved_budget'], 2); ?></strong></div>
                    <?php endif; ?>
                    <div><?php echo nl2br(h($project['director_remarks'])); ?></div>
                    <small style="color: #6b7280;">Director: <?php echo h($project['director_name'] ?? 'Director'); ?> | <?php echo !empty($project['director_reviewed_at']) ? date('M d, Y', strtotime($project['director_reviewed_at'])) : ''; ?></small>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['executive_remarks'])): ?>
                <div style="border-left: 3px solid #7e22ce; padding-left: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                    <strong style="color: #7e22ce;">Executive Final Approval:</strong>
                    <div><?php echo nl2br(h($project['executive_remarks'])); ?></div>
                    <small style="color: #6b7280;">Approved by: <?php echo h($project['executive_name'] ?? 'OVPRE Executive'); ?> | <?php echo !empty($project['executive_approved_at']) ? date('M d, Y', strtotime($project['executive_approved_at'])) : ''; ?></small>
                </div>
            <?php endif; ?>

            <!-- PROGRESS REPORTS LIST -->
            <?php if ($progressReports): ?>
                <h3 style="margin-top: 16px; font-size: 1rem; color: #166534;"><i class="ri-task-line"></i> Submitted Milestones, Liquidation, & Impact Outputs</h3>
                <div style="display: grid; gap: 8px; margin-bottom: 16px;">
                    <?php foreach ($progressReports as $rep): ?>
                        <div style="padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.85rem; background: #ffffff;">
                            <div style="display: flex; justify-content: space-between;">
                                <strong style="color: #0369a1;"><?php echo h($rep['report_type']); ?>: <?php echo h($rep['title']); ?></strong>
                                <small style="color: #6b7280;"><?php echo date('M d, Y', strtotime($rep['created_at'])); ?></small>
                            </div>
                            <p style="margin: 4px 0; color: #4b5563;"><?php echo nl2br(h($rep['description'])); ?></p>
                            <?php if (!empty($rep['attachment_filename'])): ?>
                                <a class="ghost-btn mini-btn" href="<?php echo h(record_access_url($rep['attachment_filename'], '../', (int) $rep['document_id'])); ?>" target="_blank" rel="noopener">
                                    <i class="ri-file-download-line"></i> Download Attachment
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- TIMELINE LIST -->
            <h3 style="margin-top: 16px; font-size: 1rem;"><i class="ri-history-line"></i> Audit Trail & History</h3>
            <div class="timeline-list">
                <?php foreach ($logs as $log): ?>
                    <div>
                        <strong><?php echo h($log['action']); ?></strong>
                        <p><?php echo h($log['remarks'] ?? ''); ?></p>
                        <small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small>
                    </div>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <p class="muted">No logs recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. COORDINATOR SCREENING MODAL -->
    <?php if ($isCoordinator || $isExecutive): ?>
    <div class="modal" id="coordinator-review-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 600px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2 style="color: #b45309;"><i class="ri-shield-check-line"></i> College Extension Coordinator Screening</h2>
            <p><strong><?php echo h($project['project_title']); ?></strong></p>
            <p style="font-size: 0.9rem; color: #4b5563;">Proponent: <?php echo h($project['fullname']); ?> | Partner: <?php echo h($project['community_partner']); ?></p>

            <form method="post" style="display: grid; gap: 14px; margin-top: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $projId; ?>">
                
                <label class="field">
                    <span>Endorsement Remarks / Feedback (College-level screening findings):</span>
                    <textarea name="remarks" rows="4" placeholder="e.g. Community proposal screened. Selected community is suitable and aligned with the college extension agenda." required></textarea>
                </label>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                    <button class="primary-btn" type="submit" name="action" value="coordinator_endorse" style="background: #15803d;">
                        <i class="ri-check-line"></i> Endorse to Extension Director
                    </button>
                    <button class="ghost-btn" type="submit" name="action" value="coordinator_return" style="color: #c2410c; border-color: #fed7aa;">
                        <i class="ri-arrow-go-back-line"></i> Return for Revision
                    </button>
                    <button class="ghost-btn danger-btn" type="submit" name="action" value="decline" onclick="return confirm('Decline this extension proposal?');">
                        <i class="ri-close-line"></i> Decline
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 3. DIRECTOR EVALUATION MODAL -->
    <?php if ($isDirector || $isExecutive): ?>
    <div class="modal" id="director-review-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 640px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2 style="color: #1d4ed8;"><i class="ri-award-line"></i> Extension Director Evaluation & Budget Review</h2>
            <p><strong><?php echo h($project['project_title']); ?></strong></p>
            <div style="background: #eff6ff; padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px;">
                <strong>Coordinator Endorsement:</strong> <?php echo h($project['coordinator_remarks'] ?? 'Endorsed for review'); ?>
            </div>

            <form method="post" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $projId; ?>">

                <div class="form-grid">
                    <label class="field">
                        <span>Evaluation Rating / Score (out of 100):</span>
                        <input type="number" step="0.01" min="0" max="100" name="evaluation_rating" placeholder="e.g. 88.00" required>
                    </label>
                    <label class="field">
                        <span>Recommended Approved Budget (PHP):</span>
                        <input type="number" step="0.01" min="0" name="approved_budget" value="<?php echo h($project['budget']); ?>" required>
                    </label>
                </div>

                <label class="field">
                    <span>Director Evaluation & Recommendation Remarks:</span>
                    <textarea name="remarks" rows="3" placeholder="Director evaluation remarks regarding community partnership, safety protocols, and budget allocation..." required></textarea>
                </label>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; margin-top: 8px;">
                    <button class="primary-btn" type="submit" name="action" value="director_endorse" style="background: #1d4ed8;">
                        <i class="ri-send-plane-line"></i> Endorse to VP / Executive
                    </button>
                    <button class="ghost-btn" type="submit" name="action" value="director_return" style="color: #c2410c; border-color: #fed7aa;">
                        <i class="ri-arrow-go-back-line"></i> Return for Revision
                    </button>
                    <button class="ghost-btn danger-btn" type="submit" name="action" value="decline" onclick="return confirm('Decline this project?');">
                        <i class="ri-close-line"></i> Decline
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 4. EXECUTIVE FINAL APPROVAL MODAL -->
    <?php if ($isExecutive): ?>
    <div class="modal" id="executive-review-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 640px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2 style="color: #7e22ce;"><i class="ri-verified-badge-line"></i> OVPRE Executive Final Approval (Extension)</h2>
            <p><strong><?php echo h($project['project_title']); ?></strong></p>

            <div style="background: #faf5ff; padding: 12px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px; border: 1px solid #e9d5ff;">
                <div><strong>1. Coordinator Endorsement:</strong> <?php echo h($project['coordinator_remarks'] ?? 'Screened'); ?></div>
                <div style="margin-top: 4px;"><strong>2. Director Evaluation:</strong> Score <?php echo h($project['evaluation_rating'] ?? 'N/A'); ?>/100 | Approved Budget: PHP <?php echo number_format((float) ($project['approved_budget'] ?? $project['budget']), 2); ?></div>
                <div style="margin-top: 4px;"><?php echo h($project['director_remarks'] ?? ''); ?></div>
            </div>

            <form method="post" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $projId; ?>">

                <label class="field">
                    <span>Executive Approval Resolution & Community Directives:</span>
                    <textarea name="remarks" rows="4" placeholder="Official approval resolution, budget allocation directives, and Notice to Implement..." required>Approved for implementation under the Institutional Extension Program. Notice to Implement issued.</textarea>
                </label>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; margin-top: 8px;">
                    <button class="primary-btn" type="submit" name="action" value="executive_approve" style="background: #16a34a;">
                        <i class="ri-checkbox-circle-line"></i> Grant Final Approval
                    </button>
                    <button class="ghost-btn danger-btn" type="submit" name="action" value="decline" onclick="return confirm('Disapprove this extension project?');">
                        <i class="ri-close-line"></i> Disapprove
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. FACULTY EXTENSIONIST PROGRESS / LIQUIDATION / IMPACT SUBMISSION MODAL -->
    <?php if ($isExtensionist && ($project['status'] ?? '') === 'Approved'): ?>
    <div class="modal" id="progress-report-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 600px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2 style="color: #15803d;"><i class="ri-file-chart-line"></i> Submit Milestone, Liquidation, or Impact Output</h2>
            <p><strong><?php echo h($project['project_title']); ?></strong></p>

            <form method="post" enctype="multipart/form-data" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="submit_progress_report">
                <input type="hidden" name="id" value="<?php echo $projId; ?>">
                <input type="hidden" name="category_id" value="<?php echo (int) ($project['category_id'] ?? 4); ?>">

                <label class="field">
                    <span>Report / Output Type:</span>
                    <select name="report_type" required>
                        <option value="Milestone Report">1. Outreach & Training Milestone Progress Report</option>
                        <option value="Liquidation Report">2. Financial Liquidation Report (Receipts & Accounting)</option>
                        <option value="Community Impact & Output">3. Community Impact & Output (Terminal Narrative, Signed MOA, Certificates)</option>
                    </select>
                </label>

                <label class="field">
                    <span>Report / Output Title:</span>
                    <input name="title" placeholder="e.g. First Community Training Milestone / Liquidation Batch 1 / Terminal Narrative" required>
                </label>

                <label class="field">
                    <span>Description / Summary of Accomplishments:</span>
                    <textarea name="description" rows="3" placeholder="Describe completed training, community attendance, fund disbursement, or beneficiary feedback..." required></textarea>
                </label>

                <label class="field">
                    <span>Supporting Document / Proof (PDF, DOCX, ZIP):</span>
                    <input type="file" name="progress_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip" required>
                </label>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                    <button class="primary-btn" type="submit"><i class="ri-upload-cloud-line"></i> Submit Report</button>
                    <button class="ghost-btn" type="button" data-close-modal>Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 6. FACULTY RESUBMIT MODAL (IF NEEDS REVISION) -->
    <?php if ($isExtensionist && ($project['status'] ?? '') === 'Needs Revision'): ?>
    <div class="modal" id="resubmit-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 600px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2 style="color: #c2410c;"><i class="ri-edit-line"></i> Revise and Resubmit Extension Proposal</h2>
            <div style="background: #fff7ed; padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px; border: 1px solid #ffedd5;">
                <strong>Feedback / Remarks:</strong> <?php echo nl2br(h($project['status_remarks'] ?? 'Needs revision')); ?>
            </div>

            <form method="post" enctype="multipart/form-data" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="resubmit_proposal">
                <input type="hidden" name="id" value="<?php echo $projId; ?>">
                <input type="hidden" name="category_id" value="<?php echo (int) ($project['category_id'] ?? 3); ?>">

                <label class="field"><span>Project Title</span><input name="project_title" value="<?php echo h($project['project_title']); ?>" required></label>
                <label class="field"><span>Community Partner</span><input name="community_partner" value="<?php echo h($project['community_partner']); ?>" required></label>
                <label class="field"><span>Location</span><input name="location" value="<?php echo h($project['location'] ?? ''); ?>"></label>
                <label class="field"><span>Target Beneficiaries</span><input name="target_beneficiaries" value="<?php echo h($project['target_beneficiaries'] ?? ''); ?>"></label>
                <label class="field"><span>Budget (PHP)</span><input type="number" step="0.01" name="budget" value="<?php echo h($project['budget']); ?>" required></label>
                <label class="field"><span>Revision Notes</span><textarea name="revision_notes" rows="2" placeholder="Summary of changes made based on reviewer feedback..." required></textarea></label>
                <label class="field"><span>Updated Proposal Document (Optional)</span><input type="file" name="required_document" accept=".pdf,.doc,.docx,.zip"></label>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                    <button class="primary-btn" type="submit" style="background: #c2410c;"><i class="ri-send-plane-line"></i> Resubmit to Coordinator</button>
                    <button class="ghost-btn" type="button" data-close-modal>Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

<?php endforeach; ?>

<?php portal_footer(); ?>
