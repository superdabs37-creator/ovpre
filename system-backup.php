<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_super_admin();
verify_csrf();

$backupTables = [
    'departments', 'users', 'categories', 'documents', 'document_versions', 'archived_documents',
    'announcements', 'activities', 'notifications', 'audit_logs', 'review_logs', 'login_history',
    'research_projects', 'extension_projects', 'accomplishment_reports',
];
$notice = flash();

function backup_delete_record_files(array $filenames): void
{
    $storagePaths = [];
    $uploadRoot = realpath(UPLOAD_DIR);
    foreach ($filenames as $filename) {
        $filename = (string) $filename;
        if (str_starts_with($filename, 'storage:')) {
            $storagePaths[] = ltrim(substr($filename, strlen('storage:')), '/');
            continue;
        }
        if ($uploadRoot === false) continue;
        $path = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename);
        if ($path !== false && str_starts_with($path, $uploadRoot . DIRECTORY_SEPARATOR) && is_file($path)) {
            unlink($path);
        }
    }
    if ($storagePaths) {
        try {
            supabase_request('DELETE', '/storage/v1/object/' . rawurlencode(SUPABASE_STORAGE_BUCKET), ['prefixes' => array_values(array_unique($storagePaths))], null, true);
        } catch (Throwable $error) {
            error_log('Archived record storage cleanup failed: ' . $error->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'bulk_unarchive_archived') {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), static fn($id) => $id > 0)));
        if (!$ids) {
            flash('error', 'Select one or more archived records to unarchive.');
            redirect_to('system-backup.php#archived-records');
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT id FROM documents WHERE archived_at IS NOT NULL AND id IN ($marks)");
        $stmt->execute($ids);
        $restoredIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$restoredIds) {
            flash('error', 'None of the selected records are currently archived.');
            redirect_to('system-backup.php#archived-records');
        }
        $restoredMarks = implode(',', array_fill(0, count($restoredIds), '?'));
        db()->prepare("UPDATE documents SET archived_at = NULL, updated_at = NOW() WHERE id IN ($restoredMarks)")->execute($restoredIds);
        foreach ($restoredIds as $id) {
            log_review('document', $id, (int) $user['id'], 'Restored', 'Restored from Backup and Recovery.');
            log_action((int) $user['id'], "Restored archived document #{$id} from Backup and Recovery");
        }
        flash('success', count($restoredIds) . ' archived record(s) unarchived.');
        redirect_to('system-backup.php#archived-records');
    }

    if ($action === 'unarchive_document') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE documents SET archived_at = NULL, updated_at = NOW() WHERE id = ? AND archived_at IS NOT NULL');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_review('document', $id, (int) $user['id'], 'Restored', 'Restored from Backup and Recovery.');
            log_action((int) $user['id'], "Restored archived document #{$id} from Backup and Recovery");
            flash('success', 'Record removed from the archive and restored.');
        } else {
            flash('error', 'That archived record could not be found.');
        }
        redirect_to('system-backup.php#archived-records');
    }

    if ($action === 'delete_archived_document') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT filename FROM documents WHERE id = ? AND archived_at IS NOT NULL');
        $stmt->execute([$id]);
        $record = $stmt->fetch();
        if (!$record) {
            flash('error', 'That archived record could not be found.');
            redirect_to('system-backup.php#archived-records');
        }
        $versionStmt = db()->prepare('SELECT filename FROM document_versions WHERE document_id = ?');
        $versionStmt->execute([$id]);
        $filenames = array_column($versionStmt->fetchAll(), 'filename');
        $filenames[] = $record['filename'];
        try {
            db()->beginTransaction();
            db()->prepare('DELETE FROM archived_documents WHERE document_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM documents WHERE id = ? AND archived_at IS NOT NULL')->execute([$id]);
            db()->commit();
            backup_delete_record_files($filenames);
            log_action((int) $user['id'], "Permanently deleted archived document #{$id}");
            flash('success', 'Archived record permanently deleted.');
        } catch (Throwable $error) {
            if (db()->inTransaction()) db()->rollBack();
            error_log('Archived record deletion failed: ' . $error->getMessage());
            flash('error', 'The archived record could not be deleted.');
        }
        redirect_to('system-backup.php#archived-records');
    }

    if ($action === 'download') {
        $availableTables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $tables = [];
        foreach ($backupTables as $table) {
            if (in_array($table, $availableTables, true)) {
                $tables[$table] = db()->query("SELECT * FROM `{$table}`")->fetchAll();
            }
        }
        $payload = [
            'format' => 'ovpre-database-backup-v1',
            'created_at' => date(DATE_ATOM),
            'database' => DB_NAME,
            'tables' => $tables,
        ];
        log_action((int) $user['id'], 'Downloaded database backup');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="ovpre-backup-' . date('Ymd-His') . '.json"');
        header('Cache-Control: no-store, private');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'restore' && ($_POST['confirm_restore'] ?? '') === 'MERGE BACKUP') {
        $file = $_FILES['backup_file'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 50 * 1024 * 1024) {
            flash('error', 'Choose a valid backup file up to 50 MB.');
            redirect_to('system-backup.php');
        }
        $decoded = json_decode((string) file_get_contents((string) $file['tmp_name']), true);
        if (!is_array($decoded) || ($decoded['format'] ?? '') !== 'ovpre-database-backup-v1' || !is_array($decoded['tables'] ?? null)) {
            flash('error', 'The uploaded file is not a valid OVPRE database backup.');
            redirect_to('system-backup.php');
        }
        try {
            $pdo = db();
            $availableTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->beginTransaction();
            $restoredRows = 0;
            foreach ($backupTables as $table) {
                if (!in_array($table, $availableTables, true) || !isset($decoded['tables'][$table]) || !is_array($decoded['tables'][$table])) continue;
                $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
                $columns = array_values(array_filter($columns, static fn($column) => preg_match('/^[A-Za-z0-9_]+$/', (string) $column)));
                if (!$columns) continue;
                $quotedColumns = array_map(static fn($column) => '`' . $column . '`', $columns);
                $updates = array_values(array_filter($columns, static fn($column) => $column !== 'id'));
                $updateSql = $updates ? ' ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn($column) => '`' . $column . '` = VALUES(`' . $column . '`)', $updates)) : ' ON DUPLICATE KEY UPDATE `id` = `id`';
                $insert = $pdo->prepare('INSERT INTO `' . $table . '` (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')' . $updateSql);
                foreach ($decoded['tables'][$table] as $row) {
                    if (!is_array($row)) continue;
                    $values = [];
                    foreach ($columns as $column) $values[] = $row[$column] ?? null;
                    $insert->execute($values);
                    $restoredRows++;
                }
            }
            $pdo->commit();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            log_action((int) $user['id'], 'Restored database backup by merging ' . $restoredRows . ' rows');
            flash('success', "Backup recovery completed: {$restoredRows} rows merged. Newer rows were kept unless their IDs matched the backup.");
        } catch (Throwable $error) {
            if (db()->inTransaction()) db()->rollBack();
            try { db()->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $ignored) {}
            error_log('Database backup restore failed: ' . $error->getMessage());
            flash('error', 'The backup could not be restored. No partial database changes were kept.');
        }
        redirect_to('system-backup.php');
    }
    flash('error', 'Confirm the recovery action by entering MERGE BACKUP.');
    redirect_to('system-backup.php');
}

$archivedRecords = db()->query('SELECT d.id, d.title, d.original_filename, d.status, d.archived_at, ad.reason AS archive_reason, au.fullname AS archived_by_name FROM documents d LEFT JOIN archived_documents ad ON ad.id = (SELECT MAX(ad2.id) FROM archived_documents ad2 WHERE ad2.document_id = d.id) LEFT JOIN users au ON au.id = ad.archived_by WHERE d.archived_at IS NOT NULL ORDER BY d.archived_at DESC')->fetchAll();

renderHeader('Backup and Recovery', 'backup');
?>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2>Create a database backup</h2></div>
    <p>Download a JSON snapshot of database records. Store the file somewhere separate from this computer. Uploaded documents and profile photos are stored separately and are not included.</p>
    <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="download"><button class="primary-btn" type="submit"><i class="ri-download-cloud-line"></i> Download Backup</button></form>
</section>
<section class="panel">
    <div class="panel-heading"><h2>Recover from a database backup</h2></div>
    <p>Recovery merges saved records into the current database. Matching IDs are updated from the backup; rows created since the backup are kept. Back up the current database first.</p>
    <form method="post" enctype="multipart/form-data" class="backup-restore-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="restore"><label class="field"><span>Backup file (JSON, up to 50 MB)</span><input type="file" name="backup_file" accept=".json,application/json" required></label><label class="field"><span>Type MERGE BACKUP to confirm</span><input name="confirm_restore" autocomplete="off" required></label><button class="danger-btn" type="submit"><i class="ri-history-line"></i> Merge Backup into Database</button></form>
</section>
<section class="panel" id="archived-records">
    <div class="panel-heading"><h2>Archived Records</h2><span><?php echo count($archivedRecords); ?> archived record<?php echo count($archivedRecords) === 1 ? '' : 's'; ?></span></div>
    <div class="table-scroll">
        <div class="table-selection-toolbar" data-selection-toolbar="archivedRecordsTable" hidden>
            <span data-selection-count>0 selected</span>
            <form id="bulk-archivedRecordsTable" method="post" data-bulk-selection-form><?php echo csrf_field(); ?><input type="hidden" name="action" value="bulk_unarchive_archived"><button class="ghost-btn action-btn" type="submit" data-bulk-submit><i class="ri-arrow-go-back-line"></i><span>Unarchive Selected</span></button></form>
        </div>
        <table class="data-table portal-table" id="archivedRecordsTable">
            <thead><tr><th><label class="select-all-control"><input form="bulk-archivedRecordsTable" type="checkbox" aria-label="Select all archived records" data-select-all="archivedRecordsTable"><span>Select all</span></label></th><th>Record</th><th>File</th><th>Status</th><th>Archived By</th><th>Archived Date</th><th>Reason</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($archivedRecords as $record): ?>
                <tr>
                    <td><input form="bulk-archivedRecordsTable" type="checkbox" name="ids[]" aria-label="Select archived record <?php echo (int) $record['id']; ?>" value="<?php echo (int) $record['id']; ?>" data-row-select="archivedRecordsTable"></td>
                    <td><?php echo h($record['title']); ?><br><small>REC-<?php echo str_pad((string) $record['id'], 5, '0', STR_PAD_LEFT); ?></small></td>
                    <td><?php echo h($record['original_filename']); ?></td>
                    <td><?php echo h($record['status']); ?></td>
                    <td><?php echo h($record['archived_by_name'] ?? 'Administrator'); ?></td>
                    <td><?php echo h(date('M d, Y', strtotime($record['archived_at']))); ?></td>
                    <td><?php echo h($record['archive_reason'] ?: '-'); ?></td>
                    <td><div class="archive-record-actions">
                        <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="unarchive_document"><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><button class="ghost-btn action-btn" type="submit"><i class="ri-arrow-go-back-line"></i><span>Unarchive</span></button></form>
                        <form method="post" onsubmit="return confirm('Permanently delete this archived record and its file versions? This cannot be undone.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_archived_document"><input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>"><button class="danger-btn action-btn" type="submit"><i class="ri-delete-bin-line"></i><span>Deleted</span></button></form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$archivedRecords): ?><tr><td colspan="8">No archived records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter(); ?>
