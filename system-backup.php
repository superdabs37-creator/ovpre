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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
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
<?php renderFooter(); ?>
