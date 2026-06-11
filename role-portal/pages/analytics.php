<?php
if (!portal_can('analytics')) { http_response_code(403); exit('Analytics is not available for this role.'); }
$user = portal_header('Analytics Dashboard', 'analytics');
$data = portal_analytics_data();
?>
<script>window.rolePortalCharts = <?php echo json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<section class="stats-grid">
    <?php $stats = portal_stats(); ?>
    <article><i class="ri-flask-line"></i><span>Research Statistics</span><strong><?php echo $stats['research_total']; ?></strong></article>
    <article><i class="ri-community-line"></i><span>Extension Statistics</span><strong><?php echo $stats['extension_total']; ?></strong></article>
    <article><i class="ri-checkbox-circle-line"></i><span>Approval Statistics</span><strong><?php echo $stats['approved_research'] + $stats['approved_documents']; ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending Items</span><strong><?php echo $stats['pending_research'] + $stats['pending_documents']; ?></strong></article>
</section>
<section class="chart-grid">
    <article class="panel chart-panel"><div class="panel-heading"><h2>Research Status</h2></div><canvas id="roleResearchChart"></canvas></article>
    <article class="panel chart-panel"><div class="panel-heading"><h2>Extension Progress</h2></div><canvas id="roleExtensionChart"></canvas></article>
    <article class="panel chart-panel wide"><div class="panel-heading"><h2>Approval Statistics</h2></div><canvas id="roleDocumentChart"></canvas></article>
</section>
<?php portal_footer(); ?>
