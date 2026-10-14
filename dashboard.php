<?php
require_once 'includes/layout.php';
$user = require_login();
$pendingAccountCount = $user['role'] === ROLE_SUPER_ADMIN
    ? (int) db()->query('SELECT COUNT(*) FROM users WHERE status = "Pending" AND deleted_at IS NULL AND role IN ("Research Coordinator", "Extension Coordinator")')->fetchColumn()
    : 0;
$recent = db()->query('SELECT l.*, u.fullname FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 8')->fetchAll();
$monthlyDocuments = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
    ? db()->query("SELECT TO_CHAR(created_at, 'Mon') AS label, COUNT(*) AS total FROM documents WHERE created_at >= NOW() - INTERVAL '12 MONTH' GROUP BY TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)")->fetchAll()
    : db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll();
$pendingDocuments = db()->query("SELECT d.id, d.title, d.status, d.submission_date, c.category_name, dep.department_name FROM documents d LEFT JOIN categories c ON c.id = d.category_id LEFT JOIN departments dep ON dep.id = d.department_id WHERE d.archived_at IS NULL AND d.status IN ('Pending', 'Under Review') ORDER BY d.updated_at DESC, d.created_at DESC LIMIT 6")->fetchAll();
$research = db()->query('SELECT status, COUNT(*) total FROM research_projects GROUP BY status')->fetchAll();
$departmentCounts = db()->query('SELECT dep.department_name, COUNT(d.id) total FROM departments dep LEFT JOIN documents d ON d.department_id = dep.id AND d.archived_at IS NULL GROUP BY dep.id, dep.department_name ORDER BY total DESC, dep.department_name')->fetchAll();
renderHeader('Dashboard', 'dashboard');
?>
<section class="welcome-panel">
    <div>
        <p class="welcome-greeting">Welcome back, <?php echo h($user['fullname']); ?></p>
        <p class="welcome-description">Manage your research proposals and follow each submission through review.</p>
    </div>
    <a class="primary-btn" href="research.php">
        <i class="ri-add-circle-line" aria-hidden="true"></i>
        <span>Submit<br>Research Proposal</span>
    </a>
    <a class="ghost-btn" href="submitted-files.php"><i class="ri-folder-open-line" aria-hidden="true"></i><span>View All Files</span></a>
</section>

<section class="analytics-grid">
    <article class="panel chart-panel">
        <div class="panel-heading">
            <h2>Monthly Submissions</h2><span>Dashboard records</span>
        </div><canvas id="reportMonthlyChart"></canvas>
    </article>
    <article class="panel chart-panel">
        <div class="panel-heading">
            <h2>Approval Rate</h2><span>Research status</span>
        </div><canvas id="reportResearchChart"></canvas>
    </article>
    <article class="panel chart-panel wide">
        <div class="panel-heading">
            <h2>Department Output</h2><span>Document distribution</span>
        </div><canvas id="reportDepartmentChart"></canvas>
    </article>
</section>

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
                        <td><span class="dept-pill"><?php echo h($activity['ip_address']); ?></span></td>
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
