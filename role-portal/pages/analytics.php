<?php
if (!portal_can('analytics')) { http_response_code(403); exit('Analytics is not available for this role.'); }
$user = portal_header('Analytics Dashboard', 'analytics');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
if ($isExtensionCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $dataStmt = db()->prepare('SELECT e.progress_status AS label, COUNT(*) AS total FROM extension_projects e JOIN users submitter ON submitter.id = e.submitted_by WHERE 1=1' . $scopeSql . ' GROUP BY e.progress_status');
    $dataStmt->execute([$scopeId]);
    $data = ['extension' => $dataStmt->fetchAll()];
} elseif ($isResearchCoordinator) {
    $scopeSql = !empty($user['department_id']) ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
    $scopeId = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id'];
    $dataStmt = db()->prepare('SELECT r.status AS label, COUNT(*) AS total FROM research_projects r JOIN users submitter ON submitter.id = r.submitted_by WHERE 1=1' . $scopeSql . ' GROUP BY r.status');
    $dataStmt->execute([$scopeId]);
    $data = ['research' => $dataStmt->fetchAll()];
} else {
    $data = portal_analytics_data();
}
?>
<script>window.rolePortalCharts = <?php echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<section class="stats-grid">
<?php if ($isExtensionCoordinator): $statsStmt = db()->prepare('SELECT COUNT(*) AS total, COUNT(CASE WHEN e.progress_status = "Planning" THEN 1 END) AS planning, COUNT(CASE WHEN e.progress_status = "Ongoing" THEN 1 END) AS ongoing, COUNT(CASE WHEN e.progress_status = "Completed" THEN 1 END) AS completed FROM extension_projects e JOIN users submitter ON submitter.id = e.submitted_by WHERE 1=1' . $scopeSql); $statsStmt->execute([$scopeId]); $stats = $statsStmt->fetch(); ?>
    <article><i class="ri-community-line"></i><span>Total Extension Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Planning</span><strong><?php echo (int) ($stats['planning'] ?? 0); ?></strong></article>
    <article><i class="ri-loader-4-line"></i><span>Ongoing</span><strong><?php echo (int) ($stats['ongoing'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Completed</span><strong><?php echo (int) ($stats['completed'] ?? 0); ?></strong></article>
    <?php elseif ($isResearchCoordinator): $statsStmt = db()->prepare('SELECT COUNT(*) AS total, COUNT(CASE WHEN r.status IN ("Pending", "Under Review") THEN 1 END) AS in_review, COUNT(CASE WHEN r.status = "Approved" THEN 1 END) AS approved, COUNT(CASE WHEN r.status = "Declined" THEN 1 END) AS declined FROM research_projects r JOIN users submitter ON submitter.id = r.submitted_by WHERE 1=1' . $scopeSql); $statsStmt->execute([$scopeId]); $stats = $statsStmt->fetch(); ?>
    <article><i class="ri-flask-line"></i><span>Total Research Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending / Under Review</span><strong><?php echo (int) ($stats['in_review'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approved</span><strong><?php echo (int) ($stats['approved'] ?? 0); ?></strong></article>
    <article><i class="ri-close-circle-line"></i><span>Rejected</span><strong><?php echo (int) ($stats['declined'] ?? 0); ?></strong></article>
    <?php else: $stats = portal_stats(); ?>
    <article><i class="ri-flask-line"></i><span>Research Statistics</span><strong><?php echo $stats['research_total']; ?></strong></article>
    <article><i class="ri-community-line"></i><span>Extension Statistics</span><strong><?php echo $stats['extension_total']; ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approval Statistics</span><strong><?php echo $stats['approved_research'] + $stats['approved_documents']; ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Items</span><strong><?php echo $stats['pending_research'] + $stats['pending_documents']; ?></strong></article>
    <?php endif; ?>
</section>
<?php if ($isExtensionCoordinator): ?>
<section class="chart-grid"><article class="panel chart-panel wide"><div class="panel-heading"><h2>Extension Project Progress</h2></div><canvas id="roleExtensionChart"></canvas></article></section>
<?php elseif ($isResearchCoordinator): ?>
<section class="chart-grid"><article class="panel chart-panel wide"><div class="panel-heading"><h2>Research Submission Status</h2></div><canvas id="roleResearchChart"></canvas></article></section>
<?php else: ?>
<section class="chart-grid">
    <article class="panel chart-panel"><div class="panel-heading"><h2>Research Status</h2></div><canvas id="roleResearchChart"></canvas></article>
    <article class="panel chart-panel"><div class="panel-heading"><h2>Extension Progress</h2></div><canvas id="roleExtensionChart"></canvas></article>
    <article class="panel chart-panel wide"><div class="panel-heading"><h2>Approval Statistics</h2></div><canvas id="roleDocumentChart"></canvas></article>
</section>
<?php endif; ?>
<?php portal_footer(); ?>
