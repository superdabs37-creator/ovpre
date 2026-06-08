<?php
require_once 'includes/layout.php';
require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator', 'Extension Coordinator']);
$categoryCounts = db()->query('SELECT c.category_name, COUNT(d.id) total FROM categories c LEFT JOIN documents d ON d.category_id = c.id GROUP BY c.id ORDER BY c.category_name')->fetchAll();
$research = db()->query('SELECT status, COUNT(*) total FROM research_projects GROUP BY status')->fetchAll();
$extension = db()->query('SELECT progress_status status, COUNT(*) total FROM extension_projects GROUP BY progress_status')->fetchAll();
renderHeader('Reports', 'reports');
?>
<section class="module-header"><div class="module-icon"><i class="ri-file-chart-line"></i></div><div><p>Reports Module</p><h2>Research and Extension Reports</h2><span>Generate monthly, quarterly, annual, project status, and accomplishment report summaries.</span></div></section>
<section class="quick-actions"><a href="#" onclick="window.print();return false;"><i class="ri-printer-line"></i> Print PDF</a><a href="admin-documents.php"><i class="ri-file-excel-line"></i> Export source table</a><a href="research.php"><i class="ri-flask-line"></i> Research repository</a><a href="extension.php"><i class="ri-community-line"></i> Extension status</a></section>
<section class="analytics-grid">
    <article class="panel chart-panel"><div class="panel-heading"><h2>Documents by Category</h2><span>Record distribution</span></div><canvas id="reportCategoryChart"></canvas></article>
    <article class="panel chart-panel"><div class="panel-heading"><h2>Research Status</h2><span>Review process</span></div><canvas id="reportResearchChart"></canvas></article>
    <article class="panel chart-panel wide"><div class="panel-heading"><h2>Extension Project Status</h2><span>Progress monitoring</span></div><canvas id="reportExtensionChart"></canvas></article>
</section>
<script>window.reportCharts = <?php echo json_encode(['categories' => $categoryCounts, 'research' => $research, 'extension' => $extension]); ?>;</script>
<?php renderFooter(); ?>
