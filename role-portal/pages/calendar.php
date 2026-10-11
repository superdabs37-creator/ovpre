<?php
$user = portal_require_user();
ensure_activity_date_to_column();
ensure_activity_archived_at_column();
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$canSchedule = $isExtensionCoordinator || $isResearchCoordinator;
$activityTypes = $isExtensionCoordinator
    ? ['Training', 'Workshop', 'Community Outreach']
    : ['Seminar', 'Project Defense'];
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canSchedule) {
    $action = (string) ($_POST['action'] ?? 'create');
    $requestId = (int) ($_POST['request_id'] ?? 0);
    if (in_array($action, ['update', 'delete'], true)) {
        $ownedRequest = db()->prepare('SELECT id FROM activities WHERE id = ? AND created_by = ? AND approval_status <> "Approved" LIMIT 1');
        $ownedRequest->execute([$requestId, (int) $user['id']]);
        if (!$ownedRequest->fetch()) {
            flash('error', 'Only your schedule requests that are not approved can be changed.');
            redirect_to('calendar.php');
        }
        if ($action === 'delete') {
            db()->prepare('DELETE FROM activities WHERE id = ? AND created_by = ? AND approval_status <> "Approved"')
                ->execute([$requestId, (int) $user['id']]);
            log_action((int) $user['id'], 'Deleted unapproved calendar request #' . $requestId);
            flash('success', 'Schedule request deleted.');
            redirect_to('calendar.php');
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
        db()->prepare('UPDATE activities SET title = ?, activity_type = ?, date = ?, date_to = ?, venue = ?, description = ?, approval_status = "Pending" WHERE id = ? AND created_by = ? AND approval_status <> "Approved"')
            ->execute([$title, $type, $dateFrom, $dateTo, $venue, $description, $requestId, (int) $user['id']]);
        log_action((int) $user['id'], 'Updated calendar activity request #' . $requestId);
        flash('success', 'Schedule request updated and sent for admin approval.');
    } else {
        db()->prepare('INSERT INTO activities (title, activity_type, date, date_to, venue, description, approval_status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, "Pending", ?, NOW())')
            ->execute([$title, $type, $dateFrom, $dateTo, $venue, $description, (int) $user['id']]);
        log_action((int) $user['id'], 'Requested calendar activity: ' . $title);
        flash('success', 'Schedule submitted. Please wait for approval.');
    }
    redirect_to('calendar.php');
}
$user = portal_header($isResearchCoordinator ? 'Activities' : 'Calendar', 'calendar');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$activities = db()->query('SELECT a.*, u.fullname AS creator_name FROM activities a LEFT JOIN users u ON u.id = a.created_by WHERE a.approval_status = "Approved" AND a.archived_at IS NULL ORDER BY a.date ASC, a.created_at ASC')->fetchAll();
$myScheduleRequests = [];
if ($canSchedule) {
    $requestStmt = db()->prepare('SELECT * FROM activities WHERE created_by = ? AND approval_status <> "Approved" AND archived_at IS NULL ORDER BY created_at DESC');
    $requestStmt->execute([(int) $user['id']]);
    $myScheduleRequests = $requestStmt->fetchAll();
}
$events = array_map(static fn($activity) => ['title' => $activity['title'], 'start' => $activity['date'], 'end' => date('Y-m-d', strtotime(($activity['date_to'] ?: $activity['date']) . ' +1 day')), 'allDay' => true, 'type' => $activity['activity_type'], 'venue' => $activity['venue']], $activities);
?>
<script>window.roleCalendarEvents = <?php echo json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<?php portal_notice($notice); ?>
<?php if ($canSchedule): ?>
<section class="panel schedule-launcher">
    <div class="panel-heading"><div><h2>Activities</h2><span>Schedule an activity for the shared calendar.</span></div><button class="primary-btn" type="button" data-open-modal="scheduleActivityModal"><i class="ri-calendar-event-line"></i> Schedule Activity</button></div>
</section>
<div class="modal" id="scheduleActivityModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <section class="modal-card schedule-modal-card" role="dialog" aria-modal="true" aria-labelledby="scheduleActivityModalTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close schedule form"><i class="ri-close-line"></i></button>
        <div class="panel-heading"><div><h2 id="scheduleActivityModalTitle">Schedule an Activity</h2><span data-schedule-modal-description>The Super Admin must approve your request before it appears on the shared calendar.</span></div></div>
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
            <div class="schedule-modal-actions"><button class="primary-btn" type="submit" data-schedule-submit><i class="ri-calendar-event-line"></i> Schedule Activity</button><button class="ghost-btn" type="button" data-close-modal>Cancel</button></div>
        </form>
    </section>
</div>
<section class="panel"><div class="panel-heading"><h2>My Schedule Requests</h2><span>Edit or delete requests until they are approved.</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Activity</th><th>Type</th><th>Date</th><th>Venue</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($myScheduleRequests as $request): ?><tr><td><?php echo h($request['title']); ?></td><td><?php echo h($request['activity_type']); ?></td><td><?php echo h(date('M d, Y', strtotime($request['date'])) . ($request['date_to'] && $request['date_to'] !== $request['date'] ? ' – ' . date('M d, Y', strtotime($request['date_to'])) : '')); ?></td><td><?php echo h($request['venue']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($request['approval_status'])); ?>"><?php echo h($request['approval_status']); ?></span></td><td><div class="file-actions"><button class="ghost-btn mini-btn" type="button" data-edit-schedule="<?php echo h(json_encode(['id' => (int) $request['id'], 'title' => $request['title'], 'type' => $request['activity_type'], 'dateFrom' => $request['date'], 'dateTo' => $request['date_to'] ?: $request['date'], 'venue' => $request['venue'], 'description' => $request['description'] ?? ''], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>"><i class="ri-edit-line"></i> Edit</button><form method="post" onsubmit="return confirm('Delete this schedule request?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>"><button class="danger-btn mini-btn" type="submit"><i class="ri-delete-bin-line"></i> Delete</button></form></div></td></tr><?php endforeach; ?><?php if (!$myScheduleRequests): ?><tr><td colspan="6">No pending or rejected schedule requests.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2><?php echo $isExtensionCoordinator ? 'Extension Activities and Deadlines' : ($isResearchCoordinator ? 'Research Activities and Deadlines' : 'Research, Extension, Meetings, and Deadlines'); ?></h2><span><?php echo $isExtensionCoordinator ? 'Extension calendar' : ($isResearchCoordinator ? 'Research calendar' : 'Shared OVPRE calendar'); ?></span></div>
    <div class="mini-calendar" id="roleMiniCalendar"></div>
</section>
<section class="panel"><div class="panel-heading"><h2>All Calendar Activities</h2><span><?php echo count($activities); ?> events</span></div><div class="table-scroll"><table class="data-table" id="roleCalendarActivitiesTable" data-paginate="10"><thead><tr><th>Date</th><th>Activity</th><th>Type</th><th>Venue</th><th>Scheduled By</th></tr></thead><tbody><?php foreach ($activities as $activity): ?><tr><td><?php echo h(date('M d, Y', strtotime($activity['date'])) . ($activity['date_to'] && $activity['date_to'] !== $activity['date'] ? ' – ' . date('M d, Y', strtotime($activity['date_to'])) : '')); ?></td><td><?php echo h($activity['title']); ?></td><td><?php echo h($activity['activity_type']); ?></td><td><?php echo h($activity['venue']); ?></td><td><?php echo h($activity['creator_name'] ?? 'System Administrator'); ?></td></tr><?php endforeach; ?><?php if (!$activities): ?><tr data-pagination-empty><td colspan="5">No calendar activities scheduled.</td></tr><?php endif; ?></tbody></table></div></section>
<script>
const scheduleDateFrom = document.querySelector('[name="date_from"]');
const scheduleDateTo = document.querySelector('[name="date_to"]');
scheduleDateFrom?.addEventListener('change', () => {
    scheduleDateTo.min = scheduleDateFrom.value;
    if (scheduleDateTo.value && scheduleDateTo.value < scheduleDateFrom.value) scheduleDateTo.value = scheduleDateFrom.value;
});
</script>
<?php portal_footer(); ?>
