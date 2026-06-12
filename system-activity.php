<?php
require_once 'includes/layout.php';

require_login(['Super Admin']);

$logs = db()->query('SELECT l.*, u.fullname FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 200')->fetchAll();

renderHeader('System Activity', 'audit');
?>
<section class="module-header">
    <div class="module-icon">
        <i class="ri-history-line"></i>
    </div>
    <div>
        <p>System Activity</p>
        <h2>System Activity</h2>
        <span>Tracks login, logout, upload, edit, delete, archive, and approval actions with IP addresses.</span>
    </div>
</section>

<section class="tool-panel">
    <div class="search-control">
        <i class="ri-search-line"></i>
        <input data-table-search type="search" placeholder="Search system activity">
    </div>
</section>

<section class="panel">
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>IP Address</th>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr data-table-row>
                        <td><?php echo h($log['fullname'] ?? 'System'); ?></td>
                        <td><?php echo h($log['action']); ?></td>
                        <td><?php echo h($log['ip_address']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($log['created_at']))); ?></td>
                        <td><?php echo h(date('h:i A', strtotime($log['created_at']))); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?>
                    <tr>
                        <td colspan="5">No system activity found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php renderFooter(); ?>
