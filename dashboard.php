<?php
require_once 'includes/layout.php';
$user = require_login();
$pendingAccountCount = $user['role'] === ROLE_SUPER_ADMIN
    ? (int) db()->query('SELECT COUNT(*) FROM users WHERE status = "Pending Approval" AND deleted_at IS NULL')->fetchColumn()
    : 0;
$recent = db()->query('SELECT l.*, u.fullname, dep.department_name FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN departments dep ON dep.id = u.department_id ORDER BY l.created_at DESC LIMIT 8')->fetchAll();
$monthlyDocuments = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
    ? db()->query("SELECT TO_CHAR(created_at, 'Mon') AS label, COUNT(*) AS total FROM documents WHERE created_at >= NOW() - INTERVAL '12 MONTH' GROUP BY TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)")->fetchAll()
    : db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll();
$pendingDocuments = db()->query("SELECT d.id, d.title, d.status, d.submission_date, c.category_name, dep.department_name FROM documents d LEFT JOIN categories c ON c.id = d.category_id LEFT JOIN departments dep ON dep.id = d.department_id WHERE d.archived_at IS NULL AND d.status IN ('Pending', 'Under Review') ORDER BY d.updated_at DESC, d.created_at DESC LIMIT 6")->fetchAll();
$research = db()->query('SELECT status, COUNT(*) total FROM research_projects GROUP BY status')->fetchAll();
$departmentCounts = db()->query('SELECT dep.department_name, COUNT(d.id) total FROM departments dep LEFT JOIN documents d ON d.department_id = dep.id AND d.archived_at IS NULL GROUP BY dep.id, dep.department_name ORDER BY total DESC, dep.department_name')->fetchAll();

$pendingExecutiveProposals = [];
try {
    $resStmt = db()->query("SELECT r.id, r.title, r.status, r.workflow_stage, r.created_at, u.fullname, dep.department_name, dir.fullname AS director_name, 'Research' AS proposal_type FROM research_projects r JOIN users u ON u.id = r.submitted_by LEFT JOIN departments dep ON dep.id = u.department_id LEFT JOIN users dir ON dir.id = r.director_id WHERE r.status = 'Pending Executive Approval' ORDER BY r.created_at DESC LIMIT 5");
    if ($resStmt) {
        foreach ($resStmt->fetchAll() as $row) { $pendingExecutiveProposals[] = $row; }
    }
    $extStmt = db()->query("SELECT e.id, e.project_title AS title, e.status, e.workflow_stage, e.created_at, u.fullname, dep.department_name, dir.fullname AS director_name, 'Extension' AS proposal_type FROM extension_projects e JOIN users u ON u.id = e.submitted_by LEFT JOIN departments dep ON dep.id = u.department_id LEFT JOIN users dir ON dir.id = e.director_id WHERE e.status = 'Pending Executive Approval' ORDER BY e.created_at DESC LIMIT 5");
    if ($extStmt) {
        foreach ($extStmt->fetchAll() as $row) { $pendingExecutiveProposals[] = $row; }
    }
} catch (Throwable $e) {}

renderHeader('Dashboard', 'dashboard');
?>
<section class="welcome-panel">
    <div>
        <p class="welcome-greeting">Welcome back, <?php echo h($user['fullname']); ?></p>
    </div>
    <div class="welcome-actions" style="display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;margin-left:auto;">
        <a class="primary-btn" href="research.php">
            <i class="ri-flask-line" aria-hidden="true"></i>
            <span>Research<br>Pipeline</span>
        </a>
        <a class="primary-btn" href="extension.php" style="background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);">
            <i class="ri-community-line" aria-hidden="true"></i>
            <span>Extension<br>Pipeline</span>
        </a>
        <a class="ghost-btn" href="submitted-files.php"><i class="ri-folder-open-line" aria-hidden="true"></i><span>View All Files</span></a>
    </div>
</section>

<section class="analytics-grid">
    <article class="panel chart-panel wide department-output-panel">
        <div class="panel-heading">
            <h2>Department Output</h2><span>Document distribution</span>
        </div><canvas id="reportDepartmentChart"></canvas>
    </article>
    <article class="panel chart-panel dashboard-summary-chart">
        <div class="panel-heading">
            <h2>Monthly Submissions</h2><span>Dashboard records</span>
        </div><canvas id="reportMonthlyChart"></canvas>
    </article>
    <article class="panel chart-panel dashboard-summary-chart">
        <div class="panel-heading">
            <h2>Approval Rate</h2><span>Research status</span>
        </div><canvas id="reportResearchChart"></canvas>
    </article>
</section>

<?php if (!empty($pendingExecutiveProposals)): ?>
<section class="panel">
    <div class="panel-heading">
        <h2>Executive Approval Queue (Research &amp; Extension)</h2>
        <span style="font-weight: 700; color: #15803d;"><?php echo count($pendingExecutiveProposals); ?> proposal<?php echo count($pendingExecutiveProposals) === 1 ? '' : 's'; ?> awaiting Final Sign-off</span>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Proposal</th>
                    <th>Proponent</th>
                    <th>Department</th>
                    <th>Endorsed By</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingExecutiveProposals as $prop): ?>
                    <tr>
                        <td><strong><span class="status-pill status-<?php echo $prop['proposal_type'] === 'Research' ? 'approved' : 'planning'; ?>"><?php echo h($prop['proposal_type']); ?></span></strong></td>
                        <td><?php echo h($prop['title']); ?></td>
                        <td><?php echo h($prop['fullname']); ?></td>
                        <td><?php echo h($prop['department_name'] ?? 'Unassigned'); ?></td>
                        <td><?php echo h($prop['director_name'] ?? 'Director'); ?></td>
                        <td><span class="status-pill status-pending-executive-approval">Pending Executive Approval</span></td>
                        <td>
                            <a class="ghost-btn action-btn" href="<?php echo $prop['proposal_type'] === 'Research' ? 'research.php#project-' . (int) $prop['id'] : 'extension.php#project-' . (int) $prop['id']; ?>">
                                <i class="ri-checkbox-circle-line"></i><span>Sign-off</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <h2>Pending Approval Queue</h2>
        <a href="admin-documents.php?status=Pending">Open records</a>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>Record</th>
                    <th>Category</th>
                    <th>Department</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingDocuments as $document): ?>
                    <tr>
                        <td><?php echo h($document['title']); ?><br><small>REC-<?php echo str_pad((string) $document['id'], 5, '0', STR_PAD_LEFT); ?></small></td>
                        <td><?php echo h($document['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo h($document['department_name'] ?? 'Unassigned'); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($document['submission_date']))); ?></td>
                        <td><span class="status-pill status-<?php echo h(statusClass($document['status'])); ?>"><?php echo h($document['status']); ?></span></td>
                        <td><a class="ghost-btn action-btn" href="admin-documents.php?status=<?php echo urlencode($document['status']); ?>"><i class="ri-arrow-right-line"></i><span>Review</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pendingDocuments): ?><tr>
                        <td colspan="6">No records need approval right now.</td>
                    </tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>Recent Activity</h2>
        <a href="admin-reports.php">View all</a>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>User</th>
                    <th>Department</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $activity): ?>
                    <tr>
                        <td><?php echo h($activity['action']); ?></td>
                        <td><?php echo h($activity['fullname'] ?? 'System'); ?></td>
                        <td><span class="dept-pill"><?php echo h($activity['department_name'] ?? 'Unassigned'); ?></span></td>
                        <td><?php echo h(date('M d, Y h:i A', strtotime($activity['created_at']))); ?></td>
                        <td><span class="status-pill">Logged</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
    window.reportCharts = {
        monthly: <?php echo json_encode($monthlyDocuments); ?>,
        research: <?php echo json_encode($research); ?>,
        departments: <?php echo json_encode($departmentCounts); ?>
    };
</script>
<?php renderFooter(); ?>
