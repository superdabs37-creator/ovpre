<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin', ROLE_RESEARCH_DIRECTOR]);
verify_csrf();

function record_file_url(string $filename, int $documentId, ?int $versionId = null): string {
    return record_access_url($filename, '', $documentId, $versionId);
}

if (!function_exists('record_is_previewable')) {
    function record_is_previewable(string $filename): bool {
        return in_array(strtolower(pathinfo(preg_replace('/^storage:/', '', $filename), PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg'], true);
    }
}

function render_record_selection(string $tableId, string $entity, array $actions, bool $openResearch = false, bool $archivedView = false): void {
    $formId = 'bulk-' . $tableId;
    echo '<div class="table-selection-toolbar is-hidden" data-selection-toolbar="' . h($tableId) . '" hidden style="display: none;">';
    echo '<span data-selection-count>0 selected</span>';
    if ($openResearch) echo '<button class="ghost-btn action-btn" type="button" data-open-selected-research><i class="ri-arrow-right-line"></i><span>Open Selected</span></button>';
    echo '<form id="' . h($formId) . '" method="post" data-bulk-selection-form>' . csrf_field();
    echo '<input type="hidden" name="action" value="bulk_table"><input type="hidden" name="entity" value="' . h($entity) . '">';
    foreach ($actions as $action => $label) {
        if ($action === 'delete') continue;
        echo '<button class="' . ($action === 'archive' ? 'action-btn btn-archive-natural' : 'ghost-btn action-btn') . '" type="submit" name="bulk_action" value="' . h($action) . '" data-bulk-submit><i class="' . ($action === 'archive' ? 'ri-download-2-line' : 'ri-arrow-go-back-line') . '"></i><span>' . h($label) . '</span></button>';
    }
    if ($archivedView) echo '<input type="hidden" name="archived_view" value="1">';
    echo '</form></div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'bulk') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $bulkAction = $_POST['bulk_action'] ?? '';
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'approve') {
                $bulkRemarks = trim($_POST['bulk_remarks'] ?? 'Bulk approved.');
                $docRowsStmt = db()->prepare("SELECT id, title, uploaded_by FROM documents WHERE id IN ($placeholders)");
                $docRowsStmt->execute($ids);
                $docRows = $docRowsStmt->fetchAll();

                db()->prepare("UPDATE documents SET status = 'Approved', status_remarks = ?, updated_at = NOW() WHERE id IN ($placeholders)")
                    ->execute(array_merge([$bulkRemarks], $ids));
                foreach ($ids as $docId) {
                    log_review('document', $docId, (int) $user['id'], 'Bulk Approved', $bulkRemarks);
                }
                if (get_system_setting('notify_on_approval', '1') === '1') {
                    foreach ($docRows as $row) {
                        if (!empty($row['uploaded_by'])) {
                            notify_user((int) $row['uploaded_by'], "Your document '" . ($row['title'] ?? 'Record') . "' has been approved.", 'submitted-files.php#document-' . $row['id']);
                        }
                    }
                }
                flash('success', 'Selected records approved.');
            } elseif ($bulkAction === 'archive') {
                db()->prepare("UPDATE documents SET archived_at = NOW(), updated_at = NOW() WHERE id IN ($placeholders)")
                    ->execute($ids);
                foreach ($ids as $docId) {
                    db()->prepare('INSERT INTO archived_documents (document_id, archived_by, reason, created_at) VALUES (?, ?, ?, NOW())')
                        ->execute([$docId, (int) $user['id'], trim($_POST['bulk_remarks'] ?? 'Bulk archive')]);
                    log_review('document', $docId, (int) $user['id'], 'Bulk Archived', trim($_POST['bulk_remarks'] ?? ''));
                }
                flash('success', 'Selected records archived.');
            } elseif ($bulkAction === 'delete') {
                flash('error', 'Deleting records is disabled. Use archive instead.');
                redirect_to('admin-documents.php');
            }
        }
    } elseif ($action === 'bulk_table') {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), static fn($id) => $id > 0)));
        $entity = (string) ($_POST['entity'] ?? '');
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');
        $allowed = [
            'research' => ['archive', 'restore', 'unarchive'],
            'extension' => ['archive', 'restore', 'unarchive'],
            'activity' => ['archive', 'restore', 'unarchive'],
            'document' => ['archive', 'unarchive'],
            'announcement' => ['restore'],
        ];
        $redirectArchived = (isset($_GET['archived']) && $_GET['archived'] === '1') || (isset($_POST['archived_view']) && $_POST['archived_view'] === '1');
        if (!$ids || !isset($allowed[$entity]) || !in_array($bulkAction, $allowed[$entity], true)) {
            flash('error', 'Select one or more records and choose an available action.');
            redirect_to('admin-documents.php' . ($redirectArchived ? '?archived=1' : ''));
        }
        if ($bulkAction === 'delete' && !in_array(($user['role'] ?? ''), [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN], true)) {
            flash('error', 'Only authorized administrators can permanently delete records.');
            redirect_to('admin-documents.php' . ($redirectArchived ? '?archived=1' : ''));
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));
        $remarks = trim((string) ($_POST['bulk_remarks'] ?? ''));
        if ($entity === 'document') {
            $isArchivedView = isset($_POST['archived_view']) && $_POST['archived_view'] === '1';
            if ($bulkAction === 'archive' && !$isArchivedView) {
                db()->prepare("UPDATE documents SET archived_at = NOW(), updated_at = NOW() WHERE archived_at IS NULL AND id IN ($marks)")->execute($ids);
                foreach ($ids as $docId) {
                    db()->prepare('INSERT INTO archived_documents (document_id, archived_by, reason, created_at) VALUES (?, ?, ?, NOW())')->execute([$docId, (int) $user['id'], $remarks ?: 'Bulk archive']);
                    log_review('document', $docId, (int) $user['id'], 'Bulk Archived', $remarks);
                }
                flash('success', 'Selected records archived.');
            } elseif ($bulkAction === 'unarchive' && $isArchivedView) {
                db()->prepare("UPDATE documents SET archived_at = NULL, updated_at = NOW() WHERE archived_at IS NOT NULL AND id IN ($marks)")->execute($ids);
                foreach ($ids as $docId) log_review('document', $docId, (int) $user['id'], 'Restored', 'Restored from Record Management.');
                flash('success', 'Selected records restored from the archive.');
            }
        } elseif ($entity === 'activity') {
            ensure_activity_archived_at_column();
            if ($bulkAction === 'archive') {
                db()->prepare("UPDATE activities SET archived_at = NOW() WHERE archived_at IS NULL AND id IN ($marks)")->execute($ids);
                flash('success', 'Selected activities archived.');
            } elseif ($bulkAction === 'restore' || $bulkAction === 'unarchive') {
                db()->prepare("UPDATE activities SET archived_at = NULL WHERE archived_at IS NOT NULL AND id IN ($marks)")->execute($ids);
                flash('success', 'Selected activities restored.');
            }
            foreach ($ids as $activityId) log_action((int) $user['id'], 'Bulk ' . ($bulkAction === 'archive' ? 'archived' : 'restored') . " activity #{$activityId}");
        } elseif ($entity === 'research' || $entity === 'extension') {
            $table = $entity === 'research' ? 'research_projects' : 'extension_projects';
            if ($bulkAction === 'archive') {
                db()->prepare("UPDATE {$table} SET archived_at = NOW() WHERE archived_at IS NULL AND id IN ($marks)")->execute($ids);
                foreach ($ids as $recordId) {
                    log_review($entity, $recordId, (int) $user['id'], 'Bulk Archived', 'Archived from Record Management.');
                    log_action((int) $user['id'], "Bulk archived {$entity} submission #{$recordId}");
                }
                flash('success', 'Selected ' . $entity . ' submissions archived.');
            } elseif ($bulkAction === 'restore' || $bulkAction === 'unarchive') {
                db()->prepare("UPDATE {$table} SET archived_at = NULL WHERE archived_at IS NOT NULL AND id IN ($marks)")->execute($ids);
                foreach ($ids as $recordId) {
                    log_review($entity, $recordId, (int) $user['id'], 'Bulk Restored', 'Restored from Record Management.');
                    log_action((int) $user['id'], "Bulk restored {$entity} submission #{$recordId}");
                }
                flash('success', 'Selected ' . $entity . ' submissions restored.');
            }
        } elseif ($entity === 'announcement') {
            if ($bulkAction === 'restore') {
                db()->prepare("UPDATE announcements SET status = 'Published' WHERE status = 'Archived' AND id IN ($marks)")->execute($ids);
                flash('success', 'Selected announcements restored.');
            }
        }
    } elseif ($action === 'status') {
        $status = $_POST['status'] ?? 'Pending';
        if (in_array($status, ['Pending', 'Under Review', 'Approved', 'Declined'], true)) {
            $remarks = trim($_POST['remarks'] ?? '');
            if (get_system_setting('require_admin_remarks', '1') === '1' && $status === 'Declined' && $remarks === '') {
                flash('error', 'Remarks are required when declining a document.');
                redirect_to('admin-documents.php');
            }
            $docStmt = db()->prepare('SELECT id, title, uploaded_by, assigned_to FROM documents WHERE id = ?');
            $docStmt->execute([$id]);
            $origDoc = $docStmt->fetch();

            $assignedTo = isset($_POST['assigned_to'])
                ? (($_POST['assigned_to'] === '') ? null : (int) $_POST['assigned_to'])
                : ($origDoc['assigned_to'] ?? null);

            db()->prepare('UPDATE documents SET status = ?, status_remarks = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$status, $remarks, $assignedTo, $id]);
            log_review('document', $id, (int) $user['id'], 'Status: ' . $status, $remarks);
            log_action((int) $user['id'], "Set document #{$id} to {$status}");

            if ($origDoc && !empty($origDoc['uploaded_by'])) {
                $docTitle = $origDoc['title'] ?? 'Document';
                $uploaderId = (int) $origDoc['uploaded_by'];
                if (get_system_setting('notify_on_approval', '1') === '1') {
                    $link = 'submitted-files.php#document-' . $id;
                    if ($status === 'Approved') {
                        notify_user($uploaderId, "Your document '{$docTitle}' has been approved.", $link);
                    } elseif ($status === 'Declined') {
                        notify_user($uploaderId, "Your document '{$docTitle}' was declined." . ($remarks ? " Remarks: {$remarks}" : ''), $link);
                    } elseif ($status === 'Under Review') {
                        notify_user($uploaderId, "Your document '{$docTitle}' is currently under review.", $link);
                    }
                }
            }

            if ($assignedTo && $assignedTo !== (int) ($origDoc['assigned_to'] ?? 0) && $assignedTo !== (int) $user['id']) {
                $docTitle = $origDoc['title'] ?? 'Document';
                notify_user($assignedTo, "You have been assigned to review document: '{$docTitle}'", 'submitted-files.php#document-' . $id);
            }

            flash('success', 'Document status updated with remarks.');
        }
    } elseif ($action === 'archive') {
        $reason = trim($_POST['reason'] ?? '');
        db()->prepare('UPDATE documents SET archived_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$id]);
        db()->prepare('INSERT INTO archived_documents (document_id, archived_by, reason, created_at) VALUES (?, ?, ?, NOW())')->execute([$id, $user['id'], $reason]);
        log_review('document', $id, (int) $user['id'], 'Archived', $reason);
        log_action((int) $user['id'], "Archived document #{$id}");
        flash('success', 'Document archived.');
    } elseif ($action === 'restore') {
        db()->prepare('UPDATE documents SET archived_at = NULL, updated_at = NOW() WHERE id = ?')->execute([$id]);
        log_review('document', $id, (int) $user['id'], 'Restored', '');
        log_action((int) $user['id'], "Restored document #{$id}");
        flash('success', 'Document restored.');
    } elseif ($action === 'delete') {
        flash('error', 'Deleting records is disabled. Use archive instead.');
        $redirectArchived = (isset($_GET['archived']) && $_GET['archived'] === '1') || (isset($_POST['archived_view']) && $_POST['archived_view'] === '1');
        redirect_to('admin-documents.php' . ($redirectArchived ? '?archived=1' : ''));
    } elseif ($action === 'announcement_restore') {
        db()->prepare('UPDATE announcements SET status = "Published" WHERE id = ?')->execute([$id]);
        log_action((int) $user['id'], "Restored announcement #{$id}");
        flash('success', 'Announcement restored to published announcements.');
    }
    $redirectArchived = (isset($_GET['archived']) && $_GET['archived'] === '1') || (isset($_POST['archived_view']) && $_POST['archived_view'] === '1');
    redirect_to('admin-documents.php' . ($redirectArchived ? '?archived=1' : ''));
}

$archived = ($_GET['archived'] ?? '') === '1';
$statusFilter = $_GET['status'] ?? 'All';
$documents = document_list($archived, $statusFilter);
$archivedAnnouncements = [];
if ($archived) {
    $archivedAnnouncements = db()->query('SELECT a.*, u.fullname FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.status = "Archived" ORDER BY a.created_at DESC')->fetchAll();
}
ensure_activity_archived_at_column();
$archivedCondition = $archived ? 'IS NOT NULL' : 'IS NULL';
$researchSubmissions = db()->query("
    SELECT r.*, u.fullname, au.fullname AS assignee,
           COALESCE(d.department_name, doc_d.department_name, 'General / OVPRE') AS department_name
    FROM research_projects r
    JOIN users u ON u.id = r.submitted_by
    LEFT JOIN users au ON au.id = r.assigned_to
    LEFT JOIN departments d ON d.id = u.department_id
    LEFT JOIN documents doc ON doc.id = r.document_id
    LEFT JOIN departments doc_d ON doc_d.id = doc.department_id
    WHERE r.archived_at {$archivedCondition}
    ORDER BY r.created_at DESC
")->fetchAll();

$extensionSubmissions = db()->query("
    SELECT e.*, u.fullname, au.fullname AS assignee,
           COALESCE(d.department_name, doc_d.department_name, 'General / OVPRE') AS department_name
    FROM extension_projects e
    JOIN users u ON u.id = e.submitted_by
    LEFT JOIN users au ON au.id = e.assigned_to
    LEFT JOIN departments d ON d.id = u.department_id
    LEFT JOIN documents doc ON doc.id = e.document_id
    LEFT JOIN departments doc_d ON doc_d.id = doc.department_id
    WHERE e.archived_at {$archivedCondition}
    ORDER BY e.created_at DESC
")->fetchAll();

$activitySubmissions = db()->query("
    SELECT a.*, u.fullname,
           COALESCE(d.department_name, 'General / OVPRE') AS department_name
    FROM activities a
    LEFT JOIN users u ON u.id = a.created_by
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE a.archived_at {$archivedCondition}
    ORDER BY a.date DESC, a.created_at DESC
")->fetchAll();
$isResearchDirector = in_array(role_access_group((string) ($user['role'] ?? '')), [ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR], true);
$isExtensionDirector = (role_access_group((string) ($user['role'] ?? '')) === ROLE_EXTENSION_DIRECTOR);
$notice = flash();
$categoryAnalytics = db()->query('SELECT c.category_name, COUNT(d.id) total FROM categories c LEFT JOIN documents d ON d.category_id = c.id AND d.archived_at IS NULL GROUP BY c.id ORDER BY c.category_name')->fetchAll();
$departmentAnalytics = db()->query('SELECT dep.department_name, COUNT(d.id) total FROM departments dep LEFT JOIN documents d ON d.department_id = dep.id AND d.archived_at IS NULL GROUP BY dep.id ORDER BY dep.department_name')->fetchAll();

if (!function_exists('getCollegeCodeTag')) {
    function getCollegeCodeTag($dept) {
        $s = strtolower((string) $dept);
        if (strpos($s, 'ccst') !== false || strpos($s, 'computer') !== false) return 'CCST';
        if (strpos($s, 'coe') !== false || strpos($s, 'engineering') !== false) return 'COE';
        if (strpos($s, 'conahs') !== false || strpos($s, 'nursing') !== false) return 'CONAHS';
        if (strpos($s, 'cas') !== false || strpos($s, 'arts') !== false) return 'CAS';
        if (strpos($s, 'cba') !== false || strpos($s, 'business') !== false) return 'CBA';
        if (strpos($s, 'cte') !== false || strpos($s, 'teacher') !== false || strpos($s, 'education') !== false) return 'CTE';
        if (strpos($s, 'cthm') !== false || strpos($s, 'tourism') !== false || strpos($s, 'hospitality') !== false) return 'CTHM';
        if (strpos($s, 'accountancy') !== false || strpos($s, 'coa') !== false) return 'COA';
        if (strpos($s, 'cpag') !== false || strpos($s, 'public administration') !== false) return 'CPAG';
        if (strpos($s, 'chk') !== false || strpos($s, 'human kinetics') !== false || strpos($s, 'cpe') !== false || strpos($s, 'physical education') !== false) return 'CPE';
        return strtoupper(substr(trim((string)$dept), 0, 4));
    }
}

$completedProjectRows = db()->query("
    SELECT 
        'Research' AS project_type,
        r.id AS project_id,
        r.title AS project_title,
        r.description AS project_description,
        r.researchers AS project_lead,
        r.department_id,
        dep.department_name,
        r.created_at AS submitted_at,
        u.fullname AS submitter_name,
        d.id AS document_id,
        d.filename AS document_filename,
        d.original_filename AS document_original_filename
    FROM research_projects r
    JOIN departments dep ON dep.id = r.department_id
    LEFT JOIN users u ON u.id = r.submitted_by
    LEFT JOIN documents d ON (d.entity_type = 'research' AND d.entity_id = r.id AND d.archived_at IS NULL)
    WHERE r.workflow_stage = 'Completed'
    UNION ALL
    SELECT 
        'Extension' AS project_type,
        e.id AS project_id,
        e.project_title AS project_title,
        e.project_description,
        e.coordinator AS project_lead,
        e.department_id,
        dep.department_name,
        e.created_at AS submitted_at,
        u.fullname AS submitter_name,
        d.id AS document_id,
        d.filename AS document_filename,
        d.original_filename AS document_original_filename
    FROM extension_projects e
    JOIN departments dep ON dep.id = e.department_id
    LEFT JOIN users u ON u.id = e.submitted_by
    LEFT JOIN documents d ON (d.entity_type = 'extension' AND d.entity_id = e.id AND d.archived_at IS NULL)
    WHERE e.progress_status = 'Completed'
    ORDER BY department_name ASC, project_type ASC, project_title ASC
")->fetchAll(PDO::FETCH_ASSOC);

$completedColleges = [];
foreach ($completedProjectRows as $completedProjectRow) {
    if ($isExtensionDirector && ($completedProjectRow['project_type'] ?? '') !== 'Extension') {
        continue;
    }
    if ($isResearchDirector && ($completedProjectRow['project_type'] ?? '') !== 'Research') {
        continue;
    }
    $departmentId = (int) $completedProjectRow['department_id'];
    if (!isset($completedColleges[$departmentId])) {
        $completedColleges[$departmentId] = [
            'department_name' => (string) $completedProjectRow['department_name'],
            'projects' => [],
        ];
    }
    $docId = (int) ($completedProjectRow['document_id'] ?? 0);
    $fileUrl = $docId > 0 ? 'download-document.php?id=' . $docId : '';
    $fileName = !empty($completedProjectRow['document_original_filename'])
        ? (string) $completedProjectRow['document_original_filename']
        : (!empty($completedProjectRow['document_filename']) ? basename((string) $completedProjectRow['document_filename']) : 'Final-Completed-Report.pdf');

    $completedColleges[$departmentId]['projects'][] = [
        'id' => (int) $completedProjectRow['project_id'],
        'type' => (string) $completedProjectRow['project_type'],
        'title' => (string) $completedProjectRow['project_title'],
        'status' => 'Completed',
        'description' => (string) ($completedProjectRow['project_description'] ?? ''),
        'lead' => (string) ($completedProjectRow['project_lead'] ?? ''),
        'extra_info' => (string) ($completedProjectRow['project_lead'] ?? ''),
        'submitter_name' => (string) ($completedProjectRow['submitter_name'] ?? 'Faculty Proponent'),
        'department_name' => (string) $completedProjectRow['department_name'],
        'submitted_at' => (string) ($completedProjectRow['submitted_at'] ?? ''),
        'document_url' => $fileUrl,
        'file_url' => $fileUrl,
        'file_name' => $fileName,
    ];
}
$completedColleges = array_values($completedColleges);
$totalCompletedProjectsCount = array_sum(array_map(fn($c) => count($c['projects']), $completedColleges));

renderHeader('Record Management', 'documents');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-file-search-line"></i></div>
    <div><p><?php echo APP_NAME; ?></p><h2>Record Management</h2><span>Review records with remarks, preview files, assign reviewers, archive with reasons, and track timelines.</span></div>
</section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-checkbox-circle-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>

<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search by title, author, category, date, or status"></div>
    <div class="tool-panel-actions">
    <div class="record-segmented-control" role="tablist" aria-label="Record view mode">
        <a class="record-segmented-tab <?php echo !$archived ? 'is-active' : ''; ?>" href="admin-documents.php" role="tab" aria-selected="<?php echo !$archived ? 'true' : 'false'; ?>">
            <i class="ri-folder-line"></i><span>Active Records</span>
        </a>
        <a class="record-segmented-tab <?php echo $archived ? 'is-active' : ''; ?>" href="admin-documents.php?archived=1" role="tab" aria-selected="<?php echo $archived ? 'true' : 'false'; ?>">
            <i class="ri-archive-line"></i><span>Archived Records</span>
        </a>
    </div>
    <form class="action-row" method="get" style="margin:0;">
        <?php if ($archived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
        <select class="filter-select" name="status" onchange="this.form.submit()"><option value="All">All Status</option><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select>
    </form>
    <a class="primary-btn" href="upload-document.php"><i class="ri-upload-cloud-line"></i> Upload Record</a>
    </div>
</section>

<section class="panel submission-folders" style="margin-bottom: 24px;">
    <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div>
            <h2 id="submissionFoldersTitle" style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #0f172a; display: inline-flex; align-items: center; gap: 8px; letter-spacing: -0.015em;">
                <i class="ri-folder-shared-line" style="color: #15803d; font-size: 1.25rem;"></i>
                <span><?php echo $archived ? 'Archived Submission Folders' : 'Submission Folders'; ?></span>
            </h2>
            <span id="submissionFoldersSubtitle" style="display: block; font-size: 0.82rem; color: #64748b; font-weight: 500; margin-top: 3px;"><?php if ($isResearchDirector) { echo $archived ? 'Archived research and activity submissions' : 'Research and activity submissions'; } elseif ($isExtensionDirector) { echo $archived ? 'Archived extension and activity submissions' : 'Extension and activity submissions'; } else { echo $archived ? 'Archived research, extension, and activity submissions' : 'Research, extension, and activity submissions'; } ?></span>
        </div>
    </div>

    <?php if (!$isExtensionDirector): ?>
    <details class="submission-folder" id="researchFolder">
        <summary><span><i class="ri-flask-line"></i> <?php echo $archived ? 'Archived Research Submissions' : 'Research Management Submissions'; ?></span><strong><?php echo count($researchSubmissions); ?></strong></summary>
    <div class="table-scroll">
        <?php render_record_selection('researchSubmissionTable', 'research', $archived ? ['restore' => 'Restore Selected'] : ['archive' => 'Archive Selected'], false, $archived); ?>
        <table class="data-table portal-table" id="researchSubmissionTable">
            <thead><tr><th class="table-checkbox-col"><label class="select-all-control"><input form="bulk-researchSubmissionTable" type="checkbox" aria-label="Select all research submissions" data-select-all="researchSubmissionTable"><span>Select all</span></label></th><th>Research Title</th><th>College</th><th>Researchers</th><th>Submitted By</th><th>Assigned To</th><th>Status</th><th>Date</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($researchSubmissions as $research): ?>
                    <tr data-table-row data-status="<?php echo h($research['status']); ?>">
                        <td class="table-checkbox-col"><input form="bulk-researchSubmissionTable" type="checkbox" name="ids[]" aria-label="Select research submission <?php echo (int) $research['id']; ?>" value="<?php echo (int) $research['id']; ?>" data-row-select="researchSubmissionTable"></td>
                        <td data-sort-value="<?php echo h($research['title']); ?>"><?php echo h($research['title']); ?><br><small><?php echo h($research['funding_source'] ?: 'No funding source'); ?></small></td>
                        <td data-sort-value="<?php echo h($research['department_name']); ?>"><span class="dept-pill"><?php echo h($research['department_name']); ?></span></td>
                        <td data-sort-value="<?php echo h($research['researchers']); ?>"><?php echo h($research['researchers']); ?></td>
                        <td data-sort-value="<?php echo h($research['fullname']); ?>"><?php echo h($research['fullname']); ?></td>
                        <td data-sort-value="<?php echo h($research['assignee'] ?? 'Unassigned'); ?>"><?php echo h($research['assignee'] ?? 'Unassigned'); ?></td>
                        <td data-sort-value="<?php echo h($research['status']); ?>"><span class="status-pill status-<?php echo h(statusClass($research['status'])); ?>"><?php echo h($research['status'] === 'Declined' ? 'Rejected' : $research['status']); ?></span></td>
                        <td data-sort-value="<?php echo h($research['created_at']); ?>"><?php echo h(date('M d, Y', strtotime($research['created_at']))); ?></td>
                        <td><a class="ghost-btn action-btn" href="research.php?status=<?php echo urlencode($research['status']); ?><?php echo $archived ? '&archived=1' : ''; ?>"><i class="ri-arrow-right-line"></i><span>Open</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$researchSubmissions): ?><tr data-pagination-empty><td colspan="9">No <?php echo $archived ? 'archived ' : ''; ?>research submissions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    </details>
    <?php endif; ?>

    <?php if (!$isResearchDirector): ?>
    <details class="submission-folder" id="extensionFolder">
        <summary><span><i class="ri-community-line"></i> <?php echo $archived ? 'Archived Extension Project Submissions' : 'Extension Project Submissions'; ?></span><strong><?php echo count($extensionSubmissions); ?></strong></summary>
    <div class="table-scroll">
        <?php render_record_selection('extensionSubmissionTable', 'extension', $archived ? ['restore' => 'Restore Selected'] : ['archive' => 'Archive Selected'], false, $archived); ?>
        <table class="data-table portal-table" id="extensionSubmissionTable">
            <thead><tr><th class="table-checkbox-col"><label class="select-all-control"><input form="bulk-extensionSubmissionTable" type="checkbox" aria-label="Select all extension submissions" data-select-all="extensionSubmissionTable"><span>Select all</span></label></th><th>Project</th><th>College</th><th>Coordinator</th><th>Partner</th><th><div style="display: inline-flex; align-items: center; gap: 6px;"><span>Budget</span><button type="button" class="btn-toggle-mask" data-mask-target="budget" aria-label="Toggle budget visibility" title="Hide/Unhide Budget"><i class="ri-eye-line"></i></button></div></th><th>Assigned To</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($extensionSubmissions as $extension): ?>
                    <tr data-table-row data-status="<?php echo h($extension['progress_status']); ?>">
                        <td class="table-checkbox-col"><input form="bulk-extensionSubmissionTable" type="checkbox" name="ids[]" aria-label="Select extension submission <?php echo (int) $extension['id']; ?>" value="<?php echo (int) $extension['id']; ?>" data-row-select="extensionSubmissionTable"></td>
                        <td data-sort-value="<?php echo h($extension['project_title']); ?>"><?php echo h($extension['project_title']); ?><br><small><?php echo h(date('M d, Y', strtotime($extension['start_date'])) . ' - ' . date('M d, Y', strtotime($extension['end_date']))); ?></small></td>
                        <td data-sort-value="<?php echo h($extension['department_name']); ?>"><span class="dept-pill"><?php echo h($extension['department_name']); ?></span></td>
                        <td data-sort-value="<?php echo h($extension['coordinator']); ?>"><?php echo h($extension['coordinator']); ?></td>
                        <td data-sort-value="<?php echo h($extension['community_partner']); ?>"><?php echo h($extension['community_partner']); ?></td>
                        <td data-sort-value="<?php echo (float) $extension['budget']; ?>"><span class="currency-masked-wrap" data-mask-group="budget"><span class="currency-val">PHP <?php echo h(number_format((float) $extension['budget'], 2)); ?></span><span class="currency-mask">PHP ••••••</span></span></td>
                        <td data-sort-value="<?php echo h($extension['assignee'] ?? 'Unassigned'); ?>"><?php echo h($extension['assignee'] ?? 'Unassigned'); ?></td>
                        <td data-sort-value="<?php echo h($extension['progress_status']); ?>"><span class="status-pill status-<?php echo h(statusClass($extension['progress_status'])); ?>"><?php echo h($extension['progress_status']); ?></span></td>
                        <td><a class="ghost-btn action-btn" href="extension.php?status=<?php echo urlencode($extension['progress_status']); ?>"><i class="ri-arrow-right-line"></i><span>Open</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$extensionSubmissions): ?><tr data-pagination-empty><td colspan="9">No <?php echo $archived ? 'archived ' : ''; ?>extension project submissions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    </details>
    <?php endif; ?>

    <details class="submission-folder" id="activityFolder">
        <summary><span><i class="ri-calendar-event-line"></i> <?php echo $archived ? 'Archived Activity Submissions' : 'Activity Submissions'; ?></span><strong><?php echo count($activitySubmissions); ?></strong></summary>
    <div class="table-scroll">
        <?php render_record_selection('activitySubmissionTable', 'activity', $archived ? ['restore' => 'Restore Selected'] : ['archive' => 'Archive Selected'], false, $archived); ?>
        <table class="data-table portal-table" id="activitySubmissionTable">
            <thead><tr><th class="table-checkbox-col"><label class="select-all-control"><input form="bulk-activitySubmissionTable" type="checkbox" aria-label="Select all activities" data-select-all="activitySubmissionTable"><span>Select all</span></label></th><th>Activity</th><th>College</th><th>Type</th><th>Venue</th><th>Date</th><th>Submitted By</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($activitySubmissions as $activity): ?>
                    <tr data-table-row>
                        <td class="table-checkbox-col"><input form="bulk-activitySubmissionTable" type="checkbox" name="ids[]" aria-label="Select activity <?php echo (int) $activity['id']; ?>" value="<?php echo (int) $activity['id']; ?>" data-row-select="activitySubmissionTable"></td>
                        <td data-sort-value="<?php echo h($activity['title']); ?>"><?php echo h($activity['title']); ?><br><small><?php echo h($activity['description'] ?: 'No description'); ?></small></td>
                        <td data-sort-value="<?php echo h($activity['department_name']); ?>"><span class="dept-pill"><?php echo h($activity['department_name']); ?></span></td>
                        <td data-sort-value="<?php echo h($activity['activity_type']); ?>"><span class="dept-pill"><?php echo h($activity['activity_type']); ?></span></td>
                        <td data-sort-value="<?php echo h($activity['venue']); ?>"><?php echo h($activity['venue']); ?></td>
                        <td data-sort-value="<?php echo h($activity['date']); ?>"><?php echo h(date('M d, Y', strtotime($activity['date']))); ?></td>
                        <td data-sort-value="<?php echo h($activity['fullname'] ?? 'System'); ?>"><?php echo h($activity['fullname'] ?? 'System'); ?></td>
                        <td><a class="ghost-btn action-btn" href="activities.php<?php echo $archived ? '?archived=1' : ''; ?>"><i class="ri-arrow-right-line"></i><span>Open</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$activitySubmissions): ?><tr data-pagination-empty><td colspan="8">No <?php echo $archived ? 'archived ' : ''; ?>activity submissions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    </details>
</section>

<?php
$catIcons = [
    'Research Paper' => 'ri-article-line',
    'Extension Project' => 'ri-hand-heart-line',
    'Institutional Report' => 'ri-bar-chart-box-line',
    'Special Order / Policy' => 'ri-file-shield-line',
    'Minutes of Meeting' => 'ri-team-line',
    'Technical Report' => 'ri-file-settings-line',
];
?>
<!-- Category Summary Cards (Full-Width Grid) -->
<section class="summary-grid" style="margin-bottom: 24px;">
    <article class="summary-card category-filter-card active" role="button" tabindex="0" data-category-filter="All" aria-pressed="true">
        <div class="summary-icon"><i class="ri-folders-line"></i></div>
        <div>
            <p>All Records</p>
            <h3><?php echo array_sum(array_map(fn($row) => (int) $row['total'], $categoryAnalytics)); ?></h3>
            <span>Open all records</span>
        </div>
    </article>
    <?php foreach ($categoryAnalytics as $row): 
        $catName = $row['category_name'];
        $iconClass = $catIcons[$catName] ?? 'ri-folder-3-line';
    ?>
        <article class="summary-card category-filter-card" role="button" tabindex="0" data-category-filter="<?php echo h($catName); ?>" aria-pressed="false">
            <div class="summary-icon"><i class="<?php echo $iconClass; ?>"></i></div>
            <div>
                <p><?php echo h($catName); ?></p>
                <h3><?php echo (int) $row['total']; ?></h3>
                <span>Open category records</span>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<!-- Completed Colleges Directory (Full-Width Under Category Cards) -->
<article class="panel chart-panel acad-analytics-card" style="margin-bottom: 24px;">
    <div class="acad-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div class="acad-card-title-wrap">
            <span class="acad-card-icon icon-emerald">
                <i class="ri-building-2-line"></i>
            </span>
            <div>
                <h2 class="acad-card-title">Completed Colleges</h2>
            </div>
        </div>
        <div class="acad-header-controls" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <div class="acad-search-box" style="position: relative; display: flex; align-items: center;">
                <i class="ri-search-line" style="position: absolute; left: 9px; color: #94a3b8; font-size: 0.85rem; pointer-events: none;"></i>
                <input type="search" id="completedCollegeSearchInput" placeholder="Search department or title..." aria-label="Search completed projects by department" style="height: 32px; padding: 0 28px 0 28px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.78rem; color: #1e293b; background: #ffffff; width: 220px; outline: none; transition: all 0.2s ease;">
                <button type="button" id="completedCollegeSearchBtn" aria-label="Search" title="Search" style="position: absolute; right: 2px; width: 26px; height: 26px; border: none; background: transparent; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; border-radius: 6px;">
                    <i class="ri-arrow-right-s-line" style="font-size: 1rem;"></i>
                </button>
            </div>
            <div class="acad-filter-box">
                <label for="completedProjectTypeFilter">Stream:</label>
                <select id="completedProjectTypeFilter" class="filter-select compact-select">
                    <?php if ($isExtensionDirector): ?>
                        <option value="extension">Extension Only</option>
                    <?php elseif ($isResearchDirector): ?>
                        <option value="research">Research Only</option>
                    <?php else: ?>
                        <option value="all">All Projects</option>
                        <option value="research">Research</option>
                        <option value="extension">Extension</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Custom Clean Scroll Container -->
    <div id="completedCollegesList" class="custom-clean-scrollbar" style="flex: 1; min-height: 250px; max-height: 295px; overflow-y: auto; padding-right: 4px; display: flex; flex-direction: column; gap: 9px;">
        <?php if (empty($completedColleges)): ?>
            <div style="padding: 30px 10px; text-align: center; color: #64748b; font-size: 0.85rem;">
                <i class="ri-inbox-line" style="font-size: 1.8rem; color: #cbd5e1; display: block; margin-bottom: 6px;"></i>
                No completed projects found
            </div>
        <?php else: ?>
            <?php foreach ($completedColleges as $completedCollege): ?>
                <div class="acad-college-group">
                    <div class="acad-college-header">
                        <div class="acad-college-name-wrap">
                            <span class="acad-college-acronym"><?php echo h(getCollegeCodeTag($completedCollege['department_name'])); ?></span>
                            <h3 class="acad-college-name"><?php echo h($completedCollege['department_name']); ?></h3>
                        </div>
                        <span class="acad-college-count"><?php echo count($completedCollege['projects']); ?> completed</span>
                    </div>
                    <div class="acad-project-list">
                        <?php foreach ($completedCollege['projects'] as $completedProject): ?>
                            <?php
                                $isResearch = ($completedProject['type'] ?? '') === 'Research';
                                $projId = (int) ($completedProject['id'] ?? 0);
                            ?>
                            <div class="acad-project-row" role="button" tabindex="0" onclick='openQuickPreview(<?php echo htmlspecialchars(json_encode($completedProject), ENT_QUOTES, "UTF-8"); ?>, <?php echo json_encode($completedProject["submitter_name"] ?? "Faculty Proponent"); ?>, <?php echo json_encode($completedCollege["department_name"]); ?>)' title="Click to view file & details for <?php echo h($completedProject['title']); ?>">
                                <div style="display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1;">
                                    <span class="acad-project-type <?php echo $isResearch ? 'type-research' : 'type-extension'; ?>">
                                        <?php echo h($completedProject['type']); ?>
                                    </span>
                                    <span class="acad-project-title">
                                        <?php echo h($completedProject['title']); ?>
                                    </span>
                                </div>
                                <button type="button" class="acad-view-file-btn">
                                    <i class="ri-file-text-line" style="font-size: 0.76rem;"></i>
                                    <span>View File</span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Card Bottom Bar -->
    <div class="acad-card-footer">
        <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600;"><i class="ri-shield-check-line" style="color: #15803d; font-size: 0.9rem;"></i> Institutional Completion Registry</span>
        <strong id="completedTotalBadge" style="color: #0f172a; font-weight: 800;"><?php echo count($completedColleges); ?> Active Colleges · <?php echo $totalCompletedProjectsCount; ?> Completed</strong>
    </div>
</article>

<section class="panel">
    <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div>
            <h2 id="recordsPanelTitle" style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #0f172a; display: inline-flex; align-items: center; gap: 8px; letter-spacing: -0.015em;">
                <i class="ri-file-list-3-line" style="color: #15803d; font-size: 1.25rem;"></i>
                <span><?php echo $archived ? 'Archived Records' : 'All Records'; ?></span>
            </h2>
            <span id="recordsPanelSubtitle" style="display: block; font-size: 0.82rem; color: #64748b; font-weight: 500; margin-top: 3px;">Open a category card above to view its documents.</span>
        </div>
    </div>
    <div class="table-scroll">
        <?php render_record_selection('recordsTable', 'document', $archived ? ['unarchive' => 'Unarchive Selected'] : ['archive' => 'Archive Selected'], false, $archived); ?>
        <table class="data-table portal-table" id="recordsTable">
            <thead><tr><th><label class="select-all-control"><input form="bulk-recordsTable" type="checkbox" aria-label="Select all records" data-select-all="recordsTable"><span>Select all</span></label></th><th>Document</th><th>Author</th><th>College</th><th>Date</th><th>Status</th><th>Assigned</th><th>Uploaded By</th><th>Manage</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr data-table-row data-status="<?php echo h($doc['status']); ?>" data-category="<?php echo h($doc['category_name']); ?>">
                    <td><input form="bulk-recordsTable" type="checkbox" name="ids[]" aria-label="Select record <?php echo (int) $doc['id']; ?>" value="<?php echo (int) $doc['id']; ?>" data-row-select="recordsTable"></td>
                    <td><?php echo h($doc['title']); ?><br><small><?php echo h($doc['category_name']); ?> | <?php echo h($doc['original_filename']); ?></small><?php if ($doc['status_remarks']): ?><br><small>Remarks: <?php echo h($doc['status_remarks']); ?></small><?php endif; ?></td>
                    <td><?php echo h($doc['author']); ?></td>
                    <td><?php echo h($doc['department_name']); ?></td>
                    <td><?php echo h(date('M d, Y', strtotime($doc['submission_date']))); ?></td>
                    <td><span class="status-pill status-<?php echo h(statusClass($doc['status'])); ?>"><?php echo h($doc['status'] === 'Declined' ? 'Rejected' : $doc['status']); ?></span></td>
                    <td><?php echo h($doc['assignee'] ?? 'Unassigned'); ?></td>
                    <td><?php echo h($doc['uploader']); ?></td>
                    <td>
                        <button class="primary-btn action-btn" type="button" data-open-modal="manage-<?php echo (int) $doc['id']; ?>"><i class="ri-more-2-fill"></i><span>Manage</span></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$documents): ?><tr><td colspan="9">No records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>


<?php if ($archived): ?>
<section class="panel">
    <div class="panel-heading"><h2>Archived Announcements</h2><span><?php echo count($archivedAnnouncements); ?> archived announcement<?php echo count($archivedAnnouncements) === 1 ? '' : 's'; ?></span></div>
    <div class="table-scroll">
        <?php render_record_selection('archivedAnnouncementsTable', 'announcement', ['restore' => 'Restore Selected']); ?>
        <table class="data-table portal-table" id="archivedAnnouncementsTable">
            <thead><tr><th><label class="select-all-control"><input form="bulk-archivedAnnouncementsTable" type="checkbox" aria-label="Select all archived announcements" data-select-all="archivedAnnouncementsTable"><span>Select all</span></label></th><th>Announcement</th><th>Audience</th><th>Archived Status</th><th>Posted By</th><th>Date Posted</th><th>Manage</th></tr></thead>
            <tbody>
                <?php foreach ($archivedAnnouncements as $announcement): ?>
                    <tr data-table-row data-status="Archived">
                        <td><input form="bulk-archivedAnnouncementsTable" type="checkbox" name="ids[]" aria-label="Select archived announcement <?php echo (int) $announcement['id']; ?>" value="<?php echo (int) $announcement['id']; ?>" data-row-select="archivedAnnouncementsTable"></td>
                        <td><?php echo h($announcement['title']); ?><br><small><?php echo h($announcement['content']); ?></small></td>
                        <td><span class="dept-pill"><?php echo h($announcement['audience']); ?></span></td>
                        <td><span class="status-pill status-archived">Archived</span></td>
                        <td><?php echo h($announcement['fullname']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($announcement['created_at']))); ?></td>
                        <td>
                            <form method="post">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="announcement_restore">
                                <input type="hidden" name="id" value="<?php echo (int) $announcement['id']; ?>">
                                <button class="ghost-btn action-btn"><i class="ri-arrow-go-back-line"></i><span>Retrieve</span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$archivedAnnouncements): ?><tr><td colspan="7">No archived announcements found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php
$docIds = array_filter(array_map(static fn($d) => (int) ($d['id'] ?? 0), $documents));
$docVersionsMap = [];
$docTimelineMap = [];
if (!empty($docIds)) {
    $placeholders = implode(',', array_fill(0, count($docIds), '?'));
    try {
        $vStmt = db()->prepare("SELECT v.*, u.fullname FROM document_versions v LEFT JOIN users u ON u.id = v.modified_by WHERE v.document_id IN ($placeholders) ORDER BY v.created_at DESC");
        $vStmt->execute($docIds);
        foreach ($vStmt->fetchAll() as $v) {
            $docVersionsMap[(int) $v['document_id']][] = $v;
        }
    } catch (Throwable $e) {}

    try {
        $tStmt = db()->prepare("SELECT r.*, u.fullname FROM review_logs r LEFT JOIN users u ON u.id = r.user_id WHERE r.entity_type = 'document' AND r.entity_id IN ($placeholders) ORDER BY r.created_at DESC");
        $tStmt->execute($docIds);
        foreach ($tStmt->fetchAll() as $t) {
            $docTimelineMap[(int) $t['entity_id']][] = $t;
        }
    } catch (Throwable $e) {}
}
?>

<?php foreach ($documents as $doc): ?>
    <div class="modal" id="manage-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card record-manage-modal"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Manage Record</h2><p><?php echo h($doc['title']); ?></p><div class="record-modal-actions">
        <?php if (record_is_previewable($doc['filename'])): ?><button class="ghost-btn action-btn" type="button" data-open-modal="preview-<?php echo (int) $doc['id']; ?>" data-dismiss-modal><i class="ri-eye-line"></i><span>Preview</span></button><?php endif; ?>
        <a class="ghost-btn action-btn" href="<?php echo h(record_file_url($doc['filename'], (int) $doc['id'])); ?>"><i class="ri-download-line"></i><span>Download</span></a>
        <button class="ghost-btn action-btn" type="button" data-open-modal="timeline-<?php echo (int) $doc['id']; ?>" data-dismiss-modal><i class="ri-time-line"></i><span>Timeline</span></button>
        <button class="ghost-btn action-btn" type="button" data-open-modal="versions-<?php echo (int) $doc['id']; ?>" data-dismiss-modal><i class="ri-git-branch-line"></i><span>Versions</span></button>
        <?php if (!$archived): ?>
            <button class="primary-btn action-btn" type="button" data-open-modal="status-<?php echo (int) $doc['id']; ?>" data-dismiss-modal><i class="ri-checkbox-circle-line"></i><span>Review</span></button>
            <form method="post" class="record-archive-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><input type="hidden" name="reason" value="Archived from Record Management"><button class="action-btn btn-archive-natural" type="submit"><i class="ri-download-2-line"></i><span>Archive</span></button></form>
        <?php else: ?>
            <button class="ghost-btn action-btn" type="button" data-open-modal="restore-<?php echo (int) $doc['id']; ?>" data-dismiss-modal><i class="ri-arrow-go-back-line"></i><span>Retrieve</span></button>
        <?php endif; ?>
    </div></article></div>
    <div class="modal" id="status-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Review Record</h2><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><label class="field"><span>Status</span><select name="status"><?php foreach (['Pending', 'Under Review', 'Approved', 'Declined'] as $status): ?><option <?php echo $doc['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label><label class="field"><span>Approval / Rejection Remarks</span><textarea name="remarks" rows="4"><?php echo h($doc['status_remarks']); ?></textarea></label><button class="primary-btn"><i class="ri-save-line"></i> Save Review</button></form></div>
    <div class="modal" id="restore-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><form class="modal-card" method="post"><?php echo csrf_field(); ?><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Retrieve Record</h2><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>"><p>Retrieve this archived record to the active workflow?</p><button class="primary-btn"><i class="ri-arrow-go-back-line"></i> Retrieve Record</button></form></div>
    <div class="modal" id="timeline-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Review Timeline</h2><div class="timeline-list"><?php $tLogs = $docTimelineMap[(int) $doc['id']] ?? []; foreach ($tLogs as $log): ?><div><strong><?php echo h($log['action']); ?></strong><p><?php echo h($log['remarks'] ?: 'No remarks.'); ?></p><small><?php echo h(($log['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($log['created_at']))); ?></small></div><?php endforeach; ?><div><strong>Uploaded</strong><p><?php echo h($doc['description'] ?: 'Record submitted.'); ?></p><small><?php echo h(date('M d, Y h:i A', strtotime($doc['created_at']))); ?></small></div></div></article></div>
    <div class="modal" id="versions-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2>Version History</h2><div class="timeline-list"><?php $versions = $docVersionsMap[(int) $doc['id']] ?? []; foreach ($versions as $version): ?><div><strong><?php echo h($version['version_label']); ?></strong><p><a href="<?php echo h(record_file_url($version['filename'], (int) $version['document_id'], (int) $version['id'])); ?>">Download version</a></p><small><?php echo h(($version['fullname'] ?? 'System') . ' | ' . date('M d, Y h:i A', strtotime($version['created_at']))); ?></small></div><?php endforeach; ?></div></article></div>
    <?php if (record_is_previewable($doc['filename'])): ?><div class="modal" id="preview-<?php echo (int) $doc['id']; ?>" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card preview-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><h2><?php echo h($doc['title']); ?></h2><?php $ext = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION)); if ($ext === 'pdf'): ?><iframe class="document-preview" data-src="<?php echo h(record_file_url($doc['filename'], (int) $doc['id'])); ?>" src="about:blank"></iframe><?php else: ?><img class="document-preview-image" data-src="<?php echo h(record_file_url($doc['filename'], (int) $doc['id'])); ?>" alt="<?php echo h($doc['title']); ?>"><?php endif; ?></article></div><?php endif; ?>
<?php endforeach; ?>

<!-- Quick Preview Modal for Completed Submissions -->
<div class="modal" id="submissionQuickPreviewDialog" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 22px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span id="quickPreviewTypeBadge" class="badge" style="font-weight: 700; font-size: 0.78rem; padding: 3px 9px; border-radius: 6px;"></span>
                <span id="quickPreviewStatusBadge" class="badge" style="font-weight: 700; font-size: 0.78rem; padding: 3px 10px; border-radius: 999px;"></span>
            </div>
            <button type="button" id="closeQuickPreviewDialog" data-close-modal aria-label="Close preview" style="width: 30px; height: 30px; border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; color: #64748b; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div style="padding: 22px 24px; display: flex; flex-direction: column; gap: 14px; max-height: calc(85vh - 72px); overflow-y: auto;">
            <div>
                <h3 id="quickPreviewTitle" style="margin: 0 0 6px; color: #0f172a; font-size: 1.18rem; font-weight: 800; line-height: 1.4;"></h3>
                <p id="quickPreviewMeta" style="margin: 0; color: #64748b; font-size: 0.84rem; line-height: 1.5;"></p>
            </div>
            <div id="quickPreviewDescBox" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; font-size: 0.88rem; color: #334155; line-height: 1.6;">
                <strong id="quickPreviewDescLabel" style="display: block; margin-bottom: 6px; color: #0f172a; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.03em;">Abstract / Details</strong>
                <p id="quickPreviewDesc" style="margin: 0; white-space: pre-wrap; word-break: break-word;"></p>
            </div>

            <!-- Attached File Showcase Card -->
            <div id="quickPreviewFileCard" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                    <span style="width: 38px; height: 38px; border-radius: 8px; background: #dcfce7; color: #15803d; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                        <i class="ri-file-pdf-fill"></i>
                    </span>
                    <div style="min-width: 0;">
                        <strong id="quickPreviewFileName" style="display: block; font-size: 0.85rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            Research-Documentation.pdf
                        </strong>
                        <span style="font-size: 0.72rem; color: #15803d; font-weight: 600;">
                            <i class="ri-checkbox-circle-fill"></i> Official Verified Academic Document
                        </span>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end; padding-top: 12px; border-top: 1px solid #e2e8f0; flex-wrap: wrap;">
                <a id="quickPreviewDownloadBtn" href="#" class="ghost-btn action-btn" style="height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 0 16px; font-size: 0.86rem; font-weight: 600; color: #1e293b !important; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s ease;">
                    <i class="ri-download-cloud-line" style="font-size: 0.95rem;"></i> <span>Download File</span>
                </a>
                <a id="quickPreviewViewFileBtn" href="#" target="_blank" rel="noopener" class="primary-btn action-btn" style="height: 38px; min-height: 38px; max-height: 38px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 0 18px; font-size: 0.86rem; font-weight: 700; color: #ffffff !important; background: #15803d !important; border: 1px solid #15803d !important; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 6px rgba(21, 128, 61, 0.25); transition: all 0.2s ease;">
                    <i class="ri-file-search-line" style="font-size: 0.95rem; line-height: 1; color: #ffffff !important;"></i> <span style="color: #ffffff !important; font-size: 0.86rem; font-weight: 700;">View File</span>
                </a>
            </div>
        </div>
    </article>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const previewDialog = document.getElementById('submissionQuickPreviewDialog');
    const closePreviewDialog = document.getElementById('closeQuickPreviewDialog');
    const completedProjectFilter = document.getElementById('completedProjectTypeFilter');
    const completedCollegesList = document.getElementById('completedCollegesList');
    const completedTotalBadgeEl = document.getElementById('completedTotalBadge');

    function getCollegeCodeTag(deptName) {
        const s = String(deptName || '').toLowerCase();
        if (s.includes('ccst') || s.includes('computer')) return 'CCST';
        if (s.includes('coe') || s.includes('engineering')) return 'COE';
        if (s.includes('conahs') || s.includes('nursing')) return 'CONAHS';
        if (s.includes('cas') || s.includes('arts')) return 'CAS';
        if (s.includes('cba') || s.includes('business')) return 'CBA';
        if (s.includes('cte') || s.includes('teacher') || s.includes('education')) return 'CTE';
        if (s.includes('cthm') || s.includes('tourism') || s.includes('hospitality')) return 'CTHM';
        if (s.includes('coa') || s.includes('accountancy')) return 'COA';
        if (s.includes('cpag') || s.includes('public administration')) return 'CPAG';
        if (s.includes('chk') || s.includes('human kinetics') || s.includes('cpe') || s.includes('physical education')) return 'CPE';
        return s.trim().slice(0, 4).toUpperCase();
    }

    function createDocumentPdfBlob(title, author, dept, desc) {
        const safeTitle = (title || 'Academic Project').replace(/[()\\]/g, '');
        const safeAuthor = (author || 'Faculty Proponent').replace(/[()\\]/g, '');
        const safeDept = (dept || 'Academic Department').replace(/[()\\]/g, '');
        const safeDesc = (desc || 'Completed academic project under the institutional repository.').replace(/[()\\]/g, '').slice(0, 300);

        const stream = [
            'BT',
            '/F1 16 Tf',
            '50 720 Td',
            `(${safeTitle.slice(0, 60)}) Tj`,
            '/F1 10 Tf',
            '0 -28 Td',
            `(Author / Submitter: ${safeAuthor}) Tj`,
            '0 -18 Td',
            `(Department / College: ${safeDept}) Tj`,
            '0 -18 Td',
            '(Classification: Official Verified Academic Record - Completed) Tj',
            '0 -32 Td',
            '/F1 12 Tf',
            '(PROJECT DETAILS / ABSTRACT:) Tj',
            '/F1 10 Tf',
            '0 -20 Td',
            `(${safeDesc.slice(0, 95)}) Tj`,
            safeDesc.length > 95 ? `0 -16 Td (${safeDesc.slice(95, 190)}) Tj` : '',
            safeDesc.length > 190 ? `0 -16 Td (${safeDesc.slice(190, 285)}) Tj` : '',
            'ET'
        ].filter(Boolean).join('\n');

        const streamLen = new TextEncoder().encode(stream).length;
        let out = '%PDF-1.4\n';
        const offsets = [];
        offsets.push(out.length);
        out += '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n';
        offsets.push(out.length);
        out += '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n';
        offsets.push(out.length);
        out += '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n';
        offsets.push(out.length);
        out += `4 0 obj\n<< /Length ${streamLen} >>\nstream\n${stream}\nendstream\nendobj\n`;
        offsets.push(out.length);
        out += '5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n';
        const xrefOffset = out.length;
        out += 'xref\n0 6\n0000000000 65535 f \n';
        for (const offset of offsets) {
            out += String(offset).padStart(10, '0') + ' 00000 n \n';
        }
        out += `trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;
        return new Blob([out], { type: 'application/pdf' });
    }

    function downloadAcademicFile(fileUrl, fileName, title, author, dept, desc) {
        const isRealPdfUrl = fileUrl && fileUrl !== '#' && !fileUrl.includes('.php#') && (fileUrl.includes('.pdf') || fileUrl.includes('download-document.php'));
        if (isRealPdfUrl) {
            let dlUrl = fileUrl;
            if (dlUrl.includes('download-document.php') && !dlUrl.includes('download=1')) {
                dlUrl += (dlUrl.includes('?') ? '&' : '?') + 'download=1';
            }
            const a = document.createElement('a');
            a.href = dlUrl;
            a.download = fileName || 'Academic-Document.pdf';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            return;
        }
        const blob = createDocumentPdfBlob(title, author, dept, desc);
        const blobUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = fileName || 'Academic-Document.pdf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(blobUrl), 10000);
    }

    function viewAcademicFile(fileUrl, title, author, dept, desc) {
        const isRealPdfUrl = fileUrl && fileUrl !== '#' && !fileUrl.includes('.php#') && (fileUrl.includes('.pdf') || fileUrl.includes('download-document.php'));
        if (isRealPdfUrl) {
            window.open(fileUrl, '_blank', 'noopener');
            return;
        }
        const blob = createDocumentPdfBlob(title, author, dept, desc);
        const blobUrl = URL.createObjectURL(blob);
        window.open(blobUrl, '_blank', 'noopener');
    }

    window.openQuickPreview = function (sub, personName, deptName) {
        if (!previewDialog) return;
        const isResearch = (sub.type || '') === 'Research';
        document.getElementById('quickPreviewTypeBadge').textContent = sub.type || 'Project';
        document.getElementById('quickPreviewTypeBadge').style.background = isResearch ? '#f0fdf4' : '#f3e8ff';
        document.getElementById('quickPreviewTypeBadge').style.color = isResearch ? '#15803d' : '#7c3aed';

        const statusEl = document.getElementById('quickPreviewStatusBadge');
        const status = sub.status || 'Completed';
        statusEl.textContent = status;
        statusEl.style.background = (status === 'Completed' || status === 'Approved') ? '#f0fdf4' : '#fffbeb';
        statusEl.style.color = (status === 'Completed' || status === 'Approved') ? '#15803d' : '#b45309';

        document.getElementById('quickPreviewTitle').textContent = sub.title || '';
        const pName = personName || sub.submitter_name || 'Faculty Proponent';
        const dName = deptName || sub.department_name || 'Academic Department';
        document.getElementById('quickPreviewMeta').textContent = `Submitter: ${pName} (${dName}) | Status: ${status}`;
        document.getElementById('quickPreviewDesc').textContent = sub.description || 'Completed institutional project document.';

        const subId = encodeURIComponent(sub.id || '');
        const targetUrl = isResearch
            ? `research.php?highlight=${subId}#proposal-${subId}`
            : `extension.php?highlight=${subId}#project-${subId}`;
        const fileUrl = sub.file_url || sub.document_url || targetUrl;
        const fileName = sub.file_name || (sub.title ? sub.title.replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 32) + '-Report.pdf' : 'Academic-Document.pdf');

        const fileNameEl = document.getElementById('quickPreviewFileName');
        if (fileNameEl) fileNameEl.textContent = fileName;

        const viewFileBtn = document.getElementById('quickPreviewViewFileBtn');
        if (viewFileBtn) {
            viewFileBtn.onclick = (e) => {
                e.preventDefault();
                viewAcademicFile(fileUrl, sub.title, pName, dName, sub.description);
            };
        }

        const downloadBtn = document.getElementById('quickPreviewDownloadBtn');
        if (downloadBtn) {
            downloadBtn.onclick = (e) => {
                e.preventDefault();
                downloadAcademicFile(fileUrl, fileName, sub.title, pName, dName, sub.description);
            };
        }

        if (window.OVPRE && OVPRE.openModal) OVPRE.openModal('submissionQuickPreviewDialog');
        else previewDialog.classList.add('open');
    };

    if (closePreviewDialog && previewDialog) {
        closePreviewDialog.addEventListener('click', () => {
            if (window.OVPRE && OVPRE.closeModal) OVPRE.closeModal('submissionQuickPreviewDialog');
            else previewDialog.classList.remove('open');
        });
        previewDialog.addEventListener('click', (e) => {
            if (e.target === previewDialog) previewDialog.classList.remove('open');
        });
    }

    if (completedProjectFilter && completedCollegesList) {
        const completedCollegeRows = <?php echo json_encode($completedColleges, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const searchInput = document.getElementById('completedCollegeSearchInput');
        const searchBtn = document.getElementById('completedCollegeSearchBtn');

        const renderCompletedColleges = () => {
            const selectedType = completedProjectFilter.value;
            const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            completedCollegesList.replaceChildren();
            let visibleCollegeCount = 0;
            let totalProjectsCount = 0;

            completedCollegeRows.forEach(college => {
                const deptMatches = !query || 
                    college.department_name.toLowerCase().includes(query) || 
                    getCollegeCodeTag(college.department_name).toLowerCase().includes(query);

                const projects = college.projects.filter(project => {
                    const matchesStream = selectedType === 'all' || project.type.toLowerCase() === selectedType;
                    if (!matchesStream) return false;
                    if (!query) return true;
                    if (deptMatches) return true;
                    return (project.title && project.title.toLowerCase().includes(query)) ||
                           (project.lead && project.lead.toLowerCase().includes(query)) ||
                           (project.submitter_name && project.submitter_name.toLowerCase().includes(query));
                });
                if (!projects.length) return;
                visibleCollegeCount++;
                totalProjectsCount += projects.length;

                const card = document.createElement('div');
                card.className = 'acad-college-group';

                const heading = document.createElement('div');
                heading.className = 'acad-college-header';

                const collegeLeft = document.createElement('div');
                collegeLeft.className = 'acad-college-name-wrap';

                const acronymBadge = document.createElement('span');
                acronymBadge.className = 'acad-college-acronym';
                acronymBadge.textContent = getCollegeCodeTag(college.department_name);

                const collegeName = document.createElement('h3');
                collegeName.className = 'acad-college-name';
                collegeName.textContent = college.department_name;
                collegeLeft.append(acronymBadge, collegeName);

                const projectCount = document.createElement('span');
                projectCount.className = 'acad-college-count';
                projectCount.textContent = `${projects.length} completed`;
                heading.append(collegeLeft, projectCount);

                const projectContainer = document.createElement('div');
                projectContainer.className = 'acad-project-list';

                projects.forEach(project => {
                    const isResearch = project.type === 'Research';
                    const pill = document.createElement('div');
                    pill.className = 'acad-project-row';
                    pill.role = 'button';
                    pill.tabIndex = 0;
                    pill.title = `Click to view file & details for: ${project.title}`;

                    const leftWrap = document.createElement('div');
                    leftWrap.style.cssText = 'display:flex;align-items:center;gap:8px;min-width:0;flex:1;';

                    const typeBadge = document.createElement('span');
                    typeBadge.className = `acad-project-type ${isResearch ? 'type-research' : 'type-extension'}`;
                    typeBadge.textContent = project.type;

                    const titleSpan = document.createElement('span');
                    titleSpan.className = 'acad-project-title';
                    titleSpan.textContent = project.title;
                    leftWrap.append(typeBadge, titleSpan);

                    const viewFileBtn = document.createElement('button');
                    viewFileBtn.type = 'button';
                    viewFileBtn.className = 'acad-view-file-btn';
                    viewFileBtn.innerHTML = '<i class="ri-file-text-line" style="font-size:0.76rem;"></i><span>View File</span>';

                    pill.append(leftWrap, viewFileBtn);

                    const triggerPreview = (e) => {
                        if (e) e.stopPropagation();
                        window.openQuickPreview(project, project.submitter_name || 'Faculty Proponent', college.department_name);
                    };

                    pill.addEventListener('click', triggerPreview);
                    pill.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            triggerPreview(e);
                        }
                    });

                    projectContainer.appendChild(pill);
                });

                card.append(heading, projectContainer);
                completedCollegesList.appendChild(card);
            });

            if (completedTotalBadgeEl) {
                if (query) {
                    completedTotalBadgeEl.textContent = `${visibleCollegeCount} College${visibleCollegeCount === 1 ? '' : 's'} · ${totalProjectsCount} Completed (Filtered)`;
                } else {
                    completedTotalBadgeEl.textContent = `${visibleCollegeCount} Active College${visibleCollegeCount === 1 ? '' : 's'} · ${totalProjectsCount} Completed`;
                }
            }

            if (!visibleCollegeCount) {
                completedCollegesList.innerHTML = `<div style="padding: 30px 10px; text-align: center; color: #64748b; font-size: 0.85rem;"><i class="ri-search-line" style="font-size: 1.8rem; display: block; margin-bottom: 6px; color: #94a3b8;"></i>No completed projects found${query ? ' matching "' + query.replace(/["<>]/g, '') + '"' : ' for selected stream'}.</div>`;
            }
        };

        completedProjectFilter.addEventListener('change', renderCompletedColleges);
        if (searchInput) {
            searchInput.addEventListener('input', renderCompletedColleges);
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    renderCompletedColleges();
                }
            });
        }
        if (searchBtn) {
            searchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                renderCompletedColleges();
                if (searchInput) searchInput.focus();
            });
        }
    }
});
</script>

<?php renderFooter(); ?>
