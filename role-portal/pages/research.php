<?php
if (!portal_can('research')) {
    http_response_code(403);
    exit('Research module is not available for this role.');
}
$user = portal_require_user();
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $documentId = portal_save_document($_POST, $_FILES['supporting_document'] ?? [], $user);
        db()->prepare('INSERT INTO research_projects (title, researchers, abstract, document_id, submitted_by, status, created_at) VALUES (?, ?, ?, ?, ?, "Pending", NOW())')
            ->execute([trim($_POST['title']), trim($_POST['researchers']), trim($_POST['abstract']), $documentId, $user['id']]);
        log_review('research', (int) db()->lastInsertId(), (int) $user['id'], 'Submitted', trim($_POST['abstract']));
        log_action((int) $user['id'], 'Submitted research proposal: ' . trim($_POST['title']));
        flash('success', 'Research proposal submitted. Please wait for approval.');
        redirect_to('research.php');
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}
$user = portal_header('Research Submission', 'research');
$projects = portal_research_projects();
?>
<?php portal_notice($notice); ?>
<section class="panel proposal-launcher">
    <div class="panel-heading">
        <div>
            <h2>Research Proposal</h2><span>Submit a proposal for Super Admin review.</span>
        </div><button class="primary-btn" type="button" data-open-modal="researchProposalModal"><i class="ri-add-line"></i> New Proposal</button>
    </div>
</section>
<div class="modal" id="researchProposalModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <section class="modal-card proposal-modal-card" role="dialog" aria-modal="true" aria-labelledby="researchProposalModalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close proposal form"><i class="ri-close-line"></i></button>
        <div class="panel-heading">
            <div>
                <h2 style="font-size: small;" id="researchProposalModalTitle">Submit Research Proposal</h2><span>Visible to Super Admin</span>
            </div>
        </div>
        <form class="portal-form" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <label class="field"><span>Proposal Title</span><input name="title" required></label>
                <label class="field"><span>Researchers</span><input name="researchers" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Document Category</span><select name="category_id"><?php foreach (categories() as $category): ?><option value="<?php echo (int) $category['id']; ?>"><?php echo h($category['category_name']); ?></option><?php endforeach; ?></select></label>
            </div>
            <label class="field"><span>Abstract</span><textarea name="abstract" rows="5" required></textarea></label>
            <label class="field"><span>Supporting Document</span><input type="file" name="supporting_document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"></label>
            <input type="hidden" name="document_title" value="Research Supporting Document">
            <input type="hidden" name="document_description" value="Research proposal supporting document">
            <div class="proposal-modal-actions"><button class="primary-btn" type="submit"><i class="ri-send-plane-line"></i> Submit Proposal</button><button class="ghost-btn" type="button" data-close-modal>Cancel</button></div>
        </form>
    </section>
</div>
<section class="panel">
    <div class="panel-heading">
        <h2>Research Submissions</h2><label class="table-search"><span class="sr-only">Search research submissions</span><i class="ri-search-line"></i><input type="search" data-table-search="researchSubmissionTable" placeholder="Search research submissions"></label>
    </div>
    <div class="table-scroll">
        <table class="data-table" id="researchSubmissionTable" data-paginate="10">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Researchers</th>
                    <th>Submitted By</th>
                    <th>Status</th>
                    <th>File</th>
                    <th>Review Feedback</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody><?php foreach ($projects as $project): ?><?php $remarks = trim((string) ($project['status_remarks'] ?? ''));
                                                            $feedback = $remarks !== '' ? $remarks : ($project['status'] === 'Declined' ? 'No rejection reason was provided.' : ($project['status'] === 'Approved' ? 'No recommendation provided.' : 'No review remarks yet.')); ?><tr>
                    <td><?php echo h($project['title']); ?></td>
                    <td><?php echo h($project['researchers']); ?></td>
                    <td><?php echo h($project['fullname']); ?></td>
                    <td><span class="status-pill status-<?php echo h(statusClass($project['status'])); ?>"><?php echo h($project['status'] === 'Declined' ? 'Rejected' : $project['status']); ?></span></td>
                    <td><?php if (!empty($project['attachment_filename'])): ?><div class="file-actions"><a class="ghost-btn mini-btn" href="<?php echo h('../uploads/records/' . $project['attachment_filename']); ?>" target="_blank" rel="noopener"><i class="ri-download-line"></i> File</a><button class="ghost-btn mini-btn" type="button" data-print-file="<?php echo h('../uploads/records/' . $project['attachment_filename']); ?>"><i class="ri-printer-line"></i> Print</button></div><?php else: ?><span class="muted">None</span><?php endif; ?></td>
                    <td><button class="ghost-btn action-btn" type="button" data-open-modal="research-feedback-<?php echo (int) $project['id']; ?>"><i class="ri-chat-3-line"></i><span>View Feedback</span></button></td>
                    <td><?php echo h(date('M d, Y', strtotime($project['created_at']))); ?></td>
                </tr><?php endforeach; ?><?php if (!$projects): ?><tr data-pagination-empty>
                    <td colspan="7">No research submissions yet.</td>
                </tr><?php endif; ?></tbody>
        </table>
        <p class="search-empty" data-search-empty="researchSubmissionTable" hidden>No matching research submissions.</p>
    </div>
</section>
<?php foreach ($projects as $project): ?><?php $remarks = trim((string) ($project['status_remarks'] ?? ''));
                                            $feedback = $remarks !== '' ? $remarks : ($project['status'] === 'Declined' ? 'No rejection reason was provided.' : ($project['status'] === 'Approved' ? 'No recommendation provided.' : 'No review remarks yet.')); ?><div class="modal" id="research-feedback-<?php echo (int) $project['id']; ?>" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="research-feedback-title-<?php echo (int) $project['id']; ?>"><button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
        <h2 id="research-feedback-title-<?php echo (int) $project['id']; ?>">Review Feedback</h2>
        <p><strong><?php echo h($project['title']); ?></strong></p>
        <p><span class="status-pill status-<?php echo h(statusClass($project['status'])); ?>"><?php echo h($project['status'] === 'Declined' ? 'Rejected' : $project['status']); ?></span></p><?php if ($project['status'] === 'Declined'): ?><p class="feedback-label"><strong>Rejection reason</strong></p><?php endif; ?><div class="feedback-content"><?php echo nl2br(h($feedback)); ?></div>
    </div>
</div><?php endforeach; ?>
<?php portal_footer(); ?>
