<?php
$user = portal_require_user();
ensure_activity_date_to_column();
ensure_activity_archived_at_column();
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$isResearchDirector = in_array(PORTAL_ROLE, ['Research Director', 'Director for Research and Creative Works'], true);
$isExtensionDirector = PORTAL_ROLE === 'Extension Director';
$isDirector = $isResearchDirector || $isExtensionDirector;
$canSchedule = $isExtensionCoordinator || $isResearchCoordinator || $isDirector;
$activityTypes = ['Seminar', 'Training', 'Workshop', 'Community Outreach', 'Project Defense'];
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $activityId = (int) ($_POST['activity_id'] ?? $requestId);

    if ($action === 'archive_schedule_request') {
        db()->prepare("UPDATE activities SET archived_at = NOW() WHERE id = ? AND created_by = ?")->execute([$requestId, (int) $user['id']]);
        log_action((int) $user['id'], 'Archived calendar request #' . $requestId);
        flash('success', 'Schedule request archived.');
        redirect_to('calendar.php' . (!empty($_POST['requests_archived']) ? '?requests_archived=1' : ''));
    } elseif ($action === 'restore_schedule_request') {
        db()->prepare("UPDATE activities SET archived_at = NULL WHERE id = ? AND created_by = ?")->execute([$requestId, (int) $user['id']]);
        log_action((int) $user['id'], 'Restored calendar request #' . $requestId);
        flash('success', 'Schedule request restored.');
        redirect_to('calendar.php?requests_archived=1');
    } elseif ($action === 'bulk_schedule_archive') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $bulkAction = (string) ($_POST['bulk_action'] ?? 'archive');
        $isArchivedView = !empty($_POST['requests_archived']);
        if (!empty($ids)) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'restore') {
                db()->prepare("UPDATE activities SET archived_at = NULL WHERE id IN ($marks) AND created_by = ?")->execute(array_merge($ids, [(int) $user['id']]));
                flash('success', count($ids) . ' schedule request(s) restored.');
                redirect_to('calendar.php?requests_archived=1');
            } else {
                db()->prepare("UPDATE activities SET archived_at = NOW() WHERE id IN ($marks) AND created_by = ?")->execute(array_merge($ids, [(int) $user['id']]));
                flash('success', count($ids) . ' schedule request(s) archived.');
                redirect_to('calendar.php');
            }
        } else {
            flash('error', 'No schedule requests selected.');
            redirect_to('calendar.php' . ($isArchivedView ? '?requests_archived=1' : ''));
        }
    } elseif ($action === 'archive_activity') {
        db()->prepare("UPDATE activities SET archived_at = NOW() WHERE id = ?")->execute([$activityId]);
        log_action((int) $user['id'], 'Archived calendar activity #' . $activityId);
        flash('success', 'Calendar activity archived.');
        redirect_to('calendar.php' . (!empty($_POST['activities_archived']) ? '?activities_archived=1' : ''));
    } elseif ($action === 'restore_activity') {
        db()->prepare("UPDATE activities SET archived_at = NULL WHERE id = ?")->execute([$activityId]);
        log_action((int) $user['id'], 'Restored calendar activity #' . $activityId);
        flash('success', 'Calendar activity restored.');
        redirect_to('calendar.php?activities_archived=1');
    } elseif ($action === 'bulk_activity_archive') {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        $bulkAction = (string) ($_POST['bulk_action'] ?? 'archive');
        $isArchivedView = !empty($_POST['activities_archived']);
        if (!empty($ids)) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulkAction === 'restore') {
                db()->prepare("UPDATE activities SET archived_at = NULL WHERE id IN ($marks)")->execute($ids);
                flash('success', count($ids) . ' activity record(s) restored.');
                redirect_to('calendar.php?activities_archived=1');
            } else {
                db()->prepare("UPDATE activities SET archived_at = NOW() WHERE id IN ($marks)")->execute($ids);
                flash('success', count($ids) . ' activity record(s) archived.');
                redirect_to('calendar.php');
            }
        } else {
            flash('error', 'No activities selected.');
            redirect_to('calendar.php' . ($isArchivedView ? '?activities_archived=1' : ''));
        }
    } elseif (in_array($action, ['update', 'delete'], true)) {
        if ($isDirector) {
            $actStmt = db()->prepare("SELECT id FROM activities WHERE id = ? LIMIT 1");
            $actStmt->execute([$requestId]);
            if (!$actStmt->fetch()) {
                flash('error', 'Activity not found.');
                redirect_to('calendar.php');
            }
            if ($action === 'delete') {
                db()->prepare("DELETE FROM activities WHERE id = ?")->execute([$requestId]);
                log_action((int) $user['id'], 'Deleted calendar activity #' . $requestId);
                flash('success', 'Calendar activity deleted.');
                redirect_to('calendar.php');
            }
        } else {
            $ownedRequest = db()->prepare("SELECT id FROM activities WHERE id = ? AND created_by = ? AND approval_status <> 'Approved' LIMIT 1");
            $ownedRequest->execute([$requestId, (int) $user['id']]);
            if (!$ownedRequest->fetch()) {
                flash('error', 'Only your schedule requests that are not approved can be changed.');
                redirect_to('calendar.php');
            }
            if ($action === 'delete') {
                db()->prepare("DELETE FROM activities WHERE id = ? AND created_by = ? AND approval_status <> 'Approved'")
                    ->execute([$requestId, (int) $user['id']]);
                log_action((int) $user['id'], 'Deleted unapproved calendar request #' . $requestId);
                flash('success', 'Schedule request deleted.');
                redirect_to('calendar.php');
            }
        }
    } elseif ($action !== 'create') {
        flash('error', 'Invalid schedule request action.');
        redirect_to('calendar.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $type = (string) ($_POST['activity_type'] ?? '');
    $dateFrom = (string) ($_POST['date_from'] ?? '');
    $dateTo = (string) ($_POST['date_to'] ?? '');
    $venue = trim((string) ($_POST['venue'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $parsedDateFrom = DateTime::createFromFormat('!Y-m-d', $dateFrom);
    $parsedDateTo = DateTime::createFromFormat('!Y-m-d', $dateTo);
    if ($title === '' || mb_strlen($title) > 220 || !in_array($type, $activityTypes, true) || !$parsedDateFrom || $parsedDateFrom->format('Y-m-d') !== $dateFrom || !$parsedDateTo || $parsedDateTo->format('Y-m-d') !== $dateTo || $dateTo < $dateFrom || $venue === '' || mb_strlen($venue) > 180) {
        flash('error', 'Enter a valid title, activity type, date range, and venue.');
    } elseif ($action === 'update') {
        if ($isDirector) {
            db()->prepare("UPDATE activities SET title = ?, activity_type = ?, date = ?, date_to = ?, venue = ?, description = ? WHERE id = ?")
                ->execute([$title, $type, $dateFrom, $dateTo, $venue, $description, $requestId]);
            log_action((int) $user['id'], 'Updated calendar activity #' . $requestId);
            flash('success', 'Calendar activity updated.');
        } else {
            db()->prepare("UPDATE activities SET title = ?, activity_type = ?, date = ?, date_to = ?, venue = ?, description = ?, approval_status = 'Pending' WHERE id = ? AND created_by = ? AND approval_status <> 'Approved'")
                ->execute([$title, $type, $dateFrom, $dateTo, $venue, $description, $requestId, (int) $user['id']]);
            log_action((int) $user['id'], 'Updated calendar activity request #' . $requestId);
            flash('success', 'Schedule request updated and sent for admin approval.');
        }
    } else {
        $status = $isDirector ? 'Approved' : 'Pending';
        db()->prepare("INSERT INTO activities (title, activity_type, date, date_to, venue, description, approval_status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())")
            ->execute([$title, $type, $dateFrom, $dateTo, $venue, $description, $status, (int) $user['id']]);
        log_action((int) $user['id'], ($isDirector ? 'Created calendar activity: ' : 'Requested calendar activity: ') . $title);
        flash('success', $isDirector ? 'Activity scheduled and published to the shared calendar.' : 'Schedule submitted. Please wait for approval.');
    }
    redirect_to('calendar.php');
}

$requestsArchived = ($_GET['requests_archived'] ?? '') === '1';
$activitiesArchived = ($_GET['activities_archived'] ?? '') === '1';

$user = portal_header($isResearchCoordinator ? 'Activities' : 'Calendar', 'calendar');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$isResearchDirector = in_array(PORTAL_ROLE, ['Research Director', 'Director for Research and Creative Works'], true);
$isExtensionDirector = PORTAL_ROLE === 'Extension Director';
$isDirector = $isResearchDirector || $isExtensionDirector;
$canSchedule = $isExtensionCoordinator || $isResearchCoordinator || $isDirector;

$activities = db()->query("SELECT a.*, u.fullname AS creator_name FROM activities a LEFT JOIN users u ON u.id = a.created_by WHERE a.approval_status = 'Approved' AND a.archived_at IS NULL ORDER BY a.date ASC, a.created_at ASC")->fetchAll();

$tableActivities = db()->query("SELECT a.*, u.fullname AS creator_name FROM activities a LEFT JOIN users u ON u.id = a.created_by WHERE a.approval_status = 'Approved' AND " . ($activitiesArchived ? 'a.archived_at IS NOT NULL' : 'a.archived_at IS NULL') . " ORDER BY a.date ASC, a.created_at ASC")->fetchAll();

$activityCounts = [
    'active' => (int) db()->query("SELECT COUNT(*) FROM activities WHERE approval_status = 'Approved' AND archived_at IS NULL")->fetchColumn(),
    'archived' => (int) db()->query("SELECT COUNT(*) FROM activities WHERE approval_status = 'Approved' AND archived_at IS NOT NULL")->fetchColumn(),
];

$upcomingStmt = db()->prepare("SELECT * FROM activities WHERE approval_status = 'Approved' AND archived_at IS NULL AND (date_to >= CURRENT_DATE OR (date_to IS NULL AND date >= CURRENT_DATE)) ORDER BY date ASC LIMIT 6");
$upcomingStmt->execute();
$upcomingActivities = $upcomingStmt->fetchAll();

$eventColors = [
    'Seminar' => '#15803d',
    'Training' => '#2563eb',
    'Workshop' => '#0891b2',
    'Community Outreach' => '#b45309',
    'Project Defense' => '#7c3aed',
    'Research Proposal' => '#15803d',
    'Research Colloquium' => '#0891b2',
    'Research Completion and Final Presentation' => '#7c3aed',
    'Research Publication' => '#2563eb',
    'Research Seminar' => '#15803d',
    'Research Monitoring and Evaluation' => '#b45309',
];

$calendarEvents = array_map(static function (array $activity) use ($eventColors): array {
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
            'createdBy' => (int) ($activity['created_by'] ?? 0),
        ],
    ];
}, $activities);

$myScheduleRequests = [];
$requestCounts = ['active' => 0, 'archived' => 0];
if ($canSchedule) {
    $reqWhere = $requestsArchived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL';
    $requestStmt = db()->prepare("SELECT * FROM activities WHERE created_by = ? AND approval_status <> 'Approved' AND {$reqWhere} ORDER BY created_at DESC");
    $requestStmt->execute([(int) $user['id']]);
    $myScheduleRequests = $requestStmt->fetchAll();

    $actC = db()->prepare("SELECT COUNT(*) FROM activities WHERE created_by = ? AND approval_status <> 'Approved' AND archived_at IS NULL");
    $actC->execute([(int) $user['id']]);
    $requestCounts['active'] = (int) $actC->fetchColumn();

    $arcC = db()->prepare("SELECT COUNT(*) FROM activities WHERE created_by = ? AND approval_status <> 'Approved' AND archived_at IS NOT NULL");
    $arcC->execute([(int) $user['id']]);
    $requestCounts['archived'] = (int) $arcC->fetchColumn();
}

?>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script>
window.roleCalendarEvents = <?php echo json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>

<?php portal_notice($notice); ?>

<!-- SYSTEM ADMIN STYLE CALENDAR LAYOUT -->
<section class="calendar-page-layout">
    <aside class="calendar-side panel">
        <div class="calendar-legend fullcalendar-legend" aria-label="Activity type legend">
            <span><i class="legend-dot seminar"></i> Seminar</span>
            <span><i class="legend-dot training"></i> Training</span>
            <span><i class="legend-dot workshop"></i> Workshop</span>
            <span><i class="legend-dot outreach"></i> Outreach</span>
            <span><i class="legend-dot defense"></i> Defense</span>
        </div>

        <div class="panel-heading upcoming-heading">
            <h2>Upcoming</h2>
            <span>Next 6</span>
        </div>
        <div class="upcoming-list">
            <?php foreach ($upcomingActivities as $activity): ?>
                <article>
                    <div class="upcoming-date">
                        <strong><?php echo h(date('d', strtotime($activity['date']))); ?></strong>
                        <span><?php echo h(date('M', strtotime($activity['date']))); ?></span>
                    </div>
                    <div>
                        <h3><?php echo h($activity['title']); ?></h3>
                        <p><?php echo h($activity['activity_type']); ?> | <?php echo h(date('M d', strtotime($activity['date'])) . ($activity['date_to'] !== $activity['date'] ? ' – ' . date('M d, Y', strtotime($activity['date_to'])) : '') . ' | ' . $activity['venue']); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$upcomingActivities): ?>
                <p class="muted">No upcoming activities yet.</p>
            <?php endif; ?>
        </div>

        <?php if ($canSchedule): ?>
            <button class="primary-btn full-btn calendar-create-btn" type="button" data-open-modal="scheduleActivityModal">
                <i class="ri-calendar-event-line"></i> Schedule Activity
            </button>
        <?php endif; ?>
    </aside>

    <article class="panel fullcalendar-panel">
        <div id="activityCalendar"></div>
    </article>
</section>

<!-- MY SCHEDULE REQUESTS (POSITIONED DIRECTLY BELOW CALENDAR) -->
<?php if ($canSchedule && (!$isDirector || !empty($myScheduleRequests))): ?>
<section class="panel">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <h2><?php echo $requestsArchived ? 'Archived Schedule Requests' : 'My Schedule Requests'; ?></h2>
            <span><?php echo $requestsArchived ? 'View and restore archived schedule requests.' : 'Edit, archive, or delete requests until they are approved.'; ?></span>
        </div>
        <div class="portal-table-tabs" style="margin-left:auto;">
            <a class="portal-table-tab <?php echo !$requestsArchived ? 'active' : ''; ?>" href="calendar.php">
                <i class="ri-calendar-line"></i>
                <span>Active Requests</span>
                <span class="tab-count"><?php echo $requestCounts['active']; ?></span>
            </a>
            <a class="portal-table-tab <?php echo $requestsArchived ? 'active' : ''; ?>" href="calendar.php?requests_archived=1">
                <i class="ri-archive-line"></i>
                <span>Archived</span>
                <span class="tab-count"><?php echo $requestCounts['archived']; ?></span>
            </a>
        </div>
    </div>

    <!-- Bulk Selection Toolbar for Schedule Requests -->
    <div class="table-selection-toolbar" data-selection-toolbar="myScheduleRequestsTable" hidden>
        <span data-selection-count>0 selected</span>
        <form id="bulk-myScheduleRequestsTable" method="post" data-bulk-selection-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_schedule_archive">
            <?php if ($requestsArchived): ?>
                <input type="hidden" name="requests_archived" value="1">
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="restore" data-bulk-submit>
                    <i class="ri-arrow-go-back-line"></i><span>Restore Selected</span>
                </button>
            <?php else: ?>
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="archive" data-bulk-submit onclick="return confirm('Archive selected schedule requests?');">
                    <i class="ri-archive-line"></i><span>Archive Selected</span>
                </button>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="myScheduleRequestsTable" data-paginate="10">
            <thead>
                <tr>
                    <th class="table-checkbox-col">
                        <label class="select-all-control">
                            <input form="bulk-myScheduleRequestsTable" type="checkbox" aria-label="Select all schedule requests" data-select-all="myScheduleRequestsTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Activity</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myScheduleRequests as $request): ?>
                    <tr>
                        <td class="table-checkbox-col">
                            <input form="bulk-myScheduleRequestsTable" type="checkbox" name="ids[]" aria-label="Select schedule request <?php echo (int) $request['id']; ?>" value="<?php echo (int) $request['id']; ?>" data-row-select="myScheduleRequestsTable">
                        </td>
                        <td><?php echo h($request['title']); ?></td>
                        <td><?php echo h($request['activity_type']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($request['date'])) . ($request['date_to'] && $request['date_to'] !== $request['date'] ? ' – ' . date('M d, Y', strtotime($request['date_to'])) : '')); ?></td>
                        <td><?php echo h($request['venue']); ?></td>
                        <td><span class="status-pill status-<?php echo h(statusClass($request['approval_status'])); ?>"><?php echo h($request['approval_status']); ?></span></td>
                        <td>
                            <div class="file-actions">
                                <?php if (!$requestsArchived): ?>
                                    <button class="ghost-btn mini-btn" type="button" data-edit-schedule="<?php echo h(json_encode(['id' => (int) $request['id'], 'title' => $request['title'], 'type' => $request['activity_type'], 'dateFrom' => $request['date'], 'dateTo' => $request['date_to'] ?: $request['date'], 'venue' => $request['venue'], 'description' => $request['description'] ?? ''], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>"><i class="ri-edit-line"></i> Edit</button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Archive this schedule request?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="archive_schedule_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit" style="color:#b45309;"><i class="ri-archive-line"></i> Archive</button>
                                    </form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this schedule request?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button class="danger-btn mini-btn" type="submit"><i class="ri-delete-bin-line"></i> Delete</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore_schedule_request">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit"><i class="ri-arrow-go-back-line"></i> Restore</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$myScheduleRequests): ?>
                    <tr data-pagination-empty>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #6b7280;"><?php echo $requestsArchived ? 'No archived schedule requests.' : 'No pending or rejected schedule requests.'; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<!-- ALL CALENDAR ACTIVITIES TABLE -->
<section class="panel">
    <div class="panel-heading" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <h2><?php echo $activitiesArchived ? 'Archived Calendar Activities' : 'All Calendar Activities'; ?></h2>
            <span><?php echo count($tableActivities); ?> event<?php echo count($tableActivities) === 1 ? '' : 's'; ?></span>
        </div>
        <div class="panel-heading-actions" style="display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-wrap:wrap; margin-left:auto;">
            <div class="portal-table-tabs" style="margin-left:auto;">
                <a class="portal-table-tab <?php echo !$activitiesArchived ? 'active' : ''; ?>" href="calendar.php">
                    <i class="ri-calendar-event-line"></i>
                    <span>Scheduled</span>
                    <span class="tab-count"><?php echo $activityCounts['active']; ?></span>
                </a>
                <a class="portal-table-tab <?php echo $activitiesArchived ? 'active' : ''; ?>" href="calendar.php?activities_archived=1">
                    <i class="ri-archive-line"></i>
                    <span>Archived</span>
                    <span class="tab-count"><?php echo $activityCounts['archived']; ?></span>
                </a>
            </div>
            <label class="table-search" style="margin-left:auto;">
                <span class="sr-only">Search calendar activities</span>
                <i class="ri-search-line"></i>
                <input type="search" data-table-search="roleCalendarActivitiesTable" placeholder="Search activities, venue, type...">
            </label>
        </div>
    </div>

    <!-- Bulk Selection Toolbar for Activities -->
    <div class="table-selection-toolbar" data-selection-toolbar="roleCalendarActivitiesTable" hidden>
        <span data-selection-count>0 selected</span>
        <form id="bulk-roleCalendarActivitiesTable" method="post" data-bulk-selection-form>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_activity_archive">
            <?php if ($activitiesArchived): ?>
                <input type="hidden" name="activities_archived" value="1">
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="restore" data-bulk-submit>
                    <i class="ri-arrow-go-back-line"></i><span>Restore Selected</span>
                </button>
            <?php else: ?>
                <button class="ghost-btn action-btn" type="submit" name="bulk_action" value="archive" data-bulk-submit onclick="return confirm('Archive selected calendar activities?');">
                    <i class="ri-archive-line"></i><span>Archive Selected</span>
                </button>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="roleCalendarActivitiesTable" data-paginate="10">
            <thead>
                <tr>
                    <th class="table-checkbox-col">
                        <label class="select-all-control">
                            <input form="bulk-roleCalendarActivitiesTable" type="checkbox" aria-label="Select all activities" data-select-all="roleCalendarActivitiesTable">
                            <span>Select all</span>
                        </label>
                    </th>
                    <th>Date</th>
                    <th>Activity</th>
                    <th>Type</th>
                    <th>Venue</th>
                    <th>Scheduled By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableActivities as $activity): ?>
                    <tr>
                        <td class="table-checkbox-col">
                            <input form="bulk-roleCalendarActivitiesTable" type="checkbox" name="ids[]" aria-label="Select activity <?php echo (int) $activity['id']; ?>" value="<?php echo (int) $activity['id']; ?>" data-row-select="roleCalendarActivitiesTable">
                        </td>
                        <td><?php echo h(date('M d, Y', strtotime($activity['date'])) . ($activity['date_to'] && $activity['date_to'] !== $activity['date'] ? ' – ' . date('M d, Y', strtotime($activity['date_to'])) : '')); ?></td>
                        <td><?php echo h($activity['title']); ?></td>
                        <td><?php echo h($activity['activity_type']); ?></td>
                        <td><?php echo h($activity['venue']); ?></td>
                        <td><?php echo h($activity['creator_name'] ?? 'System Administrator'); ?></td>
                        <td>
                            <div class="file-actions">
                                <?php if ($activitiesArchived): ?>
                                    <form method="post" style="margin:0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="restore_activity">
                                        <input type="hidden" name="activity_id" value="<?php echo (int) $activity['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit"><i class="ri-arrow-go-back-line"></i> Restore</button>
                                    </form>
                                <?php else: ?>
                                    <?php if ($isDirector || (int) ($activity['created_by'] ?? 0) === (int) $user['id']): ?>
                                        <button class="ghost-btn mini-btn" type="button" data-edit-schedule="<?php echo h(json_encode(['id' => (int) $activity['id'], 'title' => $activity['title'], 'type' => $activity['activity_type'], 'dateFrom' => $activity['date'], 'dateTo' => $activity['date_to'] ?: $activity['date'], 'venue' => $activity['venue'], 'description' => $activity['description'] ?? ''], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>"><i class="ri-edit-line"></i> Edit</button>
                                    <?php endif; ?>
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Archive this activity?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="archive_activity">
                                        <input type="hidden" name="activity_id" value="<?php echo (int) $activity['id']; ?>">
                                        <button class="ghost-btn mini-btn" type="submit" style="color:#b45309;"><i class="ri-archive-line"></i> Archive</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tableActivities): ?>
                    <tr data-pagination-empty>
                        <td colspan="7" style="text-align:center; padding: 20px; color: #6b7280;"><?php echo $activitiesArchived ? 'No archived calendar activities.' : 'No calendar activities scheduled.'; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- MODAL: SCHEDULE AN ACTIVITY -->
<?php if ($canSchedule): ?>
<div class="modal" id="scheduleActivityModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <section class="modal-card schedule-modal-card" role="dialog" aria-modal="true" aria-labelledby="scheduleActivityModalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close schedule form"><i class="ri-close-line"></i></button>
        <div class="panel-heading">
            <div>
                <h2 id="scheduleActivityModalTitle" data-default-title="<?php echo $isDirector ? 'Schedule Activity' : 'Schedule an Activity'; ?>"><?php echo $isDirector ? 'Schedule Activity' : 'Schedule an Activity'; ?></h2>
                <span data-schedule-modal-description data-default-desc="<?php echo $isDirector ? 'Schedule and publish an activity directly to the shared calendar.' : 'The Super Admin must approve your request before it appears on the shared calendar.'; ?>"><?php echo $isDirector ? 'Schedule and publish an activity directly to the shared calendar.' : 'The Super Admin must approve your request before it appears on the shared calendar.'; ?></span>
            </div>
        </div>
        <form class="portal-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create" data-schedule-action>
            <input type="hidden" name="request_id" value="" data-schedule-request-id>
            <div class="form-grid">
                <label class="field"><span>Activity Title</span><input name="title" maxlength="220" required></label>
                <label class="field"><span>Activity Type</span><select name="activity_type" required><?php foreach ($activityTypes as $type): ?><option value="<?php echo h($type); ?>"><?php echo h($type); ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Date From</span><input type="date" name="date_from" min="<?php echo h(date('Y-m-d')); ?>" required></label>
                <label class="field"><span>Date To</span><input type="date" name="date_to" min="<?php echo h(date('Y-m-d')); ?>" required></label>
                <label class="field"><span>Venue</span><input name="venue" maxlength="180" required></label>
            </div>
            <label class="field"><span>Description</span><textarea name="description" rows="3"></textarea></label>
            <div class="schedule-modal-actions">
                <button class="primary-btn" type="submit" data-schedule-submit><i class="ri-calendar-event-line"></i> Schedule Activity</button>
                <button class="ghost-btn" type="button" data-close-modal>Cancel</button>
            </div>
        </form>
    </section>
</div>
<?php endif; ?>

<!-- MODAL: EVENT DETAILS -->
<div class="modal" id="activityDetailModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card" role="dialog" aria-modal="true" style="max-width: 520px;">
        <button class="modal-close" type="button" data-close-modal aria-label="Close"><i class="ri-close-line"></i></button>
        <div class="panel-heading" style="margin-bottom: 14px;">
            <div>
                <span class="status-pill status-approved" id="modalEventCategory">Activity</span>
                <h2 id="modalEventTitle" style="margin-top: 8px; font-size: 1.3rem;">Event Details</h2>
            </div>
        </div>
        <div style="display: grid; gap: 12px; font-size: 0.92rem; color: #374151;">
            <div><i class="ri-calendar-line" style="color: #15803d;"></i> <strong>Schedule:</strong> <span id="modalEventDate">-</span></div>
            <div><i class="ri-map-pin-line" style="color: #b45309;"></i> <strong>Venue:</strong> <span id="modalEventVenue">-</span></div>
            <div id="modalEventDescWrap" style="background: #f9fafb; padding: 12px; border-radius: 10px; border: 1px solid #e5e7eb;">
                <strong>Description:</strong>
                <p id="modalEventDescription" style="margin: 6px 0 0; color: #4b5563; line-height: 1.45;">-</p>
            </div>
        </div>
        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 8px;">
            <?php if ($canSchedule): ?>
                <button class="primary-btn mini-btn" type="button" id="modalEventEditBtn" style="display:none;"><i class="ri-edit-line"></i> Edit Activity</button>
            <?php endif; ?>
            <button class="ghost-btn" type="button" data-close-modal>Close</button>
        </div>
    </article>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
const scheduleDateFrom = document.querySelector('[name="date_from"]');
const scheduleDateTo = document.querySelector('[name="date_to"]');
scheduleDateFrom?.addEventListener('change', () => {
    scheduleDateTo.min = scheduleDateFrom.value;
    if (scheduleDateTo.value && scheduleDateTo.value < scheduleDateFrom.value) scheduleDateTo.value = scheduleDateFrom.value;
});

// FullCalendar Initialization
const activityCalendarEl = document.getElementById('activityCalendar');
if (activityCalendarEl && window.FullCalendar) {
    const calendar = new FullCalendar.Calendar(activityCalendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        dayMaxEvents: true,
        nowIndicator: true,
        events: window.roleCalendarEvents || [],
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        eventClick(info) {
            const props = info.event.extendedProps || {};
            const titleEl = document.getElementById('modalEventTitle');
            const catEl = document.getElementById('modalEventCategory');
            const dateEl = document.getElementById('modalEventDate');
            const venueEl = document.getElementById('modalEventVenue');
            const descEl = document.getElementById('modalEventDescription');
            const modal = document.getElementById('activityDetailModal');
            const editBtn = document.getElementById('modalEventEditBtn');

            if (titleEl) titleEl.textContent = info.event.title;
            if (catEl) catEl.textContent = props.type || 'Activity';
            if (dateEl) {
                const startStr = props.date || info.event.startStr.slice(0, 10);
                const endStr = props.dateTo || startStr;
                dateEl.textContent = startStr === endStr ? startStr : `${startStr} to ${endStr}`;
            }
            if (venueEl) venueEl.textContent = props.venue || 'N/A';
            if (descEl) descEl.textContent = props.description || 'No additional details provided.';

            if (editBtn) {
                const canEditEvent = <?php echo json_encode($isDirector); ?> || String(props.createdBy || '') === String(<?php echo json_encode((int) $user['id']); ?>);
                if (canEditEvent) {
                    editBtn.style.display = 'inline-flex';
                    editBtn.onclick = () => {
                        closeRoleModal();
                        const editData = {
                            id: info.event.id,
                            title: info.event.title,
                            type: props.type,
                            dateFrom: props.date,
                            dateTo: props.dateTo,
                            venue: props.venue,
                            description: props.description
                        };
                        const tempBtn = document.createElement('button');
                        tempBtn.dataset.editSchedule = JSON.stringify(editData);
                        document.body.appendChild(tempBtn);
                        tempBtn.click();
                        tempBtn.remove();
                    };
                } else {
                    editBtn.style.display = 'none';
                }
            }

            if (modal) {
                closeRoleModal();
                activeRoleModal = modal;
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
            }
        }
    });
    calendar.render();
}
</script>
<?php portal_footer(); ?>
