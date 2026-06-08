<?php
$user = portal_header('Dashboard', 'dashboard');
$stats = portal_stats();
$activities = portal_recent_activities();
$announcements = array_slice(portal_announcements(), 0, 3);
?>
<section class="hero">
    <div><p>Welcome, <?php echo h($user['fullname']); ?></p><h2><?php echo h(portal_config()['purpose']); ?></h2></div>
    <a class="primary-btn" href="<?php echo portal_can('research') ? 'research.php' : 'extension.php'; ?>"><i class="ri-add-circle-line"></i> New Submission</a>
</section>
<section class="stats-grid">
    <article><i class="ri-flask-line"></i><span>Total Research Submissions</span><strong><?php echo $stats['research_total']; ?></strong></article>
    <article><i class="ri-community-line"></i><span>Total Extension Projects</span><strong><?php echo $stats['extension_total']; ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Approvals</span><strong><?php echo $stats['pending_research'] + $stats['pending_documents']; ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved Projects</span><strong><?php echo $stats['approved_research'] + $stats['approved_documents']; ?></strong></article>
</section>
<section class="two-col">
    <article class="panel"><div class="panel-heading"><h2>Recent Activities</h2><a href="calendar.php">Calendar</a></div><div class="list"><?php foreach ($activities as $activity): ?><div class="list-row"><i class="ri-calendar-event-line"></i><div><strong><?php echo h($activity['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($activity['date'])) . ' | ' . $activity['venue']); ?></span></div></div><?php endforeach; ?></div></article>
    <article class="panel"><div class="panel-heading"><h2>Announcements</h2><a href="announcements.php">View all</a></div><div class="list"><?php foreach ($announcements as $item): ?><div class="list-row"><i class="ri-megaphone-line"></i><div><strong><?php echo h($item['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></span></div></div><?php endforeach; ?></div></article>
</section>
<?php portal_footer(); ?>
