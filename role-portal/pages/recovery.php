<?php
$user = portal_require_user();
verify_csrf();

if (!recovery_enabled()) {
    http_response_code(403);
    exit('Backup and Recovery is not available for your role.');
}

$entity = (string) recovery_entity();
$entityLabel = $entity === 'extension' ? 'Extension' : 'Research';
$scopeDescription = match (PORTAL_ROLE) {
    'Researcher', 'Faculty Extensionist' => 'your own submissions',
    'Research Coordinator', 'Extension Coordinator' => 'submissions from your college',
    default => 'all ' . strtolower($entityLabel) . ' submissions',
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'download_backup') {
        ensure_research_archived_at_column();
        ensure_extension_archived_at_column();
        $payload = recovery_backup_payload();
        log_action((int) $user['id'], 'Downloaded ' . strtolower($entityLabel) . ' backup (' . PORTAL_ROLE . ', ' . count($payload['records']) . ' records)');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ovpre-' . portal_slug_for_role(PORTAL_ROLE) . '-backup-' . date('Ymd-His') . '.json"');
        header('Cache-Control: no-store, private');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
        exit;
    }

    if ($action === 'restore' || $action === 'bulk_restore') {
        $ids = $action === 'restore' ? [(int) ($_POST['id'] ?? 0)] : array_map('intval', (array) ($_POST['ids'] ?? []));
        if (!array_filter($ids)) {
            flash('error', 'Select one or more archived records to restore.');
        } else {
            $restored = recovery_set_archived($ids, false, 'Backup and Recovery');
            if ($restored) {
                flash('success', count($restored) . ' record(s) restored.');
            } else {
                flash('error', 'Nothing was restored. The record may already be active or is outside your access.');
            }
        }
        redirect_to('recovery.php');
    }

    flash('error', 'Unknown action.');
    redirect_to('recovery.php');
}

$notice = flash();
$user = portal_header('Backup and Recovery', 'recovery');
portal_notice($notice);

$archivedRecords = recovery_scoped_records([], true);
$activeCount = count(recovery_scoped_records([], false));
$archivedCount = count($archivedRecords);
?>
<section class="stats-grid">
    <article><i class="ri-folder-open-line"></i><span>Active <?php echo h($entityLabel); ?> Records</span><strong><?php echo $activeCount; ?></strong></article>
    <article><i class="ri-archive-line"></i><span>Archived (Recoverable)</span><strong><?php echo $archivedCount; ?></strong></article>
    <article><i class="ri-shield-check-line"></i><span>Your Access</span><strong style="font-size:1rem;"><?php echo h(ucfirst($scopeDescription)); ?></strong></article>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Download my backup</h2></div>
    <p>Download a JSON snapshot of <?php echo h($scopeDescription); ?>, including review history and progress reports. Keep the file somewhere safe and separate from this computer. Uploaded files (PDF, DOCX, etc.) are stored separately and are not included.</p>
    <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="download_backup">
        <button class="primary-btn" type="submit"><i class="ri-download-cloud-line"></i> Download Backup</button>
    </form>
</section>

<section class="panel" id="archived-records">
    <div class="panel-heading">
        <h2>Archived <?php echo h($entityLabel); ?> Records</h2>
        <span><?php echo $archivedCount; ?> archived record<?php echo $archivedCount === 1 ? '' : 's'; ?></span>
    </div>
    <p>Archived records are hidden from your active lists but are not deleted. Restore a record to bring it back with its full review history. Permanent deletion is reserved for the Super Admin.</p>
    <div class="table-scroll">
        <div class="table-selection-toolbar" data-selection-toolbar="recoveryTable" hidden>
            <span data-selection-count>0 selected</span>
            <form id="bulk-recoveryTable" method="post" data-bulk-selection-form>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="bulk_restore">
                <button class="ghost-btn action-btn" type="submit" data-bulk-submit><i class="ri-arrow-go-back-line"></i><span>Restore Selected</span></button>
            </form>
        </div>
        <table class="data-table" id="recoveryTable" data-paginate="10">
            <thead>
                <tr>
                    <th class="table-checkbox-col">
                        <label class="select-all-control">
                            <input form="bulk-recoveryTable" type="checkbox" aria-label="Select all archived records" data-select-all="recoveryTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Record</th>
                    <?php if (!in_array(PORTAL_ROLE, ['Researcher', 'Faculty Extensionist'], true)): ?><th>Submitted By</th><?php endif; ?>
                    <th>Status</th>
                    <th>Archived On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archivedRecords as $record): ?>
                    <?php $recordStatus = recovery_record_status($record); ?>
                    <tr>
                        <td class="table-checkbox-col">
                            <input form="bulk-recoveryTable" type="checkbox" name="ids[]" aria-label="Select archived record <?php echo (int) $record['id']; ?>" value="<?php echo (int) $record['id']; ?>" data-row-select="recoveryTable">
                        </td>
                        <td><strong><?php echo h(recovery_record_title($record)); ?></strong><br><small>REC-<?php echo str_pad((string) (int) $record['id'], 5, '0', STR_PAD_LEFT); ?></small></td>
                        <?php if (!in_array(PORTAL_ROLE, ['Researcher', 'Faculty Extensionist'], true)): ?>
                            <td><?php echo h($record['submitter_name'] ?? ''); ?><?php if (!empty($record['department_name'])): ?><br><small><?php echo h($record['department_name']); ?></small><?php endif; ?></td>
                        <?php endif; ?>
                        <td><span class="status-pill status-<?php echo h(statusClass($recordStatus)); ?>"><?php echo h($recordStatus); ?></span></td>
                        <td><?php echo h(date('M d, Y h:i A', strtotime((string) $record['archived_at']))); ?></td>
                        <td>
                            <form method="post" style="margin:0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>">
                                <button class="ghost-btn mini-btn" type="submit"><i class="ri-arrow-go-back-line"></i> Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$archivedRecords): ?>
                    <tr data-pagination-empty><td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">No archived records to recover.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php portal_footer(); ?>
