<?php
require_once 'includes/layout.php';
$user = require_login();
$stats = dashboard_stats();
$recent = db()->query('SELECT l.*, u.fullname FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 8')->fetchAll();
$monthlyDocuments = db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll();
$pendingDocuments = db()->query("SELECT d.id, d.title, d.status, d.submission_date, c.category_name, dep.department_name FROM documents d LEFT JOIN categories c ON c.id = d.category_id LEFT JOIN departments dep ON dep.id = d.department_id WHERE d.archived_at IS NULL AND d.status IN ('Pending', 'Under Review') ORDER BY d.updated_at DESC, d.created_at DESC LIMIT 6")->fetchAll();
$pendingResearch = (int) db()->query("SELECT COUNT(*) FROM research_projects WHERE status IN ('Pending', 'Under Review')")->fetchColumn();
$pendingExtension = (int) db()->query("SELECT COUNT(*) FROM extension_projects WHERE progress_status IN ('Pending', 'Under Review')")->fetchColumn();
$lockedUsers = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'Locked' AND deleted_at IS NULL")->fetchColumn();
renderHeader('Dashboard', 'dashboard');
?>
<section class="welcome-panel">
    <div>
        <p>Welcome back, <?php echo h($user['fullname']); ?></p>
    </div>
    <a class="primary-btn" href="upload-document.php"><i class="ri-upload-cloud-line"></i> Upload Record</a>
</section>

<section class="summary-grid">
    <?php foreach ([
        ['label' => 'Total Records', 'value' => $stats['total_records'], 'icon' => 'ri-archive-drawer-line', 'trend' => 'All categories'],
        ['label' => 'Active Records', 'value' => $stats['active_records'], 'icon' => 'ri-file-list-3-line', 'trend' => 'Available for retrieval'],
        ['label' => 'Archived Records', 'value' => $stats['archived_records'], 'icon' => 'ri-inbox-archive-line', 'trend' => 'Retention storage'],
        ['label' => 'Pending Approvals', 'value' => $stats['pending'], 'icon' => 'ri-time-line', 'trend' => 'Needs action'],
        ['label' => 'Research Queue', 'value' => $pendingResearch, 'icon' => 'ri-flask-line', 'trend' => 'Awaiting review'],
        ['label' => 'Extension Queue', 'value' => $pendingExtension, 'icon' => 'ri-community-line', 'trend' => 'Awaiting review'],
        ['label' => 'Locked Accounts', 'value' => $lockedUsers, 'icon' => 'ri-lock-line', 'trend' => 'Security review'],
    ] as $card): ?>
        <article class="summary-card">
            <div class="summary-icon"><i class="<?php echo $card['icon']; ?>"></i></div>
            <div>
                <p><?php echo $card['label']; ?></p>
                <h3><?php echo h($card['value']); ?></h3>
                <span><?php echo $card['trend']; ?></span>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>Pending Approval Queue</h2>
        <a href="admin-documents.php?status=Pending">Open records</a>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>Record</th><th>Category</th><th>Department</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
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
                <?php if (!$pendingDocuments): ?><tr><td colspan="6">No records need approval right now.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="analytics-grid">
    <article class="panel chart-panel">
        <div class="panel-heading">
            <h2>Department Share</h2>
            <span>Pie Chart</span>
        </div>
        <canvas id="pieChart"></canvas>
    </article>
    <article class="panel chart-panel">
        <div class="panel-heading">
            <h2>Monthly Uploads</h2>
            <span>Bar Graph</span>
        </div>
        <canvas id="barChart"></canvas>
    </article>
    <article class="panel chart-panel wide">
        <div class="panel-heading">
            <h2>Review Completion</h2>
            <span>Line Graph</span>
        </div>
        <canvas id="lineChart"></canvas>
    </article>
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
window.irecordCharts = {
    monthly: <?php echo json_encode($monthlyDocuments); ?>,
    status: <?php echo json_encode(db()->query('SELECT status AS label, COUNT(*) AS total FROM documents GROUP BY status')->fetchAll()); ?>,
    extension: <?php echo json_encode(db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM extension_projects GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll()); ?>
};
</script>
<?php renderFooter(); ?>
