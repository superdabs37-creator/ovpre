<?php
require_once 'includes/layout.php';
$user = require_login();

$role = role_access_group((string) ($user['role'] ?? ''));
$isSuperAdmin = $role === ROLE_SUPER_ADMIN;
$isOVPREAdmin = $role === ROLE_OVPRE_ADMIN;

$dbDriver = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') ? 'PostgreSQL (Supabase Cloud)' : 'MySQL Database';

// ==========================================
// 1. DATA QUERIES FOR ANALYTICAL METRICS
// ==========================================
$totalDocuments = (int) db()->query('SELECT COUNT(*) FROM documents WHERE archived_at IS NULL')->fetchColumn();
$totalResearch = (int) db()->query('SELECT COUNT(*) FROM research_projects')->fetchColumn();
$totalExtension = (int) db()->query('SELECT COUNT(*) FROM extension_projects')->fetchColumn();
$totalProposals = $totalResearch + $totalExtension;

$totalUsers = (int) db()->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
$activeUsers = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'Active' AND deleted_at IS NULL")->fetchColumn();
$totalColleges = (int) db()->query('SELECT COUNT(*) FROM departments')->fetchColumn();
$activeCollegesCount = (int) db()->query('SELECT COUNT(DISTINCT department_id) FROM documents WHERE department_id IS NOT NULL AND archived_at IS NULL')->fetchColumn();

// Monthly Submissions Overall (12 Months)
$monthlyDocuments = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
    ? db()->query("SELECT TO_CHAR(created_at, 'Mon') AS label, COUNT(*) AS total FROM documents WHERE created_at >= NOW() - INTERVAL '12 MONTH' AND archived_at IS NULL GROUP BY TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)")->fetchAll(PDO::FETCH_ASSOC)
    : db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND archived_at IS NULL GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll(PDO::FETCH_ASSOC);

// Monthly Submissions By College
$monthlyByCollege = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
    ? db()->query("
        SELECT 
            TO_CHAR(d.created_at, 'Mon') AS month_label,
            TO_CHAR(d.created_at, 'YYYY-MM') AS month_key,
            COALESCE(dep.id, 0) AS department_id,
            COALESCE(dep.department_name, 'Other / Unassigned') AS department_name,
            COUNT(d.id) AS total
        FROM documents d
        LEFT JOIN departments dep ON dep.id = d.department_id
        WHERE d.created_at >= NOW() - INTERVAL '12 MONTH' AND d.archived_at IS NULL
        GROUP BY TO_CHAR(d.created_at, 'YYYY-MM'), TO_CHAR(d.created_at, 'Mon'), DATE_TRUNC('month', d.created_at), dep.id, dep.department_name
        ORDER BY DATE_TRUNC('month', d.created_at) ASC, department_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : db()->query("
        SELECT 
            DATE_FORMAT(d.created_at, '%b') AS month_label,
            DATE_FORMAT(d.created_at, '%Y-%m') AS month_key,
            COALESCE(dep.id, 0) AS department_id,
            COALESCE(dep.department_name, 'Other / Unassigned') AS department_name,
            COUNT(d.id) AS total
        FROM documents d
        LEFT JOIN departments dep ON dep.id = d.department_id
        WHERE d.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND d.archived_at IS NULL
        GROUP BY DATE_FORMAT(d.created_at, '%Y-%m'), DATE_FORMAT(d.created_at, '%b'), dep.id, dep.department_name
        ORDER BY DATE_FORMAT(d.created_at, '%Y-%m') ASC, department_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

// Monthly Research vs Extension Projects
$monthlyResearch = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
    ? db()->query("SELECT TO_CHAR(created_at, 'Mon') AS label, COUNT(*) AS total FROM research_projects WHERE created_at >= NOW() - INTERVAL '12 MONTH' GROUP BY TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)")->fetchAll(PDO::FETCH_ASSOC)
    : db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM research_projects WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll(PDO::FETCH_ASSOC);

$monthlyExtension = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
    ? db()->query("SELECT TO_CHAR(created_at, 'Mon') AS label, COUNT(*) AS total FROM extension_projects WHERE created_at >= NOW() - INTERVAL '12 MONTH' GROUP BY TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)")->fetchAll(PDO::FETCH_ASSOC)
    : db()->query("SELECT DATE_FORMAT(created_at, '%b') AS label, COUNT(*) AS total FROM extension_projects WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll(PDO::FETCH_ASSOC);

// Colleges Output Comparison
$departmentCounts = db()->query('SELECT dep.department_name, COUNT(d.id) AS total FROM departments dep LEFT JOIN documents d ON d.department_id = dep.id AND d.archived_at IS NULL GROUP BY dep.id, dep.department_name ORDER BY total DESC, dep.department_name')->fetchAll(PDO::FETCH_ASSOC);

// Research Proposals by College
$researchByCollege = db()->query("
    SELECT dep.department_name, COUNT(DISTINCT r.id) AS total 
    FROM departments dep 
    LEFT JOIN users u ON u.department_id = dep.id AND u.deleted_at IS NULL 
    LEFT JOIN research_projects r ON r.submitted_by = u.id 
    GROUP BY dep.id, dep.department_name 
    ORDER BY total DESC, dep.department_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Extension Projects by College
$extensionByCollege = db()->query("
    SELECT dep.department_name, COUNT(DISTINCT e.id) AS total 
    FROM departments dep 
    LEFT JOIN users u ON u.department_id = dep.id AND u.deleted_at IS NULL 
    LEFT JOIN extension_projects e ON e.submitted_by = u.id 
    GROUP BY dep.id, dep.department_name 
    ORDER BY total DESC, dep.department_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Document Categories Distribution
$categories = db()->query("SELECT c.category_name, COUNT(d.id) AS total FROM categories c LEFT JOIN documents d ON d.category_id = c.id AND d.archived_at IS NULL GROUP BY c.id, c.category_name ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);

// Status Distributions
$researchStatus = db()->query('SELECT status, COUNT(*) AS total FROM research_projects GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
$extensionStatus = db()->query('SELECT status, COUNT(*) AS total FROM extension_projects GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
$documentStatus = db()->query('SELECT status, COUNT(*) AS total FROM documents WHERE archived_at IS NULL GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);

// Tactical Colleges & Differentiated Submissions Breakdown
$allDepartmentsList = departments();

try {
    $submissionsBreakdown = db()->query("
        SELECT 
            dep.id,
            dep.department_name,
            COALESCE(rp.cnt, 0) AS res_proposals,
            COALESCE(pub.cnt, 0) AS res_publications,
            COALESCE(ep.cnt, 0) AS ext_proposals,
            COALESCE(acc.cnt, 0) + COALESCE(doc_acc.cnt, 0) AS ext_accomplishments,
            COALESCE(u_cnt.cnt, 0) AS total_faculty,
            (COALESCE(rp.cnt, 0) + COALESCE(pub.cnt, 0) + COALESCE(ep.cnt, 0) + COALESCE(acc.cnt, 0) + COALESCE(doc_acc.cnt, 0)) AS total_submissions
        FROM departments dep
        LEFT JOIN (
            SELECT u.department_id, COUNT(DISTINCT u.id) AS cnt
            FROM users u
            WHERE u.deleted_at IS NULL AND u.department_id IS NOT NULL
            GROUP BY u.department_id
        ) u_cnt ON u_cnt.department_id = dep.id
        LEFT JOIN (
            SELECT u.department_id, COUNT(DISTINCT r.id) AS cnt 
            FROM research_projects r 
            JOIN users u ON u.id = r.submitted_by 
            WHERE u.deleted_at IS NULL AND u.department_id IS NOT NULL
            GROUP BY u.department_id
        ) rp ON rp.department_id = dep.id
        LEFT JOIN (
            SELECT COALESCE(d.department_id, u.department_id) AS department_id, COUNT(DISTINCT d.id) AS cnt 
            FROM documents d 
            JOIN categories c ON c.id = d.category_id 
            LEFT JOIN users u ON u.id = d.uploaded_by 
            WHERE c.category_name IN ('Publication', 'Research Report') AND d.archived_at IS NULL 
            GROUP BY COALESCE(d.department_id, u.department_id)
        ) pub ON pub.department_id = dep.id
        LEFT JOIN (
            SELECT u.department_id, COUNT(DISTINCT e.id) AS cnt 
            FROM extension_projects e 
            JOIN users u ON u.id = e.submitted_by 
            WHERE u.deleted_at IS NULL AND u.department_id IS NOT NULL
            GROUP BY u.department_id
        ) ep ON ep.department_id = dep.id
        LEFT JOIN (
            SELECT u.department_id, COUNT(DISTINCT ar.id) AS cnt 
            FROM accomplishment_reports ar 
            JOIN users u ON u.id = ar.submitted_by 
            WHERE u.deleted_at IS NULL AND u.department_id IS NOT NULL
            GROUP BY u.department_id
        ) acc ON acc.department_id = dep.id
        LEFT JOIN (
            SELECT COALESCE(d.department_id, u.department_id) AS department_id, COUNT(DISTINCT d.id) AS cnt 
            FROM documents d 
            JOIN categories c ON c.id = d.category_id 
            LEFT JOIN users u ON u.id = d.uploaded_by 
            WHERE c.category_name = 'Accomplishment Report' AND d.archived_at IS NULL 
            GROUP BY COALESCE(d.department_id, u.department_id)
        ) doc_acc ON doc_acc.department_id = dep.id
        ORDER BY total_submissions DESC, dep.department_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $submissionsBreakdown = db()->query("
        SELECT 
            dep.id,
            dep.department_name,
            COALESCE(rp.cnt, 0) AS res_proposals,
            COALESCE(pub.cnt, 0) AS res_publications,
            COALESCE(ep.cnt, 0) AS ext_proposals,
            COALESCE(doc_acc.cnt, 0) AS ext_accomplishments,
            COALESCE(u_cnt.cnt, 0) AS total_faculty,
            (COALESCE(rp.cnt, 0) + COALESCE(pub.cnt, 0) + COALESCE(ep.cnt, 0) + COALESCE(doc_acc.cnt, 0)) AS total_submissions
        FROM departments dep
        LEFT JOIN (
            SELECT u.department_id, COUNT(DISTINCT u.id) AS cnt
            FROM users u
            WHERE u.deleted_at IS NULL AND u.department_id IS NOT NULL
            GROUP BY u.department_id
        ) u_cnt ON u_cnt.department_id = dep.id
        LEFT JOIN (
            SELECT u.department_id, COUNT(DISTINCT r.id) AS cnt 
            FROM research_projects r 
            JOIN users u ON u.id = r.submitted_by 
            WHERE u.deleted_at IS NULL AND u.department_id IS NOT NULL
            GROUP BY u.department_id
        ) rp ON rp.department_id = dep.id
        LEFT JOIN (
            SELECT COALESCE(d.department_id, u.department_id) AS department_id, COUNT(DISTINCT d.id) AS cnt 
            FROM documents d 
            JOIN categories c ON c.id = d.category_id 
            LEFT JOIN users u ON u.id = d.uploaded_by 
            WHERE c.category_name IN ('Publication', 'Research Report') AND d.archived_at IS NULL 
            GROUP BY COALESCE(d.department_id, u.department_id)
        ) pub ON pub.department_id = dep.id
        LEFT JOIN (
            SELECT u.department_id, COUNT(DISTINCT e.id) AS cnt 
            FROM extension_projects e 
            JOIN users u ON u.id = e.submitted_by 
            WHERE u.deleted_at IS NULL AND u.department_id IS NOT NULL
            GROUP BY u.department_id
        ) ep ON ep.department_id = dep.id
        LEFT JOIN (
            SELECT COALESCE(d.department_id, u.department_id) AS department_id, COUNT(DISTINCT d.id) AS cnt 
            FROM documents d 
            JOIN categories c ON c.id = d.category_id 
            LEFT JOIN users u ON u.id = d.uploaded_by 
            WHERE c.category_name = 'Accomplishment Report' AND d.archived_at IS NULL 
            GROUP BY COALESCE(d.department_id, u.department_id)
        ) doc_acc ON doc_acc.department_id = dep.id
        ORDER BY total_submissions DESC, dep.department_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$totalResProposals = (int) array_sum(array_column($submissionsBreakdown, 'res_proposals'));
$totalResPublications = (int) array_sum(array_column($submissionsBreakdown, 'res_publications'));
$totalExtProposals = (int) array_sum(array_column($submissionsBreakdown, 'ext_proposals'));
$totalExtAccomplishments = (int) array_sum(array_column($submissionsBreakdown, 'ext_accomplishments'));
$overallSubmissionsCount = $totalResProposals + $totalResPublications + $totalExtProposals + $totalExtAccomplishments;

$collegeMatrix = $submissionsBreakdown;

// =========================================================
// SUBMISSION COMPLETION STATUS ENGINE (TAPOS NA VS. HINDI PA TAPOS)
// =========================================================
$allDeptsList = db()->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$completionByCollege = [];
$totalAllCompleted = 0;
$totalAllOngoing = 0;

foreach ($allDeptsList as $d) {
    $dId = (int) $d['id'];
    
    // Research Projects: Completed (workflow_stage = 'Completed') vs Ongoing / In-Review
    $rComp = (int) db()->query("
        SELECT COUNT(*) FROM research_projects r 
        JOIN users u ON u.id = r.submitted_by 
        WHERE u.department_id = {$dId} AND r.workflow_stage = 'Completed'
    ")->fetchColumn();
    
    $rOng = (int) db()->query("
        SELECT COUNT(*) FROM research_projects r 
        JOIN users u ON u.id = r.submitted_by 
        WHERE u.department_id = {$dId} AND (r.workflow_stage != 'Completed' OR r.workflow_stage IS NULL)
    ")->fetchColumn();
    
    // Extension Projects: Completed (progress_status = 'Completed') vs Ongoing / In-Progress
    $eComp = (int) db()->query("
        SELECT COUNT(*) FROM extension_projects e 
        JOIN users u ON u.id = e.submitted_by 
        WHERE u.department_id = {$dId} AND e.progress_status = 'Completed'
    ")->fetchColumn();
    
    $eOng = (int) db()->query("
        SELECT COUNT(*) FROM extension_projects e 
        JOIN users u ON u.id = e.submitted_by 
        WHERE u.department_id = {$dId} AND (e.progress_status != 'Completed' OR e.progress_status IS NULL)
    ")->fetchColumn();

    $comp = $rComp + $eComp;
    $ong = $rOng + $eOng;
    $totalAllCompleted += $comp;
    $totalAllOngoing += $ong;

    $completionByCollege[] = [
        'department_name' => $d['department_name'],
        'completed' => $comp,
        'ongoing' => $ong,
        'res_completed' => $rComp,
        'res_ongoing' => $rOng,
        'ext_completed' => $eComp,
        'ext_ongoing' => $eOng,
        'total' => $comp + $ong
    ];
}

$grandSubmissionsTotal = $totalAllCompleted + $totalAllOngoing;
$overallCompletionRate = $grandSubmissionsTotal > 0 ? round(($totalAllCompleted / $grandSubmissionsTotal) * 100, 1) : 0;

// Wing-Specific Submissions
$researchTotalCompleted = (int) db()->query("SELECT COUNT(*) FROM research_projects WHERE workflow_stage = 'Completed'")->fetchColumn();
$researchTotalOngoing = (int) db()->query("SELECT COUNT(*) FROM research_projects WHERE workflow_stage != 'Completed' OR workflow_stage IS NULL")->fetchColumn();
$extensionTotalCompleted = (int) db()->query("SELECT COUNT(*) FROM extension_projects WHERE progress_status = 'Completed'")->fetchColumn();
$extensionTotalOngoing = (int) db()->query("SELECT COUNT(*) FROM extension_projects WHERE progress_status != 'Completed' OR progress_status IS NULL")->fetchColumn();

// Operational Executive Queue
$pendingExecutiveProposals = [];
try {
    $resStmt = db()->query("
        SELECT r.id, r.title, r.status, r.workflow_stage, r.grant_amount, r.created_at, 
               u.fullname, dep.department_name, dir.fullname AS director_name, 'Research' AS proposal_type 
        FROM research_projects r 
        JOIN users u ON u.id = r.submitted_by 
        LEFT JOIN departments dep ON dep.id = u.department_id 
        LEFT JOIN users dir ON dir.id = r.director_id 
        WHERE r.status = 'Pending Executive Approval' 
        ORDER BY r.created_at DESC LIMIT 6
    ");
    if ($resStmt) {
        foreach ($resStmt->fetchAll() as $row) { $pendingExecutiveProposals[] = $row; }
    }
    $extStmt = db()->query("
        SELECT e.id, e.project_title AS title, e.status, e.workflow_stage, e.approved_budget AS grant_amount, e.created_at, 
               u.fullname, dep.department_name, dir.fullname AS director_name, 'Extension' AS proposal_type 
        FROM extension_projects e 
        JOIN users u ON u.id = e.submitted_by 
        LEFT JOIN departments dep ON dep.id = u.department_id 
        LEFT JOIN users dir ON dir.id = e.director_id 
        WHERE e.status = 'Pending Executive Approval' 
        ORDER BY e.created_at DESC LIMIT 6
    ");
    if ($extStmt) {
        foreach ($extStmt->fetchAll() as $row) { $pendingExecutiveProposals[] = $row; }
    }
} catch (Throwable $e) {}

renderHeader('Dashboard', 'dashboard');
?>

<!-- =========================================================
     ADMIN ANALYTICAL DASHBOARD HEADER & QUICK DISPATCH
========================================================= -->
<section class="welcome-panel">
    <div>
        <h2 style="margin: 0 0 6px; font-size: 1.55rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
            <i class="ri-bar-chart-box-line" style="color: #15803d;"></i> Analytical Command Center
        </h2>
        <p class="welcome-greeting" style="font-size: 0.95rem; color: #475569; margin: 0;">
            Real-time visual intelligence: submission trends, college output comparisons, and pipeline status distributions.
        </p>
    </div>
    <div class="welcome-actions" style="display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-left:auto;">
        <a class="primary-btn" href="research.php" style="background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);">
            <i class="ri-flask-line" aria-hidden="true"></i>
            <span>Research<br>Pipeline</span>
        </a>
        <a class="primary-btn" href="extension.php" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
            <i class="ri-community-line" aria-hidden="true"></i>
            <span>Extension<br>Pipeline</span>
        </a>
        <a class="primary-btn" href="admin-reports.php" style="background: linear-gradient(135deg, #0284c7 0%, #38bdf8 100%);">
            <i class="ri-file-chart-line" aria-hidden="true"></i>
            <span>Reports &amp;<br>Analytics</span>
        </a>
        <a class="primary-btn" href="announcements.php" style="background: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);">
            <i class="ri-megaphone-line" aria-hidden="true"></i>
            <span>Announcements</span>
        </a>
        <a class="ghost-btn" href="admin-users.php">
            <i class="ri-shield-user-line" aria-hidden="true"></i>
            <span>Users</span>
        </a>
    </div>
</section>

<!-- =========================================================
     ANALYTICAL KPI SUMMARY CAPSULES
========================================================= -->
<section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 24px;">
    <!-- Records -->
    <div class="panel" style="margin: 0; padding: 16px; border-left: 4px solid #15803d; background: #ffffff;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Academic Records</span>
            <i class="ri-file-text-line" style="font-size: 1.3rem; color: #15803d;"></i>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo $totalDocuments; ?></div>
        <small style="color: #64748b; font-size: 0.8rem;">Cataloged in repository</small>
    </div>

    <!-- Research Proposals -->
    <div class="panel" style="margin: 0; padding: 16px; border-left: 4px solid #0284c7; background: #ffffff;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Research Proposals</span>
            <i class="ri-flask-line" style="font-size: 1.3rem; color: #0284c7;"></i>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo $totalResearch; ?></div>
        <small style="color: #64748b; font-size: 0.8rem;">Faculty submissions</small>
    </div>

    <!-- Extension Projects -->
    <div class="panel" style="margin: 0; padding: 16px; border-left: 4px solid #10b981; background: #ffffff;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Extension Initiatives</span>
            <i class="ri-community-line" style="font-size: 1.3rem; color: #10b981;"></i>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo $totalExtension; ?></div>
        <small style="color: #64748b; font-size: 0.8rem;">Community outreach</small>
    </div>

    <!-- Colleges Participation -->
    <div class="panel" style="margin: 0; padding: 16px; border-left: 4px solid #7c3aed; background: #ffffff;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Colleges Active</span>
            <i class="ri-government-line" style="font-size: 1.3rem; color: #7c3aed;"></i>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo $activeCollegesCount; ?> / <?php echo $totalColleges; ?></div>
        <small style="color: #64748b; font-size: 0.8rem;">Colleges with submissions</small>
    </div>

    <!-- Active Faculty -->
    <div class="panel" style="margin: 0; padding: 16px; border-left: 4px solid #d97706; background: #ffffff;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">Registered Users</span>
            <i class="ri-team-line" style="font-size: 1.3rem; color: #d97706;"></i>
        </div>
        <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?php echo $activeUsers; ?> <span style="font-size: 0.85rem; font-weight: 600; color: #64748b;">/ <?php echo $totalUsers; ?></span></div>
        <small style="color: #64748b; font-size: 0.8rem;">Active accounts</small>
    </div>
</section>

<!-- =========================================================
     TOP SPOTLIGHT: SUBMISSIONS BY ACADEMIC DEPARTMENT (HIGHLIGHTED HERO)
========================================================= -->
<article class="panel chart-panel featured-spotlight-card" style="margin-bottom: 24px; border: 2px solid #16a34a; border-radius: 20px; box-shadow: 0 12px 32px -4px rgba(22, 163, 74, 0.16), 0 4px 12px -2px rgba(0, 0, 0, 0.05); background: #ffffff; padding: 24px 26px;">
    <div class="panel-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 16px;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; background: #dcfce7; border: 1px solid #86efac; border-radius: 999px; color: #15803d; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                <i class="ri-sparkling-fill"></i> Primary University Benchmark
            </div>
            <h2 id="deptChartTitle" style="color: #15803d; font-size: 1.65rem; font-weight: 800; display: flex; align-items: center; gap: 10px; margin: 0;">
                <i class="ri-building-line" style="color: #15803d; font-size: 1.8rem;"></i> Submissions by Academic Department
            </h2>
            <span id="deptChartSubtitle" style="font-size: 0.92rem; color: #475569; font-weight: 600; display: block; margin-top: 4px;">Differentiated volume per college across all 9 departments</span>
        </div>
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <label for="submissionKindFilter" style="font-size: 0.82rem; font-weight: 700; color: #475569;">Submission Kind:</label>
                <select id="submissionKindFilter" class="filter-select compact-select" style="font-size: 0.82rem; font-weight: 700; padding: 6px 12px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #1e293b; cursor: pointer;">
                    <option value="all">All Kinds (Comparative Breakdown)</option>
                    <option value="res_proposals">Research Proposals Only</option>
                    <option value="res_publications">Research Publications &amp; Reports</option>
                    <option value="ext_proposals">Extension Proposals Only</option>
                    <option value="ext_accomplishments">Extension Accomplishment Reports</option>
                </select>
            </div>
            <span class="badge" style="background: #e2e8f0; color: #1e293b; font-weight: 800; font-size: 0.95rem; padding: 6px 16px; border-radius: 999px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.06);">
                <?php echo $overallSubmissionsCount; ?> Total Submissions
            </span>
        </div>
    </div>
    <div style="position: relative; height: 350px; width: 100%;">
        <canvas id="differentiatedSubmissionsChart"></canvas>
    </div>
</article>

<!-- =========================================================
     ROW 2: SUBMISSION COMPLETION INTELLIGENCE (COMPLETED VS. IN-PROGRESS)
========================================================= -->
<div style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <i class="ri-checkbox-circle-line" style="color: #15803d; font-size: 1.3rem;"></i> Submission Completion Status: Completed vs. In Progress
        </h3>
        <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Status breakdown: Completed terminal submissions vs. ongoing or pending review records</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px;">
        
        <!-- Card 1: Per-College Completed vs Ongoing -->
        <article class="panel chart-panel" style="margin: 0; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #ffffff;">
            <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px; margin: 0;">
                        <i class="ri-building-line" style="color: #15803d;"></i> College Completion Breakdown
                    </h2>
                    <span style="font-size: 0.8rem; color: #64748b;">Completed vs. In Progress submissions per academic department</span>
                </div>
                <span class="badge" style="background: #dcfce7; color: #166534; font-weight: 800; font-size: 0.78rem; padding: 4px 10px; border-radius: 999px;">
                    <?php echo $totalAllCompleted; ?> Completed / <?php echo $totalAllOngoing; ?> Ongoing
                </span>
            </div>
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="completionByCollegeBarChart"></canvas>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; margin-top: 8px; background: #f8fafc; border-radius: 8px; font-size: 0.78rem; font-weight: 700; color: #475569;">
                <span>Completed: <strong style="color: #15803d;"><?php echo $totalAllCompleted; ?></strong></span>
                <span>In Progress / Ongoing: <strong style="color: #ea580c;"><?php echo $totalAllOngoing; ?></strong></span>
                <span>Total Submissions: <strong style="color: #0f172a;"><?php echo $grandSubmissionsTotal; ?></strong></span>
            </div>
        </article>

        <!-- Card 2: Overall Ratio (Donut Chart) -->
        <article class="panel chart-panel" style="margin: 0; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #ffffff;">
            <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px; margin: 0;">
                        <i class="ri-pie-chart-2-line" style="color: #0284c7;"></i> University Completion Ratio
                    </h2>
                    <span style="font-size: 0.8rem; color: #64748b;">Overall proportion of completed vs. in-progress initiatives</span>
                </div>
                <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 0.78rem; padding: 4px 10px; border-radius: 999px;">
                    <?php echo $overallCompletionRate; ?>% Completion Rate
                </span>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 135px; gap: 12px; align-items: center;">
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="completionRatioDonutChart"></canvas>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px; justify-content: center;">
                    <div style="padding: 10px; background: #f0fdf4; border-left: 3px solid #15803d; border-radius: 6px;">
                        <small style="color: #166534; font-weight: 700; text-transform: uppercase; font-size: 0.65rem; display: block;">Completed</small>
                        <strong style="font-size: 1.25rem; color: #15803d;"><?php echo $totalAllCompleted; ?></strong>
                        <span style="font-size: 0.7rem; color: #64748b; display: block;">Finalized Records</span>
                    </div>
                    <div style="padding: 10px; background: #fff7ed; border-left: 3px solid #ea580c; border-radius: 6px;">
                        <small style="color: #9a3412; font-weight: 700; text-transform: uppercase; font-size: 0.65rem; display: block;">In Progress</small>
                        <strong style="font-size: 1.25rem; color: #ea580c;"><?php echo $totalAllOngoing; ?></strong>
                        <span style="font-size: 0.7rem; color: #64748b; display: block;">Ongoing / In Review</span>
                    </div>
                    <div style="padding: 8px 10px; background: #f1f5f9; border-radius: 6px; text-align: center;">
                        <small style="color: #475569; font-weight: 700; font-size: 0.68rem; display: block;">Completion Rate</small>
                        <strong style="font-size: 0.95rem; color: #0f172a;"><?php echo $overallCompletionRate; ?>%</strong>
                    </div>
                </div>
            </div>
        </article>

        <!-- Card 3: Research vs Extension Breakdown -->
        <article class="panel chart-panel" style="margin: 0; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #ffffff;">
            <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px; margin: 0;">
                        <i class="ri-git-branch-line" style="color: #7c3aed;"></i> Completion by Stream
                    </h2>
                    <span style="font-size: 0.8rem; color: #64748b;">Research proposals vs. extension community projects</span>
                </div>
                <span class="badge" style="background: #f3e8ff; color: #6b21a8; font-weight: 800; font-size: 0.78rem; padding: 4px 10px; border-radius: 999px;">
                    Research &amp; Extension
                </span>
            </div>
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="completionByWingBarChart"></canvas>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
                <div style="padding: 8px 10px; background: #f8fafc; border-radius: 6px; text-align: center;">
                    <small style="color: #64748b; font-weight: 700; font-size: 0.68rem; display: block;">Research Wing</small>
                    <strong style="font-size: 0.85rem; color: #15803d;"><?php echo $researchTotalCompleted; ?> Completed</strong> / 
                    <strong style="font-size: 0.85rem; color: #ea580c;"><?php echo $researchTotalOngoing; ?> In Progress</strong>
                </div>
                <div style="padding: 8px 10px; background: #f8fafc; border-radius: 6px; text-align: center;">
                    <small style="color: #64748b; font-weight: 700; font-size: 0.68rem; display: block;">Extension Wing</small>
                    <strong style="font-size: 0.85rem; color: #15803d;"><?php echo $extensionTotalCompleted; ?> Completed</strong> / 
                    <strong style="font-size: 0.85rem; color: #ea580c;"><?php echo $extensionTotalOngoing; ?> In Progress</strong>
                </div>
            </div>
        </article>

    </div>
</div>

<!-- =========================================================
     ROW 3: TACTICAL COLLEGES PERFORMANCE MATRIX (DATA TABLE)
========================================================= -->
<section class="panel" style="margin-bottom: 24px;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-table-line"></i> Tactical Colleges Submissions Matrix</h2>
            <small style="color: #64748b;">Granular breakdown differentiating Research Proposals, Publications, Extension Proposals, and Accomplishment Reports per college.</small>
        </div>
        <a href="admin-reports.php" class="ghost-btn" style="color: #ffffff !important; text-decoration: none; font-weight: 700;">
            <i class="ri-file-chart-line" style="color: #ffffff !important; font-size: 1.15rem;"></i><span style="color: #ffffff !important; font-weight: 700;">Export Reports &amp; Analytics &rarr;</span>
        </a>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>College Name</th>
                    <th style="text-align: center;">Faculty</th>
                    <th style="text-align: center;"><span style="color: #15803d; font-weight: 800;">●</span> Research Proposals</th>
                    <th style="text-align: center;"><span style="color: #7c3aed; font-weight: 800;">●</span> Research Publications</th>
                    <th style="text-align: center;"><span style="color: #0284c7; font-weight: 800;">●</span> Extension Proposals</th>
                    <th style="text-align: center;"><span style="color: #ea580c; font-weight: 800;">●</span> Accomplishments</th>
                    <th style="text-align: center;">Total Submissions</th>
                    <th style="text-align: center;">Activity Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissionsBreakdown as $col): ?>
                    <tr>
                        <td>
                            <strong><?php echo h($col['department_name']); ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="font-weight: 700;"><?php echo (int) $col['total_faculty']; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: #dcfce7; color: #166534; font-weight: 700;"><?php echo (int) $col['res_proposals']; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: #f3e8ff; color: #6b21a8; font-weight: 700;"><?php echo (int) $col['res_publications']; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;"><?php echo (int) $col['ext_proposals']; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge" style="background: #ffedd5; color: #9a3412; font-weight: 700;"><?php echo (int) $col['ext_accomplishments']; ?></span>
                        </td>
                        <td style="text-align: center;">
                            <strong style="font-size: 0.95rem; color: #0f172a;"><?php echo (int) $col['total_submissions']; ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($col['total_submissions'] > 0 || $col['total_faculty'] > 0): ?>
                                <span class="status-pill status-approved"><i class="ri-check-line"></i> Active Submissions</span>
                            <?php else: ?>
                                <span class="status-pill" style="background: #f1f5f9; color: #64748b;">Awaiting Submissions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- =========================================================
     ROW 4: OPERATIONAL ACTION QUEUE (EXECUTIVE APPROVALS)
========================================================= -->
<?php if (!empty($pendingExecutiveProposals)): ?>
<section class="panel" style="margin-bottom: 24px; border-left: 4px solid #15803d;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-shield-check-line" style="color: #15803d;"></i> Executive Proposal Approval Queue (Stage 4 Final Sign-off)</h2>
            <small style="color: #64748b;">Proposals endorsed by College Coordinators and Directors awaiting Final Executive Grant Release &amp; Activation.</small>
        </div>
        <span class="status-pill status-pending-executive-approval" style="font-weight: 700;">
            <?php echo count($pendingExecutiveProposals); ?> Awaiting Decision
        </span>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Proposal Title</th>
                    <th>Proponent</th>
                    <th>College</th>
                    <th>Endorsed By</th>
                    <th>Grant Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingExecutiveProposals as $prop): ?>
                    <tr>
                        <td>
                            <strong><span class="status-pill status-<?php echo $prop['proposal_type'] === 'Research' ? 'approved' : 'planning'; ?>">
                                <?php echo h($prop['proposal_type']); ?>
                            </span></strong>
                        </td>
                        <td>
                            <strong><?php echo h($prop['title']); ?></strong>
                        </td>
                        <td><?php echo h($prop['fullname']); ?></td>
                        <td><span class="dept-pill"><?php echo h($prop['department_name'] ?? 'Unassigned'); ?></span></td>
                        <td><?php echo h($prop['director_name'] ?? 'Director'); ?></td>
                        <td><strong><?php echo $prop['grant_amount'] ? 'PHP ' . number_format((float)$prop['grant_amount'], 2) : 'Under Review'; ?></strong></td>
                        <td><span class="status-pill status-pending-executive-approval">Pending Executive Approval</span></td>
                        <td>
                            <a class="primary-btn compact-btn" href="<?php echo $prop['proposal_type'] === 'Research' ? 'research.php#project-' . (int) $prop['id'] : 'extension.php#project-' . (int) $prop['id']; ?>" style="padding: 6px 12px; font-size: 0.82rem; background: #15803d;">
                                <i class="ri-checkbox-circle-line"></i><span>Sign-off / Review</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<!-- =========================================================
     ROW 5: SYSTEM INTEGRITY TELEMETRY (COMPACT FOOTER)
========================================================= -->
<section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-bottom: 24px; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <i class="ri-database-2-line" style="font-size: 1.5rem; color: #2563eb;"></i>
        <div>
            <div style="font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Database Engine</div>
            <strong style="color: #0f172a; font-size: 0.95rem;"><?php echo h($dbDriver); ?></strong>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <i class="ri-shield-keyhole-line" style="font-size: 1.5rem; color: #16a34a;"></i>
        <div>
            <div style="font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Security Controls</div>
            <strong style="color: #0f172a; font-size: 0.95rem;">CSRF &bull; Session Isolation &bull; Bcrypt</strong>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <i class="ri-user-settings-line" style="font-size: 1.5rem; color: #0d9488;"></i>
        <div>
            <div style="font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Active Accounts</div>
            <strong style="color: #0f172a; font-size: 0.95rem;"><?php echo $activeUsers; ?> Active / <?php echo $totalUsers; ?> Registered</strong>
        </div>
    </div>
    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
        <a href="system-activity.php" class="ghost-btn" style="font-size: 0.82rem; padding: 6px 12px;">
            <i class="ri-history-line"></i> Audit Trail
        </a>
        <a href="system-backup.php" class="ghost-btn" style="font-size: 0.82rem; padding: 6px 12px;">
            <i class="ri-database-2-line"></i> Backup Snapshot
        </a>
    </div>
</section>

<!-- =========================================================
     CLIENT-SIDE CHART.JS ANALYTICS ENGINE
========================================================= -->
<script>
window.reportCharts = {
    monthly: <?php echo json_encode($monthlyDocuments); ?>,
    monthlyByCollege: <?php echo json_encode($monthlyByCollege); ?>,
    collegesList: <?php echo json_encode($allDepartmentsList); ?>,
    research: <?php echo json_encode($researchStatus); ?>,
    departments: <?php echo json_encode($departmentCounts); ?>,
    categories: <?php echo json_encode($categories); ?>,
    researchByCollege: <?php echo json_encode($researchByCollege); ?>,
    extensionByCollege: <?php echo json_encode($extensionByCollege); ?>,
    submissionsBreakdown: <?php echo json_encode($submissionsBreakdown); ?>
};

document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;

    // Palette tokens
    const vibrantColors = ['#15803d', '#0284c7', '#7c3aed', '#d97706', '#10b981', '#e11d48', '#ea580c', '#4f46e5', '#0d9488', '#64748b'];

    // 1. Completion by Academic College Bar Chart (Completed vs In Progress)
    const collegeCompCanvas = document.getElementById('completionByCollegeBarChart');
    if (collegeCompCanvas) {
        const rawComp = <?php echo json_encode($completionByCollege); ?>;
        new Chart(collegeCompCanvas, {
            type: 'bar',
            data: {
                labels: rawComp.map(r => r.department_name),
                datasets: [
                    {
                        label: 'Completed',
                        data: rawComp.map(r => r.completed),
                        backgroundColor: '#15803d',
                        borderColor: '#166534',
                        borderWidth: 1,
                        borderRadius: 5,
                        barPercentage: 0.75,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'In Progress / Ongoing',
                        data: rawComp.map(r => r.ongoing),
                        backgroundColor: '#f59e0b',
                        borderColor: '#d97706',
                        borderWidth: 1,
                        borderRadius: 5,
                        barPercentage: 0.75,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, padding: 8, font: { size: 11, weight: 600 } }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (ctx) => {
                                const val = ctx.raw;
                                const college = rawComp[ctx.dataIndex];
                                const total = college.total || 1;
                                const pct = Math.round((val / total) * 100);
                                return ` ${ctx.dataset.label}: ${val} (${pct}% of college submissions)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 9.5, weight: 600 },
                            maxRotation: 45,
                            minRotation: 20
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { precision: 0, stepSize: 1, color: '#64748b' }
                    }
                }
            }
        });
    }

    // 2. University-Wide Completion Ratio Donut Chart (Completed vs In Progress)
    const ratioCanvas = document.getElementById('completionRatioDonutChart');
    if (ratioCanvas) {
        new Chart(ratioCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'In Progress / Under Review'],
                datasets: [{
                    data: [
                        <?php echo (int) $totalAllCompleted; ?>,
                        <?php echo (int) $totalAllOngoing; ?>
                    ],
                    backgroundColor: ['#15803d', '#ea580c'],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const total = <?php echo max(1, (int) $grandSubmissionsTotal); ?>;
                                const pct = Math.round((ctx.raw / total) * 100);
                                return ` ${ctx.label}: ${ctx.raw} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // 3. Completion by Stream: Research vs Extension Bar Chart
    const wingCompCanvas = document.getElementById('completionByWingBarChart');
    if (wingCompCanvas) {
        new Chart(wingCompCanvas, {
            type: 'bar',
            data: {
                labels: ['Research Wing', 'Extension Wing'],
                datasets: [
                    {
                        label: 'Completed',
                        data: [
                            <?php echo (int) $researchTotalCompleted; ?>,
                            <?php echo (int) $extensionTotalCompleted; ?>
                        ],
                        backgroundColor: '#15803d',
                        borderColor: '#166534',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.6
                    },
                    {
                        label: 'In Progress / Ongoing',
                        data: [
                            <?php echo (int) $researchTotalOngoing; ?>,
                            <?php echo (int) $extensionTotalOngoing; ?>
                        ],
                        backgroundColor: '#ea580c',
                        borderColor: '#c2410c',
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, padding: 8, font: { size: 10.5, weight: 600 } }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (ctx) => {
                                const isResearch = ctx.dataIndex === 0;
                                const total = isResearch 
                                    ? <?php echo max(1, $researchTotalCompleted + $researchTotalOngoing); ?>
                                    : <?php echo max(1, $extensionTotalCompleted + $extensionTotalOngoing); ?>;
                                const pct = Math.round((ctx.raw / total) * 100);
                                return ` ${ctx.dataset.label}: ${ctx.raw} (${pct}%)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { weight: 700, size: 11 }, color: '#334155' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { precision: 0, stepSize: 1, color: '#64748b' }
                    }
                }
            }
        });
    }

    // 2. Differentiated Submissions by Department Bar Chart (with Interactive Kind Filtering)
    const diffSubCanvas = document.getElementById('differentiatedSubmissionsChart');
    const filterSelect = document.getElementById('submissionKindFilter');
    const titleEl = document.getElementById('deptChartTitle');
    const subtitleEl = document.getElementById('deptChartSubtitle');

    if (diffSubCanvas) {
        const rawRows = <?php echo json_encode($submissionsBreakdown); ?>;
        const labels = rawRows.map(r => r.department_name);

        const datasetConfig = {
            res_proposals: {
                label: 'Research Proposals',
                data: rawRows.map(r => Number(r.res_proposals)),
                backgroundColor: '#15803d',
                borderColor: '#166534',
                borderWidth: 1,
                borderRadius: 5
            },
            res_publications: {
                label: 'Research Publications & Reports',
                data: rawRows.map(r => Number(r.res_publications)),
                backgroundColor: '#7c3aed',
                borderColor: '#6d28d9',
                borderWidth: 1,
                borderRadius: 5
            },
            ext_proposals: {
                label: 'Extension Proposals',
                data: rawRows.map(r => Number(r.ext_proposals)),
                backgroundColor: '#0284c7',
                borderColor: '#0369a1',
                borderWidth: 1,
                borderRadius: 5
            },
            ext_accomplishments: {
                label: 'Accomplishment Reports',
                data: rawRows.map(r => Number(r.ext_accomplishments)),
                backgroundColor: '#ea580c',
                borderColor: '#c2410c',
                borderWidth: 1,
                borderRadius: 5
            }
        };

        function getActiveDatasets(filter) {
            if (filter === 'res_proposals') return [datasetConfig.res_proposals];
            if (filter === 'res_publications') return [datasetConfig.res_publications];
            if (filter === 'ext_proposals') return [datasetConfig.ext_proposals];
            if (filter === 'ext_accomplishments') return [datasetConfig.ext_accomplishments];
            return [
                datasetConfig.res_proposals,
                datasetConfig.res_publications,
                datasetConfig.ext_proposals,
                datasetConfig.ext_accomplishments
            ];
        }

        const deptChart = new Chart(diffSubCanvas, {
            type: 'bar',
            data: {
                labels,
                datasets: getActiveDatasets('all')
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { boxWidth: 10, padding: 10, font: { size: 11, weight: 600 }, color: '#334155' }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 10, weight: 600 },
                            maxRotation: 45,
                            minRotation: 20
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { precision: 0, color: '#64748b', stepSize: 1 }
                    }
                }
            }
        });

        if (filterSelect) {
            filterSelect.addEventListener('change', () => {
                const val = filterSelect.value;
                deptChart.data.datasets = getActiveDatasets(val);
                if (titleEl && subtitleEl) {
                    if (val === 'res_proposals') {
                        titleEl.innerHTML = '<i class="ri-flask-line" style="color: #15803d;"></i> Research Proposals by Department';
                        subtitleEl.textContent = 'Initial faculty research submissions seeking coordinator & director review';
                    } else if (val === 'res_publications') {
                        titleEl.innerHTML = '<i class="ri-book-read-line" style="color: #7c3aed;"></i> Research Publications & Reports by Department';
                        subtitleEl.textContent = 'Published manuscripts, final outputs, and formal research reports';
                    } else if (val === 'ext_proposals') {
                        titleEl.innerHTML = '<i class="ri-community-line" style="color: #0284c7;"></i> Extension Proposals by Department';
                        subtitleEl.textContent = 'Community outreach and institutional extension initiative proposals';
                    } else if (val === 'ext_accomplishments') {
                        titleEl.innerHTML = '<i class="ri-medal-line" style="color: #ea580c;"></i> Extension Accomplishment Reports by Department';
                        subtitleEl.textContent = 'Completed outreach documentation and terminal impact reports';
                    } else {
                        titleEl.innerHTML = '<i class="ri-building-line" style="color: #15803d;"></i> Submissions by Academic Department';
                        subtitleEl.textContent = 'Differentiated volume per college across all 9 departments';
                    }
                }
                deptChart.update();
            });
        }
    }
});
</script>

<?php
renderFooter();
?>
