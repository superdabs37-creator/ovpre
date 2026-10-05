<?php
if (!portal_can('analytics')) { http_response_code(403); exit('Analytics is not available for this role.'); }
$user = portal_header('Analytics Dashboard', 'analytics');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
$isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
if ($isExtensionCoordinator) {
    $data = ['extension' => db()->query('SELECT progress_status AS label, COUNT(*) AS total FROM extension_projects GROUP BY progress_status')->fetchAll()];
} elseif ($isResearchCoordinator) {
    $data = ['research' => db()->query('SELECT status AS label, COUNT(*) AS total FROM research_projects GROUP BY status')->fetchAll()];
} else {
    $data = portal_analytics_data();
}
?>
<script>window.rolePortalCharts = <?php echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<section class="stats-grid">
    <?php if ($isExtensionCoordinator): $stats = db()->query('SELECT COUNT(*) AS total, SUM(progress_status = "Planning") AS planning, SUM(progress_status = "Ongoing") AS ongoing, SUM(progress_status = "Completed") AS completed FROM extension_projects')->fetch(); ?>
    <article><i class="ri-community-line"></i><span>Total Extension Submissions</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong></article>
    <article><i class="ri-time-line"></i><span>Planning</span><strong><?php echo (int) ($stats['planning'] ?? 0); ?></strong></article>
    <article><i class="ri-loader-4-line"></i><span>Ongoing</span><strong><?php echo (int) ($stats['ongoing'] ?? 0); ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Completed</span><strong><?php echo (int) ($stats['completed'] ?? 0); ?></strong></article>
    <?php elseif ($isResearchCoordinator): $stats = db()->query('SELECT COUNT(*) AS total, SUM(status IN ("Pending", "Under Review")) AS in_review, SUM(status = "Approved") AS approved, SUM(status = "Declined") AS declined FROM research_projects')->fetch(); ?>
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
