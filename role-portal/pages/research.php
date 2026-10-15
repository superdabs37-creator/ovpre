<?php
if (!portal_can('research')) {
    http_response_code(403);
    exit('Research module is not available for this role.');
}
$user = portal_require_user();
verify_csrf();
$notice = flash();

$isResearcher = PORTAL_ROLE === 'Researcher';
$isCoordinator = PORTAL_ROLE === 'Research Coordinator';
$isDirector = PORTAL_ROLE === 'Research Director';
$isExecutive = in_array(PORTAL_ROLE, ['Vice President', 'OVPRE Admin'], true) || ($user['role'] ?? '') === ROLE_SUPER_ADMIN;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    try {
        if ($action === 'create' || $action === 'create_proposal') {
            $documentId = portal_save_document($_POST, $_FILES['supporting_document'] ?? [], $user);
            $title = trim((string) ($_POST['title'] ?? ''));
            $researchers = trim((string) ($_POST['researchers'] ?? ''));
            $abstract = trim((string) ($_POST['abstract'] ?? ''));
            $fundingSource = trim((string) ($_POST['funding_source'] ?? 'Institutional Grant'));

            if ($title === '' || $researchers === '' || $abstract === '') {
                throw new InvalidArgumentException('Title, researchers, and abstract are required.');
            }

            db()->prepare('INSERT INTO research_projects (title, researchers, abstract, funding_source, document_id, submitted_by, status, workflow_stage, created_at) VALUES (?, ?, ?, ?, ?, ?, "Pending Coordinator Review", "Proposal", NOW())')
                ->execute([$title, $researchers, $abstract, $fundingSource, $documentId, (int) $user['id']]);
            $projectId = (int) db()->lastInsertId();

            log_review('research', $projectId, (int) $user['id'], 'Stage 1: Proposal Submitted', 'Submitted proposal for College Research Coordinator initial screening.');
            log_action((int) $user['id'], 'Submitted research proposal: ' . $title);

            // Notify college coordinators
            $coordStmt = db()->prepare('SELECT id FROM users WHERE department_id = ? AND role = "Research Coordinator" AND status = "Active"');
            $coordStmt->execute([(int) ($user['department_id'] ?? 0)]);
            $coords = $coordStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($coords as $coordId) {
                notify_user((int) $coordId, 'New research proposal submitted for your college screening: ' . $title, 'research.php#proposal-' . $projectId);
            }

            flash('success', 'Research proposal submitted successfully! It has been routed to your College Research Coordinator for initial screening.');
            redirect_to('research.php');

        } elseif ($action === 'resubmit_proposal') {
            $projectId = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $abstract = trim((string) ($_POST['abstract'] ?? ''));
            $revisionNotes = trim((string) ($_POST['revision_notes'] ?? ''));

            $existingStmt = db()->prepare('SELECT * FROM research_projects WHERE id = ? AND submitted_by = ?');
            $existingStmt->execute([$projectId, (int) $user['id']]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                throw new RuntimeException('Proposal not found or access denied.');
            }

            $documentId = $existing['document_id'];
            if (!empty($_FILES['supporting_document']['name'])) {
                $newDocId = portal_save_document($_POST, $_FILES['supporting_document'], $user);
                if ($newDocId) {
                    $documentId = $newDocId;
                }
            }

            db()->prepare('UPDATE research_projects SET title = ?, abstract = ?, document_id = ?, status = "Pending Coordinator Review", status_remarks = ? WHERE id = ?')
                ->execute([$title, $abstract, $documentId, 'Resubmitted with revisions: ' . $revisionNotes, $projectId]);

            log_review('research', $projectId, (int) $user['id'], 'Proposal Resubmitted', $revisionNotes);
            log_action((int) $user['id'], 'Resubmitted research proposal #' . $projectId);

            flash('success', 'Proposal resubmitted for coordinator review.');
            redirect_to('research.php');

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

            db()->prepare('INSERT INTO research_progress_reports (research_id, report_type, title, description, document_id, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "Submitted", NOW())')
                ->execute([$projectId, $reportType, $title, $description, $documentId, (int) $user['id']]);

            // Update workflow stage of project to reflect current progress
            $stage = match ($reportType) {
                'Liquidation Report' => 'Liquidation',
                'Dissemination & Output' => 'Dissemination',
                default => 'Implementation'
            };
            db()->prepare('UPDATE research_projects SET workflow_stage = ? WHERE id = ?')->execute([$stage, $projectId]);

            log_review('research', $projectId, (int) $user['id'], 'Submitted ' . $reportType, $title . ': ' . $description);
            log_action((int) $user['id'], 'Submitted ' . $reportType . ' for project #' . $projectId);

            flash('success', $reportType . ' submitted successfully!');
            redirect_to('research.php');

        } elseif ($action === 'coordinator_endorse') {
            if (!$isCoordinator && !$isExecutive) {
                throw new RuntimeException('Only Research Coordinators can endorse at college level.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Passed college-level screening and aligned with department research agenda.'));

            db()->prepare('UPDATE research_projects SET status = "Pending Director Review", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('research', $projectId, (int) $user['id'], 'Stage 2: Endorsed by Coordinator', $remarks);
            log_action((int) $user['id'], 'Coordinator endorsed research #' . $projectId);

            // Notify author & directors
            $owner = db()->query('SELECT submitted_by, title FROM research_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your research proposal "' . $owner['title'] . '" was endorsed by your Coordinator to the Research Director.', 'research.php#proposal-' . $projectId);
            }
            // Notify Directors
            $directors = db()->query('SELECT id FROM users WHERE role IN ("Research Director", "Director for Research and Creative Works") AND status = "Active"')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($directors as $dirId) {
                notify_user((int) $dirId, 'New proposal awaiting AIHR & director evaluation: ' . ($owner['title'] ?? ''), 'research.php#proposal-' . $projectId);
            }

            flash('success', 'Proposal endorsed to the Research Director!');
            redirect_to('research.php');

        } elseif ($action === 'coordinator_return') {
            if (!$isCoordinator && !$isExecutive) {
                throw new RuntimeException('Unauthorized.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Please revise according to college feedback.'));

            db()->prepare('UPDATE research_projects SET status = "Needs Revision", coordinator_id = ?, coordinator_remarks = ?, coordinator_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('research', $projectId, (int) $user['id'], 'Returned for Revision by Coordinator', $remarks);
            $owner = db()->query('SELECT submitted_by, title FROM research_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your research proposal "' . $owner['title'] . '" requires revision: ' . $remarks, 'research.php#proposal-' . $projectId);
            }

            flash('success', 'Proposal returned to faculty for revision.');
            redirect_to('research.php');

        } elseif ($action === 'director_endorse') {
            if (!$isDirector && !$isExecutive) {
                throw new RuntimeException('Only Research Directors can perform AIHR and institutional evaluation.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $aihrStatus = trim((string) ($_POST['aihr_status'] ?? 'Passed AIHR'));
            $aihrScore = ($_POST['aihr_score'] ?? '') !== '' ? (float) $_POST['aihr_score'] : null;
            $grantAmount = ($_POST['grant_amount'] ?? '') !== '' ? (float) $_POST['grant_amount'] : null;
            $remarks = trim((string) ($_POST['remarks'] ?? 'Evaluated under university research guidelines and recommended for executive funding approval.'));

            db()->prepare('UPDATE research_projects SET status = "Pending Executive Approval", director_id = ?, director_remarks = ?, aihr_status = ?, aihr_score = ?, grant_amount = ?, director_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $aihrStatus, $aihrScore, $grantAmount, $remarks, $projectId]);

            $logDetails = "AIHR Status: {$aihrStatus} | Score: " . ($aihrScore ?? 'N/A') . " | Recommended Grant: PHP " . number_format((float) $grantAmount, 2) . "\n" . $remarks;
            log_review('research', $projectId, (int) $user['id'], 'Stage 3: Endorsed by Research Director', $logDetails);
            log_action((int) $user['id'], 'Research Director endorsed research #' . $projectId . ' with score ' . $aihrScore);

            $owner = db()->query('SELECT submitted_by, title FROM research_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your research proposal passed AIHR evaluation and is forwarded to OVPRE Executive for final approval.', 'research.php#proposal-' . $projectId);
            }
            // Notify System Admin / VP
            $admins = db()->query('SELECT id FROM users WHERE role IN ("Super Admin", "OVPRE Admin", "Vice President") AND status = "Active"')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                notify_user((int) $adminId, 'Proposal awaiting Final Executive Approval: ' . ($owner['title'] ?? ''), 'research.php#proposal-' . $projectId);
            }

            flash('success', 'AIHR evaluation completed and endorsed to VP / OVPRE Executive!');
            redirect_to('research.php');

        } elseif ($action === 'director_return') {
            if (!$isDirector && !$isExecutive) {
                throw new RuntimeException('Unauthorized.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Returned by Research Director for amendment.'));

            db()->prepare('UPDATE research_projects SET status = "Needs Revision", director_id = ?, director_remarks = ?, director_reviewed_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('research', $projectId, (int) $user['id'], 'Returned for Revision by Research Director', $remarks);
            $owner = db()->query('SELECT submitted_by, title FROM research_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your research proposal was returned by the Research Director for revision: ' . $remarks, 'research.php#proposal-' . $projectId);
            }

            flash('success', 'Proposal returned for revision.');
            redirect_to('research.php');

        } elseif ($action === 'executive_approve') {
            if (!$isExecutive) {
                throw new RuntimeException('Only the Vice President / Super Admin can grant final executive approval.');
            }
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Granted Final Institutional Approval and official grant release.'));

            db()->prepare('UPDATE research_projects SET status = "Approved", executive_id = ?, executive_remarks = ?, executive_approved_at = NOW(), status_remarks = ? WHERE id = ?')
                ->execute([(int) $user['id'], $remarks, $remarks, $projectId]);

            log_review('research', $projectId, (int) $user['id'], 'Stage 4: Final Executive Approval Granted', $remarks);
            log_action((int) $user['id'], 'Executive approved research #' . $projectId);

            $owner = db()->query('SELECT submitted_by, title FROM research_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], '🎉 Congratulations! Your research project "' . $owner['title'] . '" has received FINAL APPROVAL from the Office of the Vice President.', 'research.php#proposal-' . $projectId);
            }

            flash('success', 'Final Institutional Approval successfully granted!');
            redirect_to('research.php');

        } elseif ($action === 'decline') {
            $projectId = (int) ($_POST['id'] ?? 0);
            $remarks = trim((string) ($_POST['remarks'] ?? 'Proposal declined.'));

            db()->prepare('UPDATE research_projects SET status = "Declined", status_remarks = ? WHERE id = ?')
                ->execute([$remarks, $projectId]);

            log_review('research', $projectId, (int) $user['id'], 'Declined', $remarks);
            $owner = db()->query('SELECT submitted_by, title FROM research_projects WHERE id = ' . $projectId)->fetch();
            if ($owner) {
                notify_user((int) $owner['submitted_by'], 'Your research proposal "' . $owner['title'] . '" was declined. Remarks: ' . $remarks, 'research.php#proposal-' . $projectId);
            }

            flash('success', 'Proposal has been declined.');
            redirect_to('research.php');
        }
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}

$user = portal_header('Research Management & Workflow', 'research');
$projects = portal_research_projects();

// Stage count helpers
$pendingCoordinatorCount = 0;
$pendingDirectorCount = 0;
$pendingExecutiveCount = 0;
$approvedCount = 0;
foreach ($projects as $p) {
    if ($p['status'] === 'Pending Coordinator Review') $pendingCoordinatorCount++;
    elseif ($p['status'] === 'Pending Director Review') $pendingDirectorCount++;
    elseif ($p['status'] === 'Pending Executive Approval') $pendingExecutiveCount++;
    elseif ($p['status'] === 'Approved') $approvedCount++;
}
?>

<?php portal_notice($notice); ?>

<!-- WORKFLOW PROGRESS BANNER -->
<section class="panel" style="margin-bottom: 20px; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); border-left: 5px solid #16a34a;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="margin: 0; color: #166534; font-size: 1.25rem;">
                <i class="ri-git-merge-line"></i> Research Review & Approval Pipeline
            </h2>
            <p style="margin: 4px 0 0; color: #4b5563; font-size: 0.9rem;">
                Hierarchical Academic Flow: <strong>Faculty Researcher</strong> &rarr; <strong>Research Coordinator</strong> &rarr; <strong>Research Director</strong> &rarr; <strong>Vice President / Admin</strong>
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
                Faculty Researcher
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">Proposal Submission, Milestones, Liquidation, Dissemination</small>
        </div>

        <div style="background: #ffffff; padding: 12px 14px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #b45309;">
                <span style="background: #fef3c7; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">2</span>
                Research Coordinator
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">College Liaison, Initial Screening & Endorsement, Local Monitoring</small>
        </div>

        <div style="background: #ffffff; padding: 12px 14px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #1d4ed8;">
                <span style="background: #dbeafe; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">3</span>
                Research Director
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">Agency In-House Review (AIHR), Policies & Manuals, Funding & Grants</small>
        </div>

        <div style="background: #ffffff; padding: 12px 14px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: #7e22ce;">
                <span style="background: #f3e8ff; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">4</span>
                VP for Research & Ext.
            </div>
            <small style="color: #6b7280; display: block; margin-top: 4px; line-height: 1.3;">Strategic Direction, External Linkages, Final Institutional Approval</small>
        </div>
    </div>
</section>

<!-- FACULTY RESEARCHER SUBMIT BUTTON -->
<?php if ($isResearcher || $isExecutive): ?>
<section class="panel proposal-launcher" style="margin-bottom: 20px;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-file-add-line"></i> Submit New Research Proposal</h2>
            <span>Initiate your research. This will be routed to your College Research Coordinator for initial screening.</span>
        </div>
        <button class="primary-btn" type="button" data-open-modal="researchProposalModal"><i class="ri-add-line"></i> New Proposal</button>
    </div>
</section>

<!-- MODAL: SUBMIT PROPOSAL -->
<div class="modal" id="researchProposalModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <section class="modal-card proposal-modal-card" role="dialog" aria-modal="true" aria-labelledby="researchProposalModalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close proposal form"><i class="ri-close-line"></i></button>
        <div class="panel-heading">
            <div>
                <h2 id="researchProposalModalTitle">Submit Research Proposal</h2>
                <small style="color: #6b7280;">Stage 1: Proponent Submission (Routed to Research Coordinator for Screening)</small>
            </div>
        </div>
        <form class="portal-form" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_proposal">
            <div class="form-grid">
                <label class="field"><span>Proposal Title</span><input name="title" placeholder="e.g. Artificial Intelligence in Local Governance" required></label>
                <label class="field"><span>Researchers / Proponents</span><input name="researchers" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Target Funding Source</span><input name="funding_source" value="Institutional Research Grant (OVPRE)"></label>
                <label class="field"><span>Document Category</span>
                    <select name="category_id">
                        <?php foreach (categories() as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <label class="field"><span>Proposal Abstract</span><textarea name="abstract" rows="5" placeholder="State the research objectives, methodology, and expected outputs..." required></textarea></label>
            <label class="field"><span>Proposal Document (PDF, DOCX, ZIP up to 15 MB)</span><input type="file" name="supporting_document" accept=".pdf,.doc,.docx,.zip" required></label>
            <input type="hidden" name="document_title" value="Research Proposal Document">
            <input type="hidden" name="document_description" value="Supporting document for research proposal.">
            <div class="proposal-modal-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                <button class="primary-btn" type="submit"><i class="ri-send-plane-line"></i> Submit to Coordinator</button>
                <button class="ghost-btn" type="button" data-close-modal>Cancel</button>
            </div>
        </form>
    </section>
</div>
<?php endif; ?>

<!-- RESEARCH PROJECTS LIST -->
<section class="panel">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <h2>
                <?php
                if ($isCoordinator) echo 'College Research Proposals & Monitoring';
                elseif ($isDirector) echo 'Institutional Research Evaluation & AIHR';
                elseif ($isExecutive) echo 'Executive Research Oversight & Approvals';
                else echo 'My Research Submissions';
                ?>
            </h2>
            <small style="color: #6b7280;">
                <?php
                if ($isCoordinator) echo 'Proposals and reports from your College/Department.';
                elseif ($isDirector) echo 'Proposals endorsed by College Coordinators for AIHR and institutional grant review.';
                elseif ($isExecutive) echo 'Proposals ready for Final Executive Approval by the OVPRE.';
                else echo 'Track proposal status from College Coordinator screening to Final Executive Approval.';
                ?>
            </small>
        </div>
        <label class="table-search">
            <span class="sr-only">Search research submissions</span>
            <i class="ri-search-line"></i>
            <input type="search" data-table-search="researchSubmissionTable" placeholder="Search title, proponent, or status...">
        </label>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="researchSubmissionTable" data-paginate="10">
            <thead>
                <tr>
                    <th>Research Title</th>
                    <th>Proponent</th>
                    <th>College / Dept</th>
                    <th>Workflow Stage</th>
                    <th>Status</th>
                    <th>Document</th>
                    <th>Action / Review</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                    <?php
                    $pStatus = $project['status'];
                    $stage = $project['workflow_stage'] ?? 'Proposal';
                    $progressReports = portal_progress_reports((int) $project['id']);
                    ?>
                    <tr id="proposal-<?php echo (int) $project['id']; ?>">
                        <td>
                            <strong><?php echo h($project['title']); ?></strong>
                            <div style="font-size: 0.8rem; color: #6b7280; margin-top: 3px;">
                                <i class="ri-calendar-line"></i> <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                                <?php if (!empty($project['funding_source'])): ?>
                                    | <i class="ri-bank-card-line"></i> <?php echo h($project['funding_source']); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo h($project['fullname']); ?></td>
                        <td><span class="dept-pill"><?php echo h($project['department_name'] ?? 'College'); ?></span></td>
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
                            <?php if (!empty($project['attachment_filename'])): ?>
                                <?php $attachmentUrl = record_access_url($project['attachment_filename'], '../', (int) $project['document_id']); ?>
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
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <!-- REVIEW / ACTION BUTTON -->
                                <?php if ($isCoordinator && $pStatus === 'Pending Coordinator Review'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="coordinator-review-<?php echo (int) $project['id']; ?>" style="background: #b45309;">
                                        <i class="ri-check-double-line"></i> Screen & Endorse
                                    </button>
                                <?php elseif ($isDirector && $pStatus === 'Pending Director Review'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="director-review-<?php echo (int) $project['id']; ?>" style="background: #1d4ed8;">
                                        <i class="ri-medal-line"></i> AIHR Evaluation
                                    </button>
                                <?php elseif ($isExecutive && $pStatus === 'Pending Executive Approval'): ?>
                                    <button class="primary-btn mini-btn" type="button" data-open-modal="executive-review-<?php echo (int) $project['id']; ?>" style="background: #7e22ce;">
                                        <i class="ri-shield-check-line"></i> Final Approval
                                    </button>
                                <?php endif; ?>

                                <!-- VIEW TIMELINE & DETAILS BUTTON -->
                                <button class="ghost-btn mini-btn" type="button" data-open-modal="project-details-<?php echo (int) $project['id']; ?>">
                                    <i class="ri-eye-line"></i> Details & History
                                </button>

                                <!-- FOR RESEARCHER: IF APPROVED, ALLOW PROGRESS/LIQUIDATION/OUTPUT SUBMISSION -->
                                <?php if ($isResearcher && $pStatus === 'Approved'): ?>
                                    <button class="ghost-btn mini-btn" type="button" data-open-modal="progress-report-<?php echo (int) $project['id']; ?>" style="color: #15803d; border-color: #86efac;">
                                        <i class="ri-upload-cloud-line"></i> Submit Output / Liquidation
                                    </button>
                                <?php endif; ?>

                                <!-- FOR RESEARCHER: IF NEEDS REVISION -->
                                <?php if ($isResearcher && $pStatus === 'Needs Revision'): ?>
                                    <button class="ghost-btn mini-btn" type="button" data-open-modal="resubmit-<?php echo (int) $project['id']; ?>" style="color: #c2410c; border-color: #fed7aa;">
                                        <i class="ri-edit-line"></i> Resubmit
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$projects): ?>
                    <tr data-pagination-empty>
                        <td colspan="7" style="text-align: center; padding: 24px; color: #6b7280;">No research records found.</td>
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
    $logs = review_timeline('research', $projId);
    $progressReports = portal_progress_reports($projId);
    ?>

    <!-- 1. DETAILS & TIMELINE MODAL -->
    <div class="modal" id="project-details-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 680px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2><?php echo h($project['title']); ?></h2>
            <div style="margin-bottom: 12px;">
                <span class="status-pill status-<?php echo h(statusClass($project['status'])); ?>"><?php echo h($project['status']); ?></span>
                <span style="margin-left: 8px; color: #6b7280; font-size: 0.85rem;"><i class="ri-user-line"></i> <?php echo h($project['fullname']); ?> (<?php echo h($project['department_name'] ?? 'College'); ?>)</span>
            </div>

            <div style="background: #f9fafb; padding: 12px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 16px;">
                <strong>Abstract:</strong>
                <p style="margin: 4px 0 0; color: #374151;"><?php echo nl2br(h($project['abstract'])); ?></p>
            </div>

            <!-- REVIEW INFORMATION HIGHLIGHTS -->
            <?php if (!empty($project['coordinator_remarks'])): ?>
                <div style="border-left: 3px solid #b45309; padding-left: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                    <strong style="color: #b45309;">College Coordinator Endorsement:</strong>
                    <div><?php echo nl2br(h($project['coordinator_remarks'])); ?></div>
                    <small style="color: #6b7280;">Endorsed by: <?php echo h($project['coordinator_name'] ?? 'Coordinator'); ?> | <?php echo !empty($project['coordinator_reviewed_at']) ? date('M d, Y', strtotime($project['coordinator_reviewed_at'])) : ''; ?></small>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['director_remarks']) || !empty($project['aihr_score'])): ?>
                <div style="border-left: 3px solid #1d4ed8; padding-left: 10px; margin-bottom: 12px; font-size: 0.85rem;">
                    <strong style="color: #1d4ed8;">Research Director AIHR Evaluation:</strong>
                    <div>Rating / Score: <strong><?php echo h($project['aihr_score'] ?? 'N/A'); ?>/100</strong> (<?php echo h($project['aihr_status'] ?? 'AIHR Evaluated'); ?>)</div>
                    <?php if (!empty($project['grant_amount'])): ?>
                        <div>Recommended Grant: <strong>PHP <?php echo number_format((float) $project['grant_amount'], 2); ?></strong></div>
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

            <!-- PROGRESS REPORTS TAB / LIST -->
            <?php if ($progressReports): ?>
                <h3 style="margin-top: 16px; font-size: 1rem; color: #166534;"><i class="ri-task-line"></i> Submitted Milestones, Liquidation, & Outputs</h3>
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
            <h2 style="color: #b45309;"><i class="ri-shield-check-line"></i> College Research Coordinator Screening</h2>
            <p><strong><?php echo h($project['title']); ?></strong></p>
            <p style="font-size: 0.9rem; color: #4b5563;">Proponent: <?php echo h($project['fullname']); ?> (<?php echo h($project['department_name'] ?? 'College'); ?>)</p>

            <form method="post" style="display: grid; gap: 14px; margin-top: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $projId; ?>">
                
                <label class="field">
                    <span>Endorsement Remarks / Feedback (College-level screening findings):</span>
                    <textarea name="remarks" rows="4" placeholder="e.g. Proposal screened. Documentation is complete and aligned with the college research agenda." required></textarea>
                </label>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end;">
                    <button class="primary-btn" type="submit" name="action" value="coordinator_endorse" style="background: #15803d;">
                        <i class="ri-check-line"></i> Endorse to Research Director
                    </button>
                    <button class="ghost-btn" type="submit" name="action" value="coordinator_return" style="color: #c2410c; border-color: #fed7aa;">
                        <i class="ri-arrow-go-back-line"></i> Return for Revision
                    </button>
                    <button class="ghost-btn danger-btn" type="submit" name="action" value="decline" onclick="return confirm('Are you sure you want to decline this proposal?');">
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
            <h2 style="color: #1d4ed8;"><i class="ri-award-line"></i> Research Director AIHR & Policy Review</h2>
            <p><strong><?php echo h($project['title']); ?></strong></p>
            <div style="background: #eff6ff; padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px;">
                <strong>Coordinator Endorsement:</strong> <?php echo h($project['coordinator_remarks'] ?? 'Endorsed for review'); ?>
            </div>

            <form method="post" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $projId; ?>">

                <div class="form-grid">
                    <label class="field">
                        <span>AIHR Evaluation Status:</span>
                        <select name="aihr_status">
                            <option value="Passed AIHR">Passed AIHR</option>
                            <option value="Passed with Conditions">Passed with Conditions</option>
                            <option value="Under AIHR Panel Review">Under AIHR Panel Review</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>AIHR Score (Rating out of 100):</span>
                        <input type="number" step="0.01" min="0" max="100" name="aihr_score" placeholder="e.g. 88.50" required>
                    </label>
                </div>

                <div class="form-grid">
                    <label class="field">
                        <span>Recommended Institutional Grant (PHP):</span>
                        <input type="number" step="0.01" min="0" name="grant_amount" placeholder="e.g. 50000.00" value="50000.00">
                    </label>
                    <label class="field">
                        <span>National Agenda Alignment:</span>
                        <input type="text" name="agenda_alignment" value="CHED NHERA / DOST HNRDA / SDG Goals">
                    </label>
                </div>

                <label class="field">
                    <span>Director Evaluation & Recommendation Remarks:</span>
                    <textarea name="remarks" rows="3" placeholder="Evaluation and recommendations regarding methodology, budget allocation, and national agenda alignment..." required></textarea>
                </label>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; margin-top: 8px;">
                    <button class="primary-btn" type="submit" name="action" value="director_endorse" style="background: #1d4ed8;">
                        <i class="ri-send-plane-line"></i> Endorse to VP / Executive
                    </button>
                    <button class="ghost-btn" type="submit" name="action" value="director_return" style="color: #c2410c; border-color: #fed7aa;">
                        <i class="ri-arrow-go-back-line"></i> Return for Revision
                    </button>
                    <button class="ghost-btn danger-btn" type="submit" name="action" value="decline" onclick="return confirm('Decline this proposal?');">
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
            <h2 style="color: #7e22ce;"><i class="ri-verified-badge-line"></i> OVPRE Executive Final Approval</h2>
            <p><strong><?php echo h($project['title']); ?></strong></p>

            <div style="background: #faf5ff; padding: 12px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px; border: 1px solid #e9d5ff;">
                <div><strong>1. Coordinator Endorsement:</strong> <?php echo h($project['coordinator_remarks'] ?? 'Screened'); ?></div>
                <div style="margin-top: 4px;"><strong>2. Director AIHR Evaluation:</strong> Score <?php echo h($project['aihr_score'] ?? 'N/A'); ?>/100 (<?php echo h($project['aihr_status'] ?? ''); ?>) | Rec. Grant: PHP <?php echo number_format((float) ($project['grant_amount'] ?? 0), 2); ?></div>
                <div style="margin-top: 4px;"><?php echo h($project['director_remarks'] ?? ''); ?></div>
            </div>

            <form method="post" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $projId; ?>">

                <label class="field">
                    <span>Executive Approval Resolution & Directives (Board/Grant Notice):</span>
                    <textarea name="remarks" rows="4" placeholder="Official approval resolution, fund allocation directives, and project commencement guidelines..." required>Approved for implementation under the Institutional Research Program. Grant funding released as recommended.</textarea>
                </label>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; margin-top: 8px;">
                    <button class="primary-btn" type="submit" name="action" value="executive_approve" style="background: #16a34a;">
                        <i class="ri-checkbox-circle-line"></i> Grant Final Approval
                    </button>
                    <button class="ghost-btn danger-btn" type="submit" name="action" value="decline" onclick="return confirm('Disapprove this proposal?');">
                        <i class="ri-close-line"></i> Disapprove
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. FACULTY RESEARCHER PROGRESS / LIQUIDATION / OUTPUT SUBMISSION MODAL -->
    <?php if ($isResearcher && $project['status'] === 'Approved'): ?>
    <div class="modal" id="progress-report-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 600px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2 style="color: #15803d;"><i class="ri-file-chart-line"></i> Submit Milestone, Liquidation, or Dissemination Output</h2>
            <p><strong><?php echo h($project['title']); ?></strong></p>

            <form method="post" enctype="multipart/form-data" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="submit_progress_report">
                <input type="hidden" name="id" value="<?php echo $projId; ?>">
                <input type="hidden" name="category_id" value="<?php echo (int) ($project['category_id'] ?? 4); ?>">

                <label class="field">
                    <span>Report / Output Type:</span>
                    <select name="report_type" required>
                        <option value="Milestone Report">1. Implementation & Milestone Progress Report</option>
                        <option value="Liquidation Report">2. Financial Liquidation Report (Receipts & Accounting)</option>
                        <option value="Dissemination & Output">3. Dissemination & Output (Published Journal, Conference, IP)</option>
                    </select>
                </label>

                <label class="field">
                    <span>Report / Output Title:</span>
                    <input name="title" placeholder="e.g. First Quarter Milestone / Liquidation Batch 1 / Scopus Publication" required>
                </label>

                <label class="field">
                    <span>Description / Summary of Accomplishments:</span>
                    <textarea name="description" rows="3" placeholder="Describe completed milestones, financial expenditures, or publications/patents achieved..." required></textarea>
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
    <?php if ($isResearcher && $project['status'] === 'Needs Revision'): ?>
    <div class="modal" id="resubmit-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 600px;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <h2 style="color: #c2410c;"><i class="ri-edit-line"></i> Revise and Resubmit Proposal</h2>
            <div style="background: #fff7ed; padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 12px; border: 1px solid #ffedd5;">
                <strong>Feedback / Remarks:</strong> <?php echo nl2br(h($project['status_remarks'] ?? 'Needs revision')); ?>
            </div>

            <form method="post" enctype="multipart/form-data" style="display: grid; gap: 12px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="resubmit_proposal">
                <input type="hidden" name="id" value="<?php echo $projId; ?>">
                <input type="hidden" name="category_id" value="<?php echo (int) ($project['category_id'] ?? 1); ?>">

                <label class="field"><span>Research Title</span><input name="title" value="<?php echo h($project['title']); ?>" required></label>
                <label class="field"><span>Abstract</span><textarea name="abstract" rows="4" required><?php echo h($project['abstract']); ?></textarea></label>
                <label class="field"><span>Revision Notes</span><textarea name="revision_notes" rows="2" placeholder="Summary of changes made based on reviewer feedback..." required></textarea></label>
                <label class="field"><span>Updated Proposal Document (Optional if re-uploading file)</span><input type="file" name="supporting_document" accept=".pdf,.doc,.docx,.zip"></label>

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
