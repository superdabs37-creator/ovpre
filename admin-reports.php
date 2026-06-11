<?php
require_once 'includes/layout.php';
require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator', 'Extension Coordinator']);

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');
$from = DateTime::createFromFormat('Y-m-d', $dateFrom) ? $dateFrom : date('Y-m-01');
$to = DateTime::createFromFormat('Y-m-d', $dateTo) ? $dateTo : date('Y-m-d');
$fromDateTime = $from . ' 00:00:00';
$toDateTime = $to . ' 23:59:59';

$categoryStmt = db()->prepare('SELECT c.category_name, COUNT(d.id) total FROM categories c LEFT JOIN documents d ON d.category_id = c.id AND d.created_at BETWEEN ? AND ? GROUP BY c.id ORDER BY c.category_name');
$categoryStmt->execute([$fromDateTime, $toDateTime]);
$categoryCounts = $categoryStmt->fetchAll();

$departmentStmt = db()->prepare('SELECT dep.department_name, COUNT(d.id) total FROM departments dep LEFT JOIN documents d ON d.department_id = dep.id AND d.created_at BETWEEN ? AND ? GROUP BY dep.id ORDER BY total DESC, dep.department_name');
$departmentStmt->execute([$fromDateTime, $toDateTime]);
$departmentCounts = $departmentStmt->fetchAll();

$researchStmt = db()->prepare('SELECT status, COUNT(*) total FROM research_projects WHERE created_at BETWEEN ? AND ? GROUP BY status');
$researchStmt->execute([$fromDateTime, $toDateTime]);
$research = $researchStmt->fetchAll();

$extensionStmt = db()->prepare('SELECT progress_status status, COUNT(*) total FROM extension_projects WHERE created_at BETWEEN ? AND ? GROUP BY progress_status');
$extensionStmt->execute([$fromDateTime, $toDateTime]);
$extension = $extensionStmt->fetchAll();

$monthlyStmt = db()->prepare("SELECT DATE_FORMAT(created_at, '%b %Y') AS label, COUNT(*) AS total FROM documents WHERE created_at BETWEEN ? AND ? GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)");
$monthlyStmt->execute([$fromDateTime, $toDateTime]);
$monthlySubmissions = $monthlyStmt->fetchAll();

$documentsStmt = db()->prepare('SELECT d.id, d.title, d.status, d.submission_date, c.category_name, dep.department_name FROM documents d LEFT JOIN categories c ON c.id = d.category_id LEFT JOIN departments dep ON dep.id = d.department_id WHERE d.created_at BETWEEN ? AND ? ORDER BY d.submission_date DESC LIMIT 100');
$documentsStmt->execute([$fromDateTime, $toDateTime]);
$documents = $documentsStmt->fetchAll();

renderHeader('Reports', 'reports');
?>
<section class="module-header report-print-header"><div class="module-icon"><i class="ri-file-chart-line"></i></div><div><p>Pamantasan ng Lungsod ng San Pablo | OVPRE</p><h2>Research and Extension Reports</h2><span>Filtered report period: <?php echo h(date('M d, Y', strtotime($from))); ?> to <?php echo h(date('M d, Y', strtotime($to))); ?></span></div></section>

<section class="tool-panel no-print">
    <form class="action-row" method="get">
        <label class="field compact-field"><span>From</span><input type="date" name="from" value="<?php echo h($from); ?>"></label>
        <label class="field compact-field"><span>To</span><input type="date" name="to" value="<?php echo h($to); ?>"></label>
        <button class="primary-btn" type="submit"><i class="ri-filter-3-line"></i> Filter</button>
    </form>
    <button class="ghost-btn" type="button" onclick="window.print();"><i class="ri-printer-line"></i> Print / PDF</button>
    <button class="ghost-btn" type="button" data-export-table="reportDocumentsTable"><i class="ri-file-excel-2-line"></i> Export CSV</button>
</section>

<section class="analytics-grid">
    <article class="panel chart-panel"><div class="panel-heading"><h2>Monthly Submissions</h2><span>Filtered records</span></div><canvas id="reportMonthlyChart"></canvas></article>
    <article class="panel chart-panel"><div class="panel-heading"><h2>Approval Rate</h2><span>Research status</span></div><canvas id="reportResearchChart"></canvas></article>
    <article class="panel chart-panel wide"><div class="panel-heading"><h2>Department Output</h2><span>Document distribution</span></div><canvas id="reportDepartmentChart"></canvas></article>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Document Report</h2><span>Printable and export-ready table</span></div>
    <div class="table-scroll">
        <table class="data-table portal-table" id="reportDocumentsTable">
            <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Department</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td>REC-<?php echo str_pad((string) $document['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo h($document['title']); ?></td>
                        <td><?php echo h($document['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo h($document['department_name'] ?? 'Unassigned'); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($document['submission_date']))); ?></td>
                        <td><?php echo h($document['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$documents): ?><tr><td colspan="6">No records found for this date range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="summary-grid">
    <?php foreach ($categoryCounts as $row): ?>
        <article class="summary-card"><div class="summary-icon"><i class="ri-folder-chart-line"></i></div><div><p><?php echo h($row['category_name']); ?></p><h3><?php echo (int) $row['total']; ?></h3><span>Category output</span></div></article>
    <?php endforeach; ?>
</section>

<script>
window.reportCharts = <?php echo json_encode([
    'categories' => $categoryCounts,
    'research' => $research,
    'extension' => $extension,
    'departments' => $departmentCounts,
    'monthly' => $monthlySubmissions,
]); ?>;
</script>
<?php renderFooter(); ?>
