<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Staff']);
verify_csrf();

$activityTypes = ['Seminar', 'Training', 'Workshop', 'Community Outreach', 'Project Defense'];

function valid_activity_type(string $type, array $activityTypes): string {
    return in_array($type, $activityTypes, true) ? $type : 'Seminar';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        db()->prepare('INSERT INTO activities (title, activity_type, date, venue, description, approval_status, created_by, created_at) VALUES (?, ?, ?, ?, ?, "Approved", ?, NOW())')
            ->execute([
                trim((string) $_POST['title']),
                valid_activity_type((string) ($_POST['activity_type'] ?? 'Seminar'), $activityTypes),
                $_POST['date'],
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
        db()->prepare('UPDATE activities SET title = ?, activity_type = ?, date = ?, venue = ?, description = ? WHERE id = ?')
            ->execute([
                trim((string) $_POST['title']),
                valid_activity_type((string) ($_POST['activity_type'] ?? 'Seminar'), $activityTypes),
                $_POST['date'],
                trim((string) $_POST['venue']),
                trim((string) ($_POST['description'] ?? '')),
                $id,
            ]);
        log_action((int) $user['id'], 'Updated activity #' . $id);
        flash('success', 'Activity updated.');
        redirect_to('activities.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM activities WHERE id = ?')->execute([$id]);
        log_action((int) $user['id'], 'Deleted activity #' . $id);
        flash('success', 'Activity deleted.');
        redirect_to('activities.php');
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
        $requestStmt = db()->prepare('SELECT title, created_by FROM activities WHERE id = ? AND approval_status IN ("Pending", "Rejected") LIMIT 1');
        $requestStmt->execute([$id]);
        $request = $requestStmt->fetch();
        if (!$request) {
            flash('error', 'This schedule request is no longer pending.');
            redirect_to('activities.php');
        }
        db()->prepare('UPDATE activities SET approval_status = "Approved" WHERE id = ? AND approval_status IN ("Pending", "Rejected")')->execute([$id]);
        if (!empty($request['created_by'])) {
            notify_user((int) $request['created_by'], 'Your activity schedule was approved and added to the calendar: ' . $request['title']);
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
        $requestStmt = db()->prepare('SELECT title, created_by FROM activities WHERE id = ? AND approval_status IN ("Pending", "Approved") LIMIT 1');
        $requestStmt->execute([$id]);
        $activity = $requestStmt->fetch();
        if (!$activity) {
            flash('error', 'This activity has already been rejected or no longer exists.');
            redirect_to('activities.php');
        }
        $rejectStmt = db()->prepare('UPDATE activities SET approval_status = "Rejected" WHERE id = ? AND approval_status IN ("Pending", "Approved")');
        $rejectStmt->execute([$id]);
        if ($rejectStmt->rowCount() !== 1) {
            flash('error', 'This activity has already been reviewed. Refresh the page and try again.');
            redirect_to('activities.php');
        }
        if (!empty($activity['created_by'])) {
            notify_user((int) $activity['created_by'], 'Your activity schedule was rejected: ' . $activity['title']);
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
        db()->prepare('UPDATE activities SET date = ? WHERE id = ?')->execute([$date, $id]);
        log_action((int) $user['id'], 'Moved activity #' . $id . ' to ' . $date);
        echo json_encode(['ok' => true]);
        exit;
    }
}

$notice = flash();
$activities = db()->query('SELECT a.*, u.fullname AS creator_name FROM activities a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.date DESC')->fetchAll();
$approvedActivities = array_values(array_filter($activities, static fn(array $activity): bool => $activity['approval_status'] === 'Approved'));
$upcomingStmt = db()->prepare('SELECT * FROM activities WHERE approval_status = "Approved" AND date >= CURDATE() ORDER BY date ASC LIMIT 6');
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
        ],
    ];
};
$calendarEvents = array_map($toCalendarEvent, $approvedActivities);
$activityEditorEvents = array_map($toCalendarEvent, $activities);

renderHeader('Calendar of Activities', 'activities');
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

<section class="calendar-page-layout">
    <aside class="calendar-side panel">
        <div class="panel-heading"><h2>Create Activity</h2><span>Quick add</span></div>
        <form class="calendar-form" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <label class="field"><span>Title</span><input name="title" required></label>
            <label class="field"><span>Type</span><select name="activity_type"><?php foreach ($activityTypes as $type): ?><option><?php echo h($type); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Date</span><input type="date" name="date" value="<?php echo h(date('Y-m-d')); ?>" required></label>
            <label class="field"><span>Venue</span><input name="venue" required></label>
            <label class="field"><span>Description</span><textarea name="description" rows="4"></textarea></label>
            <button class="primary-btn full-btn"><i class="ri-add-circle-line"></i> Add Activity</button>
        </form>

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
                        <p><?php echo h($activity['activity_type']); ?> | <?php echo h($activity['venue']); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$upcomingActivities): ?><p class="muted">No upcoming activities yet.</p><?php endif; ?>
        </div>
    </aside>

    <article class="panel fullcalendar-panel">
        <div id="activityCalendar"></div>
    </article>
</section>

<section class="panel">
    <div class="panel-heading"><h2>All Activities</h2><span><?php echo count($activities); ?> total</span></div>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Activity</th><th>Type</th><th>Venue</th><th>Created By</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?php echo h(date('M d, Y', strtotime($activity['date']))); ?></td>
                        <td><?php echo h($activity['title']); ?></td>
                        <td><?php echo h($activity['activity_type']); ?></td>
                        <td><?php echo h($activity['venue']); ?></td>
                        <td><?php echo h($activity['creator_name'] ?? 'System Administrator'); ?></td>
                        <td><div class="activity-status-actions"><span class="status-pill status-<?php echo h(strtolower($activity['approval_status'])); ?>"><?php echo h($activity['approval_status']); ?></span><?php if ($activity['approval_status'] !== 'Rejected' && ($user['role'] ?? '') === 'Super Admin'): ?><form method="post" onsubmit="return confirm('Reject this activity and remove it from the shared calendar?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?php echo (int) $activity['id']; ?>"><button class="danger-btn mini-btn" type="submit"><i class="ri-close-line"></i> Reject</button></form><?php endif; ?></div></td>
                        <td><div class="file-actions"><button class="ghost-btn mini-btn" type="button" data-edit-activity="<?php echo (int) $activity['id']; ?>" aria-label="Edit activity"><i class="ri-edit-line"></i></button><?php if ($activity['approval_status'] === 'Pending' && ($user['role'] ?? '') === 'Super Admin'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="review"><input type="hidden" name="id" value="<?php echo (int) $activity['id']; ?>"><button class="primary-btn mini-btn" type="submit"><i class="ri-check-line"></i> Approve</button></form><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$activities): ?><tr><td colspan="7">No activities recorded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal" id="activityEditorModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <article class="modal-card activity-modal-card">
        <button class="modal-close" type="button" data-close-modal aria-label="Close activity editor"><i class="ri-close-line"></i></button>
        <div class="activity-modal-head">
            <span class="category-badge" id="activityModalMode">Activity</span>
            <h2 id="activityModalTitle">Edit Activity</h2>
        </div>
        <form class="calendar-form" method="post" id="activityEditorForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" id="activityAction" value="update">
            <input type="hidden" name="id" id="activityId">
            <input type="hidden" name="approval_status" value="Approved">
            <label class="field"><span>Title</span><input name="title" id="activityTitle" required></label>
            <div class="form-grid">
                <label class="field"><span>Type</span><select name="activity_type" id="activityType"><?php foreach ($activityTypes as $type): ?><option><?php echo h($type); ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Date</span><input type="date" name="date" id="activityDate" required></label>
            </div>
            <label class="field"><span>Venue</span><input name="venue" id="activityVenue" required></label>
            <label class="field"><span>Description</span><textarea name="description" id="activityDescription" rows="4"></textarea></label>
            <div class="action-row modal-actions">
                <?php if (($user['role'] ?? '') === 'Super Admin'): ?><button class="primary-btn" type="submit" name="action" value="review" id="approveActivityButton"><i class="ri-check-line"></i> Approve</button><button class="danger-btn" type="submit" name="action" value="reject" id="rejectActivityButton" onclick="return confirm('Reject this activity and remove it from the shared calendar?')"><i class="ri-close-line"></i> Reject</button><?php endif; ?>
                <button class="primary-btn" type="submit" name="action" value="update"><i class="ri-save-line"></i> Save Changes</button>
                <button class="danger-btn" type="submit" name="action" value="delete" id="deleteActivityButton" onclick="return confirm('Delete this activity?')"><i class="ri-delete-bin-line"></i> Delete</button>
                <button class="ghost-btn" type="button" data-close-modal>Cancel</button>
            </div>
        </form>
    </article>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<?php renderFooter(); ?>
