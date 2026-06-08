<?php
require_once 'includes/layout.php';
$user = require_login();
$stats = dashboard_stats();
$recent = db()->query('SELECT l.*, u.fullname FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 8')->fetchAll();
$monthlyDocuments = db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll();
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
        <a href="reports.php">View all</a>
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
