<?php
$user = portal_header('Dashboard', 'dashboard');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$isFacultyExtensionist = PORTAL_ROLE === 'Faculty Extensionist';
$isResearcher = PORTAL_ROLE === 'Researcher';
if ($isExtensionCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $statsStmt = db()->prepare('SELECT COUNT(*) AS total, COUNT(CASE WHEN e.progress_status = "Planning" THEN 1 END) AS planning, COUNT(CASE WHEN e.progress_status = "Ongoing" THEN 1 END) AS ongoing, COUNT(CASE WHEN e.progress_status = "Completed" THEN 1 END) AS completed FROM extension_projects e JOIN users submitter ON submitter.id = e.submitted_by WHERE 1=1' . $scopeSql);
    $statsStmt->execute([$scopeId]);
    $stats = $statsStmt->fetch();
    $activities = db()->query('SELECT * FROM activities WHERE approval_status = "Approved" AND archived_at IS NULL AND activity_type IN ("Training", "Workshop", "Community Outreach") ORDER BY date ASC LIMIT 6')->fetchAll();
} elseif ($isResearchCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $statsStmt = db()->prepare('SELECT COUNT(*) AS total, COUNT(CASE WHEN r.status IN ("Pending", "Under Review") THEN 1 END) AS in_review, COUNT(CASE WHEN r.status = "Approved" THEN 1 END) AS approved, COUNT(CASE WHEN r.status = "Declined" THEN 1 END) AS declined FROM research_projects r JOIN users submitter ON submitter.id = r.submitted_by WHERE 1=1' . $scopeSql);
    $statsStmt->execute([$scopeId]);
    $stats = $statsStmt->fetch();
    $activities = db()->query('SELECT * FROM activities WHERE approval_status = "Approved" AND archived_at IS NULL AND activity_type = "Project Defense" ORDER BY date ASC LIMIT 6')->fetchAll();
} elseif ($isFacultyExtensionist || $isResearcher) {
    $stats = portal_stats();
    $activities = portal_recent_activities();
} else {
    $stats = portal_stats();
    $activities = portal_recent_activities();
}
$announcements = array_slice(portal_announcements(), 0, 3);
$heroDescription = $isExtensionCoordinator
    ? 'Manage your community projects and keep track of their progress.'
    : ($isResearchCoordinator
        ? 'Manage your research proposals and follow each submission through review.'
        : portal_config()['purpose']);
$submissionLabel = $isExtensionCoordinator
    ? 'Submit Extension Project'
    : ($isResearchCoordinator || $isResearcher ? 'Submit Research Proposal' : ($isFacultyExtensionist ? 'Submit Extension Proposal' : 'New Submission'));
?>
<section class="hero">
    <div><p class="welcome-greeting">Welcome back, <?php echo h($user['fullname']); ?></p><p class="welcome-description"><?php echo h($heroDescription); ?></p></div>
    <a class="primary-btn" href="<?php echo portal_can('research') ? 'research.php' : 'extension.php'; ?>"><i class="ri-add-circle-line"></i> <?php echo h($submissionLabel); ?></a>
    <a class="ghost-btn" href="../submitted-files.php"><i class="ri-folder-open-line"></i> View All Files</a>
</section>
<section class="stats-grid">
    <?php if ($isExtensionCoordinator): ?>
    <article><i class="ri-community-line"></i><span>Total Extension Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Planning</span><strong><?php echo (int) ($stats['planning'] ?? 0); ?></strong></article>
    <article><i class="ri-loader-4-line"></i><span>Ongoing</span><strong><?php echo (int) ($stats['ongoing'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Completed</span><strong><?php echo (int) ($stats['completed'] ?? 0); ?></strong></article>
    <?php elseif ($isResearchCoordinator || $isResearcher): ?>
    <article><i class="ri-flask-line"></i><span>Total Research Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending / Under Review</span><strong><?php echo (int) ($stats['in_review'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved</span><strong><?php echo (int) ($stats['approved'] ?? 0); ?></strong></article>
    <article><i class="ri-close-circle-line"></i><span>Rejected</span><strong><?php echo (int) ($stats['declined'] ?? 0); ?></strong></article>
    <?php else: ?>
    <?php if ($isFacultyExtensionist): ?><article><i class="ri-community-line"></i><span>My Extension Submissions</span><strong><?php echo (int) $stats['extension_total']; ?></strong></article><article><i class="ri-time-line"></i><span>My Research Submissions</span><strong>0</strong></article><article><i class="ri-shield-check-line"></i><span>Account</span><strong>Active</strong></article>
    <?php else: ?><article><i class="ri-flask-line"></i><span>My Research Submissions</span><strong><?php echo (int) $stats['research_total']; ?></strong></article><article><i class="ri-time-line"></i><span>My Extension Submissions</span><strong>0</strong></article><article><i class="ri-shield-check-line"></i><span>Account</span><strong>Active</strong></article><?php endif; ?>
    <?php endif; ?>
</section>
<section class="two-col">
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator || $isResearcher ? 'Research Activities' : ($isExtensionCoordinator || $isFacultyExtensionist ? 'Extension Activities' : 'Recent Activities'); ?></h2><?php if (portal_can('calendar')): ?><a href="calendar.php">Calendar</a><?php endif; ?></div><div class="list"><?php foreach ($activities as $activity): ?><div class="list-row"><i class="ri-calendar-event-line"></i><div><strong><?php echo h($activity['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($activity['date'])) . ' | ' . $activity['venue']); ?></span></div></div><?php endforeach; ?></div></article>
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator ? 'Research Announcements' : ($isExtensionCoordinator ? 'Extension Announcements' : 'Announcements'); ?></h2><a href="announcements.php">View all</a></div><div class="list"><?php foreach ($announcements as $item): ?><div class="list-row"><i class="ri-megaphone-line"></i><div><strong><?php echo h($item['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></span></div></div><?php endforeach; ?></div></article>
</section>
<?php portal_footer(); ?>
