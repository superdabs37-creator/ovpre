<?php
$user = portal_header('Dashboard', 'dashboard');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
if ($isExtensionCoordinator) {
    $stats = db()->query('SELECT COUNT(*) AS total, SUM(progress_status = "Planning") AS planning, SUM(progress_status = "Ongoing") AS ongoing, SUM(progress_status = "Completed") AS completed FROM extension_projects')->fetch();
    $activities = db()->query('SELECT * FROM activities WHERE approval_status = "Approved" AND activity_type IN ("Training", "Workshop", "Community Outreach") ORDER BY date ASC LIMIT 6')->fetchAll();
} elseif ($isResearchCoordinator) {
    $stats = db()->query('SELECT COUNT(*) AS total, SUM(status IN ("Pending", "Under Review")) AS in_review, SUM(status = "Approved") AS approved, SUM(status = "Declined") AS declined FROM research_projects')->fetch();
    $activities = db()->query('SELECT * FROM activities WHERE approval_status = "Approved" AND activity_type = "Project Defense" ORDER BY date ASC LIMIT 6')->fetchAll();
} else {
    $stats = portal_stats();
    $activities = portal_recent_activities();
}
$announcements = array_slice(portal_announcements(), 0, 3);
?>
<section class="hero">
    <div><p>Welcome, <?php echo h($user['fullname']); ?></p><h2><?php echo h(portal_config()['purpose']); ?></h2></div>
    <a class="primary-btn" href="<?php echo portal_can('research') ? 'research.php' : 'extension.php'; ?>"><i class="ri-add-circle-line"></i> New Submission</a>
</section>
<section class="stats-grid">
    <?php if ($isExtensionCoordinator): ?>
    <article><i class="ri-community-line"></i><span>Total Extension Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Planning</span><strong><?php echo (int) ($stats['planning'] ?? 0); ?></strong></article>
    <article><i class="ri-loader-4-line"></i><span>Ongoing</span><strong><?php echo (int) ($stats['ongoing'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Completed</span><strong><?php echo (int) ($stats['completed'] ?? 0); ?></strong></article>
    <?php elseif ($isResearchCoordinator): ?>
    <article><i class="ri-flask-line"></i><span>Total Research Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending / Under Review</span><strong><?php echo (int) ($stats['in_review'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved</span><strong><?php echo (int) ($stats['approved'] ?? 0); ?></strong></article>
    <article><i class="ri-close-circle-line"></i><span>Rejected</span><strong><?php echo (int) ($stats['declined'] ?? 0); ?></strong></article>
    <?php else: ?>
    <article><i class="ri-flask-line"></i><span>Total Research Submissions</span><strong><?php echo $stats['research_total']; ?></strong></article>
    <article><i class="ri-community-line"></i><span>Total Extension Projects</span><strong><?php echo $stats['extension_total']; ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Approvals</span><strong><?php echo $stats['pending_research'] + $stats['pending_documents']; ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved Projects</span><strong><?php echo $stats['approved_research'] + $stats['approved_documents']; ?></strong></article>
    <?php endif; ?>
</section>
<section class="two-col">
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator ? 'Research Activities' : ($isExtensionCoordinator ? 'Extension Activities' : 'Recent Activities'); ?></h2><a href="calendar.php">Calendar</a></div><div class="list"><?php foreach ($activities as $activity): ?><div class="list-row"><i class="ri-calendar-event-line"></i><div><strong><?php echo h($activity['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($activity['date'])) . ' | ' . $activity['venue']); ?></span></div></div><?php endforeach; ?></div></article>
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator ? 'Research Announcements' : ($isExtensionCoordinator ? 'Extension Announcements' : 'Announcements'); ?></h2><a href="announcements.php">View all</a></div><div class="list"><?php foreach ($announcements as $item): ?><div class="list-row"><i class="ri-megaphone-line"></i><div><strong><?php echo h($item['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></span></div></div><?php endforeach; ?></div></article>
</section>
<?php portal_footer(); ?>
