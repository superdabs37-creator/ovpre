<?php
require_once 'includes/student-layout.php';
$user = student_render_header('Student Dashboard', 'dashboard');
$stats = student_dashboard_stats((int) $user['id']);
$charts = student_chart_data((int) $user['id']);
$recentDocs = array_slice(student_documents((int) $user['id']), 0, 5);
$announcements = student_announcements(3);
?>
<section class="welcome-panel">
    <div>
        <p>Welcome back, <?php echo h($user['fullname']); ?></p>
        <h2>Manage your research and extension records in one secure student workspace.</h2>
    </div>
    <a class="primary-btn" href="upload.php"><i class="ri-upload-cloud-line"></i> Upload Document</a>
</section>

<section class="summary-grid">
    <article class="summary-card"><span class="summary-icon"><i class="ri-folder-3-line"></i></span><div><p>Total Documents</p><h3><?php echo $stats['total']; ?></h3><span>All submissions</span></div></article>
    <article class="summary-card"><span class="summary-icon yellow"><i class="ri-time-line"></i></span><div><p>Pending</p><h3><?php echo $stats['pending']; ?></h3><span>Awaiting review</span></div></article>
    <article class="summary-card"><span class="summary-icon"><i class="ri-checkbox-circle-line"></i></span><div><p>Approved</p><h3><?php echo $stats['approved']; ?></h3><span>Cleared records</span></div></article>
    <article class="summary-card"><span class="summary-icon red"><i class="ri-close-circle-line"></i></span><div><p>Rejected</p><h3><?php echo $stats['rejected']; ?></h3><span>Needs revision</span></div></article>
</section>

<script>
window.studentCharts = <?php echo json_encode($charts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<section class="analytics-grid">
    <article class="panel chart-panel"><div class="panel-heading"><h2>Upload Trends</h2><span>Last 6 months</span></div><canvas id="studentUploadChart"></canvas></article>
    <article class="panel chart-panel"><div class="panel-heading"><h2>Approval Rate</h2><span>Status mix</span></div><canvas id="studentApprovalChart"></canvas></article>
</section>

<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-heading"><h2>Recent Documents</h2><a href="documents.php">View all</a></div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentDocs as $doc): ?>
                    <tr>
                        <td><?php echo h(student_tracking_number((int) $doc['id'])); ?></td>
                        <td><?php echo h($doc['title']); ?></td>
                        <td><?php echo h($doc['category_name']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($doc['submission_date']))); ?></td>
                        <td><?php echo student_badge($doc['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentDocs): ?><tr><td colspan="5">No documents submitted yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
    <article class="panel">
        <div class="panel-heading"><h2>Announcements</h2><a href="announcements.php">Read more</a></div>
        <div class="announcement-list compact-list">
            <?php foreach ($announcements as $item): ?>
                <article class="mini-announcement">
                    <span class="category-badge"><?php echo h($item['audience']); ?></span>
                    <h3><?php echo h($item['title']); ?></h3>
                    <p><?php echo h(mb_strimwidth(strip_tags($item['content']), 0, 120, '...')); ?></p>
                </article>
            <?php endforeach; ?>
            <?php if (!$announcements): ?><p class="muted">No announcements available.</p><?php endif; ?>
        </div>
    </article>
</section>
<?php student_render_footer(); ?>
