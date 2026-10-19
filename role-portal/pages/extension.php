<?php
if (!portal_can('extension')) {
    http_response_code(403);
    exit('Extension module is not available for this role.');
}
$user = portal_require_user();
verify_csrf();
$notice = flash();
ensure_extension_proposal_columns();

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

            // Fields from Documentation per Extension Activity / Faculty Extensionist Output
            $academicYear = trim((string) ($_POST['academic_year'] ?? '2026-2027'));
            $needsAssessment = trim((string) ($_POST['needs_assessment'] ?? ''));
            $moaMou = trim((string) ($_POST['moa_mou'] ?? ''));
            $implementationDetails = trim((string) ($_POST['implementation_details'] ?? ''));
            $outputs = trim((string) ($_POST['outputs'] ?? ''));
            $outcome = trim((string) ($_POST['outcome'] ?? ''));
            $impactAssessment = trim((string) ($_POST['impact_assessment'] ?? ''));
            $sustainability = trim((string) ($_POST['sustainability'] ?? ''));
            $accomplishmentReport = trim((string) ($_POST['accomplishment_report'] ?? ''));
            $evidenceChecklist = !empty($_POST['evidence_checklist']) && is_array($_POST['evidence_checklist'])
                ? implode(', ', array_map('trim', $_POST['evidence_checklist']))
                : trim((string) ($_POST['evidence_checklist'] ?? ''));

            if ($title === '' || $partner === '') {
                throw new InvalidArgumentException('Name of Activity and Community/Beneficiary are required.');
            }

            db()->prepare('INSERT INTO extension_projects (project_title, coordinator, community_partner, location, target_beneficiaries, budget, start_date, end_date, progress_status, status, workflow_stage, document_id, submitted_by, academic_year, needs_assessment, moa_mou, implementation_details, outputs, outcome, impact_assessment, sustainability, evidence_checklist, accomplishment_report, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Planning", "Pending Coordinator Review", "Proposal", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$title, $coordinatorName, $partner, $location, $beneficiaries, $budget, $startDate, $endDate, $documentId, (int) $user['id'], $academicYear, $needsAssessment, $moaMou, $implementationDetails, $outputs, $outcome, $impactAssessment, $sustainability, $evidenceChecklist, $accomplishmentReport]);
            $projectId = (int) db()->lastInsertId();

            log_review('extension', $projectId, (int) $user['id'], 'Stage 1: Proposal Submitted', 'Submitted extension project proposal for College Coordinator initial screening.');
            log_action((int) $user['id'], 'Submitted extension project: ' . $title);

            // Notify college extension coordinators
            $coordStmt = db()->prepare('SELECT id FROM users WHERE department_id = ? AND role = "Extension Coordinator" AND status = "Active"');
            $coordStmt->execute([(int) ($user['department_id'] ?? 0)]);
            foreach ($coordStmt->fetchAll(PDO::FETCH_COLUMN) as $coordId) {
                notify_user((int) $coordId, 'New extension proposal submitted for your college screening: ' . $title, 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension project proposal & activity documentation submitted successfully! Routed to College Extension Coordinator.');
            redirect_to('extension.php');

        } elseif ($action === 'resubmit_proposal') {
            $projectId = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['project_title'] ?? ''));
            $partner = trim((string) ($_POST['community_partner'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $beneficiaries = trim((string) ($_POST['target_beneficiaries'] ?? ''));
            $budget = (float) ($_POST['budget'] ?? 0);
            $startDate = $_POST['start_date'] ?? date('Y-m-d');
            $endDate = $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'));
            $revisionNotes = trim((string) ($_POST['revision_notes'] ?? ''));

            $academicYear = trim((string) ($_POST['academic_year'] ?? '2026-2027'));
            $needsAssessment = trim((string) ($_POST['needs_assessment'] ?? ''));
            $moaMou = trim((string) ($_POST['moa_mou'] ?? ''));
            $implementationDetails = trim((string) ($_POST['implementation_details'] ?? ''));
            $outputs = trim((string) ($_POST['outputs'] ?? ''));
            $outcome = trim((string) ($_POST['outcome'] ?? ''));
            $impactAssessment = trim((string) ($_POST['impact_assessment'] ?? ''));
            $sustainability = trim((string) ($_POST['sustainability'] ?? ''));
            $accomplishmentReport = trim((string) ($_POST['accomplishment_report'] ?? ''));
            $evidenceChecklist = !empty($_POST['evidence_checklist']) && is_array($_POST['evidence_checklist'])
                ? implode(', ', array_map('trim', $_POST['evidence_checklist']))
                : trim((string) ($_POST['evidence_checklist'] ?? ''));

            $existingStmt = db()->prepare('SELECT * FROM extension_projects WHERE id = ? AND submitted_by = ?');
            $existingStmt->execute([$projectId, (int) $user['id']]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                throw new RuntimeException('Extension project not found or access denied.');
            }

            $documentId = $existing['document_id'];
            if (!empty($_FILES['required_document']['name'])) {
                if (empty($_POST['document_title'])) {
                    $_POST['document_title'] = 'Revised Extension Proposal - ' . $title;
                }
                if (empty($_POST['document_description'])) {
                    $_POST['document_description'] = 'Updated proposal and supporting documents for extension project.';
                }
                if (empty($_POST['category_id'])) {
                    $_POST['category_id'] = 3;
                }
                $newDocId = portal_save_document($_POST, $_FILES['required_document'], $user);
                if ($newDocId) {
                    $documentId = $newDocId;
                }
            }

            db()->prepare('UPDATE extension_projects SET project_title = ?, community_partner = ?, location = ?, target_beneficiaries = ?, budget = ?, start_date = ?, end_date = ?, document_id = ?, academic_year = ?, needs_assessment = ?, moa_mou = ?, implementation_details = ?, outputs = ?, outcome = ?, impact_assessment = ?, sustainability = ?, evidence_checklist = ?, accomplishment_report = ?, status = "Pending Coordinator Review", status_remarks = ? WHERE id = ?')
                ->execute([$title, $partner, $location, $beneficiaries, $budget, $startDate, $endDate, $documentId, $academicYear, $needsAssessment, $moaMou, $implementationDetails, $outputs, $outcome, $impactAssessment, $sustainability, $evidenceChecklist, $accomplishmentReport, 'Resubmitted with revisions: ' . $revisionNotes, $projectId]);

            log_review('extension', $projectId, (int) $user['id'], 'Proposal Resubmitted', $revisionNotes);
            log_action((int) $user['id'], 'Resubmitted extension proposal #' . $projectId);

            // Notify college extension coordinator
            $coordStmt = db()->prepare('SELECT id FROM users WHERE department_id = ? AND role = "Extension Coordinator" AND status = "Active"');
            $coordStmt->execute([(int) ($user['department_id'] ?? 0)]);
            foreach ($coordStmt->fetchAll(PDO::FETCH_COLUMN) as $coordId) {
                notify_user((int) $coordId, 'Extension project proposal was revised and resubmitted by proponent: ' . $title, 'extension.php#project-' . $projectId);
            }

            flash('success', 'Extension proposal and revised files resubmitted successfully for coordinator review.');
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

<!-- FACULTY EXTENSIONIST SUBMIT BUTTON -->
<?php if ($isExtensionist || $isExecutive): ?>
<section class="panel proposal-launcher" style="margin-bottom: 20px;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-add-box-line"></i> Documentation per Extension Activity / Proposal</h2>
            <span>Dalubhasaan ng Lungsod ng San Pablo &bull; Office of the Director for Extension (Stage 1: Coordinator Screening)</span>
        </div>
        <button class="primary-btn" type="button" data-open-modal="extensionProposalModal"><i class="ri-add-line"></i> New Extension Activity / Proposal</button>
    </div>
</section>

<!-- MODAL: SUBMIT EXTENSION PROPOSAL / DOCUMENTATION PER EXTENSION ACTIVITY -->
<div class="modal" id="extensionProposalModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <section class="modal-card proposal-modal-card" role="dialog" aria-modal="true" aria-labelledby="extensionProposalModalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close proposal form"><i class="ri-close-line"></i></button>

        <!-- INSTITUTIONAL HEADER (IMAGE 1 & 2) -->
        <div style="border-bottom: 2px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 16px;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div style="font-size: 0.72rem; font-weight: 800; letter-spacing: 0.06em; color: #166534; text-transform: uppercase;">
                        Dalubhasaan ng Lungsod ng San Pablo &bull; Office of the Director for Extension
                    </div>
                    <h2 id="extensionProposalModalTitle" style="font-size: 1.35rem; color: #0f231c; margin: 4px 0 2px;">
                        Documentation per Extension Activity
                    </h2>
                    <div style="font-size: 0.82rem; color: #4b5563; font-weight: 500;">
                        Faculty Extensionist Output & Master Monitoring Matrix
                    </div>
                </div>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 14px; border-radius: 8px; text-align: right;">
                    <span style="font-size: 0.7rem; color: #166534; font-weight: 700; display: block; text-transform: uppercase;">Academic Year</span>
                    <strong style="color: #15803d; font-size: 0.95rem;">2026-2027</strong>
                </div>
            </div>

            <!-- RECOMMENDED EXTENSION MONITORING FLOW (IMAGE 3) -->
            <div style="margin-top: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
                <div style="font-size: 0.7rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.04em;">
                    <i class="ri-flow-chart"></i> Recommended Extension Monitoring Flow:
                </div>
                <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap; font-size: 0.72rem; color: #334155;">
                    <span class="flow-pill">Needs Assessment</span> <i class="ri-arrow-right-s-line" style="color: #94a3b8;"></i>
                    <span class="flow-pill flow-active">Extension Proposal</span> <i class="ri-arrow-right-s-line" style="color: #94a3b8;"></i>
                    <span class="flow-pill">MOA / MOU</span> <i class="ri-arrow-right-s-line" style="color: #94a3b8;"></i>
                    <span class="flow-pill">Implementation</span> <i class="ri-arrow-right-s-line" style="color: #94a3b8;"></i>
                    <span class="flow-pill">Output</span> <i class="ri-arrow-right-s-line" style="color: #94a3b8;"></i>
                    <span class="flow-pill">Outcome</span> <i class="ri-arrow-right-s-line" style="color: #94a3b8;"></i>
                    <span class="flow-pill">Impact</span> <i class="ri-arrow-right-s-line" style="color: #94a3b8;"></i>
                    <span class="flow-pill">Sustainability</span>
                </div>
            </div>
        </div>

        <form class="portal-form" method="post" enctype="multipart/form-data" style="display: grid; gap: 16px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create_proposal">
            <input type="hidden" name="category_id" value="3">
            <input type="hidden" name="document_title" value="Extension Proposal & Activity Documentation">
            <input type="hidden" name="document_description" value="Supporting documentation for community extension activity.">

            <!-- 1. ACTIVITY & EXTENSIONIST PROFILE -->
            <div class="proposal-section-box">
                <div class="proposal-section-title">
                    <i class="ri-user-star-line"></i> 1. Activity & Extensionist Profile
                </div>
                <div class="form-grid">
                    <label class="field" style="grid-column: span 2;">
                        <span>Name of Activity <span style="color:#dc2626;">*</span></span>
                        <input name="project_title" placeholder="e.g. Skills Enhancement Training in Food and Beverage Services for Community Beneficiaries" required>
                    </label>

                    <label class="field">
                        <span>Extensionist Name (Colleges or Offices) <span style="color:#dc2626;">*</span></span>
                        <input name="coordinator" value="<?php echo h($user['fullname']); ?>" required>
                    </label>

                    <label class="field">
                        <span>College / Office</span>
                        <input name="college_office" value="<?php echo h($user['department_name'] ?? 'College'); ?>" readonly style="background: #f9fafb; color: #4b5563;">
                    </label>

                    <label class="field">
                        <span>Academic Year <span style="color:#dc2626;">*</span></span>
                        <input name="academic_year" value="2026-2027" placeholder="e.g. 2026-2027" required>
                    </label>

                    <label class="field">
                        <span>Proposed Budget (PHP)</span>
                        <input type="number" step="0.01" min="0" name="budget" placeholder="e.g. 25000.00" value="0.00">
                    </label>

                    <!-- START DATE & END DATE (EXPLICITLY PRESERVED AS REQUESTED) -->
                    <label class="field">
                        <span>Start Date <span style="color:#dc2626;">*</span></span>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </label>

                    <label class="field">
                        <span>End Date <span style="color:#dc2626;">*</span></span>
                        <input type="date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>" required>
                    </label>
                </div>
            </div>

            <!-- 2. COMMUNITY / BENEFICIARY -->
            <div class="proposal-section-box">
                <div class="proposal-section-title">
                    <i class="ri-community-line"></i> 2. Community & Beneficiary
                </div>
                <div class="form-grid">
                    <label class="field" style="grid-column: span 2;">
                        <span>Community / Beneficiary (Barangay, school, LGU, organization, or community served) <span style="color:#dc2626;">*</span></span>
                        <span class="proposal-hint">Identify the specific partner barangay, school, LGU, organization, or sector served.</span>
                        <input name="community_partner" placeholder="e.g. Barangay San Jose, San Pablo City / Women's Association" required>
                    </label>

                    <label class="field">
                        <span>Target Location / Venue</span>
                        <span class="proposal-hint">Venue of the training or activity</span>
                        <input name="location" placeholder="e.g. San Pablo City, Laguna">
                    </label>

                    <label class="field">
                        <span>Target Beneficiaries Count & Description</span>
                        <span class="proposal-hint">Number and description of participants</span>
                        <input name="target_beneficiaries" placeholder="e.g. 20 up community beneficiaries / solo parents">
                    </label>
                </div>
            </div>

            <!-- 3. COMMUNITY NEEDS ASSESSMENT & MOA/MOU -->
            <div class="proposal-section-box">
                <div class="proposal-section-title">
                    <i class="ri-survey-line"></i> 3. Needs Assessment & Partnership Agreement
                </div>
                <div style="display: grid; gap: 14px;">
                    <label class="field">
                        <span>Community Needs Assessment</span>
                        <span class="proposal-hint">Survey, interview, consultation, focus group discussion, or community profile</span>
                        <textarea name="needs_assessment" rows="3" placeholder="e.g. A needs assessment was conducted through consultation with community representatives and prospective beneficiaries. The assessment identified the need for livelihood-oriented hospitality skills training."></textarea>
                    </label>

                    <label class="field">
                        <span>MOA / MOU (Signed partnership agreement, if applicable)</span>
                        <span class="proposal-hint">Signed agreement details and date</span>
                        <input name="moa_mou" placeholder="e.g. MOA with Barangay San Jose dated October 15, 2026">
                    </label>
                </div>
            </div>

            <!-- 4. IMPLEMENTATION, OUTPUTS & OUTCOMES -->
            <div class="proposal-section-box">
                <div class="proposal-section-title">
                    <i class="ri-calendar-check-line"></i> 4. Extension Implementation, Outputs & Outcomes
                </div>
                <div style="display: grid; gap: 14px;">
                    <label class="field">
                        <span>Extension Implementation</span>
                        <span class="proposal-hint">Program / activity conducted, schedule, venue, participants, practical demonstrations</span>
                        <textarea name="implementation_details" rows="3" placeholder="e.g. 2-day skills enhancement training conducted; practical demonstration and hands-on activities completed."></textarea>
                    </label>

                    <label class="field">
                        <span>Outputs</span>
                        <span class="proposal-hint">Number of beneficiaries trained, materials distributed, services delivered, pre-test and post-test administered</span>
                        <textarea name="outputs" rows="3" placeholder="e.g. 20 up community beneficiaries trained; 2-day skills enhancement training conducted; training materials provided; pre-test and post-test administered."></textarea>
                    </label>

                    <label class="field">
                        <span>Outcome</span>
                        <span class="proposal-hint">Changes in knowledge, skills, practices, behavior, or capacity</span>
                        <textarea name="outcome" rows="3" placeholder="e.g. Participants demonstrated improved knowledge and practical skills in basic food and beverage service."></textarea>
                    </label>
                </div>
            </div>

            <!-- 5. IMPACT, SUSTAINABILITY & ACCOMPLISHMENT -->
            <div class="proposal-section-box">
                <div class="proposal-section-title">
                    <i class="ri-line-chart-line"></i> 5. Impact, Sustainability & Accomplishment
                </div>
                <div style="display: grid; gap: 14px;">
                    <label class="field">
                        <span>Impact Assessment</span>
                        <span class="proposal-hint">Evidence of longer-term benefit to the community (livelihood, employment, community development)</span>
                        <textarea name="impact_assessment" rows="3" placeholder="e.g. A follow-up assessment was conducted after the training to determine the application of acquired skills. Participants reported applying the skills in household livelihood activities and/or employment preparation."></textarea>
                    </label>

                    <label class="field">
                        <span>Sustainability</span>
                        <span class="proposal-hint">Follow-up activities, continuing partnership, community ownership</span>
                        <textarea name="sustainability" rows="2" placeholder="e.g. Follow-up monitoring visits, continuing partnership with the barangay council, and local community ownership."></textarea>
                    </label>

                    <label class="field">
                        <span>Accomplishment Report</span>
                        <span class="proposal-hint">Narrative and quantitative accomplishment summary</span>
                        <textarea name="accomplishment_report" rows="3" placeholder="e.g. Comprehensive accomplishment narrative highlighting project milestones, challenges addressed, and verified participant turnout."></textarea>
                    </label>
                </div>
            </div>

            <!-- 6. EVIDENCE & DOCUMENTATION CHECKLIST (IMAGE 2 & 3) -->
            <div class="proposal-section-box">
                <div class="proposal-section-title">
                    <i class="ri-checkbox-multiple-line"></i> 6. Evidence & Documentation Checklist
                </div>
                <p style="font-size: 0.8rem; color: #4b5563; margin: 0 0 10px;">
                    Check the documents and evidence attached in this submission (based on the Office of the Director for Extension monitoring matrix):
                </p>

                <div class="evidence-checklist-grid">
                    <label class="evidence-checkbox-label">
                        <input type="checkbox" name="evidence_checklist[]" value="Needs Assessment" checked>
                        <span>Needs Assessment</span>
                    </label>
                    <label class="evidence-checkbox-label">
                        <input type="checkbox" name="evidence_checklist[]" value="MOA/MOU">
                        <span>MOA / MOU</span>
                    </label>
                    <label class="evidence-checkbox-label">
                        <input type="checkbox" name="evidence_checklist[]" value="Attendance Sheet">
                        <span>Attendance Sheet</span>
                    </label>
                    <label class="evidence-checkbox-label">
                        <input type="checkbox" name="evidence_checklist[]" value="Training Design">
                        <span>Training Design</span>
                    </label>
                    <label class="evidence-checkbox-label">
                        <input type="checkbox" name="evidence_checklist[]" value="Photos/Videos">
                        <span>Photos / Videos</span>
                    </label>
                    <label class="evidence-checkbox-label">
                        <input type="checkbox" name="evidence_checklist[]" value="Impact Assessment">
                        <span>Impact Assessment</span>
                    </label>
                    <label class="evidence-checkbox-label">
                        <input type="checkbox" name="evidence_checklist[]" value="Accomplishment Report">
                        <span>Accomplishment Report</span>
                    </label>
                </div>

                <label class="field">
                    <span>Upload Proposal Document / Documentation Package (PDF, DOCX, ZIP up to 15 MB) <span style="color:#dc2626;">*</span></span>
                    <span class="proposal-hint">Upload your proposal document, signed MOA, training design, attendance sheets, or compilation package.</span>
                    <input type="file" name="required_document" accept=".pdf,.doc,.docx,.zip,.rar" required>
                </label>
            </div>

            <!-- ACTIONS -->
            <div class="proposal-modal-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:6px;">
                <button class="primary-btn" type="submit"><i class="ri-send-plane-line"></i> Submit Extension Proposal / Output</button>
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
                                <div class="file-actions" style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
                                    <a class="ghost-btn mini-btn" href="<?php echo h($attachmentUrl); ?>" target="_blank" rel="noopener" title="Download Document">
                                        <i class="ri-download-line"></i> File
                                    </a>
                                    <?php if ($isExtensionist && $pStatus === 'Needs Revision'): ?>
                                        <button class="ghost-btn mini-btn" type="button" data-open-modal="resubmit-<?php echo (int) $project['id']; ?>" title="Edit / Replace Submitted File" style="color: #c2410c; border: 1px solid #fed7aa; background: #fff7ed; width: auto; padding: 0 8px; font-size: 0.75rem;">
                                            <i class="ri-edit-line"></i> Edit File
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="muted">No file</span>
                                <?php if ($isExtensionist && $pStatus === 'Needs Revision'): ?>
                                    <button class="ghost-btn mini-btn" type="button" data-open-modal="resubmit-<?php echo (int) $project['id']; ?>" title="Upload Proposal File" style="color: #c2410c; border: 1px solid #fed7aa; background: #fff7ed; width: auto; padding: 0 8px; font-size: 0.75rem; margin-top: 3px;">
                                        <i class="ri-upload-cloud-line"></i> Attach File
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isExtensionist && $pStatus === 'Needs Revision'): ?>
                                <button class="primary-btn mini-btn" type="button" data-open-modal="resubmit-<?php echo (int) $project['id']; ?>" style="background: #c2410c; color: #fff; width: auto; padding: 0 10px; font-size: 0.8rem; margin-bottom: 6px; display: inline-flex; align-items: center; gap: 4px;" title="Edit proposal and update file">
                                    <i class="ri-edit-box-line"></i> Edit & Resubmit
                                </button>
                            <?php endif; ?>
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
                                            <i class="ri-edit-box-line"></i><span>Edit Proposal & File</span>
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
        <div class="modal-card" role="dialog" aria-modal="true" style="max-width: 780px; max-height: 90vh; overflow-y: auto;">
            <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
            <div style="font-size: 0.72rem; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.05em;">
                Documentation per Extension Activity &bull; Academic Year <?php echo h($project['academic_year'] ?? '2026-2027'); ?>
            </div>
            <h2 style="margin: 4px 0 8px;"><?php echo h($project['project_title']); ?></h2>
            <div style="margin-bottom: 14px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span class="status-pill status-<?php echo h(statusClass($project['status'] ?? 'Approved')); ?>"><?php echo h($project['status'] ?? 'Approved'); ?></span>
                <span style="color: #6b7280; font-size: 0.85rem;"><i class="ri-community-line"></i> Partner: <strong><?php echo h($project['community_partner']); ?></strong></span>
                <span style="color: #6b7280; font-size: 0.85rem;"><i class="ri-user-line"></i> Lead: <strong><?php echo h($project['coordinator'] ?: $project['fullname']); ?></strong> (<?php echo h($project['department_name'] ?? 'College'); ?>)</span>
            </div>

            <!-- CORE METRICS BOX -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                <div><span style="color:#64748b; display:block; font-size:0.75rem;">Dates (Implementation)</span><strong><?php echo date('M d, Y', strtotime($project['start_date'])); ?> to <?php echo date('M d, Y', strtotime($project['end_date'])); ?></strong></div>
                <div><span style="color:#64748b; display:block; font-size:0.75rem;">Proposed Budget</span><strong>PHP <?php echo number_format((float) $project['budget'], 2); ?></strong></div>
                <div><span style="color:#64748b; display:block; font-size:0.75rem;">Location / Venue</span><strong><?php echo h($project['location'] ?: 'Not specified'); ?></strong></div>
                <div><span style="color:#64748b; display:block; font-size:0.75rem;">Target Beneficiaries</span><strong><?php echo h($project['target_beneficiaries'] ?: 'Not specified'); ?></strong></div>
            </div>

            <!-- EXTENSION MATRIX ACCREDITATION DOCUMENTATION (FROM IMAGE 1 & 2) -->
            <?php if (!empty($project['needs_assessment'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #0284c7; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #0369a1;"><i class="ri-survey-line"></i> Community Needs Assessment:</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo nl2br(h($project['needs_assessment'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['moa_mou'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #0d9488; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #0f766e;"><i class="ri-file-shield-line"></i> MOA / MOU (Partnership Agreement):</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo h($project['moa_mou']); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['implementation_details'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #16a34a; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #15803d;"><i class="ri-calendar-check-line"></i> Extension Implementation:</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo nl2br(h($project['implementation_details'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['outputs'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #10b981; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #047857;"><i class="ri-task-line"></i> Outputs:</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo nl2br(h($project['outputs'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['outcome'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #8b5cf6; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #6d28d9;"><i class="ri-trophy-line"></i> Outcome:</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo nl2br(h($project['outcome'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['impact_assessment'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #ec4899; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #be185d;"><i class="ri-line-chart-line"></i> Impact Assessment:</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo nl2br(h($project['impact_assessment'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['sustainability'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #f59e0b; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #b45309;"><i class="ri-recycle-line"></i> Sustainability:</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo nl2br(h($project['sustainability'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['accomplishment_report'])): ?>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-left: 4px solid #4f46e5; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 0.85rem;">
                    <strong style="color: #4338ca;"><i class="ri-article-line"></i> Accomplishment Report:</strong>
                    <div style="margin-top: 3px; color: #334155;"><?php echo nl2br(h($project['accomplishment_report'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['evidence_checklist'])): ?>
                <div style="background: #fdfdfd; border: 1px solid #e5e7eb; padding: 10px 14px; border-radius: 6px; margin-bottom: 14px; font-size: 0.85rem;">
                    <strong style="color: #374151;"><i class="ri-checkbox-circle-line"></i> Evidence Checklist Submitted:</strong>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px;">
                        <?php foreach (explode(',', (string) $project['evidence_checklist']) as $evItem): ?>
                            <?php $evItem = trim($evItem); if ($evItem === '') continue; ?>
                            <span style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                <i class="ri-check-line"></i> <?php echo h($evItem); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

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
    <?php
    $resubmitFileUrl = !empty($project['attachment_filename'])
        ? record_access_url($project['attachment_filename'], '../', (int) $project['document_id'])
        : '';
    $evItems = array_map('trim', explode(',', (string) ($project['evidence_checklist'] ?? '')));
    ?>
    <div class="modal" id="resubmit-<?php echo $projId; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-card proposal-modal-card" role="dialog" aria-modal="true" aria-labelledby="resubmit-title-<?php echo $projId; ?>">
            <button class="modal-close" type="button" data-close-modal aria-label="Close modal"><i class="ri-close-line"></i></button>

            <!-- MODAL HEADER -->
            <div style="border-bottom: 2px solid #fed7aa; padding-bottom: 14px; margin-bottom: 16px;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div style="font-size: 0.72rem; font-weight: 800; letter-spacing: 0.06em; color: #c2410c; text-transform: uppercase;">
                            <i class="ri-alert-line"></i> Needs Revision &bull; Activity Documentation & File Update
                        </div>
                        <h2 id="resubmit-title-<?php echo $projId; ?>" style="font-size: 1.35rem; color: #9a3412; margin: 4px 0 2px;">
                            Edit Proposal & Replace Attached File
                        </h2>
                        <div style="font-size: 0.82rem; color: #4b5563;">
                            Update project details, modify evidence checklist, replace your documentation file, and submit for re-screening.
                        </div>
                    </div>
                    <div style="background: #fff7ed; border: 1px solid #fed7aa; padding: 6px 14px; border-radius: 8px; text-align: right;">
                        <span style="font-size: 0.7rem; color: #9a3412; font-weight: 700; display: block; text-transform: uppercase;">Academic Year</span>
                        <strong style="color: #c2410c; font-size: 0.95rem;"><?php echo h($project['academic_year'] ?? '2026-2027'); ?></strong>
                    </div>
                </div>

                <!-- REVIEWER FEEDBACK NOTICE -->
                <div style="margin-top: 12px; background: #fff7ed; border: 1px solid #ffedd5; border-left: 4px solid #ea580c; border-radius: 6px; padding: 12px 14px;">
                    <div style="font-weight: 700; color: #9a3412; font-size: 0.85rem; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        <i class="ri-chat-voice-line"></i> Reviewer's Feedback & Required Changes:
                    </div>
                    <div style="color: #7c2d12; font-size: 0.85rem; line-height: 1.5;">
                        <?php echo nl2br(h($project['status_remarks'] ?? 'Please update proposal details or provide revised documents.')); ?>
                    </div>
                </div>
            </div>

            <form class="portal-form" method="post" enctype="multipart/form-data" style="display: grid; gap: 16px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="resubmit_proposal">
                <input type="hidden" name="id" value="<?php echo $projId; ?>">
                <input type="hidden" name="category_id" value="<?php echo (int) ($project['category_id'] ?? 3); ?>">
                <input type="hidden" name="document_title" value="Revised Extension Proposal - <?php echo h($project['project_title']); ?>">
                <input type="hidden" name="document_description" value="Revised documentation package and proposal files.">

                <!-- CURRENTLY SUBMITTED FILE & FILE REPLACEMENT (PROMINENT AT TOP) -->
                <div class="proposal-section-box" style="border: 2px solid #fed7aa; background: #fffaf5;">
                    <div class="proposal-section-title" style="color: #9a3412; border-color: #fed7aa;">
                        <i class="ri-file-edit-line"></i> Proposal File & Documentation Package
                    </div>

                    <!-- Current File Display -->
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <div>
                            <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: #64748b; letter-spacing: 0.04em;">Currently Submitted File</div>
                            <?php if (!empty($project['attachment_filename'])): ?>
                                <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem; margin-top: 2px; word-break: break-all;">
                                    <i class="ri-file-text-fill" style="color: #ea580c;"></i> <?php echo h($project['attachment_filename']); ?>
                                </div>
                            <?php else: ?>
                                <div style="color: #dc2626; font-size: 0.85rem; font-weight: 600; margin-top: 2px;">
                                    <i class="ri-error-warning-line"></i> No file currently attached. Please upload your proposal document below.
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($resubmitFileUrl): ?>
                            <div>
                                <a href="<?php echo h($resubmitFileUrl); ?>" target="_blank" rel="noopener" class="ghost-btn mini-btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; font-size: 0.8rem; border-radius: 6px;" title="View or download current file">
                                    <i class="ri-download-2-line"></i> View / Download Current File
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- File Replacement Input -->
                    <label class="field" style="margin: 0;">
                        <span style="font-weight: 700; color: #9a3412;">
                            <i class="ri-upload-cloud-line"></i> Upload New / Revised Proposal Document (PDF, DOC, DOCX, XLS, XLSX, ZIP, RAR up to 15 MB)
                        </span>
                        <span class="proposal-hint">
                            Select a new file if you want to replace the current proposal file. If left blank, your existing file will be kept.
                        </span>
                        <input type="file" name="required_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" style="border: 2px dashed #fed7aa; background: #fff; padding: 10px; border-radius: 8px;">
                    </label>
                </div>

                <!-- 1. ACTIVITY & EXTENSIONIST PROFILE -->
                <div class="proposal-section-box">
                    <div class="proposal-section-title">
                        <i class="ri-user-star-line"></i> 1. Activity & Extensionist Profile
                    </div>
                    <div class="form-grid">
                        <label class="field" style="grid-column: span 2;">
                            <span>Name of Activity / Project Title <span style="color:#dc2626;">*</span></span>
                            <span class="proposal-hint">Complete institutional title of the extension project</span>
                            <input name="project_title" value="<?php echo h($project['project_title']); ?>" required>
                        </label>

                        <label class="field">
                            <span>Academic Year <span style="color:#dc2626;">*</span></span>
                            <input name="academic_year" value="<?php echo h($project['academic_year'] ?? '2026-2027'); ?>" required>
                        </label>

                        <label class="field">
                            <span>Proposed Budget (PHP) <span style="color:#dc2626;">*</span></span>
                            <input type="number" step="0.01" min="0" name="budget" value="<?php echo h($project['budget']); ?>" required>
                        </label>

                        <!-- START DATE & END DATE (EXPLICITLY PRESERVED AS REQUESTED) -->
                        <label class="field">
                            <span>Start Date <span style="color:#dc2626;">*</span></span>
                            <input type="date" name="start_date" value="<?php echo h($project['start_date']); ?>" required>
                        </label>

                        <label class="field">
                            <span>End Date <span style="color:#dc2626;">*</span></span>
                            <input type="date" name="end_date" value="<?php echo h($project['end_date']); ?>" required>
                        </label>
                    </div>
                </div>

                <!-- 2. COMMUNITY & BENEFICIARY -->
                <div class="proposal-section-box">
                    <div class="proposal-section-title">
                        <i class="ri-community-line"></i> 2. Community & Beneficiary
                    </div>
                    <div class="form-grid">
                        <label class="field" style="grid-column: span 2;">
                            <span>Community / Beneficiary <span style="color:#dc2626;">*</span></span>
                            <span class="proposal-hint">Identify the specific partner barangay, school, LGU, organization, or sector served.</span>
                            <input name="community_partner" value="<?php echo h($project['community_partner']); ?>" required>
                        </label>

                        <label class="field">
                            <span>Target Location / Venue</span>
                            <span class="proposal-hint">Venue of the training or activity</span>
                            <input name="location" value="<?php echo h($project['location'] ?? ''); ?>">
                        </label>

                        <label class="field">
                            <span>Target Beneficiaries Count & Description</span>
                            <span class="proposal-hint">e.g. 50 OSY, 30 Barangay Health Workers, etc.</span>
                            <input name="target_beneficiaries" value="<?php echo h($project['target_beneficiaries'] ?? ''); ?>">
                        </label>
                    </div>
                </div>

                <!-- 3. IMPLEMENTATION DETAILS -->
                <div class="proposal-section-box">
                    <div class="proposal-section-title">
                        <i class="ri-settings-4-line"></i> 3. Needs Assessment, MOA/MOU & Implementation
                    </div>
                    <div style="display: grid; gap: 12px;">
                        <label class="field">
                            <span>Community Needs Assessment</span>
                            <span class="proposal-hint">Context and justification based on community consultation</span>
                            <textarea name="needs_assessment" rows="2"><?php echo h($project['needs_assessment'] ?? ''); ?></textarea>
                        </label>

                        <label class="field">
                            <span>MOA / MOU Reference or Status</span>
                            <span class="proposal-hint">Memorandum of Agreement / Understanding with partner agency or LGU</span>
                            <input name="moa_mou" value="<?php echo h($project['moa_mou'] ?? ''); ?>">
                        </label>

                        <label class="field">
                            <span>Extension Implementation Plan / Methodology</span>
                            <span class="proposal-hint">Activity structure, schedule of sessions, modules, and teaching delivery</span>
                            <textarea name="implementation_details" rows="2"><?php echo h($project['implementation_details'] ?? ''); ?></textarea>
                        </label>
                    </div>
                </div>

                <!-- 4. OUTPUTS, OUTCOME & SUSTAINABILITY -->
                <div class="proposal-section-box">
                    <div class="proposal-section-title">
                        <i class="ri-line-chart-line"></i> 4. Outputs, Outcome, Impact & Sustainability
                    </div>
                    <div style="display: grid; gap: 12px;">
                        <div class="form-grid">
                            <label class="field">
                                <span>Outputs</span>
                                <span class="proposal-hint">Tangible deliverables and products</span>
                                <textarea name="outputs" rows="2"><?php echo h($project['outputs'] ?? ''); ?></textarea>
                            </label>

                            <label class="field">
                                <span>Outcome</span>
                                <span class="proposal-hint">Short-to-medium term changes or benefits</span>
                                <textarea name="outcome" rows="2"><?php echo h($project['outcome'] ?? ''); ?></textarea>
                            </label>
                        </div>

                        <div class="form-grid">
                            <label class="field">
                                <span>Impact Assessment</span>
                                <span class="proposal-hint">Longer-term community impact measurement</span>
                                <textarea name="impact_assessment" rows="2"><?php echo h($project['impact_assessment'] ?? ''); ?></textarea>
                            </label>

                            <label class="field">
                                <span>Sustainability</span>
                                <span class="proposal-hint">Follow-through mechanism or continuation</span>
                                <textarea name="sustainability" rows="2"><?php echo h($project['sustainability'] ?? ''); ?></textarea>
                            </label>
                        </div>

                        <label class="field">
                            <span>Accomplishment Report Summary</span>
                            <span class="proposal-hint">Narrative summary of progress or initial accomplishments</span>
                            <textarea name="accomplishment_report" rows="2"><?php echo h($project['accomplishment_report'] ?? ''); ?></textarea>
                        </label>
                    </div>
                </div>

                <!-- 5. EVIDENCE & DOCUMENTATION CHECKLIST -->
                <div class="proposal-section-box">
                    <div class="proposal-section-title">
                        <i class="ri-checkbox-multiple-line"></i> 5. Evidence & Documentation Checklist
                    </div>
                    <p style="font-size: 0.8rem; color: #4b5563; margin: 0 0 10px;">
                        Check the documents and evidence attached in this revision:
                    </p>

                    <div class="evidence-checklist-grid">
                        <label class="evidence-checkbox-label">
                            <input type="checkbox" name="evidence_checklist[]" value="Needs Assessment" <?php echo in_array('Needs Assessment', $evItems, true) ? 'checked' : ''; ?>>
                            <span>Needs Assessment</span>
                        </label>
                        <label class="evidence-checkbox-label">
                            <input type="checkbox" name="evidence_checklist[]" value="MOA/MOU" <?php echo in_array('MOA/MOU', $evItems, true) ? 'checked' : ''; ?>>
                            <span>MOA / MOU</span>
                        </label>
                        <label class="evidence-checkbox-label">
                            <input type="checkbox" name="evidence_checklist[]" value="Attendance Sheet" <?php echo in_array('Attendance Sheet', $evItems, true) ? 'checked' : ''; ?>>
                            <span>Attendance Sheet</span>
                        </label>
                        <label class="evidence-checkbox-label">
                            <input type="checkbox" name="evidence_checklist[]" value="Training Design" <?php echo in_array('Training Design', $evItems, true) ? 'checked' : ''; ?>>
                            <span>Training Design</span>
                        </label>
                        <label class="evidence-checkbox-label">
                            <input type="checkbox" name="evidence_checklist[]" value="Photos/Videos" <?php echo in_array('Photos/Videos', $evItems, true) ? 'checked' : ''; ?>>
                            <span>Photos / Videos</span>
                        </label>
                        <label class="evidence-checkbox-label">
                            <input type="checkbox" name="evidence_checklist[]" value="Impact Assessment" <?php echo in_array('Impact Assessment', $evItems, true) ? 'checked' : ''; ?>>
                            <span>Impact Assessment</span>
                        </label>
                        <label class="evidence-checkbox-label">
                            <input type="checkbox" name="evidence_checklist[]" value="Accomplishment Report" <?php echo in_array('Accomplishment Report', $evItems, true) ? 'checked' : ''; ?>>
                            <span>Accomplishment Report</span>
                        </label>
                    </div>
                </div>

                <!-- 6. REVISION NOTES (REQUIRED) -->
                <div class="proposal-section-box" style="border: 2px solid #fdba74; background: #fffaf0;">
                    <label class="field" style="margin: 0;">
                        <span style="font-weight: 700; color: #c2410c;">
                            <i class="ri-edit-circle-line"></i> Summary of Revisions Made <span style="color:#dc2626;">*</span>
                        </span>
                        <span class="proposal-hint">
                            Explain the revisions and improvements made in response to the reviewer's feedback.
                        </span>
                        <textarea name="revision_notes" rows="3" placeholder="e.g. Revised target beneficiaries from 20 to 50; updated training design and attached latest signed MOA with Barangay." required></textarea>
                    </label>
                </div>

                <!-- ACTIONS -->
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                    <button class="primary-btn" type="submit" style="background: #c2410c; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ri-send-plane-line"></i> Save & Resubmit Proposal
                    </button>
                    <button class="ghost-btn" type="button" data-close-modal>Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

<?php endforeach; ?>

<?php portal_footer(); ?>
