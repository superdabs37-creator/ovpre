<?php
require_once 'includes/student-layout.php';
$user = student_render_header('Document History', 'history');
$logs = student_history((int) $user['id']);
?>
<section class="module-header"><div class="module-icon"><i class="ri-history-line"></i></div><div><p>Activity Audit</p><h2>Document History</h2><span>Review upload, edit, status, and account activity related to your records.</span></div></section>
<section class="tool-panel"><div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search activity logs"></div><button class="ghost-btn" type="button" data-export-table="pdf"><i class="ri-file-pdf-2-line"></i> Export PDF</button><button class="primary-btn" type="button" data-export-table="excel"><i class="ri-file-excel-2-line"></i> Export Excel</button></section>
<section class="panel">
    <div class="table-scroll">
        <table class="data-table" id="historyTable">
            <thead><tr><th>Date</th><th>Document</th><th>Activity</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr data-table-row><td><?php echo h(date('M d, Y h:i A', strtotime($log['created_at']))); ?></td><td><?php echo h($log['title'] ?? 'Record'); ?></td><td><?php echo h($log['action']); ?></td><td><?php echo h($log['remarks'] ?? ''); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="4">No activity logs found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php student_render_footer(); ?>
