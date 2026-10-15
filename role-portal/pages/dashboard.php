<?php
$user = portal_header('Dashboard', 'dashboard');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$isFacultyExtensionist = PORTAL_ROLE === 'Faculty Extensionist';
$isResearcher = PORTAL_ROLE === 'Researcher';
$isResearchDirector = PORTAL_ROLE === 'Research Director';
$isExtensionDirector = PORTAL_ROLE === 'Extension Director';

if ($isExtensionCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $statsStmt = db()->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN COALESCE(e.status, e.progress_status) IN ('Pending', 'Pending Coordinator Review', 'Planning') THEN 1 END) AS planning, COUNT(CASE WHEN COALESCE(e.status, e.progress_status) IN ('Ongoing', 'Pending Director Review', 'Pending Executive Approval') THEN 1 END) AS ongoing, COUNT(CASE WHEN COALESCE(e.status, e.progress_status) = 'Approved' THEN 1 END) AS completed FROM extension_projects e JOIN users submitter ON submitter.id = e.submitted_by WHERE 1=1" . $scopeSql);
    $statsStmt->execute([$scopeId]);
    $stats = $statsStmt->fetch();
    $activities = db()->query("SELECT * FROM activities WHERE approval_status = 'Approved' AND archived_at IS NULL AND activity_type IN ('Training', 'Workshop', 'Community Outreach') ORDER BY date ASC LIMIT 6")->fetchAll();
} elseif ($isResearchCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $statsStmt = db()->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN r.status IN ('Pending', 'Under Review', 'Pending Coordinator Review') THEN 1 END) AS in_review, COUNT(CASE WHEN r.status = 'Approved' THEN 1 END) AS approved, COUNT(CASE WHEN r.status = 'Declined' THEN 1 END) AS declined FROM research_projects r JOIN users submitter ON submitter.id = r.submitted_by WHERE 1=1" . $scopeSql);
    $statsStmt->execute([$scopeId]);
    $stats = $statsStmt->fetch();
    $activities = db()->query("SELECT * FROM activities WHERE approval_status = 'Approved' AND archived_at IS NULL AND activity_type = 'Project Defense' ORDER BY date ASC LIMIT 6")->fetchAll();
} elseif ($isResearchDirector || $isExtensionDirector || $isFacultyExtensionist || $isResearcher) {
    $stats = portal_stats();
    $activities = portal_recent_activities();
} else {
    $stats = portal_stats();
    $activities = portal_recent_activities();
}
$announcements = array_slice(portal_announcements(), 0, 3);
$heroDescription = match (PORTAL_ROLE) {
    'Research Director' => 'Oversee university-wide research proposals, lead AIHR evaluations, and allocate research grants.',
    'Extension Director' => 'Oversee university-wide extension programs, evaluate community impact, and allocate extension funding.',
    'Extension Coordinator' => 'Manage and endorse college extension projects and monitor community outreach activities.',
    'Research Coordinator' => 'Manage and screen college research proposals and endorse them to the Director.',
    default => portal_config()['purpose'] ?? 'Welcome to the OVPRE portal.'
};
$submissionLabel = match (PORTAL_ROLE) {
    'Research Director' => 'Review Research Proposals',
    'Extension Director' => 'Review Extension Proposals',
    'Extension Coordinator' => 'Review Extension Projects',
    'Research Coordinator' => 'Review Research Proposals',
    'Faculty Extensionist' => 'Submit Extension Proposal',
    'Researcher' => 'Submit Research Proposal',
    default => 'New Submission'
};
$submissionLink = match (PORTAL_ROLE) {
    'Extension Director', 'Extension Coordinator', 'Faculty Extensionist' => 'extension.php',
    default => 'research.php'
};
?>
<section class="hero">
    <div><p class="welcome-greeting">Welcome back, <?php echo h($user['fullname']); ?></p><p class="welcome-description"><?php echo h($heroDescription); ?></p></div>
    <a class="primary-btn" href="<?php echo h($submissionLink); ?>"><i class="ri-add-circle-line"></i> <?php echo h($submissionLabel); ?></a>
    <a class="ghost-btn" href="../submitted-files.php"><i class="ri-folder-open-line"></i> View All Files</a>
</section>
<section class="stats-grid">
    <?php if ($isExtensionCoordinator): ?>
    <article><i class="ri-community-line"></i><span>Total College Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>For Screening / Planning</span><strong><?php echo (int) ($stats['planning'] ?? 0); ?></strong></article>
    <article><i class="ri-loader-4-line"></i><span>In Review / Ongoing</span><strong><?php echo (int) ($stats['ongoing'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved</span><strong><?php echo (int) ($stats['completed'] ?? 0); ?></strong></article>
    <?php elseif ($isResearchCoordinator): ?>
    <article><i class="ri-flask-line"></i><span>Total College Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Screening</span><strong><?php echo (int) ($stats['in_review'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved</span><strong><?php echo (int) ($stats['approved'] ?? 0); ?></strong></article>
    <article><i class="ri-close-circle-line"></i><span>Needs Revision / Declined</span><strong><?php echo (int) ($stats['declined'] ?? 0); ?></strong></article>
    <?php elseif ($isResearchDirector): ?>
    <article><i class="ri-flask-line"></i><span>Total Research Proposals</span><strong><?php echo (int) ($stats['research_total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Director Review</span><strong><?php echo (int) ($stats['pending_research'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved Research</span><strong><?php echo (int) ($stats['approved_research'] ?? 0); ?></strong></article>
    <article><i class="ri-shield-check-line"></i><span>AIHR Status</span><strong>Active</strong></article>
    <?php elseif ($isExtensionDirector): ?>
    <article><i class="ri-community-line"></i><span>Total Extension Proposals</span><strong><?php echo (int) ($stats['extension_total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Director Review</span><strong><?php echo (int) ($stats['pending_extension'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved Extension</span><strong><?php echo (int) ($stats['approved_extension'] ?? 0); ?></strong></article>
    <article><i class="ri-shield-check-line"></i><span>Director Status</span><strong>Active</strong></article>
    <?php elseif ($isFacultyExtensionist): ?>
    <article><i class="ri-community-line"></i><span>My Extension Submissions</span><strong><?php echo (int) ($stats['extension_total'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>My Approved Projects</span><strong><?php echo (int) ($stats['approved_extension'] ?? 0); ?></strong></article>
    <article><i class="ri-shield-check-line"></i><span>Account</span><strong>Active</strong></article>
    <?php else: ?>
    <article><i class="ri-flask-line"></i><span>My Research Submissions</span><strong><?php echo (int) ($stats['research_total'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>My Approved Projects</span><strong><?php echo (int) ($stats['approved_research'] ?? 0); ?></strong></article>
    <article><i class="ri-shield-check-line"></i><span>Account</span><strong>Active</strong></article>
    <?php endif; ?>
</section>
<section class="two-col">
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator || $isResearcher ? 'Research Activities' : ($isExtensionCoordinator || $isFacultyExtensionist ? 'Extension Activities' : 'Recent Activities'); ?></h2><?php if (portal_can('calendar')): ?><a href="calendar.php">Calendar</a><?php endif; ?></div><div class="list"><?php foreach ($activities as $activity): ?><div class="list-row"><i class="ri-calendar-event-line"></i><div><strong><?php echo h($activity['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($activity['date'])) . ' | ' . $activity['venue']); ?></span></div></div><?php endforeach; ?></div></article>
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator ? 'Research Announcements' : ($isExtensionCoordinator ? 'Extension Announcements' : 'Announcements'); ?></h2><a href="announcements.php">View all</a></div><div class="list"><?php foreach ($announcements as $item): ?><div class="list-row"><i class="ri-megaphone-line"></i><div><strong><?php echo h($item['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></span></div></div><?php endforeach; ?></div></article>
</section>
<?php portal_footer(); ?>
