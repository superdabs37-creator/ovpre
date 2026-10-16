<?php
$user = portal_require_user();
verify_csrf();

$statusTitle = match (PORTAL_ROLE) {
    'Extension Director', 'Extension Coordinator', 'Faculty Extensionist' => 'Extension Submission Status',
    'Research Director', 'Research Coordinator', 'Researcher' => 'Research Submission Status',
    default => 'Submission Status',
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $isArchivedView = !empty($_POST['archived_view']);

    if ($action === 'archive') {
        db()->prepare('UPDATE research_projects SET archived_at = NOW() WHERE id = ?')->execute([$id]);
        log_review('research', $id, (int) $user['id'], 'Proposal Archived', 'Archived from status view.');
        log_action((int) $user['id'], 'Archived research proposal #' . $id);
        flash('success', 'Research submission archived.');
        redirect_to('status.php');
    } elseif ($action === 'restore') {
        db()->prepare('UPDATE research_projects SET archived_at = NULL WHERE id = ?')->execute([$id]);
        log_review('research', $id, (int) $user['id'], 'Proposal Restored', 'Restored from status view.');
        log_action((int) $user['id'], 'Restored research proposal #' . $id);
        flash('success', 'Research submission restored.');
        redirect_to('status.php?archived=1');
    } elseif ($action === 'bulk_archive') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $bulkAction = (string) ($_POST['bulk_action'] ?? 'archive');
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'restore') {
                db()->prepare("UPDATE research_projects SET archived_at = NULL WHERE id IN ($marks)")->execute($ids);
                foreach ($ids as $projId) {
                    log_review('research', $projId, (int) $user['id'], 'Bulk Restored', 'Restored from archive via bulk action.');
                    log_action((int) $user['id'], 'Bulk restored research proposal #' . $projId);
                }
                flash('success', count($ids) . ' submission(s) restored.');
                redirect_to('status.php?archived=1');
            } else {
                db()->prepare("UPDATE research_projects SET archived_at = NOW() WHERE id IN ($marks)")->execute($ids);
                foreach ($ids as $projId) {
                    log_review('research', $projId, (int) $user['id'], 'Bulk Archived', 'Archived via bulk action.');
                    log_action((int) $user['id'], 'Bulk archived research proposal #' . $projId);
                }
                flash('success', count($ids) . ' submission(s) archived.');
                redirect_to('status.php');
            }
        } else {
            flash('error', 'No submissions selected.');
            redirect_to('status.php' . ($isArchivedView ? '?archived=1' : ''));
        }
    }
}

$notice = flash();
$viewArchived = ($_GET['archived'] ?? '') === '1';
$user = portal_header($statusTitle, 'status');
portal_notice($notice);

if (in_array(PORTAL_ROLE, ['Research Director', 'Research Coordinator', 'Researcher'], true)) {
    $research = portal_research_projects($viewArchived);
    $counts = portal_research_counts();
    ?>
    <section class="panel">
        <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <div class="panel-heading-actions" style="display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-left:auto;">
                <div class="portal-table-tabs" style="margin-left:auto;">
                    <a class="portal-table-tab <?php echo !$viewArchived ? 'active' : ''; ?>" href="status.php">
                        <i class="ri-folder-open-line"></i>
                        <span>Active</span>
                        <span class="tab-count"><?php echo $counts['active']; ?></span>
                    </a>
                    <a class="portal-table-tab <?php echo $viewArchived ? 'active' : ''; ?>" href="status.php?archived=1">
                        <i class="ri-archive-line"></i>
                        <span>Archived</span>
                        <span class="tab-count"><?php echo $counts['archived']; ?></span>
                    </a>
                </div>
                <label class="table-search" style="margin-left:auto;">
                    <span class="sr-only">Search research submissions</span>
                    <i class="ri-search-line"></i>
                    <input type="search" data-table-search="researchStatusTable" placeholder="Search research submissions...">
                </label>
            </div>
        </div>

        <!-- Bulk Selection Toolbar for Research Status -->
        <div class="table-selection-toolbar" data-selection-toolbar="researchStatusTable" hidden>
            <span data-selection-count>0 selected</span>
            <form id="bulk-researchStatusTable" method="post" data-bulk-selection-form>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="bulk_archive">
                <?php if ($viewArchived): ?>
                    <input type="hidden" name="archived_view" value="1">
                    <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="restore" data-bulk-submit>
                        <i class="ri-arrow-go-back-line"></i><span>Restore Selected</span>
                    </button>
                <?php else: ?>
                    <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="archive" data-bulk-submit onclick="return confirm('Archive selected submissions?');">
                        <i class="ri-archive-line"></i><span>Archive Selected</span>
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-scroll">
            <table class="data-table" id="researchStatusTable" data-paginate="10">
                <thead>
                    <tr>
                        <th class="table-checkbox-col">
                            <label class="select-all-control">
                                <input form="bulk-researchStatusTable" type="checkbox" aria-label="Select all submissions" data-select-all="researchStatusTable">
                                <span>Select all</span>
                            </label>
                        </th>
                        <th>Submission</th>
                        <th>Submitted By</th>
                        <th>Status</th>
                        <th>File</th>
                        <th>Review Feedback</th>
                        <th>Date</th>
                        <th>Timeline</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($research as $row): ?>
                        <?php
                        $remarks = trim((string) ($row['status_remarks'] ?? ''));
                        $feedback = $remarks !== '' ? $remarks : ($row['status'] === 'Declined' ? 'No rejection reason was provided.' : ($row['status'] === 'Approved' ? 'No recommendation provided.' : 'No review remarks yet.'));
                        ?>
                        <tr>
                            <td class="table-checkbox-col">
                                <input form="bulk-researchStatusTable" type="checkbox" name="ids[]" aria-label="Select submission <?php echo (int) $row['id']; ?>" value="<?php echo (int) $row['id']; ?>" data-row-select="researchStatusTable">
                            </td>
                            <td><strong><?php echo h($row['title']); ?></strong></td>
                            <td><?php echo h($row['fullname']); ?></td>
                            <td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></span></td>
                            <td>
                                <?php if (!empty($row['attachment_filename'])): ?>
                                    <div class="file-actions">
                                        <a class="ghost-btn mini-btn" href="<?php echo h(record_access_url($row['attachment_filename'], '../', (int) $row['document_id'])); ?>" target="_blank" rel="noopener"><i class="ri-download-line"></i> File</a>
                                        <button class="ghost-btn mini-btn" type="button" data-print-file="<?php echo h(record_access_url($row['attachment_filename'], '../', (int) $row['document_id'])); ?>"><i class="ri-printer-line"></i> Print</button>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="ghost-btn action-btn" type="button" data-open-modal="research-status-feedback-<?php echo (int) $row['id']; ?>">
                                    <i class="ri-chat-3-line"></i><span>View Feedback</span>
                                </button>
                            </td>
                            <td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td>
                            <td>
                                <details>
                                    <summary>History</summary>
                                    <div class="timeline-list">
                                        <?php foreach (review_timeline('research', (int) $row['id']) as $event): ?>
                                            <div>
                                                <strong><?php echo h($event['action']); ?></strong>
                                                <p><?php echo h($event['remarks'] ?? ''); ?></p>
                                                <small><?php echo h(($event['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($event['created_at']))); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </td>
                            <td>
                                <?php if ($viewArchived): ?>
                                    <form method="post" style="margin:0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit"><i class="ri-arrow-go-back-line"></i> Restore</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Archive this research submission?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit" style="color:#b45309;"><i class="ri-archive-line"></i> Archive</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$research): ?>
                        <tr data-pagination-empty>
                            <td colspan="9" style="text-align:center; padding:20px; color:#6b7280;"><?php echo $viewArchived ? 'No archived research submissions.' : 'No research submissions yet.'; ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="search-empty" data-search-empty="researchStatusTable" hidden>No matching research submissions.</p>
        </div>
    </section>

    <?php foreach ($research as $row): ?>
        <?php
        $remarks = trim((string) ($row['status_remarks'] ?? ''));
        $feedback = $remarks !== '' ? $remarks : ($row['status'] === 'Declined' ? 'No rejection reason was provided.' : ($row['status'] === 'Approved' ? 'No recommendation provided.' : 'No review remarks yet.'));
        ?>
        <div class="modal" id="research-status-feedback-<?php echo (int) $row['id']; ?>" aria-hidden="true">
            <div class="modal-backdrop" data-close-modal></div>
            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="research-status-title-<?php echo (int) $row['id']; ?>">
                <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
                <h2 id="research-status-title-<?php echo (int) $row['id']; ?>">Research Review Feedback</h2>
                <p><strong><?php echo h($row['title']); ?></strong></p>
                <p><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></span></p>
                <?php if ($row['status'] === 'Declined'): ?><p><strong>Rejection reason</strong></p><?php endif; ?>
                <div class="feedback-content"><?php echo nl2br(h($feedback)); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php
    portal_footer();
    return;
}

if (in_array(PORTAL_ROLE, ['Extension Director', 'Extension Coordinator', 'Faculty Extensionist'], true)) {
    $extensions = portal_extension_projects();
    ?>
    <section class="panel">
        <div class="panel-heading">
            <h2>Extension Submission Status</h2>
            <label class="table-search">
                <span class="sr-only">Search extension submissions</span>
                <i class="ri-search-line"></i>
                <input type="search" data-table-search="extensionStatusTable" placeholder="Search extension submissions">
            </label>
        </div>
        <div class="table-scroll">
            <table class="data-table" id="extensionStatusTable" data-paginate="10">
                <thead><tr><th>Submission</th><th>Submitted By</th><th>Status</th><th>File</th><th>Review Feedback</th><th>Date</th><th>Timeline</th></tr></thead>
                <tbody>
                    <?php foreach ($extensions as $row): ?>
                        <?php
                        $statusVal = $row['status'] ?? $row['progress_status'] ?? 'Pending Coordinator Review';
                        $remarksArr = [];
                        if (!empty($row['coordinator_remarks'])) { $remarksArr[] = "Coordinator: " . $row['coordinator_remarks']; }
                        if (!empty($row['director_remarks'])) { $remarksArr[] = "Director: " . $row['director_remarks']; }
                        if (!empty($row['executive_remarks'])) { $remarksArr[] = "Executive: " . $row['executive_remarks']; }
                        if (!empty($row['notes'])) { $remarksArr[] = "Notes: " . $row['notes']; }
                        $feedback = !empty($remarksArr) ? implode("\n\n", $remarksArr) : ($statusVal === 'Declined' ? 'No rejection reason was provided.' : ($statusVal === 'Approved' ? 'Project successfully approved.' : 'No review remarks yet.'));
                        ?>
                        <tr>
                            <td><?php echo h($row['project_title']); ?></td>
                            <td><?php echo h($row['fullname']); ?></td>
                            <td><span class="status-pill status-<?php echo h(statusClass($statusVal)); ?>"><?php echo h($statusVal === 'Declined' ? 'Rejected' : $statusVal); ?></span></td>
                            <td>
                                <?php if (!empty($row['attachment_filename'])): ?>
                                    <div class="file-actions">
                                        <a class="ghost-btn mini-btn" href="<?php echo h(record_access_url($row['attachment_filename'], '../', (int) $row['document_id'])); ?>" target="_blank" rel="noopener"><i class="ri-download-line"></i> File</a>
                                        <button class="ghost-btn mini-btn" type="button" data-print-file="<?php echo h(record_access_url($row['attachment_filename'], '../', (int) $row['document_id'])); ?>"><i class="ri-printer-line"></i> Print</button>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="ghost-btn action-btn" type="button" data-open-modal="extension-status-feedback-<?php echo (int) $row['id']; ?>">
                                    <i class="ri-chat-3-line"></i><span>View Feedback</span>
                                </button>
                            </td>
                            <td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td>
                            <td>
                                <details>
                                    <summary>History</summary>
                                    <div class="timeline-list">
                                        <?php foreach (review_timeline('extension', (int) $row['id']) as $event): ?>
                                            <div>
                                                <strong><?php echo h($event['action']); ?></strong>
                                                <p><?php echo h($event['remarks'] ?? ''); ?></p>
                                                <small><?php echo h(($event['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($event['created_at']))); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$extensions): ?>
                        <tr data-pagination-empty><td colspan="7">No extension submissions yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="search-empty" data-search-empty="extensionStatusTable" hidden>No matching extension submissions.</p>
        </div>
    </section>

    <?php foreach ($extensions as $row): ?>
        <?php
        $statusVal = $row['status'] ?? $row['progress_status'] ?? 'Pending Coordinator Review';
        $remarksArr = [];
        if (!empty($row['coordinator_remarks'])) { $remarksArr[] = "Coordinator Feedback:\n" . $row['coordinator_remarks']; }
        if (!empty($row['director_remarks'])) { $remarksArr[] = "Director Feedback:\n" . $row['director_remarks']; }
        if (!empty($row['evaluation_rating'])) { $remarksArr[] = "Evaluation Rating:\n" . $row['evaluation_rating']; }
        if (!empty($row['approved_budget'])) { $remarksArr[] = "Approved Budget: ₱" . number_format((float) $row['approved_budget'], 2); }
        if (!empty($row['executive_remarks'])) { $remarksArr[] = "Executive Feedback:\n" . $row['executive_remarks']; }
        if (!empty($row['notes'])) { $remarksArr[] = "Notes:\n" . $row['notes']; }
        $feedback = !empty($remarksArr) ? implode("\n\n", $remarksArr) : ($statusVal === 'Declined' ? 'No rejection reason was provided.' : ($statusVal === 'Approved' ? 'Project successfully approved.' : 'No review remarks yet.'));
        ?>
        <div class="modal" id="extension-status-feedback-<?php echo (int) $row['id']; ?>" aria-hidden="true">
            <div class="modal-backdrop" data-close-modal></div>
            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="extension-status-title-<?php echo (int) $row['id']; ?>">
                <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
                <h2 id="extension-status-title-<?php echo (int) $row['id']; ?>">Extension Review Feedback</h2>
                <p><strong><?php echo h($row['project_title']); ?></strong></p>
                <p><span class="status-pill status-<?php echo h(statusClass($statusVal)); ?>"><?php echo h($statusVal === 'Declined' ? 'Rejected' : $statusVal); ?></span></p>
                <?php if ($statusVal === 'Declined'): ?><p><strong>Rejection reason</strong></p><?php endif; ?>
                <div class="feedback-content"><?php echo nl2br(h($feedback)); ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php
    portal_footer();
    return;
}

$research = portal_research_projects();
$documents = portal_documents();
?>
<section class="panel"><div class="panel-heading"><h2>Research Submission Status</h2><span>Pending | Under Review | Approved | Rejected</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Submission</th><th>Submitted By</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($research as $row): ?><tr><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><div class="panel-heading"><h2>Supporting Document Status</h2></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Document</th><th>Uploaded By</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($documents as $row): ?><tr><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php portal_footer(); ?>
