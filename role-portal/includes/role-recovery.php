<?php

declare(strict_types=1);

/**
 * Per-role Backup and Recovery helpers.
 *
 * Every function here is scoped to the signed-in portal role so a user can only
 * see, archive, restore or back up the records that role is allowed to manage:
 *   - Researcher / Faculty Extensionist : only their own submissions
 *   - Research / Extension Coordinator  : submissions from their own department
 *   - Research / Extension Director     : every submission of their track
 * Recovery is a soft-delete recovery (archived_at) - nothing is permanently deleted here.
 */

const RECOVERY_ROLES = [
    'Research Director'     => 'research',
    'Research Coordinator'  => 'research',
    'Researcher'            => 'research',
    'Extension Director'    => 'extension',
    'Extension Coordinator' => 'extension',
    'Faculty Extensionist'  => 'extension',
];

function recovery_entity(): ?string
{
    return RECOVERY_ROLES[PORTAL_ROLE] ?? null;
}

function recovery_enabled(): bool
{
    return recovery_entity() !== null;
}

function recovery_table(string $entity): string
{
    return $entity === 'extension' ? 'extension_projects' : 'research_projects';
}

function recovery_title_column(string $entity): string
{
    return $entity === 'extension' ? 'project_title' : 'title';
}

function recovery_entity_label(string $entity): string
{
    return $entity === 'extension' ? 'extension' : 'research';
}

/** Makes sure extension_projects can be archived (research_projects is handled in role-portal.php). */
function ensure_extension_archived_at_column(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            db()->exec('ALTER TABLE extension_projects ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ DEFAULT NULL');
        } else {
            $column = db()->query("SHOW COLUMNS FROM extension_projects LIKE 'archived_at'")->fetch();
            if (!$column) {
                db()->exec('ALTER TABLE extension_projects ADD COLUMN archived_at DATETIME DEFAULT NULL');
            }
        }
    } catch (Throwable $error) {
        error_log('Could not ensure extension_projects.archived_at: ' . $error->getMessage());
    }
    $checked = true;
}

/**
 * SQL fragment (aliases: p = project row, u = submitter) and bound params that limit
 * a query to the records the current role may manage.
 *
 * @return array{0: string, 1: array<int, int>}
 */
function recovery_scope(): array
{
    $user = current_user();
    $userId = (int) ($user['id'] ?? 0);
    $departmentId = (int) ($user['department_id'] ?? 0);

    if (in_array(PORTAL_ROLE, ['Research Director', 'Extension Director'], true)) {
        return ['', []];
    }
    if (in_array(PORTAL_ROLE, ['Research Coordinator', 'Extension Coordinator'], true) && $departmentId > 0) {
        return [' AND u.department_id = ?', [$departmentId]];
    }
    // Researcher, Faculty Extensionist, and coordinators without a department: own records only.
    return [' AND p.submitted_by = ?', [$userId]];
}

/** True when the current role may archive/restore/back up record $id. */
function recovery_can_manage(int $id): bool
{
    return $id > 0 && recovery_scoped_records([$id], null) !== [];
}

/**
 * Records inside the current role's scope.
 *
 * @param int[]     $ids      Limit to these ids (empty array = no id filter).
 * @param bool|null $archived true = archived only, false = active only, null = both.
 */
function recovery_scoped_records(array $ids = [], ?bool $archived = true): array
{
    $entity = recovery_entity();
    if ($entity === null) {
        return [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    [$scopeSql, $params] = recovery_scope();

    $where = 'WHERE 1 = 1';
    if ($archived === true) {
        $where .= ' AND p.archived_at IS NOT NULL';
    } elseif ($archived === false) {
        $where .= ' AND p.archived_at IS NULL';
    }
    if ($ids) {
        $where .= ' AND p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = array_merge($ids, $params);
        // Keep bind order identical to placeholder order: id list comes before the scope placeholder.
    }

    $table = recovery_table($entity);
    $sql = "SELECT p.*, u.fullname AS submitter_name, dep.department_name
            FROM {$table} p
            JOIN users u ON u.id = p.submitted_by
            LEFT JOIN departments dep ON dep.id = u.department_id
            {$where}{$scopeSql}
            ORDER BY p.archived_at DESC, p.created_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Archive or restore records, silently skipping anything outside the role's scope or already in the target state.
 *
 * @param  int[] $ids
 * @return int[] ids that were actually changed
 */
function recovery_set_archived(array $ids, bool $archive, string $source = 'Recovery page'): array
{
    $entity = recovery_entity();
    $user = current_user();
    if ($entity === null || !$user) {
        return [];
    }
    // Only rows currently in the opposite state and inside the role's scope can change.
    $rows = recovery_scoped_records($ids, !$archive);
    $changeIds = array_map(static fn(array $row): int => (int) $row['id'], $rows);
    if (!$changeIds) {
        return [];
    }

    $table = recovery_table($entity);
    $marks = implode(',', array_fill(0, count($changeIds), '?'));
    $value = $archive ? 'NOW()' : 'NULL';
    db()->prepare("UPDATE {$table} SET archived_at = {$value} WHERE id IN ({$marks})")->execute($changeIds);

    $label = recovery_entity_label($entity);
    foreach ($changeIds as $id) {
        log_review($entity, $id, (int) $user['id'], $archive ? 'Proposal Archived' : 'Proposal Restored', ($archive ? 'Archived' : 'Restored') . ' via ' . $source . '.');
        log_action((int) $user['id'], ($archive ? 'Archived ' : 'Restored ') . $label . ' proposal #' . $id . ' (' . PORTAL_ROLE . ')');
    }
    return $changeIds;
}

/** Name of the label shown to the user for a record's title. */
function recovery_record_title(array $row): string
{
    $entity = recovery_entity() ?? 'research';
    return (string) ($row[recovery_title_column($entity)] ?? 'Untitled');
}

function recovery_record_status(array $row): string
{
    $status = (string) ($row['status'] ?? '');
    if ($status === '') {
        $status = (string) ($row['progress_status'] ?? 'Pending');
    }
    return $status === 'Declined' ? 'Rejected' : $status;
}

/** Scoped JSON snapshot (active + archived records, review history and progress reports) for the current role. */
function recovery_backup_payload(): array
{
    $entity = recovery_entity();
    $user = current_user() ?? [];
    $records = recovery_scoped_records([], null);
    $ids = array_map(static fn(array $row): int => (int) $row['id'], $records);

    $reviewLogs = [];
    $progressReports = [];
    if ($ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        try {
            $stmt = db()->prepare("SELECT * FROM review_logs WHERE entity_type = ? AND entity_id IN ({$marks}) ORDER BY created_at");
            $stmt->execute(array_merge([$entity], $ids));
            $reviewLogs = $stmt->fetchAll();
        } catch (Throwable $error) {
            error_log('Role backup review log export failed: ' . $error->getMessage());
        }
        try {
            $reportTable = $entity === 'extension' ? 'extension_progress_reports' : 'research_progress_reports';
            $reportKey = $entity === 'extension' ? 'project_id' : 'research_id';
            $stmt = db()->prepare("SELECT * FROM {$reportTable} WHERE {$reportKey} IN ({$marks}) ORDER BY created_at");
            $stmt->execute($ids);
            $progressReports = $stmt->fetchAll();
        } catch (Throwable $error) {
            error_log('Role backup progress report export failed: ' . $error->getMessage());
        }
    }

    return [
        'format'           => 'ovpre-role-backup-v1',
        'created_at'       => date(DATE_ATOM),
        'role'             => PORTAL_ROLE,
        'entity'           => $entity,
        'created_by'       => ['id' => (int) ($user['id'] ?? 0), 'fullname' => (string) ($user['fullname'] ?? '')],
        'note'             => 'Contains record data only. Uploaded files are stored separately and are not included.',
        'records'          => $records,
        'review_logs'      => $reviewLogs,
        'progress_reports' => $progressReports,
    ];
}
