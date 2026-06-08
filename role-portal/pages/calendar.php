<?php
$user = portal_header('Calendar', 'calendar');
$activities = db()->query('SELECT * FROM activities ORDER BY date ASC')->fetchAll();
$events = array_map(static fn($activity) => ['title' => $activity['title'], 'start' => $activity['date'], 'type' => $activity['activity_type'], 'venue' => $activity['venue']], $activities);
?>
<script>window.roleCalendarEvents = <?php echo json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<section class="panel">
    <div class="panel-heading"><h2>Research, Extension, Meetings, and Deadlines</h2><span>Shared OVPRE calendar</span></div>
    <div class="mini-calendar" id="roleMiniCalendar"></div>
</section>
<section class="panel"><div class="panel-heading"><h2>Activities</h2></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Date</th><th>Activity</th><th>Type</th><th>Venue</th></tr></thead><tbody><?php foreach ($activities as $activity): ?><tr><td><?php echo h(date('M d, Y', strtotime($activity['date']))); ?></td><td><?php echo h($activity['title']); ?></td><td><?php echo h($activity['activity_type']); ?></td><td><?php echo h($activity['venue']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php portal_footer(); ?>
