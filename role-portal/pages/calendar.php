<?php
$user = portal_require_user();
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$canSchedule = $isExtensionCoordinator || $isResearchCoordinator;
$activityTypes = $isExtensionCoordinator
    ? ['Training', 'Workshop', 'Community Outreach']
    : ['Seminar', 'Project Defense'];
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canSchedule) {
    $title = trim((string) ($_POST['title'] ?? ''));
    $type = (string) ($_POST['activity_type'] ?? '');
    $date = (string) ($_POST['date'] ?? '');
    $venue = trim((string) ($_POST['venue'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);
    if ($title === '' || mb_strlen($title) > 220 || !in_array($type, $activityTypes, true) || !$parsedDate || $parsedDate->format('Y-m-d') !== $date || $venue === '' || mb_strlen($venue) > 180) {
        flash('error', 'Enter a valid title, activity type, date, and venue.');
    } else {
        db()->prepare('INSERT INTO activities (title, activity_type, date, venue, description, approval_status, created_by, created_at) VALUES (?, ?, ?, ?, ?, "Pending", ?, NOW())')
            ->execute([$title, $type, $date, $venue, $description, (int) $user['id']]);
        log_action((int) $user['id'], 'Requested calendar activity: ' . $title);
        flash('success', 'Schedule submitted. Please wait for approval.');
    }
    redirect_to('calendar.php');
}
$user = portal_header('Calendar', 'calendar');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$activities = db()->query('SELECT a.*, u.fullname AS creator_name FROM activities a LEFT JOIN users u ON u.id = a.created_by WHERE a.approval_status = "Approved" ORDER BY a.date ASC, a.created_at ASC')->fetchAll();
$myScheduleRequests = [];
if ($canSchedule) {
    $requestStmt = db()->prepare('SELECT * FROM activities WHERE created_by = ? AND approval_status <> "Approved" ORDER BY created_at DESC');
    $requestStmt->execute([(int) $user['id']]);
    $myScheduleRequests = $requestStmt->fetchAll();
}
$events = array_map(static fn($activity) => ['title' => $activity['title'], 'start' => $activity['date'], 'type' => $activity['activity_type'], 'venue' => $activity['venue']], $activities);
?>
<script>window.roleCalendarEvents = <?php echo json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<?php portal_notice($notice); ?>
<?php if ($canSchedule): ?>
<section class="panel"><div class="panel-heading"><h2>Schedule an Activity</h2><span>The Super Admin must approve your request before it appears on the shared calendar.</span></div><form class="portal-form" method="post"><?php echo csrf_field(); ?><div class="form-grid"><label class="field"><span>Activity Title</span><input name="title" maxlength="220" required></label><label class="field"><span>Activity Type</span><select name="activity_type" required><?php foreach ($activityTypes as $type): ?><option value="<?php echo h($type); ?>"><?php echo h($type); ?></option><?php endforeach; ?></select></label><label class="field"><span>Date</span><input type="date" name="date" min="<?php echo h(date('Y-m-d')); ?>" required></label><label class="field"><span>Venue</span><input name="venue" maxlength="180" required></label></div><label class="field"><span>Description</span><textarea name="description" rows="3"></textarea></label><button class="primary-btn"><i class="ri-calendar-event-line"></i> Schedule Activity</button></form></section>
<section class="panel"><div class="panel-heading"><h2>My Schedule Requests</h2><span>These appear on the calendar after approval.</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Activity</th><th>Type</th><th>Date</th><th>Venue</th><th>Status</th></tr></thead><tbody><?php foreach ($myScheduleRequests as $request): ?><tr><td><?php echo h($request['title']); ?></td><td><?php echo h($request['activity_type']); ?></td><td><?php echo h(date('M d, Y', strtotime($request['date']))); ?></td><td><?php echo h($request['venue']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($request['approval_status'])); ?>"><?php echo h($request['approval_status']); ?></span></td></tr><?php endforeach; ?><?php if (!$myScheduleRequests): ?><tr><td colspan="5">No pending or rejected schedule requests.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2><?php echo $isExtensionCoordinator ? 'Extension Activities and Deadlines' : ($isResearchCoordinator ? 'Research Activities and Deadlines' : 'Research, Extension, Meetings, and Deadlines'); ?></h2><span><?php echo $isExtensionCoordinator ? 'Extension calendar' : ($isResearchCoordinator ? 'Research calendar' : 'Shared OVPRE calendar'); ?></span></div>
    <div class="mini-calendar" id="roleMiniCalendar"></div>
</section>
<section class="panel"><div class="panel-heading"><h2>All Calendar Activities</h2><span><?php echo count($activities); ?> events</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Activity</th><th>Type</th><th>Venue</th><th>Scheduled By</th></tr></thead><tbody><?php foreach ($activities as $activity): ?><tr><td><?php echo h(date('M d, Y', strtotime($activity['date']))); ?></td><td><?php echo h($activity['title']); ?></td><td><?php echo h($activity['activity_type']); ?></td><td><?php echo h($activity['venue']); ?></td><td><?php echo h($activity['creator_name'] ?? 'System Administrator'); ?></td></tr><?php endforeach; ?><?php if (!$activities): ?><tr><td colspan="5">No calendar activities scheduled.</td></tr><?php endif; ?></tbody></table></div></section>
<?php portal_footer(); ?>
