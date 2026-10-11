<?php
require_once 'includes/layout.php';
require_login(['Super Admin', 'Vice President', 'OVPRE Staff', 'Research Coordinator', 'Extension Coordinator']);
ensure_activity_archived_at_column();

function report_scalar(string $sql, array $params = []): int|float {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 0;
}

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');
$from = DateTime::createFromFormat('Y-m-d', $dateFrom) ? $dateFrom : date('Y-m-01');
$to = DateTime::createFromFormat('Y-m-d', $dateTo) ? $dateTo : date('Y-m-d');
$selectedYear = $_GET['year'] ?? date('Y');
$selectedDepartment = $_GET['department'] ?? 'All';
$selectedStatus = $_GET['status'] ?? 'All';
$fromDateTime = $from . ' 00:00:00';
$toDateTime = $to . ' 23:59:59';

$departments = db()->query('SELECT id, department_name FROM departments ORDER BY department_name')->fetchAll();
$statusOptions = ['Pending', 'Under Review', 'Approved', 'Declined'];
$yearOptions = range((int) date('Y') + 1, (int) date('Y') - 5);

$documentConditions = ['d.created_at BETWEEN ? AND ?'];
$documentParams = [$fromDateTime, $toDateTime];
if ($selectedYear !== 'All') {
    $documentConditions[] = 'YEAR(d.created_at) = ?';
    $documentParams[] = (int) $selectedYear;
}
if ($selectedDepartment !== 'All') {
    $documentConditions[] = 'd.department_id = ?';
    $documentParams[] = (int) $selectedDepartment;
}
if ($selectedStatus !== 'All') {
    $documentConditions[] = 'd.status = ?';
    $documentParams[] = $selectedStatus;
}
$documentWhere = implode(' AND ', $documentConditions);

$categoryStmt = db()->prepare("SELECT c.category_name, COUNT(d.id) total FROM categories c LEFT JOIN documents d ON d.category_id = c.id AND {$documentWhere} GROUP BY c.id ORDER BY c.category_name");
$categoryStmt->execute($documentParams);
$categoryCounts = $categoryStmt->fetchAll();

$departmentStmt = db()->prepare("SELECT dep.department_name, COUNT(d.id) total FROM departments dep LEFT JOIN documents d ON d.department_id = dep.id AND {$documentWhere} GROUP BY dep.id ORDER BY total DESC, dep.department_name");
$departmentStmt->execute($documentParams);
$departmentCounts = $departmentStmt->fetchAll();

$monthlyStmt = db()->prepare("SELECT DATE_FORMAT(d.created_at, '%b %Y') AS label, COUNT(*) AS total FROM documents d WHERE {$documentWhere} GROUP BY YEAR(d.created_at), MONTH(d.created_at) ORDER BY MIN(d.created_at)");
$monthlyStmt->execute($documentParams);
$monthlySubmissions = $monthlyStmt->fetchAll();

$documentsStmt = db()->prepare("SELECT d.id, d.title, d.status, d.submission_date, c.category_name, dep.department_name FROM documents d LEFT JOIN categories c ON c.id = d.category_id LEFT JOIN departments dep ON dep.id = d.department_id WHERE {$documentWhere} ORDER BY d.submission_date DESC LIMIT 100");
$documentsStmt->execute($documentParams);
$documents = $documentsStmt->fetchAll();

$rangeParams = [$fromDateTime, $toDateTime];
$researchStmt = db()->prepare('SELECT status, COUNT(*) total FROM research_projects WHERE created_at BETWEEN ? AND ? GROUP BY status');
$researchStmt->execute($rangeParams);
$research = $researchStmt->fetchAll();

$extensionStmt = db()->prepare('SELECT progress_status status, COUNT(*) total FROM extension_projects WHERE created_at BETWEEN ? AND ? GROUP BY progress_status');
$extensionStmt->execute($rangeParams);
$extension = $extensionStmt->fetchAll();

$totalResearch = (int) report_scalar('SELECT COUNT(*) FROM research_projects WHERE created_at BETWEEN ? AND ?', $rangeParams);
$publishedResearch = (int) report_scalar("SELECT COUNT(*) FROM documents d JOIN categories c ON c.id = d.category_id WHERE c.category_name = 'Publication' AND d.created_at BETWEEN ? AND ?", $rangeParams);
$ongoingResearch = (int) report_scalar("SELECT COUNT(*) FROM research_projects WHERE created_at BETWEEN ? AND ? AND status IN ('Pending', 'Under Review')", $rangeParams);
$completedResearch = (int) report_scalar("SELECT COUNT(*) FROM research_projects WHERE created_at BETWEEN ? AND ? AND status IN ('Approved', 'Completed')", $rangeParams);

$totalExtension = (int) report_scalar('SELECT COUNT(*) FROM extension_projects WHERE created_at BETWEEN ? AND ?', $rangeParams);
$activePrograms = (int) report_scalar("SELECT COUNT(*) FROM extension_projects WHERE created_at BETWEEN ? AND ? AND progress_status IN ('Ongoing', 'Active', 'Implementation', 'Planning')", $rangeParams);
$completedPrograms = (int) report_scalar("SELECT COUNT(*) FROM extension_projects WHERE created_at BETWEEN ? AND ? AND progress_status IN ('Completed', 'Approved')", $rangeParams);
$upcomingActivities = (int) report_scalar('SELECT COUNT(*) FROM activities WHERE approval_status = "Approved" AND archived_at IS NULL AND date >= CURDATE()');

$researchFunding = (int) report_scalar("SELECT COUNT(*) FROM research_projects WHERE created_at BETWEEN ? AND ? AND funding_source IS NOT NULL AND funding_source <> ''", $rangeParams);
$extensionFunding = (int) report_scalar('SELECT COUNT(*) FROM extension_projects WHERE created_at BETWEEN ? AND ? AND budget > 0', $rangeParams);
$totalGrants = $researchFunding + $extensionFunding;
$approvedFunding = (float) report_scalar("SELECT COALESCE(SUM(budget), 0) FROM extension_projects WHERE created_at BETWEEN ? AND ? AND progress_status IN ('Approved', 'Completed', 'Ongoing')", $rangeParams);
$requestedFunding = (float) report_scalar('SELECT COALESCE(SUM(budget), 0) FROM extension_projects WHERE created_at BETWEEN ? AND ?', $rangeParams);
$pendingRequests = (int) report_scalar("SELECT COUNT(*) FROM research_projects WHERE created_at BETWEEN ? AND ? AND status IN ('Pending', 'Under Review')", $rangeParams)
    + (int) report_scalar("SELECT COUNT(*) FROM extension_projects WHERE created_at BETWEEN ? AND ? AND progress_status IN ('Planning', 'Pending', 'Under Review')", $rangeParams);
$fundingUtilization = $requestedFunding > 0 ? round(($approvedFunding / $requestedFunding) * 100) : 0;

$totalAccomplishments = (int) report_scalar('SELECT COUNT(*) FROM accomplishment_reports WHERE created_at BETWEEN ? AND ?', $rangeParams);
$facultyAccomplishments = (int) report_scalar("SELECT COUNT(*) FROM accomplishment_reports ar JOIN users u ON u.id = ar.submitted_by WHERE ar.created_at BETWEEN ? AND ? AND u.role <> 'Student'", $rangeParams);
$studentAccomplishments = (int) report_scalar("SELECT COUNT(*) FROM accomplishment_reports ar JOIN users u ON u.id = ar.submitted_by WHERE ar.created_at BETWEEN ? AND ? AND u.role = 'Student'", $rangeParams);
$extensionAccomplishments = (int) report_scalar('SELECT COUNT(*) FROM accomplishment_reports WHERE created_at BETWEEN ? AND ? AND project_id IS NOT NULL', $rangeParams);
$researchAccomplishments = (int) report_scalar("SELECT COUNT(*) FROM documents d JOIN categories c ON c.id = d.category_id WHERE c.category_name = 'Accomplishment Report' AND d.created_at BETWEEN ? AND ?", $rangeParams);

$totalDocuments = array_sum(array_map(fn($row) => (int) $row['total'], $categoryCounts));
$summaryStats = [
    ['label' => 'Total Research Outputs', 'value' => $totalResearch, 'icon' => 'ri-flask-line'],
    ['label' => 'Total Extension Programs', 'value' => $totalExtension, 'icon' => 'ri-community-line'],
    ['label' => 'Total Grants', 'value' => $totalGrants, 'icon' => 'ri-hand-coin-line'],
    ['label' => 'Total Accomplishments', 'value' => $totalAccomplishments + $researchAccomplishments, 'icon' => 'ri-medal-line'],
];

renderHeader('Reports Management', 'reports');
?>
<style>
    .reports-page {
        display: grid;
        gap: 22px;
    }

    .reports-hero {
        position: relative;
        overflow: hidden;
        padding: 26px;
        color: var(--green-900);
        background: linear-gradient(135deg, var(--white), var(--green-100));
        border: 1px solid var(--line);
        border-radius: 22px;
        box-shadow: var(--soft-shadow);
    }

    .report-breadcrumb {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
        color: var(--muted);
        font-size: 0.88rem;
        font-weight: 800;
    }

    .report-breadcrumb i,
    .report-breadcrumb strong {
        color: var(--green-800);
    }

    .reports-hero h2 {
        margin: 0 0 8px;
        font-size: clamp(1.8rem, 4vw, 2.7rem);
    }

    .reports-hero p {
        max-width: 820px;
        margin: 0;
        color: var(--muted);
        line-height: 1.6;
    }

    .reports-filter-panel {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        align-items: end;
        padding: 18px;
        background: var(--white);
        border: 1px solid var(--line);
        border-radius: 20px;
        box-shadow: var(--soft-shadow);
    }

    .report-filter-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(150px, 1fr));
        gap: 14px;
    }

    .report-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 10px;
    }

    .report-summary-grid,
    .report-module-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .report-stat-card,
    .report-module-card,
    .statistical-card {
        background: var(--white);
        border: 1px solid var(--line);
        border-radius: 20px;
        box-shadow: var(--soft-shadow);
        transition: transform 0.24s ease, box-shadow 0.24s ease, border-color 0.24s ease;
    }

    .report-stat-card:hover,
    .report-module-card:hover,
    .statistical-card:hover {
        transform: translateY(-4px);
        border-color: rgba(21, 128, 61, 0.25);
        box-shadow: 0 18px 38px rgba(15, 61, 36, 0.14);
    }

    .report-stat-card {
        display: flex;
        align-items: center;
        gap: 16px;
        min-height: 118px;
        padding: 20px;
    }

    .report-stat-icon,
    .report-module-icon {
        width: 54px;
        height: 54px;
        flex: 0 0 auto;
        display: grid;
        place-items: center;
        color: var(--green-900);
        background: linear-gradient(135deg, var(--yellow-500), var(--green-100));
        border-radius: 16px;
        font-size: 1.5rem;
    }

    .report-stat-card p,
    .report-module-card p {
        margin: 0;
        color: var(--muted);
        font-size: 0.88rem;
        font-weight: 800;
    }

    .report-stat-card h3 {
        margin: 5px 0 0;
        color: var(--green-900);
        font-size: 2rem;
    }

    .report-module-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .report-module-card {
        display: grid;
        gap: 18px;
        padding: 22px;
    }

    .report-module-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .report-module-head h3 {
        margin: 0 0 5px;
        color: var(--green-900);
        font-size: 1.22rem;
    }

    .report-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .report-metric {
        padding: 14px;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 15px;
    }

    .report-metric span {
        display: block;
        margin-bottom: 7px;
        color: var(--muted);
        font-size: 0.8rem;
        font-weight: 800;
    }

    .report-metric strong {
        color: var(--green-900);
        font-size: 1.35rem;
    }

    .statistical-card {
        grid-column: 1 / -1;
        padding: 22px;
    }

    .statistical-card .panel-heading {
        align-items: flex-start;
    }

    .report-chart-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .report-chart-box {
        display: grid;
        grid-template-rows: auto 1fr;
        gap: 14px;
        min-height: 320px;
        padding: 18px;
        background: var(--white);
        border: 1px solid var(--line);
        border-radius: 18px;
        box-shadow: 0 12px 28px rgba(15, 61, 36, 0.08);
    }

    .report-chart-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .report-chart-title {
        min-width: 0;
    }

    .report-chart-title h4 {
        margin: 0 0 4px;
        color: var(--green-900);
        font-size: 1rem;
    }

    .report-chart-title span {
        display: block;
        color: var(--muted);
        font-size: 0.78rem;
        font-weight: 800;
        line-height: 1.35;
    }

    .report-chart-badge {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        color: var(--green-900);
        background: var(--green-100);
        border: 1px solid rgba(21, 128, 61, 0.14);
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 900;
        white-space: nowrap;
    }

    .report-chart-frame {
        position: relative;
        min-height: 240px;
        padding: 8px;
        background: linear-gradient(180deg, #ffffff, var(--surface));
        border: 1px solid rgba(15, 61, 36, 0.08);
        border-radius: 14px;
    }

    .report-chart-frame canvas {
        width: 100%;
        height: 230px;
    }

    .report-chart-empty {
        position: absolute;
        inset: 8px;
        display: none;
        place-items: center;
        padding: 18px;
        color: var(--muted);
        background: rgba(255, 255, 255, 0.86);
        border: 1px dashed var(--line);
        border-radius: 12px;
        text-align: center;
        font-size: 0.88rem;
        font-weight: 800;
    }

    .report-chart-frame.is-empty canvas {
        opacity: 0;
    }

    .report-chart-frame.is-empty .report-chart-empty {
        display: grid;
    }

    .report-table-panel {
        padding: 22px;
    }

    @media (max-width: 1180px) {
        .reports-filter-panel,
        .report-module-grid,
        .report-chart-grid {
            grid-template-columns: 1fr;
        }

        .report-filter-grid,
        .report-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 620px) {
        .reports-hero,
        .reports-filter-panel,
        .report-module-card,
        .statistical-card {
            padding: 18px;
            border-radius: 16px;
        }

        .report-filter-grid,
        .report-summary-grid,
        .report-metrics {
            grid-template-columns: 1fr;
        }

        .report-actions,
        .report-actions .primary-btn,
        .report-actions .ghost-btn {
            width: 100%;
        }

        .report-chart-head {
            display: grid;
        }

        .report-chart-badge {
            justify-content: center;
            width: fit-content;
        }
    }
</style>

<div class="reports-page">
    <section class="reports-filter-panel no-print">
        <form class="report-filter-grid" method="get">
            <label class="field compact-field"><span>Date From</span><input type="date" name="from" value="<?php echo h($from); ?>"></label>
            <label class="field compact-field"><span>Date To</span><input type="date" name="to" value="<?php echo h($to); ?>"></label>
            <label class="field compact-field"><span>Year</span><select name="year"><option value="All">All Years</option><?php foreach ($yearOptions as $year): ?><option value="<?php echo (int) $year; ?>" <?php echo (string) $selectedYear === (string) $year ? 'selected' : ''; ?>><?php echo (int) $year; ?></option><?php endforeach; ?></select></label>
            <label class="field compact-field"><span>Department</span><select name="department"><option value="All">All Departments</option><?php foreach ($departments as $department): ?><option value="<?php echo (int) $department['id']; ?>" <?php echo (string) $selectedDepartment === (string) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
            <label class="field compact-field"><span>Status</span><select name="status"><option value="All">All Status</option><?php foreach ($statusOptions as $status): ?><option value="<?php echo h($status); ?>" <?php echo $selectedStatus === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label>
            <button class="primary-btn" type="submit"><i class="ri-filter-3-line"></i> Apply Filters</button>
        </form>
        <div class="report-actions">
            <button class="ghost-btn" type="button" onclick="window.print();"><i class="ri-file-pdf-2-line"></i> Export PDF</button>
            <button class="ghost-btn" type="button" data-export-table="reportDocumentsTable"><i class="ri-file-excel-2-line"></i> Export Excel</button>
            <button class="ghost-btn" type="button" onclick="window.print();"><i class="ri-printer-line"></i> Print Report</button>
        </div>
    </section>

    <section class="report-summary-grid">
        <?php foreach ($summaryStats as $stat): ?>
            <article class="report-stat-card">
                <div class="report-stat-icon"><i class="<?php echo h($stat['icon']); ?>"></i></div>
                <div><p><?php echo h($stat['label']); ?></p><h3><?php echo h(number_format((float) $stat['value'])); ?></h3></div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="report-module-grid">
        <article class="report-module-card">
            <div class="report-module-head"><div><h3>Research Outputs</h3><p>Research production and publication status</p></div><div class="report-module-icon"><i class="ri-flask-line"></i></div></div>
            <div class="report-metrics">
                <div class="report-metric"><span>Total Researches</span><strong><?php echo number_format($totalResearch); ?></strong></div>
                <div class="report-metric"><span>Published Researches</span><strong><?php echo number_format($publishedResearch); ?></strong></div>
                <div class="report-metric"><span>Ongoing Researches</span><strong><?php echo number_format($ongoingResearch); ?></strong></div>
                <div class="report-metric"><span>Completed Researches</span><strong><?php echo number_format($completedResearch); ?></strong></div>
            </div>
            <button class="primary-btn" type="button" data-export-table="reportDocumentsTable"><i class="ri-file-chart-line"></i> Generate Report</button>
        </article>

        <article class="report-module-card">
            <div class="report-module-head"><div><h3>Extension Programs</h3><p>Community extension program monitoring</p></div><div class="report-module-icon"><i class="ri-community-line"></i></div></div>
            <div class="report-metrics">
                <div class="report-metric"><span>Total Extension Programs</span><strong><?php echo number_format($totalExtension); ?></strong></div>
                <div class="report-metric"><span>Active Programs</span><strong><?php echo number_format($activePrograms); ?></strong></div>
                <div class="report-metric"><span>Completed Programs</span><strong><?php echo number_format($completedPrograms); ?></strong></div>
                <div class="report-metric"><span>Upcoming Activities</span><strong><?php echo number_format($upcomingActivities); ?></strong></div>
            </div>
            <button class="primary-btn" type="button" data-export-table="reportDocumentsTable"><i class="ri-file-chart-line"></i> Generate Report</button>
        </article>

        <article class="report-module-card">
            <div class="report-module-head"><div><h3>Grants and Funding</h3><p>Approved budgets and pending requests</p></div><div class="report-module-icon"><i class="ri-hand-coin-line"></i></div></div>
            <div class="report-metrics">
                <div class="report-metric"><span>Total Grants</span><strong><?php echo number_format($totalGrants); ?></strong></div>
                <div class="report-metric"><span>Approved Funding</span><strong><?php echo h(number_format($approvedFunding, 2)); ?></strong></div>
                <div class="report-metric"><span>Pending Requests</span><strong><?php echo number_format($pendingRequests); ?></strong></div>
                <div class="report-metric"><span>Funding Utilization</span><strong><?php echo (int) $fundingUtilization; ?>%</strong></div>
            </div>
            <button class="primary-btn" type="button" data-export-table="reportDocumentsTable"><i class="ri-file-chart-line"></i> Generate Report</button>
        </article>

        <article class="report-module-card">
            <div class="report-module-head"><div><h3>Accomplishments</h3><p>Institutional accomplishment reporting</p></div><div class="report-module-icon"><i class="ri-medal-line"></i></div></div>
            <div class="report-metrics">
                <div class="report-metric"><span>Faculty Accomplishments</span><strong><?php echo number_format($facultyAccomplishments); ?></strong></div>
                <div class="report-metric"><span>Student Accomplishments</span><strong><?php echo number_format($studentAccomplishments); ?></strong></div>
                <div class="report-metric"><span>Extension Accomplishments</span><strong><?php echo number_format($extensionAccomplishments); ?></strong></div>
                <div class="report-metric"><span>Research Accomplishments</span><strong><?php echo number_format($researchAccomplishments); ?></strong></div>
            </div>
            <button class="primary-btn" type="button" data-export-table="reportDocumentsTable"><i class="ri-file-chart-line"></i> Generate Report</button>
        </article>

        <article class="statistical-card">
            <div class="panel-heading"><h2>Statistical Data</h2><span>Charts and analytics for the selected filters</span></div>
            <div class="report-metrics">
                <div class="report-metric"><span>Filtered Documents</span><strong><?php echo number_format($totalDocuments); ?></strong></div>
                <div class="report-metric"><span>Departments Covered</span><strong><?php echo number_format(count(array_filter($departmentCounts, fn($row) => (int) $row['total'] > 0))); ?></strong></div>
                <div class="report-metric"><span>Categories Covered</span><strong><?php echo number_format(count(array_filter($categoryCounts, fn($row) => (int) $row['total'] > 0))); ?></strong></div>
                <div class="report-metric"><span>Report Rows</span><strong><?php echo number_format(count($documents)); ?></strong></div>
            </div>
            <div class="report-chart-grid">
                <div class="report-chart-box">
                    <div class="report-chart-head">
                        <div class="report-chart-title"><h4>Department Output</h4><span>Documents grouped by department</span></div>
                        <div class="report-chart-badge"><i class="ri-bar-chart-grouped-line"></i> Bar</div>
                    </div>
                    <div class="report-chart-frame" data-chart-frame>
                        <canvas id="adminReportsBarChart"></canvas>
                        <div class="report-chart-empty">No department data for the selected filters.</div>
                    </div>
                </div>
                <div class="report-chart-box">
                    <div class="report-chart-head">
                        <div class="report-chart-title"><h4>Category Share</h4><span>Document mix by category</span></div>
                        <div class="report-chart-badge"><i class="ri-pie-chart-2-line"></i> Doughnut</div>
                    </div>
                    <div class="report-chart-frame" data-chart-frame>
                        <canvas id="adminReportsPieChart"></canvas>
                        <div class="report-chart-empty">No category data for the selected filters.</div>
                    </div>
                </div>
                <div class="report-chart-box">
                    <div class="report-chart-head">
                        <div class="report-chart-title"><h4>Monthly Trend</h4><span>Submitted records over time</span></div>
                        <div class="report-chart-badge"><i class="ri-line-chart-line"></i> Line</div>
                    </div>
                    <div class="report-chart-frame" data-chart-frame>
                        <canvas id="adminReportsLineChart"></canvas>
                        <div class="report-chart-empty">No monthly activity for the selected filters.</div>
                    </div>
                </div>
            </div>
            <div class="report-actions" style="margin-top:16px;"><button class="ghost-btn" type="button" data-export-table="reportDocumentsTable"><i class="ri-download-cloud-line"></i> Export Data</button></div>
        </article>
    </section>

    <section class="panel report-table-panel">
        <div class="panel-heading"><h2>Report Records</h2><span><?php echo h(date('M d, Y', strtotime($from))); ?> to <?php echo h(date('M d, Y', strtotime($to))); ?></span></div>
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
                            <td><span class="status-pill status-<?php echo h(statusClass($document['status'])); ?>"><?php echo h($document['status'] === 'Declined' ? 'Rejected' : $document['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$documents): ?><tr><td colspan="6">No records found for this filter.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
window.adminReportsDashboard = <?php echo json_encode([
    'categories' => $categoryCounts,
    'research' => $research,
    'extension' => $extension,
    'departments' => $departmentCounts,
    'monthly' => $monthlySubmissions,
]); ?>;

document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart || !window.adminReportsDashboard) return;

    const colors = ['#15803d', '#16a34a', '#facc15', '#0f3d24', '#86efac', '#0ea5e9', '#f97316', '#dc2626'];
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#17231c',
                    usePointStyle: true,
                    boxWidth: 8,
                    padding: 16,
                    font: { weight: 700 }
                }
            },
            tooltip: {
                backgroundColor: '#0f3d24',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                padding: 12,
                cornerRadius: 10,
                displayColors: false
            }
        },
        scales: {
            x: {
                border: { display: false },
                ticks: { color: '#6f7b73', font: { weight: 700 }, maxRotation: 0, autoSkip: true },
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                ticks: { color: '#6f7b73', precision: 0, font: { weight: 700 } },
                grid: { color: '#e6ede8' }
            }
        }
    };

    const hasValues = (rows) => rows.some((row) => Number(row.total) > 0);
    const filteredRows = (rows) => rows.filter((row) => Number(row.total) > 0);
    const markEmpty = (canvas) => {
        canvas?.closest('[data-chart-frame]')?.classList.add('is-empty');
    };
    const drawChart = (id, config) => {
        const canvas = document.getElementById(id);
        if (!canvas || !hasValues(config.rows)) {
            markEmpty(canvas);
            return;
        }
        new Chart(canvas, config.chart(filteredRows(config.rows)));
    };

    drawChart('adminReportsBarChart', {
        rows: window.adminReportsDashboard.departments || [],
        chart: (rows) => ({
            type: 'bar',
            data: {
                labels: rows.map((row) => row.department_name),
                datasets: [{
                    label: 'Documents',
                    data: rows.map((row) => Number(row.total)),
                    backgroundColor: rows.map((_, index) => colors[index % colors.length]),
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 44
                }]
            },
            options: chartOptions
        })
    });

    drawChart('adminReportsPieChart', {
        rows: window.adminReportsDashboard.categories || [],
        chart: (rows) => ({
            type: 'doughnut',
            data: {
                labels: rows.map((row) => row.category_name),
                datasets: [{
                    data: rows.map((row) => Number(row.total)),
                    backgroundColor: rows.map((_, index) => colors[index % colors.length]),
                    borderColor: '#ffffff',
                    borderWidth: 4,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: chartOptions.plugins
            }
        })
    });

    drawChart('adminReportsLineChart', {
        rows: window.adminReportsDashboard.monthly || [],
        chart: (rows) => ({
            type: 'line',
            data: {
                labels: rows.map((row) => row.label),
                datasets: [{
                    label: 'Records',
                    data: rows.map((row) => Number(row.total)),
                    borderColor: '#15803d',
                    backgroundColor: 'rgba(21, 128, 61, 0.14)',
                    fill: true,
                    tension: 0.38,
                    pointBackgroundColor: '#facc15',
                    pointBorderColor: '#0f3d24',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: chartOptions
        })
    });
});
</script>
<?php renderFooter(); ?>
