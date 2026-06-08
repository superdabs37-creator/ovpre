<?php
require_once 'includes/layout.php';
$user = require_login(['Super Admin', 'OVPRE Staff']);
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('INSERT INTO activities (title, activity_type, date, venue, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([trim($_POST['title']), $_POST['activity_type'], $_POST['date'], trim($_POST['venue']), trim($_POST['description'] ?? ''), $user['id']]);
    log_action((int) $user['id'], 'Created activity: ' . trim($_POST['title']));
    flash('success', 'Activity saved.');
    redirect_to('activities.php');
}
$notice = flash();
$activities = db()->query('SELECT * FROM activities ORDER BY date DESC')->fetchAll();
renderHeader('Activity Management', 'activities');
?>
<section class="module-header"><div class="module-icon"><i class="ri-calendar-event-line"></i></div><div><p>Schedule Tracking</p><h2>Activities</h2><span>Create seminars, trainings, workshops, and community outreach schedules.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel"><form class="editor-panel" method="post"><?php echo csrf_field(); ?><div class="form-grid"><label class="field"><span>Title</span><input name="title" required></label><label class="field"><span>Type</span><select name="activity_type"><option>Seminar</option><option>Training</option><option>Workshop</option><option>Community Outreach</option></select></label><label class="field"><span>Date</span><input type="date" name="date" required></label><label class="field"><span>Venue</span><input name="venue" required></label></div><label class="field"><span>Description</span><textarea name="description" rows="4"></textarea></label><button class="primary-btn post-btn"><i class="ri-save-line"></i> Save Activity</button></form></section>
<section class="panel"><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Activity</th><th>Type</th><th>Venue</th></tr></thead><tbody><?php foreach ($activities as $activity): ?><tr><td><?php echo h(date('M d, Y', strtotime($activity['date']))); ?></td><td><?php echo h($activity['title']); ?></td><td><?php echo h($activity['activity_type']); ?></td><td><?php echo h($activity['venue']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php renderFooter(); ?>
