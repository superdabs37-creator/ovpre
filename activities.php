<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Admin', ROLE_RESEARCH_DIRECTOR, 'Research Coordinator']);
ensure_activity_date_to_column();
ensure_activity_archived_at_column();
if (role_access_group((string) ($user['role'] ?? '')) === ROLE_RESEARCH_COORDINATOR) {
    redirect_to('research-coordinator/calendar.php');
}
verify_csrf();

$activityTypes = ['Seminar', 'Training', 'Workshop', 'Community Outreach', 'Project Defense'];

function valid_activity_type(string $type, array $activityTypes): string {
    return in_array($type, $activityTypes, true) ? $type : 'Seminar';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if (in_array($action, ['create', 'update'], true)) {
        $dateFrom = (string) ($_POST['date_from'] ?? '');
        $dateTo = (string) ($_POST['date_to'] ?? '');
        $parsedDateFrom = DateTime::createFromFormat('!Y-m-d', $dateFrom);
        $parsedDateTo = DateTime::createFromFormat('!Y-m-d', $dateTo);
        if (!$parsedDateFrom || $parsedDateFrom->format('Y-m-d') !== $dateFrom || !$parsedDateTo || $parsedDateTo->format('Y-m-d') !== $dateTo || $dateTo < $dateFrom) {
            flash('error', 'Choose a valid date range. Date To must be on or after Date From.');
            redirect_to('activities.php');
        }
    }

    if ($action === 'create') {
        db()->prepare('INSERT INTO activities (title, activity_type, date, date_to, venue, description, approval_status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, "Approved", ?, NOW())')
            ->execute([
                trim((string) $_POST['title']),
                valid_activity_type((string) ($_POST['activity_type'] ?? 'Seminar'), $activityTypes),
                $_POST['date_from'],
                $_POST['date_to'],
                trim((string) $_POST['venue']),
                trim((string) ($_POST['description'] ?? '')),
                $user['id'],
            ]);
        log_action((int) $user['id'], 'Created activity: ' . trim((string) $_POST['title']));
        flash('success', 'Activity saved.');
        redirect_to('activities.php');
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE activities SET title = ?, activity_type = ?, date = ?, date_to = ?, venue = ?, description = ? WHERE id = ?')
            ->execute([
                trim((string) $_POST['title']),
                valid_activity_type((string) ($_POST['activity_type'] ?? 'Seminar'), $activityTypes),
                $_POST['date_from'],
                $_POST['date_to'],
                trim((string) $_POST['venue']),
                trim((string) ($_POST['description'] ?? '')),
                $id,
            ]);
        log_action((int) $user['id'], 'Updated activity #' . $id);
        flash('success', 'Activity updated.');
        redirect_to('activities.php');
    }

    if ($action === 'archive') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE activities SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL');
        $stmt->execute([$id]);
        if ($stmt->rowCount()) {
            log_action((int) $user['id'], 'Archived activity #' . $id);
            flash('success', 'Activity archived.');
        } else {
            flash('error', 'That activity could not be found or is already archived.');
        }
        redirect_to('activities.php');
    }

    if ($action === 'delete') {
        if (!in_array(($user['role'] ?? ''), ['Super Admin', 'OVPRE Admin'], true)) {
            http_response_code(403);
            exit('Only authorized administrators can permanently delete activities.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT title FROM activities WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $activity = $stmt->fetch();
        if (!$activity) {
            flash('error', 'That activity could not be found.');
        } else {
            db()->prepare('DELETE FROM activities WHERE id = ?')->execute([$id]);
            log_action((int) $user['id'], 'Permanently deleted activity #' . $id . ': ' . $activity['title']);
            flash('success', 'Activity permanently deleted.');
        }
        $isArchivedRedirect = (($_GET['archived'] ?? '') === '1') || (($_POST['archived_view'] ?? '') === '1');
        redirect_to('activities.php' . ($isArchivedRedirect ? '?archived=1' : ''));
    }

    if ($action === 'restore') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE activities SET archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL')->execute([$id]);
        log_action((int) $user['id'], 'Retrieved archived activity #' . $id);
        flash('success', 'Activity retrieved and restored to the calendar.');
        redirect_to('activities.php?archived=1');
    }

    if ($action === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['ids'] ?? []), static fn($id) => $id > 0)));
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');
        $isArchivedView = (isset($_GET['archived']) && $_GET['archived'] === '1') || (isset($_POST['archived_view']) && $_POST['archived_view'] === '1');

        if (!$ids) {
            flash('error', 'No activities selected.');
            redirect_to('activities.php' . ($isArchivedView ? '?archived=1' : ''));
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));

        if ($bulkAction === 'archive') {
            db()->prepare("UPDATE activities SET archived_at = NOW() WHERE archived_at IS NULL AND id IN ($marks)")->execute($ids);
            foreach ($ids as $activityId) {
                log_action((int) $user['id'], 'Bulk archived activity #' . $activityId);
            }
            flash('success', count($ids) . ' activity record(s) archived.');
            redirect_to('activities.php');
        } elseif ($bulkAction === 'restore') {
            db()->prepare("UPDATE activities SET archived_at = NULL WHERE archived_at IS NOT NULL AND id IN ($marks)")->execute($ids);
            foreach ($ids as $activityId) {
                log_action((int) $user['id'], 'Bulk restored activity #' . $activityId);
            }
            flash('success', count($ids) . ' activity record(s) restored.');
            redirect_to('activities.php?archived=1');
        } elseif ($bulkAction === 'delete') {
            if (!in_array(($user['role'] ?? ''), ['Super Admin', 'OVPRE Admin'], true)) {
                http_response_code(403);
                exit('Only authorized administrators can delete activities.');
            }
            db()->prepare("DELETE FROM activities WHERE id IN ($marks)")->execute($ids);
            foreach ($ids as $activityId) {
                log_action((int) $user['id'], 'Bulk permanently deleted activity #' . $activityId);
            }
            flash('success', count($ids) . ' activity record(s) permanently deleted.');
            redirect_to('activities.php' . ($isArchivedView ? '?archived=1' : ''));
        } else {
            flash('error', 'Invalid bulk action.');
            redirect_to('activities.php' . ($isArchivedView ? '?archived=1' : ''));
        }
    }

    if ($action === 'review') {
        if (($user['role'] ?? '') !== 'Super Admin') {
            http_response_code(403);
            exit('Only the Super Admin can approve schedule requests.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Choose a valid schedule request.');
            redirect_to('activities.php');
        }
        $requestStmt = db()->prepare('SELECT title, created_by FROM activities WHERE id = ? AND archived_at IS NULL AND approval_status IN ("Pending", "Rejected") LIMIT 1');
        $requestStmt->execute([$id]);
        $request = $requestStmt->fetch();
        if (!$request) {
            flash('error', 'This schedule request is no longer pending.');
            redirect_to('activities.php');
        }
        db()->prepare('UPDATE activities SET approval_status = "Approved" WHERE id = ? AND archived_at IS NULL AND approval_status IN ("Pending", "Rejected")')->execute([$id]);
        if (!empty($request['created_by'])) {
            notify_user((int) $request['created_by'], 'Your activity schedule was approved and added to the calendar: ' . $request['title'], 'activities.php#activity-' . $id);
        }
        log_action((int) $user['id'], 'Approved calendar schedule request #' . $id);
        flash('success', 'Activity approved and added to the shared calendar.');
        redirect_to('activities.php');
    }

    if ($action === 'reject') {
        if (($user['role'] ?? '') !== 'Super Admin') {
            http_response_code(403);
            exit('Only the Super Admin can reject activities.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Choose a valid activity to reject.');
            redirect_to('activities.php');
        }
        $requestStmt = db()->prepare('SELECT title, created_by FROM activities WHERE id = ? AND archived_at IS NULL AND approval_status IN ("Pending", "Approved") LIMIT 1');
        $requestStmt->execute([$id]);
        $activity = $requestStmt->fetch();
        if (!$activity) {
            flash('error', 'This activity has already been rejected or no longer exists.');
            redirect_to('activities.php');
        }
        $rejectStmt = db()->prepare('UPDATE activities SET approval_status = "Rejected" WHERE id = ? AND archived_at IS NULL AND approval_status IN ("Pending", "Approved")');
        $rejectStmt->execute([$id]);
        if ($rejectStmt->rowCount() !== 1) {
            flash('error', 'This activity has already been reviewed. Refresh the page and try again.');
            redirect_to('activities.php');
        }
        if (!empty($activity['created_by'])) {
            notify_user((int) $activity['created_by'], 'Your activity schedule was rejected: ' . $activity['title'], 'activities.php');
        }
        log_action((int) $user['id'], 'Rejected calendar activity #' . $id);
        flash('success', 'Activity rejected and removed from the shared calendar.');
        redirect_to('activities.php');
    }

    if ($action === 'move') {
        header('Content-Type: application/json');
        $id = (int) ($_POST['id'] ?? 0);
        $date = $_POST['date'] ?? '';
        if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Invalid activity date.']);
            exit;
        }
        $activityStmt = db()->prepare('SELECT date, date_to FROM activities WHERE id = ? AND archived_at IS NULL LIMIT 1');
        $activityStmt->execute([$id]);
        $activity = $activityStmt->fetch();
        if (!$activity) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Activity not found.']);
            exit;
        }
        $duration = max(0, (int) ((strtotime($activity['date_to']) - strtotime($activity['date'])) / 86400));
        $newEndDate = date('Y-m-d', strtotime($date . ' +' . $duration . ' days'));
        db()->prepare('UPDATE activities SET date = ?, date_to = ? WHERE id = ?')->execute([$date, $newEndDate, $id]);
        log_action((int) $user['id'], 'Moved activity #' . $id . ' to ' . $date);
        echo json_encode(['ok' => true]);
        exit;
    }
}

$notice = flash();
$archived = ($_GET['archived'] ?? '') === '1';
$archiveCondition = $archived ? 'IS NOT NULL' : 'IS NULL';
$activities = db()->query('SELECT a.*, u.fullname AS creator_name FROM activities a LEFT JOIN users u ON u.id = a.created_by WHERE a.archived_at ' . $archiveCondition . ' ORDER BY a.date DESC')->fetchAll();
$activeCount = (int) db()->query('SELECT COUNT(*) FROM activities WHERE archived_at IS NULL')->fetchColumn();
$archivedCount = (int) db()->query('SELECT COUNT(*) FROM activities WHERE archived_at IS NOT NULL')->fetchColumn();
$approvedActivities = array_values(array_filter($activities, static fn(array $activity): bool => $activity['approval_status'] === 'Approved'));
$upcomingStmt = db()->prepare('SELECT * FROM activities WHERE approval_status = "Approved" AND archived_at IS NULL AND date_to >= CURDATE() ORDER BY date ASC LIMIT 6');
$upcomingStmt->execute();
$upcomingActivities = $upcomingStmt->fetchAll();

$eventColors = [
    'Seminar' => '#15803d',
    'Training' => '#2563eb',
    'Workshop' => '#0891b2',
    'Community Outreach' => '#b45309',
    'Project Defense' => '#7c3aed',
];
$toCalendarEvent = static function (array $activity) use ($eventColors): array {
    $type = $activity['activity_type'];
    return [
        'id' => (string) $activity['id'],
        'title' => $activity['title'],
        'start' => $activity['date'],
        'end' => date('Y-m-d', strtotime(($activity['date_to'] ?: $activity['date']) . ' +1 day')),
        'allDay' => true,
        'backgroundColor' => $eventColors[$type] ?? '#15803d',
        'borderColor' => $eventColors[$type] ?? '#15803d',
        'textColor' => '#ffffff',
        'extendedProps' => [
            'type' => $type,
            'approvalStatus' => $activity['approval_status'],
            'venue' => $activity['venue'],
            'description' => $activity['description'] ?? '',
            'date' => $activity['date'],
            'dateTo' => $activity['date_to'] ?: $activity['date'],
        ],
    ];
};
$calendarEvents = $archived ? [] : array_map($toCalendarEvent, $approvedActivities);
$activityEditorEvents = $archived ? [] : array_map($toCalendarEvent, $activities);

renderHeader($archived ? 'Archived Activities' : 'Calendar of Activities', 'activities');
?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<section class="module-header">
    <div class="module-icon"><i class="ri-calendar-event-line"></i></div>
    <div>
        <p>Schedule Tracking</p>
        <h2>Calendar of Activities</h2>
        <span>View activities by month, week, day, or list. Click an event to edit it, or drag it to reschedule.</span>
    </div>
</section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<script>
window.activityCalendarEvents = <?php echo json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
window.activityEditorEvents = <?php echo json_encode($activityEditorEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
window.activityCalendarCsrf = <?php echo json_encode(csrf_token()); ?>;
</script>

<?php if (!$archived): ?><section class="calendar-page-layout">
    <aside class="calendar-side panel">
        <div class="calendar-legend fullcalendar-legend" aria-label="Activity type legend">
            <span><i class="legend-dot seminar"></i> Seminar</span>
            <span><i class="legend-dot"></i> Training / Workshop</span>
            <span><i class="legend-dot outreach"></i> Community Outreach</span>
            <span><i class="legend-dot defense"></i> Project Defense</span>
        </div>

        <div class="panel-heading upcoming-heading"><h2>Upcoming</h2><span>Next 6</span></div>
        <div class="upcoming-list">
            <?php foreach ($upcomingActivities as $activity): ?>
                <article>
                    <div class="upcoming-date"><strong><?php echo h(date('d', strtotime($activity['date']))); ?></strong><span><?php echo h(date('M', strtotime($activity['date']))); ?></span></div>
                    <div>
                        <h3><?php echo h($activity['title']); ?></h3>
                        <p><?php echo h($activity['activity_type']); ?> | <?php echo h(date('M d', strtotime($activity['date'])) . ($activity['date_to'] !== $activity['date'] ? ' – ' . date('M d, Y', strtotime($activity['date_to'])) : '') . ' | ' . $activity['venue']); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$upcomingActivities): ?><p class="muted">No upcoming activities yet.</p><?php endif; ?>
        </div>
        <button class="primary-btn full-btn calendar-create-btn" type="button" data-create-activity><i class="ri-add-circle-line"></i> Add Activity</button>
    </aside>

    <article class="panel fullcalendar-panel">
        <div id="activityCalendar"></div>
    </article>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <h2><?php echo $archived ? 'Archived Activities' : 'All Activities'; ?></h2>
            <span><?php echo count($activities); ?> total activity<?php echo count($activities) === 1 ? '' : 'ies'; ?></span>
        </div>
        <div class="panel-heading-actions" style="display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-left:auto;">
            <div class="portal-table-tabs">
                <a class="portal-table-tab <?php echo !$archived ? 'active' : ''; ?>" href="activities.php">
                    <i class="ri-calendar-event-line"></i>
                    <span>Scheduled</span>
                    <span class="tab-count"><?php echo $activeCount; ?></span>
                </a>
                <a class="portal-table-tab <?php echo $archived ? 'active' : ''; ?>" href="activities.php?archived=1">
                    <i class="ri-archive-line"></i>
                    <span>Archived</span>
                    <span class="tab-count"><?php echo $archivedCount; ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Bulk Selection Toolbar for Activities -->
    <div class="table-selection-toolbar" data-selection-toolbar="activitiesTable" hidden>
        <span data-selection-count>0 selected</span>
        <form id="bulk-activitiesTable" method="post" data-bulk-selection-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk">
            <?php if ($archived): ?>
                <input type="hidden" name="archived_view" value="1">
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="restore" data-bulk-submit>
                    <i class="ri-arrow-go-back-line"></i><span>Restore Selected</span>
                </button>
            <?php else: ?>
                <button class="action-btn btn-archive-natural" type="submit" name="bulk_action" value="archive" data-bulk-submit onclick="return confirm('Archive selected activities from calendar?');">
                    <i class="ri-archive-line"></i><span>Archive Selected</span>
                </button>
            <?php endif; ?>
            <button class="action-btn btn-delete-natural" type="submit" name="bulk_action" value="delete" data-bulk-submit data-confirm-delete>
                <i class="ri-delete-bin-line"></i><span>Delete Selected</span>
            </button>
        </form>
    </div>

    <div class="table-scroll">
        <table class="data-table activities-table" id="activitiesTable">
            <thead>
                <tr>
                    <th class="table-checkbox-col">
                        <label class="select-all-control">
                            <input form="bulk-activitiesTable" type="checkbox" aria-label="Select all activities" data-select-all="activitiesTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Date</th>
                    <th>Activity</th>
                    <th>Type</th>
                    <th>Venue</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td class="table-checkbox-col">
                            <input form="bulk-activitiesTable" type="checkbox" name="ids[]" aria-label="Select activity <?php echo (int) $activity['id']; ?>" value="<?php echo (int) $activity['id']; ?>" data-row-select="activitiesTable">
                        </td>
                        <td><?php echo h(date('M d, Y', strtotime($activity['date'])) . ($activity['date_to'] && $activity['date_to'] !== $activity['date'] ? ' – ' . date('M d, Y', strtotime($activity['date_to'])) : '')); ?></td>
                        <td><strong><?php echo h($activity['title']); ?></strong></td>
                        <td><?php echo h($activity['activity_type']); ?></td>
                        <td><?php echo h($activity['venue']); ?></td>
                        <td><?php echo h($activity['creator_name'] ?? 'System Administrator'); ?></td>
                        <td><div class="activity-status-actions"><span class="status-pill status-<?php echo h(strtolower($activity['approval_status'])); ?>"><?php echo h($activity['approval_status']); ?></span></div></td>
                        <td>
                            <div class="archive-record-actions">
                                <?php if ($archived): ?>
                                    <form method="post" onsubmit="return confirm('Retrieve this activity and put it back on the calendar?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?php echo (int) $activity['id']; ?>">
                                        <button class="ghost-btn action-btn" type="submit" title="Retrieve Activity"><i class="ri-arrow-go-back-line"></i><span>Retrieve</span></button>
                                    </form>
                                <?php else: ?>
                                    <button class="ghost-btn action-btn" type="button" data-edit-activity="<?php echo (int) $activity['id']; ?>" title="Edit Activity"><i class="ri-edit-line"></i><span>Edit</span></button>
                                    <?php if ($activity['approval_status'] === 'Pending' && ($user['role'] ?? '') === 'Super Admin'): ?>
                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="review">
                                            <input type="hidden" name="id" value="<?php echo (int) $activity['id']; ?>">
                                            <button class="primary-btn action-btn" type="submit"><i class="ri-check-line"></i><span>Approve</span></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$activities): ?>
                    <tr><td colspan="8">No <?php echo $archived ? 'archived ' : ''; ?>activities recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if (!$archived): ?><div class="modal" id="activityEditorModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="activityModalTitle">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card activity-modal-card" style="width: min(100%, 720px); max-height: 90vh; overflow-y: auto;">
        <button class="modal-close" type="button" data-close-modal aria-label="Close activity editor"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="category-badge" id="activityModalMode">Activity</span>
                <h2 id="activityModalTitle" style="margin: 0; font-size: 1.3rem; font-weight: 800; color: #0f172a;">Edit Activity</h2>
            </div>
            <span id="activityStatusBadge" style="display: none; align-items: center; gap: 5px; padding: 4px 10px; font-size: 0.78rem; font-weight: 700; border-radius: 999px;"></span>
        </div>
        <form class="calendar-form" method="post" id="activityEditorForm" style="display: flex; flex-direction: column; gap: 14px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" id="activityAction" value="update">
            <input type="hidden" name="id" id="activityId">
            <input type="hidden" name="approval_status" value="Approved">
            <label class="field" style="margin: 0;">
                <span style="font-weight: 700; font-size: 0.84rem; color: #334155;">Activity Title</span>
                <input name="title" id="activityTitle" placeholder="e.g. Annual Faculty Research Colloquium" required style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 9px 12px; font-size: 0.88rem;">
            </label>
            <div class="form-grid" style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 12px;">
                <label class="field" style="margin: 0;">
                    <span style="font-weight: 700; font-size: 0.84rem; color: #334155;">Type</span>
                    <select name="activity_type" id="activityType" style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 9px 12px; font-size: 0.88rem; background: #ffffff;">
                        <?php foreach ($activityTypes as $type): ?>
                            <option><?php echo h($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" style="margin: 0;">
                    <span style="font-weight: 700; font-size: 0.84rem; color: #334155;">Date From</span>
                    <input type="date" name="date_from" id="activityDateFrom" required style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 9px 12px; font-size: 0.88rem;">
                </label>
                <label class="field" style="margin: 0;">
                    <span style="font-weight: 700; font-size: 0.84rem; color: #334155;">Date To</span>
                    <input type="date" name="date_to" id="activityDateTo" required style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 9px 12px; font-size: 0.88rem;">
                </label>
            </div>
            <label class="field" style="margin: 0;">
                <span style="font-weight: 700; font-size: 0.84rem; color: #334155;">Venue / Location</span>
                <input name="venue" id="activityVenue" placeholder="e.g. University Gymnasium / Audio-Visual Room" required style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 9px 12px; font-size: 0.88rem;">
            </label>
            <label class="field" style="margin: 0;">
                <span style="font-weight: 700; font-size: 0.84rem; color: #334155;">Activity Details &amp; Objectives</span>
                <textarea name="description" id="activityDescription" rows="4" placeholder="Provide background details, objectives, schedule, or instructions for this activity..." style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 10px 12px; font-size: 0.88rem; line-height: 1.5; resize: vertical;"></textarea>
            </label>
            
            <div class="activity-modal-footer" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 8px; padding-top: 16px; border-top: 1px solid #e2e8f0; flex-wrap: wrap;">
                <div class="activity-modal-destructive" style="display: flex; align-items: center; gap: 8px;">
                    <button type="submit" formnovalidate name="action" value="archive" id="archiveActivityButton" onclick="return confirm('Archive this activity from the calendar?');" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 0.82rem; font-weight: 600; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; cursor: pointer; transition: all 0.15s;">
                        <i class="ri-archive-line"></i><span>Archive</span>
                    </button>
                    <?php if (($user['role'] ?? '') === 'Super Admin'): ?>
                        <button type="submit" formnovalidate name="action" value="reject" id="rejectActivityButton" onclick="return confirm('Reject this activity and remove it from the shared calendar?')" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 0.82rem; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; cursor: pointer; transition: all 0.15s;">
                            <i class="ri-close-line"></i><span>Reject</span>
                        </button>
                    <?php endif; ?>
                    <button type="submit" formnovalidate name="action" value="delete" id="deleteActivityButton" onclick="return confirm('Permanently delete this activity? This cannot be undone.');" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 0.82rem; font-weight: 600; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; cursor: pointer; transition: all 0.15s;">
                        <i class="ri-delete-bin-line"></i><span>Delete</span>
                    </button>
                </div>
                <div class="activity-modal-primary" style="display: flex; align-items: center; gap: 8px; margin-left: auto;">
                    <button type="button" data-close-modal style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.84rem; font-weight: 600; color: #475569; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; transition: all 0.15s;">
                        Cancel
                    </button>
                    <?php if (($user['role'] ?? '') === 'Super Admin'): ?>
                        <button type="submit" name="action" value="review" id="approveActivityButton" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.84rem; font-weight: 600; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; cursor: pointer; transition: all 0.15s;">
                            <i class="ri-check-line"></i> Approve
                        </button>
                    <?php endif; ?>
                    <button type="submit" name="action" value="create" id="createActivityButton" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; font-size: 0.84rem; font-weight: 700; color: #ffffff; background: #15803d; border: 1px solid #15803d; border-radius: 8px; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <i class="ri-add-circle-line"></i> Add Activity
                    </button>
                    <button type="submit" name="action" value="update" id="saveActivityButton" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; font-size: 0.84rem; font-weight: 700; color: #ffffff; background: #15803d; border: 1px solid #15803d; border-radius: 8px; cursor: pointer; transition: all 0.15s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                        <i class="ri-save-line"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </article>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<?php renderFooter(); ?>
