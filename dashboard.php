<?php
require_once 'includes/layout.php';
$user = require_login();

$role = role_access_group((string) ($user['role'] ?? ''));
$isSuperAdmin = $role === ROLE_SUPER_ADMIN;
$isOVPREAdmin = $role === ROLE_OVPRE_ADMIN;

$pendingAccountCount = ($isSuperAdmin || $isOVPREAdmin)
    ? (int) db()->query('SELECT COUNT(*) FROM users WHERE status = "Pending Approval" AND deleted_at IS NULL')->fetchColumn()
    : 0;

if ($isSuperAdmin):
    // Super Admin: Technical & Security Control Panel data
    $dbDriver = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') ? 'PostgreSQL (Supabase Cloud)' : 'MySQL Database';
    $totalUsers = (int) db()->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
    $activeUsers = (int) db()->query('SELECT COUNT(*) FROM users WHERE status = "Active" AND deleted_at IS NULL')->fetchColumn();
    $totalAuditLogs = (int) db()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    $totalSecurityLogins = 0;
    try {
        $totalSecurityLogins = (int) db()->query('SELECT COUNT(*) FROM login_history')->fetchColumn();
    } catch (Throwable $e) {}

    $roleDistribution = db()->query('SELECT role, COUNT(*) AS count FROM users WHERE deleted_at IS NULL GROUP BY role ORDER BY count DESC')->fetchAll();
    $recentSecurityLogs = db()->query('SELECT l.*, u.fullname, u.role FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 10')->fetchAll();

    renderHeader('Technical & Security Console', 'dashboard');
?>
<section class="welcome-panel">
    <div>
        <h2 style="margin: 0 0 6px; font-size: 1.5rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
            <i class="ri-shield-keyhole-line" style="color: #1e3a8a;"></i> Technical &amp; Security Control Console
        </h2>
        <p class="welcome-greeting" style="font-size: 0.95rem; color: #475569; margin: 0;">
            System Administration, IT Governance, Database Integrity, and Security.
        </p>
    </div>
    <div class="welcome-actions" style="display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;margin-left:auto;">
        <a class="primary-btn" href="system-activity.php" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
            <i class="ri-history-line" aria-hidden="true"></i>
            <span>System Activity<br>Audit Trail</span>
        </a>
        <a class="primary-btn" href="system-backup.php" style="background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);">
            <i class="ri-database-2-line" aria-hidden="true"></i>
            <span>Database<br>Backup &amp; Recovery</span>
        </a>
        <a class="primary-btn" href="admin-settings.php" style="background: linear-gradient(135deg, #334155 0%, #475569 100%);">
            <i class="ri-settings-3-line" aria-hidden="true"></i>
            <span>System<br>Settings</span>
        </a>
        <a class="ghost-btn" href="admin-users.php">
            <i class="ri-shield-user-line" aria-hidden="true"></i>
            <span>User Security</span>
        </a>
    </div>
</section>

<!-- TECHNICAL TELEMETRY CARDS -->
<section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <article class="panel" style="margin: 0; padding: 20px; border-left: 4px solid #2563eb;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Database Status</span>
            <i class="ri-database-2-line" style="font-size: 1.4rem; color: #2563eb;"></i>
        </div>
        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">Online</div>
        <small style="color: #64748b;"><?php echo h($dbDriver); ?></small>
    </article>

    <article class="panel" style="margin: 0; padding: 20px; border-left: 4px solid #16a34a;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Security Controls</span>
            <i class="ri-shield-check-line" style="font-size: 1.4rem; color: #16a34a;"></i>
        </div>
        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">Enforced &amp; Active</div>
        <small style="color: #64748b;">CSRF Tokens &bull; Session Isolation &bull; Bcrypt/Argon2</small>
    </article>

    <article class="panel" style="margin: 0; padding: 20px; border-left: 4px solid #0d9488;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">User Security &amp; Accounts</span>
            <i class="ri-user-shared-line" style="font-size: 1.4rem; color: #0d9488;"></i>
        </div>
        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
            <?php echo $activeUsers; ?> <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">Active / <?php echo $totalUsers; ?> Total</span>
        </div>
        <small style="color: #64748b;">
            <?php if ($pendingAccountCount > 0): ?>
                <a href="admin-pending-users.php" style="color: #d97706; font-weight: 700;"><i class="ri-alert-line"></i> <?php echo $pendingAccountCount; ?> Pending Account Approval(s)</a>
            <?php else: ?>
                <span style="color: #16a34a;"><i class="ri-check-line"></i> No pending account approvals</span>
            <?php endif; ?>
        </small>
    </article>

    <article class="panel" style="margin: 0; padding: 20px; border-left: 4px solid #7c3aed;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Security Audit Log</span>
            <i class="ri-file-shield-line" style="font-size: 1.4rem; color: #7c3aed;"></i>
        </div>
        <div style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
            <?php echo number_format($totalAuditLogs); ?> <span style="font-size: 0.95rem; font-weight: 600; color: #64748b;">Events</span>
        </div>
        <small style="color: #64748b;"><a href="system-activity.php" style="color: #7c3aed; font-weight: 600;">Inspect full audit trail &rarr;</a></small>
    </article>
</section>

<!-- SYSTEM ENVIRONMENT & ROLE BREAKDOWN -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- ENVIRONMENT DIAGNOSTICS -->
    <article class="panel" style="margin: 0;">
        <div class="panel-heading">
            <h2><i class="ri-server-line"></i> System Environment &amp; Runtime</h2>
            <a href="admin-settings.php">Configure</a>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td><strong>PHP Runtime Version</strong></td>
                        <td><code><?php echo PHP_VERSION; ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Database Engine</strong></td>
                        <td><span class="status-pill status-approved"><i class="ri-check-line"></i> <?php echo h($dbDriver); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Cloud Storage Bucket</strong></td>
                        <td><code><?php echo defined('SUPABASE_STORAGE_BUCKET') ? SUPABASE_STORAGE_BUCKET : 'ovpre'; ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Server OS &amp; Architecture</strong></td>
                        <td><?php echo PHP_OS . ' (' . php_uname('m') . ')'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server Timestamp</strong></td>
                        <td><?php echo date('Y-m-d H:i:s T'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Configured Timezone</strong></td>
                        <td><?php echo date_default_timezone_get(); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>

    <!-- ACCOUNT DISTRIBUTION BY ROLE -->
    <article class="panel" style="margin: 0;">
        <div class="panel-heading">
            <h2><i class="ri-group-line"></i> System Role Governance</h2>
            <a href="admin-users.php">Manage Users</a>
        </div>
        <div class="table-scroll">
            <table class="data-table portal-table">
                <thead>
                    <tr>
                        <th>System Role</th>
                        <th>Responsibility Domain</th>
                        <th style="text-align: right;">Accounts</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roleDistribution as $dist): ?>
                        <tr>
                            <td><strong><?php echo h($dist['role']); ?></strong></td>
                            <td>
                                <small style="color: #64748b;">
                                    <?php echo match($dist['role']) {
                                        ROLE_SUPER_ADMIN => 'Technical & Security Control',
                                        ROLE_OVPRE_ADMIN => 'Institutional Operations & Records',
                                        ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR => 'Research Evaluation & RIUH',
                                        ROLE_EXTENSION_DIRECTOR => 'Extension Programs',
                                        ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR => 'College Coordination',
                                        ROLE_FACULTY_EXTENSIONIST => 'Extension Projects',
                                        ROLE_RESEARCHER => 'Faculty Research',
                                        default => 'Academic Stakeholder'
                                    }; ?>
                                </small>
                            </td>
                            <td style="text-align: right;"><strong><?php echo (int) $dist['count']; ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>

<!-- RECENT SECURITY AUDIT TRAIL -->
<section class="panel">
    <div class="panel-heading">
        <h2><i class="ri-history-line"></i> Recent Technical &amp; Security Audit Trail</h2>
        <a href="system-activity.php">View All Audit Logs</a>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>Security / System Action</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSecurityLogs as $log): ?>
                    <tr>
                        <td><strong><?php echo h($log['action']); ?></strong></td>
                        <td><?php echo h($log['fullname'] ?? 'System Process'); ?></td>
                        <td><span class="dept-pill"><?php echo h($log['role'] ?? 'System'); ?></span></td>
                        <td><code><?php echo h($log['ip_address'] ?? '127.0.0.1'); ?></code></td>
                        <td><?php echo h(date('M d, Y h:i A', strtotime($log['created_at']))); ?></td>
                        <td><span class="status-pill status-approved">Logged</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentSecurityLogs): ?>
                    <tr><td colspan="6">No system activity logged yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php
else:
    // OVPRE Admin & Operational Staff: Institutional Operations Dashboard
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
        <span style="font-size: 0.9rem; color: #475569;">Institutional Operations &amp; Academic Administration</span>
    </div>
    <div class="welcome-actions" style="display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap;margin-left:auto;">
        <?php if ($isOVPREAdmin && $pendingAccountCount > 0): ?>
            <a class="primary-btn" href="admin-pending-users.php" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">
                <i class="ri-user-follow-line" aria-hidden="true"></i>
                <span>Pending Accounts<br>(<?php echo $pendingAccountCount; ?>)</span>
            </a>
        <?php endif; ?>
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
<?php
endif;
renderFooter();
?>
