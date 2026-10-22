<?php
$user = portal_header('Dashboard', 'dashboard');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
$isFacultyExtensionist = PORTAL_ROLE === 'Faculty Extensionist';
$isResearcher = PORTAL_ROLE === 'Researcher';
$isResearchDirector = in_array(PORTAL_ROLE, ['Research Director', 'Director for Research and Creative Works'], true);
$isExtensionDirector = PORTAL_ROLE === 'Extension Director';
$isDirector = $isResearchDirector || $isExtensionDirector;

if ($isExtensionCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $statsStmt = db()->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN COALESCE(e.status, CAST(e.progress_status AS VARCHAR)) IN ('Pending', 'Pending Coordinator Review', 'Planning') THEN 1 END) AS planning, COUNT(CASE WHEN COALESCE(e.status, CAST(e.progress_status AS VARCHAR)) IN ('Ongoing', 'Pending Director Review', 'Pending Executive Approval') THEN 1 END) AS ongoing, COUNT(CASE WHEN COALESCE(e.status, CAST(e.progress_status AS VARCHAR)) = 'Approved' THEN 1 END) AS completed FROM extension_projects e JOIN users submitter ON submitter.id = e.submitted_by WHERE 1=1" . $scopeSql);
    $statsStmt->execute([$scopeId]);
    $stats = $statsStmt->fetch();
    $activities = db()->query("SELECT * FROM activities WHERE approval_status = 'Approved' AND archived_at IS NULL AND activity_type IN ('Training', 'Workshop', 'Community Outreach') ORDER BY date ASC LIMIT 6")->fetchAll();
} elseif ($isResearchCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $statsStmt = db()->prepare("SELECT COUNT(*) AS total, COUNT(CASE WHEN r.status IN ('Pending', 'Under Review', 'Pending Coordinator Review') THEN 1 END) AS in_review, COUNT(CASE WHEN r.status = 'Approved' THEN 1 END) AS approved, COUNT(CASE WHEN r.status = 'Declined' THEN 1 END) AS declined FROM research_projects r JOIN users submitter ON submitter.id = r.submitted_by WHERE r.archived_at IS NULL" . $scopeSql);
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

$departmentCounts = [];
$monthlySubmissions = [];
$statusDistribution = [];
$pendingDirectorProposals = [];

if ($isDirector) {
    if ($isResearchDirector) {
        $deptStmt = db()->query("
            SELECT dep.department_name, COUNT(r.id) AS total 
            FROM departments dep 
            LEFT JOIN users u ON u.department_id = dep.id 
            LEFT JOIN research_projects r ON r.submitted_by = u.id AND r.archived_at IS NULL 
            GROUP BY dep.id, dep.department_name 
            ORDER BY total DESC, dep.department_name
        ");
        $departmentCounts = $deptStmt ? $deptStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $monthlyStmt = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
            ? db()->query("SELECT TO_CHAR(created_at, 'Mon') AS label, COUNT(*) AS total FROM research_projects WHERE created_at >= NOW() - INTERVAL '12 MONTH' AND archived_at IS NULL GROUP BY TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)")
            : db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM research_projects WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND archived_at IS NULL GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)");
        $monthlySubmissions = $monthlyStmt ? $monthlyStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $statusStmt = db()->query("SELECT status, COUNT(*) AS total FROM research_projects WHERE archived_at IS NULL GROUP BY status ORDER BY total DESC");
        $statusDistribution = $statusStmt ? $statusStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        try {
            $queueStmt = db()->query("
                SELECT r.id, r.title, r.status, r.workflow_stage, r.created_at, u.fullname, dep.department_name, 'Research' AS proposal_type
                FROM research_projects r 
                JOIN users u ON u.id = r.submitted_by 
                LEFT JOIN departments dep ON dep.id = u.department_id 
                WHERE r.archived_at IS NULL AND r.status IN ('Pending Director Review', 'Under Review')
                ORDER BY r.created_at DESC 
                LIMIT 6
            ");
            if ($queueStmt) {
                $pendingDirectorProposals = $queueStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {}
    } else {
        $deptStmt = db()->query("
            SELECT dep.department_name, COUNT(e.id) AS total 
            FROM departments dep 
            LEFT JOIN users u ON u.department_id = dep.id 
            LEFT JOIN extension_projects e ON e.submitted_by = u.id AND e.archived_at IS NULL 
            GROUP BY dep.id, dep.department_name 
            ORDER BY total DESC, dep.department_name
        ");
        $departmentCounts = $deptStmt ? $deptStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $monthlyStmt = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
            ? db()->query("SELECT TO_CHAR(created_at, 'Mon') AS label, COUNT(*) AS total FROM extension_projects WHERE created_at >= NOW() - INTERVAL '12 MONTH' AND archived_at IS NULL GROUP BY TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)")
            : db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM extension_projects WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND archived_at IS NULL GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)");
        $monthlySubmissions = $monthlyStmt ? $monthlyStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $statusStmt = db()->query("SELECT COALESCE(status, progress_status) AS status, COUNT(*) AS total FROM extension_projects WHERE archived_at IS NULL GROUP BY COALESCE(status, progress_status) ORDER BY total DESC");
        $statusDistribution = $statusStmt ? $statusStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        try {
            $queueStmt = db()->query("
                SELECT e.id, e.project_title AS title, e.status, e.workflow_stage, e.created_at, u.fullname, dep.department_name, 'Extension' AS proposal_type
                FROM extension_projects e 
                JOIN users u ON u.id = e.submitted_by 
                LEFT JOIN departments dep ON dep.id = u.department_id 
                WHERE e.archived_at IS NULL AND (e.status IN ('Pending Director Review', 'Under Review') OR e.progress_status = 'Pending Director Review')
                ORDER BY e.created_at DESC 
                LIMIT 6
            ");
            if ($queueStmt) {
                $pendingDirectorProposals = $queueStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {}
    }

    if (empty($monthlySubmissions)) {
        for ($i = 5; $i >= 0; $i--) {
            $monthlySubmissions[] = ['label' => date('M', strtotime("-$i months")), 'total' => 0];
        }
    }
}

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
    <div><p class="welcome-greeting">Welcome back, <?php echo h($user['fullname']); ?></p></div>
    <div class="hero-actions">
        <a class="primary-btn" href="<?php echo h($submissionLink); ?>"><i class="ri-add-circle-line"></i> <?php echo h($submissionLabel); ?></a>
        <a class="ghost-btn" href="../submitted-files.php"><i class="ri-folder-open-line"></i> View All Files</a>
    </div>
</section>
<?php if (!$isDirector): ?>
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
<?php endif; ?>

<?php if ($isDirector): ?>
<section class="analytics-grid">
    <article class="panel chart-panel wide department-output-panel">
        <div class="panel-heading">
            <h2>Colleges Output</h2>
            <span><?php echo $isResearchDirector ? 'Research proposal distribution' : 'Extension project distribution'; ?></span>
        </div>
        <canvas id="reportDepartmentChart"></canvas>
    </article>
    <article class="panel chart-panel dashboard-summary-chart">
        <div class="panel-heading">
            <h2>Monthly Submissions</h2>
            <span><?php echo $isResearchDirector ? 'Research records' : 'Extension records'; ?></span>
        </div>
        <canvas id="reportMonthlyChart"></canvas>
    </article>
    <article class="panel chart-panel dashboard-summary-chart">
        <div class="panel-heading">
            <h2>Approval Rate</h2>
            <span><?php echo $isResearchDirector ? 'Research status' : 'Extension status'; ?></span>
        </div>
        <canvas id="reportResearchChart"></canvas>
    </article>
</section>

<!-- Director Stat Cards (Placed on top of Director Review Queue) -->
<section class="stats-grid">
    <?php if ($isResearchDirector): ?>
    <article><i class="ri-flask-line"></i><span>Total Research Proposals</span><strong><?php echo (int) ($stats['research_total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Director Review</span><strong><?php echo (int) ($stats['pending_research'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved Research</span><strong><?php echo (int) ($stats['approved_research'] ?? 0); ?></strong></article>
    <article><i class="ri-shield-check-line"></i><span>RIUH Status</span><strong>Active</strong></article>
    <?php else: ?>
    <article><i class="ri-community-line"></i><span>Total Extension Proposals</span><strong><?php echo (int) ($stats['extension_total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Director Review</span><strong><?php echo (int) ($stats['pending_extension'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved Extension</span><strong><?php echo (int) ($stats['approved_extension'] ?? 0); ?></strong></article>
    <article><i class="ri-shield-check-line"></i><span>Director Status</span><strong>Active</strong></article>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>Director Review Queue (<?php echo $isResearchDirector ? 'Research Proposals' : 'Extension Projects'; ?>)</h2>
        <?php if (!empty($pendingDirectorProposals)): ?>
            <span style="font-weight: 700; color: #15803d;"><?php echo count($pendingDirectorProposals); ?> proposal<?php echo count($pendingDirectorProposals) === 1 ? '' : 's'; ?> awaiting your evaluation</span>
        <?php else: ?>
            <span style="color: var(--muted); font-size: 0.9rem;">0 proposals awaiting evaluation</span>
        <?php endif; ?>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Proposal</th>
                    <th>Proponent</th>
                    <th>College</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingDirectorProposals as $prop): ?>
                    <tr>
                        <td><strong><span class="status-pill status-<?php echo $prop['proposal_type'] === 'Research' ? 'approved' : 'planning'; ?>"><?php echo h($prop['proposal_type']); ?></span></strong></td>
                        <td><?php echo h($prop['title']); ?></td>
                        <td><?php echo h($prop['fullname'] ?? 'Faculty'); ?></td>
                        <td><?php echo h($prop['department_name'] ?? 'Unassigned'); ?></td>
                        <td><span class="status-pill status-pending-director-review"><?php echo h($prop['status']); ?></span></td>
                        <td>
                            <a class="ghost-btn action-btn" href="<?php echo $prop['proposal_type'] === 'Research' ? 'research.php#project-' . (int) $prop['id'] : 'extension.php#project-' . (int) $prop['id']; ?>">
                                <i class="ri-checkbox-circle-line"></i><span>Review</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pendingDirectorProposals)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">No proposals currently awaiting director review.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
    window.reportCharts = {
        departments: <?php echo json_encode($departmentCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        monthly: <?php echo json_encode($monthlySubmissions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        research: <?php echo json_encode($statusDistribution, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        extension: <?php echo json_encode($statusDistribution, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    };
</script>
<?php endif; ?>
<section class="two-col">
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator || $isResearcher ? 'Research Activities' : ($isExtensionCoordinator || $isFacultyExtensionist ? 'Extension Activities' : 'Recent Activities'); ?></h2><?php if (portal_can('calendar')): ?><a href="calendar.php">Calendar</a><?php endif; ?></div><div class="list"><?php foreach ($activities as $activity): ?><div class="list-row"><i class="ri-calendar-event-line"></i><div><strong><?php echo h($activity['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($activity['date'])) . ' | ' . $activity['venue']); ?></span></div></div><?php endforeach; ?></div></article>
    <article class="panel"><div class="panel-heading"><h2><?php echo $isResearchCoordinator ? 'Research Announcements' : ($isExtensionCoordinator ? 'Extension Announcements' : 'Announcements'); ?></h2><a href="announcements.php">View all</a></div><div class="list"><?php foreach ($announcements as $item): ?><div class="list-row"><i class="ri-megaphone-line"></i><div><strong><?php echo h($item['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></span></div></div><?php endforeach; ?></div></article>
</section>
<?php portal_footer(); ?>
