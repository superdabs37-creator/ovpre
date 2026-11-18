<?php
require_once 'includes/layout.php';
$user = require_login();

$role = role_access_group((string) ($user['role'] ?? ''));
$isSuperAdmin = $role === ROLE_SUPER_ADMIN;
$isOVPREAdmin = $role === ROLE_OVPRE_ADMIN;
$isFacultyResearcher = in_array($role, [ROLE_RESEARCHER, 'faculty-researcher'], true)
    || in_array((string) ($user['role'] ?? ''), [ROLE_RESEARCHER, 'faculty-researcher', 'Faculty Researcher'], true)
    || (isset($_SESSION['active_role']) && in_array((string) $_SESSION['active_role'], [ROLE_RESEARCHER, 'faculty-researcher', 'Faculty Researcher'], true));

$isFacultyExtensionist = in_array($role, [ROLE_FACULTY_EXTENSIONIST, 'faculty-extensionist'], true)
    || in_array((string) ($user['role'] ?? ''), [ROLE_FACULTY_EXTENSIONIST, 'faculty-extensionist', 'Faculty Extensionists'], true)
    || (isset($_SESSION['active_role']) && in_array((string) $_SESSION['active_role'], [ROLE_FACULTY_EXTENSIONIST, 'faculty-extensionist', 'Faculty Extensionists'], true));

$isResearchCoordinator = in_array($role, [ROLE_RESEARCH_COORDINATOR, 'research-coordinator'], true)
    || in_array((string) ($user['role'] ?? ''), [ROLE_RESEARCH_COORDINATOR, 'Research Coordinator'], true)
    || (isset($_SESSION['active_role']) && in_array((string) $_SESSION['active_role'], [ROLE_RESEARCH_COORDINATOR, 'Research Coordinator'], true));

$isExtensionCoordinator = in_array($role, [ROLE_EXTENSION_COORDINATOR, 'extension-coordinator'], true)
    || in_array((string) ($user['role'] ?? ''), [ROLE_EXTENSION_COORDINATOR, 'Extension Coordinator'], true)
    || (isset($_SESSION['active_role']) && in_array((string) $_SESSION['active_role'], [ROLE_EXTENSION_COORDINATOR, 'Extension Coordinator'], true));

$isDeptScoped = $isFacultyResearcher || $isFacultyExtensionist || $isResearchCoordinator || $isExtensionCoordinator;
$userDeptId = (int) ($user['department_id'] ?? 0);
$userDeptName = '';
if ($userDeptId > 0) {
    try {
        $stmtDept = db()->prepare('SELECT department_name FROM departments WHERE id = ? LIMIT 1');
        $stmtDept->execute([$userDeptId]);
        $userDeptName = (string) ($stmtDept->fetchColumn() ?: '');
    } catch (Throwable $e) {}
}

$myResearchCount = 0;
$myExtensionCount = 0;
if (!empty($user['id'])) {
    $stmtMyRes = db()->prepare('SELECT COUNT(*) FROM research_projects WHERE submitted_by = ?');
    $stmtMyRes->execute([(int) $user['id']]);
    $myResearchCount = (int) $stmtMyRes->fetchColumn();

    $stmtMyExt = db()->prepare('SELECT COUNT(*) FROM extension_projects WHERE submitted_by = ?');
    $stmtMyExt->execute([(int) $user['id']]);
    $myExtensionCount = (int) $stmtMyExt->fetchColumn();
}

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

// Monthly submissions for the trend chart (current month plus the prior 11 months).
$monthlySubmissionRows = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql')
    ? db()->query("
        SELECT TO_CHAR(DATE_TRUNC('month', created_at), 'YYYY-MM') AS month_key, 'research' AS submission_type, COUNT(*) AS total
        FROM research_projects
        WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '11 months'
        GROUP BY DATE_TRUNC('month', created_at)
        UNION ALL
        SELECT TO_CHAR(DATE_TRUNC('month', created_at), 'YYYY-MM') AS month_key, 'extension' AS submission_type, COUNT(*) AS total
        FROM extension_projects
        WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '11 months'
        GROUP BY DATE_TRUNC('month', created_at)
        UNION ALL
        SELECT TO_CHAR(DATE_TRUNC('month', created_at), 'YYYY-MM') AS month_key, 'accomplishments' AS submission_type, COUNT(*) AS total
        FROM accomplishment_reports
        WHERE created_at >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '11 months'
        GROUP BY DATE_TRUNC('month', created_at)
        ORDER BY month_key ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : db()->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, 'research' AS submission_type, COUNT(*) AS total
        FROM research_projects
        WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        UNION ALL
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, 'extension' AS submission_type, COUNT(*) AS total
        FROM extension_projects
        WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        UNION ALL
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, 'accomplishments' AS submission_type, COUNT(*) AS total
        FROM accomplishment_reports
        WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY month_key ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
$monthlyTrendMap = [];
foreach ($monthlySubmissionRows as $monthlySubmissionRow) {
    $monthKey = (string) $monthlySubmissionRow['month_key'];
    $submissionType = (string) $monthlySubmissionRow['submission_type'];
    $monthlyTrendMap[$monthKey][$submissionType] = (int) $monthlySubmissionRow['total'];
}
$monthlySubmissionTrend = ['labels' => [], 'research' => [], 'extension' => [], 'accomplishments' => []];
$trendMonth = new DateTimeImmutable('first day of this month');
$trendMonth = $trendMonth->modify('-11 months');
for ($monthOffset = 0; $monthOffset < 12; $monthOffset++) {
    $monthKey = $trendMonth->format('Y-m');
    $monthlySubmissionTrend['labels'][] = $trendMonth->format('M Y');
    foreach (['research', 'extension', 'accomplishments'] as $submissionType) {
        $monthlySubmissionTrend[$submissionType][] = $monthlyTrendMap[$monthKey][$submissionType] ?? 0;
    }
    $trendMonth = $trendMonth->modify('+1 month');
}
$annualTrendTotal = $isFacultyExtensionist
    ? ((int) array_sum($monthlySubmissionTrend['extension']) + (int) array_sum($monthlySubmissionTrend['accomplishments']))
    : ((int) array_sum($monthlySubmissionTrend['research']) + (int) array_sum($monthlySubmissionTrend['extension']) + (int) array_sum($monthlySubmissionTrend['accomplishments']));

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

if ($isDeptScoped && $userDeptId > 0) {
    $deptFilteredBreakdown = array_values(array_filter($submissionsBreakdown, fn($c) => (int)($c['id'] ?? 0) === $userDeptId || ($userDeptName && $c['department_name'] === $userDeptName)));
    if (!empty($deptFilteredBreakdown)) {
        $submissionsBreakdown = $deptFilteredBreakdown;
    }
}

$totalResProposals = (int) array_sum(array_column($submissionsBreakdown, 'res_proposals'));
$totalResPublications = (int) array_sum(array_column($submissionsBreakdown, 'res_publications'));
$totalExtProposals = (int) array_sum(array_column($submissionsBreakdown, 'ext_proposals'));
$totalExtAccomplishments = (int) array_sum(array_column($submissionsBreakdown, 'ext_accomplishments'));
$overallSubmissionsCount = $isFacultyExtensionist
    ? ($totalExtProposals + $totalExtAccomplishments)
    : ($totalResProposals + $totalResPublications + $totalExtProposals + $totalExtAccomplishments);

$programsByCollege = [
    'College of Teacher Education' => ['Bachelor of Elementary Education', 'Bachelor of Special Needs Education', 'Bachelor of Secondary Education', 'Bachelor of Technical-Vocational Teacher Education'],
    'College of Business Administration' => ['Bachelor of Science in Business Administration', 'Bachelor of Science in Entrepreneurship', 'Bachelor of Science in Office Administration'],
    'College of Arts and Sciences' => ['Bachelor of Science in Psychology', 'Bachelor of Arts in Communication', 'Bachelor of Science in Economics', 'Bachelor of Public Administration', 'Bachelor of Arts in Political Science'],
    'College of Accountancy' => ['Bachelor of Science in Accountancy', 'Bachelor of Science in Accounting Information System', 'Bachelor of Science in Management Accounting'],
    'College of Computer Studies and Technology' => ['Bachelor of Science in Information Technology', 'Bachelor of Science in Information Systems'],
    'College of Tourism and Hospitality Management' => ['Bachelor of Science in Hospitality Management', 'Bachelor of Science in Tourism Management'],
    'College of Engineering' => ['Bachelor of Science in Computer Engineering'],
    'College of Nursing and Allied Health Sciences' => ['Bachelor of Science in Nursing'],
    'College of Human Kinetics' => ['Bachelor of Physical Education'],
];
if ($isDeptScoped && !empty($userDeptName) && isset($programsByCollege[$userDeptName])) {
    $programsByCollege = [$userDeptName => $programsByCollege[$userDeptName]];
}
$departmentRowsByName = [];
foreach ($submissionsBreakdown as $departmentRow) {
    $departmentRowsByName[(string) $departmentRow['department_name']] = $departmentRow;
}
$programAnalyticsRows = [];
$programIndex = 0;
$collegeIndex = 0;
foreach ($programsByCollege as $collegeName => $programNames) {
    $departmentRow = $departmentRowsByName[$collegeName] ?? [
        'department_name' => $collegeName,
        'res_proposals' => 0,
        'res_publications' => 0,
        'ext_proposals' => 0,
        'ext_accomplishments' => 0,
        'total_submissions' => 0,
    ];
    foreach ($programNames as $programName) {
        $programAnalyticsRows[] = array_merge($departmentRow, [
            'college_index' => $collegeIndex,
            'program_index' => $programIndex++,
            'program_name' => $programName,
        ]);
    }
    $collegeIndex++;
}

$collegeMatrix = $submissionsBreakdown;

// =========================================================
// SUBMISSION COMPLETION STATUS ENGINE (TAPOS NA VS. HINDI PA TAPOS)
// =========================================================
$allDeptsList = db()->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
if ($isDeptScoped && $userDeptId > 0) {
    $allDeptsList = array_values(array_filter($allDeptsList, fn($d) => (int)$d['id'] === $userDeptId));
}

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

// Status distribution for research and extension submissions.
if ($isDeptScoped && $userDeptId > 0) {
    $submissionStatusRows = db()->query("
        SELECT r.status FROM research_projects r JOIN users u ON u.id = r.submitted_by WHERE u.department_id = {$userDeptId}
        UNION ALL
        SELECT e.status FROM extension_projects e JOIN users u ON u.id = e.submitted_by WHERE u.department_id = {$userDeptId}
    ")->fetchAll(PDO::FETCH_COLUMN);
} else {
    $submissionStatusRows = db()->query("
        SELECT status FROM research_projects
        UNION ALL
        SELECT status FROM extension_projects
    ")->fetchAll(PDO::FETCH_COLUMN);
}
$submissionStatusCounts = ['Pending' => 0, 'Under Review' => 0, 'For Revision' => 0, 'Approved' => 0];
foreach ($submissionStatusRows as $submissionStatus) {
    $normalizedStatus = strtolower(trim((string) $submissionStatus));
    if ($normalizedStatus === 'approved') {
        $submissionStatusCounts['Approved']++;
    } elseif ($normalizedStatus === 'under review') {
        $submissionStatusCounts['Under Review']++;
    } elseif (in_array($normalizedStatus, ['for revision', 'needs revision'], true)) {
        $submissionStatusCounts['For Revision']++;
    } elseif ($normalizedStatus === 'pending' || str_starts_with($normalizedStatus, 'pending ')) {
        $submissionStatusCounts['Pending']++;
    }
}
$trackedSubmissionStatusTotal = array_sum($submissionStatusCounts);

// Use actual project completion fields; approval status alone does not qualify.
$completedProjectDeptFilter = ($isDeptScoped && $userDeptId > 0) ? "AND dep.id = {$userDeptId}" : "";
$completedProjectRows = db()->query("
    SELECT
        dep.id AS department_id,
        dep.department_name,
        'Research' AS project_type,
        r.id AS project_id,
        r.title AS project_title,
        COALESCE(r.abstract, '') AS project_description,
        COALESCE(r.researchers, '') AS project_lead,
        COALESCE(u.fullname, 'Faculty Proponent') AS submitter_name,
        r.created_at AS submitted_at,
        d.id AS document_id,
        d.filename AS document_filename,
        d.original_filename AS document_original_filename
    FROM research_projects r
    JOIN users u ON u.id = r.submitted_by
    JOIN departments dep ON dep.id = u.department_id
    LEFT JOIN documents d ON d.id = r.document_id
    WHERE r.workflow_stage = 'Completed' {$completedProjectDeptFilter}

    UNION ALL

    SELECT
        dep.id AS department_id,
        dep.department_name,
        'Extension' AS project_type,
        e.id AS project_id,
        e.project_title AS project_title,
        COALESCE(d.description, e.location, '') AS project_description,
        COALESCE(e.community_partner, e.coordinator, '') AS project_lead,
        COALESCE(u.fullname, 'Extension Coordinator') AS submitter_name,
        e.created_at AS submitted_at,
        d.id AS document_id,
        d.filename AS document_filename,
        d.original_filename AS document_original_filename
    FROM extension_projects e
    JOIN users u ON u.id = e.submitted_by
    JOIN departments dep ON dep.id = u.department_id
    LEFT JOIN documents d ON d.id = e.document_id
    WHERE e.progress_status = 'Completed' {$completedProjectDeptFilter}
    ORDER BY department_name ASC, project_type ASC, project_title ASC
")->fetchAll(PDO::FETCH_ASSOC);
$completedColleges = [];
foreach ($completedProjectRows as $completedProjectRow) {
    if ($isFacultyExtensionist && ($completedProjectRow['project_type'] ?? '') !== 'Extension') {
        continue;
    }
    $departmentId = (int) $completedProjectRow['department_id'];
    if (!isset($completedColleges[$departmentId])) {
        $completedColleges[$departmentId] = [
            'department_name' => (string) $completedProjectRow['department_name'],
            'projects' => [],
        ];
    }
    $docId = (int) ($completedProjectRow['document_id'] ?? 0);
    $fileUrl = $docId > 0 ? 'download-document.php?id=' . $docId : '';
    $fileName = !empty($completedProjectRow['document_original_filename'])
        ? (string) $completedProjectRow['document_original_filename']
        : (!empty($completedProjectRow['document_filename']) ? basename((string) $completedProjectRow['document_filename']) : 'Final-Completed-Report.pdf');

    $completedColleges[$departmentId]['projects'][] = [
        'id' => (int) $completedProjectRow['project_id'],
        'type' => (string) $completedProjectRow['project_type'],
        'title' => (string) $completedProjectRow['project_title'],
        'status' => 'Completed',
        'description' => (string) ($completedProjectRow['project_description'] ?? ''),
        'lead' => (string) ($completedProjectRow['project_lead'] ?? ''),
        'extra_info' => (string) ($completedProjectRow['project_lead'] ?? ''),
        'submitter_name' => (string) ($completedProjectRow['submitter_name'] ?? 'Faculty Proponent'),
        'department_name' => (string) $completedProjectRow['department_name'],
        'submitted_at' => (string) ($completedProjectRow['submitted_at'] ?? ''),
        'document_url' => $fileUrl,
        'file_url' => $fileUrl,
        'file_name' => $fileName,
    ];
}
$completedColleges = array_values($completedColleges);

// Wing-Specific Submissions
if ($isDeptScoped && $userDeptId > 0) {
    $researchTotalCompleted = (int) db()->query("SELECT COUNT(*) FROM research_projects r JOIN users u ON u.id = r.submitted_by WHERE u.department_id = {$userDeptId} AND r.workflow_stage = 'Completed'")->fetchColumn();
    $researchTotalOngoing = (int) db()->query("SELECT COUNT(*) FROM research_projects r JOIN users u ON u.id = r.submitted_by WHERE u.department_id = {$userDeptId} AND (r.workflow_stage != 'Completed' OR r.workflow_stage IS NULL)")->fetchColumn();
    $extensionTotalCompleted = (int) db()->query("SELECT COUNT(*) FROM extension_projects e JOIN users u ON u.id = e.submitted_by WHERE u.department_id = {$userDeptId} AND e.progress_status = 'Completed'")->fetchColumn();
    $extensionTotalOngoing = (int) db()->query("SELECT COUNT(*) FROM extension_projects e JOIN users u ON u.id = e.submitted_by WHERE u.department_id = {$userDeptId} AND (e.progress_status != 'Completed' OR e.progress_status IS NULL)")->fetchColumn();
} else {
    $researchTotalCompleted = (int) db()->query("SELECT COUNT(*) FROM research_projects WHERE workflow_stage = 'Completed'")->fetchColumn();
    $researchTotalOngoing = (int) db()->query("SELECT COUNT(*) FROM research_projects WHERE workflow_stage != 'Completed' OR workflow_stage IS NULL")->fetchColumn();
    $extensionTotalCompleted = (int) db()->query("SELECT COUNT(*) FROM extension_projects WHERE progress_status = 'Completed'")->fetchColumn();
    $extensionTotalOngoing = (int) db()->query("SELECT COUNT(*) FROM extension_projects WHERE progress_status != 'Completed' OR progress_status IS NULL")->fetchColumn();
}

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
    if ($role !== ROLE_RESEARCH_DIRECTOR) {
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
    }
} catch (Throwable $e) {}

// Submission counts and drill-down records for each submitter.
$personSubmissionRows = db()->query("
    SELECT
        u.id AS submitter_id,
        u.fullname AS submitter_name,
        dep.department_name,
        submission_rows.project_type,
        submission_rows.project_id,
        submission_rows.project_title,
        submission_rows.project_status,
        submission_rows.submitted_at,
        submission_rows.description,
        submission_rows.extra_info,
        submission_rows.document_id,
        d.filename AS document_filename
    FROM (
        SELECT submitted_by, 'Research' AS project_type, id AS project_id, title AS project_title, status AS project_status, created_at AS submitted_at, abstract AS description, researchers AS extra_info, document_id
        FROM research_projects
        UNION ALL
        SELECT submitted_by, 'Extension' AS project_type, id AS project_id, project_title, status AS project_status, created_at AS submitted_at, community_partner AS description, coordinator AS extra_info, document_id
        FROM extension_projects
    ) submission_rows
    JOIN users u ON u.id = submission_rows.submitted_by
    LEFT JOIN departments dep ON dep.id = u.department_id
    LEFT JOIN documents d ON d.id = submission_rows.document_id
    ORDER BY u.fullname ASC, submission_rows.submitted_at DESC, submission_rows.project_id DESC
")->fetchAll(PDO::FETCH_ASSOC);
$submissionPerformance = [];
foreach ($personSubmissionRows as $personSubmissionRow) {
    $submitterId = (int) $personSubmissionRow['submitter_id'];
    if (!isset($submissionPerformance[$submitterId])) {
        $submissionPerformance[$submitterId] = [
            'submitter_id' => $submitterId,
            'submitter_name' => (string) $personSubmissionRow['submitter_name'],
            'department_name' => (string) ($personSubmissionRow['department_name'] ?? ''),
            'research_count' => 0,
            'extension_count' => 0,
            'approved_count' => 0,
            'submissions' => [],
        ];
    }
    $projectType = (string) $personSubmissionRow['project_type'];
    if ($isFacultyExtensionist && $projectType === 'Research') {
        continue;
    }
    if ($projectType === 'Research') {
        $submissionPerformance[$submitterId]['research_count']++;
    } else {
        $submissionPerformance[$submitterId]['extension_count']++;
    }
    if ((string) $personSubmissionRow['project_status'] === 'Approved') {
        $submissionPerformance[$submitterId]['approved_count']++;
    }

    $docFile = (string) ($personSubmissionRow['document_filename'] ?? '');
    $docId = !empty($personSubmissionRow['document_id']) ? (int) $personSubmissionRow['document_id'] : null;
    $docUrl = ($docFile !== '' && $docId !== null) ? record_access_url($docFile, '', $docId) : '';

    $submissionPerformance[$submitterId]['submissions'][] = [
        'id' => (int) $personSubmissionRow['project_id'],
        'type' => $projectType,
        'title' => (string) $personSubmissionRow['project_title'],
        'status' => (string) ($personSubmissionRow['project_status'] ?? 'Unknown'),
        'submitted_at' => (string) $personSubmissionRow['submitted_at'],
        'description' => (string) ($personSubmissionRow['description'] ?? ''),
        'extra_info' => (string) ($personSubmissionRow['extra_info'] ?? ''),
        'document_url' => $docUrl,
    ];
}
$submissionPerformance = array_values($submissionPerformance);
if ($isFacultyExtensionist) {
    $submissionPerformance = array_values(array_filter($submissionPerformance, static fn(array $p): bool => $p['extension_count'] > 0));
}
usort($submissionPerformance, static function (array $a, array $b): int {
    $totalCompare = ($b['research_count'] + $b['extension_count']) <=> ($a['research_count'] + $a['extension_count']);
    return $totalCompare !== 0 ? $totalCompare : strcasecmp($a['submitter_name'], $b['submitter_name']);
});

renderHeader('Dashboard', 'dashboard');
?>

<!-- =========================================================
     ANALYTICAL KPI SUMMARY CAPSULES
========================================================= -->
<section class="kpi-capsules-grid">
    <?php if ($isSuperAdmin): ?>
    <!-- Records (Super Admin Only) -->
    <a href="admin-documents.php" id="kpiAcademicRecordsCard" class="panel kpi-solid-card kpi-interactive-card kpi-card-academic" role="button" aria-label="Open Academic Records in Document Management" title="Click to view all <?php echo $totalDocuments; ?> cataloged academic documents">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: rgba(255, 255, 255, 0.92); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Academic Records</span>
            <span class="kpi-icon-pill">
                <i class="ri-file-text-line"></i>
            </span>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 6px; letter-spacing: -0.02em;"><?php echo $totalDocuments; ?></div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
            <small style="color: rgba(255, 255, 255, 0.88); font-size: 0.78rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Cataloged in repository</small>
            <span class="kpi-card-action"><i class="ri-arrow-right-up-line"></i></span>
        </div>
    </a>
    <?php endif; ?>

    <?php if (!$isFacultyExtensionist): ?>
    <!-- Research Proposals -->
    <a href="<?php echo $isFacultyResearcher ? 'research.php?filter=my' : 'research.php'; ?>" id="kpiResearchCard" class="panel kpi-solid-card kpi-interactive-card kpi-card-research" role="button" aria-label="Open Research Proposals registry" title="<?php echo $isFacultyResearcher ? 'Shortcut: Click to view only your submitted research proposals' : 'Click to view ' . $totalResearch . ' faculty research proposals'; ?>">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
                <span style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: rgba(255, 255, 255, 0.92); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $isFacultyResearcher ? 'My Research Proposals' : 'Research Proposals'; ?></span>
                <?php if ($isFacultyResearcher): ?>
                <span class="kpi-role-tag"><i class="ri-user-star-line"></i> My Submissions</span>
                <?php endif; ?>
            </div>
            <span class="kpi-icon-pill">
                <i class="ri-flask-line"></i>
            </span>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 6px; letter-spacing: -0.02em;">
            <?php if ($isFacultyResearcher): ?>
                <?php echo $myResearchCount; ?> <span style="font-size: 1rem; font-weight: 600; opacity: 0.85;">/ <?php echo $totalResearch; ?> total</span>
            <?php else: ?>
                <?php echo $totalResearch; ?>
            <?php endif; ?>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
            <small style="color: rgba(255, 255, 255, 0.88); font-size: 0.78rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $isFacultyResearcher ? 'My submitted proposals (Click to open)' : 'Faculty submissions'; ?></small>
            <span class="kpi-card-action"><i class="ri-arrow-right-up-line"></i></span>
        </div>
    </a>
    <?php else: ?>
    <!-- Accomplishment Reports -->
    <a href="extension.php" class="panel kpi-solid-card kpi-interactive-card kpi-card-users" role="button" aria-label="Open Extension Accomplishment Reports" title="Click to view <?php echo $totalExtAccomplishments; ?> accomplishment reports">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: rgba(255, 255, 255, 0.92); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Accomplishment Reports</span>
            <span class="kpi-icon-pill">
                <i class="ri-medal-line"></i>
            </span>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 6px; letter-spacing: -0.02em;"><?php echo $totalExtAccomplishments; ?></div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
            <small style="color: rgba(255, 255, 255, 0.88); font-size: 0.78rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Extension documentation</small>
            <span class="kpi-card-action"><i class="ri-arrow-right-up-line"></i></span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Extension Projects -->
    <a href="<?php echo $isFacultyExtensionist ? 'extension.php?filter=my' : 'extension.php'; ?>" id="kpiExtensionCard" class="panel kpi-solid-card kpi-interactive-card kpi-card-extension" role="button" aria-label="Open Extension Programs registry" title="<?php echo $isFacultyExtensionist ? 'Shortcut: Click to view only your submitted extension initiatives' : 'Click to view ' . $totalExtension . ' community extension initiatives'; ?>">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 6px; min-width: 0;">
                <span style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: rgba(255, 255, 255, 0.92); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $isFacultyExtensionist ? 'My Extension Projects' : 'Extension Initiatives'; ?></span>
                <?php if ($isFacultyExtensionist): ?>
                <span class="kpi-role-tag"><i class="ri-user-star-line"></i> My Submissions</span>
                <?php endif; ?>
            </div>
            <span class="kpi-icon-pill">
                <i class="ri-community-line"></i>
            </span>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 6px; letter-spacing: -0.02em;">
            <?php if ($isFacultyExtensionist): ?>
                <?php echo $myExtensionCount; ?> <span style="font-size: 1rem; font-weight: 600; opacity: 0.85;">/ <?php echo $totalExtension; ?> total</span>
            <?php else: ?>
                <?php echo $totalExtension; ?>
            <?php endif; ?>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
            <small style="color: rgba(255, 255, 255, 0.88); font-size: 0.78rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $isFacultyExtensionist ? 'My submitted initiatives (Click to open)' : 'Community outreach'; ?></small>
            <span class="kpi-card-action"><i class="ri-arrow-right-up-line"></i></span>
        </div>
    </a>

    <!-- Colleges Participation -->
    <a href="#programsByCollegeModal" id="kpiCollegesCardPhp" class="panel kpi-solid-card kpi-interactive-card kpi-card-colleges" role="button" aria-label="View Programs and Colleges Breakdown" title="Click to view participating colleges and their academic programs">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: rgba(255, 255, 255, 0.92); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo ($isDeptScoped && !empty($userDeptName)) ? 'Department Active' : 'Colleges Active'; ?></span>
            <span class="kpi-icon-pill">
                <i class="ri-government-line"></i>
            </span>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 6px; letter-spacing: -0.02em;">
            <?php if ($isDeptScoped && !empty($userDeptName)): ?>
                1 <span style="font-size: 1rem; font-weight: 600; opacity: 0.85;">/ 1 Dept</span>
            <?php else: ?>
                <?php echo $activeCollegesCount; ?> <span style="font-size: 1rem; font-weight: 600; opacity: 0.85;">/ <?php echo $totalColleges; ?></span>
            <?php endif; ?>
        </div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
            <small style="color: rgba(255, 255, 255, 0.88); font-size: 0.78rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?php echo ($isDeptScoped && !empty($userDeptName)) ? 'Assigned: ' . h($userDeptName) : 'Colleges with submissions'; ?>
            </small>
            <span class="kpi-card-action"><i class="ri-list-check-3"></i></span>
        </div>
    </a>

    <?php if ($isSuperAdmin): ?>
    <!-- Active Faculty / Registered Users (Super Admin Only) -->
    <a href="admin-users.php" id="kpiUsersCard" class="panel kpi-solid-card kpi-interactive-card kpi-card-users" role="button" aria-label="Open User Management Portal" title="Click to view registered users and account directory">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: rgba(255, 255, 255, 0.92); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Registered Users</span>
            <span class="kpi-icon-pill">
                <i class="ri-team-line"></i>
            </span>
        </div>
        <div style="font-size: 1.85rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 6px; letter-spacing: -0.02em;"><?php echo $activeUsers; ?> <span style="font-size: 1rem; font-weight: 600; opacity: 0.85;">/ <?php echo $totalUsers; ?></span></div>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
            <small style="color: rgba(255, 255, 255, 0.88); font-size: 0.78rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Active accounts</small>
            <span class="kpi-card-action"><i class="ri-arrow-right-up-line"></i></span>
        </div>
    </a>
    <?php endif; ?>
</section>

<style>
.dash-hero-charts-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 24px;
    align-items: stretch;
}
@media (max-width: 1024px) {
    .dash-hero-charts-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- =========================================================
     HERO CHARTS GRID: SUBMISSIONS BY DEPT & MONTHLY TRENDS SIDE BY SIDE
========================================================= -->
<div class="dash-hero-charts-grid">
    <!-- Column 1: Submissions by Academic Department -->
    <article class="panel chart-panel featured-spotlight-card" style="margin-bottom: 0; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04); background: #ffffff; padding: 20px 24px; display: flex; flex-direction: column; justify-content: space-between; min-width: 0; height: 100%;">
        <div>
            <div class="panel-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 18px;">
                <div>
                    <div class="benchmark-highlight-badge" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 999px; color: #15803d; font-size: 0.70rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(21, 128, 61, 0.04);">
                        <i class="ri-sparkling-fill" style="color: #16a34a; font-size: 0.75rem;"></i>
                        <span>Primary University Benchmark</span>
                    </div>
                    <h2 id="deptChartTitle" style="color: #0f172a; font-size: clamp(1.15rem, 1.8vw, 1.35rem); font-weight: 700; letter-spacing: -0.015em; display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i class="ri-building-line" style="color: #15803d; font-size: 1.3rem;"></i> <?php echo ($isDeptScoped && !empty($userDeptName)) ? 'Submission Analytics - ' . h($userDeptName) : ($isFacultyExtensionist ? 'Extension Submission Analytics by College and Program' : 'Submission Analytics by College and Program'); ?>
                    </h2>
                </div>
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label for="submissionKindFilter" style="font-size: 0.8rem; font-weight: 700; color: #475569;">College:</label>
                        <select id="submissionKindFilter" class="filter-select compact-select" style="font-size: 0.82rem; font-weight: 600; padding: 8px 12px; border-radius: 9px; border: 1px solid #cbd5e1; background: #ffffff; color: #1e293b; cursor: pointer;">
                            <?php 
                            $collegeAcronymLookup = [
                                'College of Teacher Education' => 'CTE',
                                'College of Business Administration' => 'CBA',
                                'College of Arts and Sciences' => 'CAS',
                                'College of Accountancy' => 'COA',
                                'College of Computer Studies and Technology' => 'CCST',
                                'College of Tourism and Hospitality Management' => 'CTHM',
                                'College of Engineering' => 'COE',
                                'College of Nursing and Allied Health Sciences' => 'CNAHS',
                                'College of Human Kinetics' => 'CHK',
                            ];
                            ?>
                            <?php if ($isDeptScoped && !empty($userDeptName)): ?>
                                <?php $acr = $collegeAcronymLookup[$userDeptName] ?? ''; ?>
                                <option value="college-0" selected><?php echo h($userDeptName . ($acr ? " ($acr)" : '')); ?></option>
                            <?php else: ?>
                                <option value="all">All College Departments</option>
                                <?php 
                                $selectCollegeIndex = 0; 
                                foreach ($programsByCollege as $collegeName => $programNames): 
                                    $acr = $collegeAcronymLookup[$collegeName] ?? '';
                                ?>
                                    <option value="college-<?php echo $selectCollegeIndex++; ?>"><?php echo h($collegeName . ($acr ? " ($acr)" : '')); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 700; font-size: 0.82rem; padding: 8px 12px; border-radius: 999px;">
                        <?php echo $overallSubmissionsCount; ?> Total Submissions
                    </span>
                </div>
            </div>
            <div style="position: relative; height: 350px; width: 100%; padding: 12px 14px 10px 4px; border: 1px solid #eef2f7; border-radius: 12px; background: #fcfdfc;">
                <canvas id="differentiatedSubmissionsChart" role="img" aria-label="Bar chart of submissions by college and program"></canvas>
            </div>
        </div>
        <section aria-labelledby="programsByCollegeHeading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
            <div>
                <h3 id="programsByCollegeHeading" style="margin: 0 0 3px; color: #0f172a; font-size: 1.05rem; font-weight: 800;">Programs by College</h3>
            </div>
            <button type="button" id="openProgramsModal" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border: 0; border-radius: 9px; background: #15803d; color: #ffffff; font-weight: 700; cursor: pointer;">
                <i class="ri-list-check-3"></i> View Programs
            </button>
        </section>
    </article>

    <!-- Column 2: Monthly Submission Trends -->
    <article class="panel chart-panel" style="margin: 0; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04); background: #ffffff; padding: 20px 24px; display: flex; flex-direction: column; justify-content: space-between; min-width: 0; height: 100%;">
        <div>
            <div class="panel-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 16px;">
                <div>
                    <div class="benchmark-highlight-badge" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 999px; color: #15803d; font-size: 0.70rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; box-shadow: 0 1px 2px rgba(21, 128, 61, 0.04);">
                        <i class="ri-pulse-line" style="color: #16a34a; font-size: 0.75rem;"></i>
                        <span>Annual Performance</span>
                    </div>
                    <h2 style="margin: 0; color: #0f172a; font-size: clamp(1.15rem, 1.8vw, 1.35rem); font-weight: 700; letter-spacing: -0.015em; display: flex; align-items: center; gap: 8px;">
                        <i class="ri-line-chart-line" style="font-size: 1.3rem; color: #15803d;"></i> Monthly Submission Trends
                    </h2>
                    <span style="display: block; margin-top: 5px; color: #64748b; font-size: 0.84rem; font-weight: 500;">
                        <?php echo $isFacultyExtensionist ? 'Extension programs and accomplishment reports submitted each month' : 'Research proposals, extension programs, and accomplishment reports submitted each month'; ?>
                    </span>
                </div>
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <span class="badge" style="background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; font-weight: 600; font-size: 0.80rem; padding: 5px 12px; border-radius: 999px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="ri-file-list-3-line" style="color: #15803d;"></i> <?php echo $annualTrendTotal; ?> Total Submissions
                    </span>
                    <span class="badge" style="display: inline-flex; align-items: center; gap: 5px; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; box-shadow: 0 1px 2px rgba(21, 128, 61, 0.04);">
                        <i class="ri-calendar-line" style="font-size: 0.8rem; color: #16a34a;"></i> Last 12 months
                    </span>
                </div>
            </div>
            <div style="position: relative; width: 100%; height: 350px;">
                <canvas id="monthlySubmissionTrendChart" role="img" aria-label="Area chart showing monthly <?php echo $isFacultyExtensionist ? 'extension programs and accomplishment reports' : 'research proposals, extension programs, and accomplishment reports'; ?>"></canvas>
            </div>
        </div>
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 0.82rem; color: #64748b; font-weight: 600;">
            <span style="display: inline-flex; align-items: center; gap: 5px;">
                <i class="ri-history-line" style="color: #15803d;"></i> 12-Month Longitudinal Data
            </span>
            <span style="display: inline-flex; align-items: center; gap: 5px; color: #0f172a; font-weight: 700;">
                <i class="ri-sparkling-fill" style="color: #16a34a; font-size: 0.75rem;"></i> Active Submission Trends
            </span>
        </div>
    </article>
</div>

<dialog id="programsByCollegeModal" aria-labelledby="programsModalTitle" style="width: min(900px, calc(100% - 32px)); max-width: 900px; max-height: 85vh; padding: 0; border: 0; border-radius: 16px; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.3); padding: 0;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 18px 22px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
        <div>
            <h3 id="programsModalTitle" style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 800;">Programs by College</h3>
        </div>
        <button type="button" id="closeProgramsModal" aria-label="Close programs list" style="flex: 0 0 auto; width: 34px; height: 34px; border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; color: #334155; font-size: 1.2rem; cursor: pointer;">&times;</button>
    </div>
    <div style="max-height: calc(85vh - 82px); overflow-y: auto; padding: 18px 22px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">
            <?php foreach ($programsByCollege as $collegeName => $programs): ?>
                <section style="padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">
                    <h4 style="margin: 0 0 8px; color: #166534; font-size: 0.86rem; font-weight: 800;"><?php echo h($collegeName); ?></h4>
                    <ul style="margin: 0; padding-left: 18px; color: #334155; font-size: 0.8rem; line-height: 1.55;">
                        <?php foreach ($programs as $programName): ?>
                            <li><?php echo h($programName); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</dialog>
<script>
    (() => {
        const modal = document.getElementById('programsByCollegeModal');
        const openButton = document.getElementById('openProgramsModal');
        const closeButton = document.getElementById('closeProgramsModal');
        const kpiCollegesCard = document.getElementById('kpiCollegesCardPhp');
        if (!modal) return;
        if (openButton) openButton.addEventListener('click', () => modal.showModal());
        if (kpiCollegesCard) {
            kpiCollegesCard.addEventListener('click', (e) => {
                e.preventDefault();
                modal.showModal();
            });
        }
        if (closeButton) closeButton.addEventListener('click', () => modal.close());
        modal.addEventListener('click', event => {
            if (event.target === modal) modal.close();
        });
    })();
</script>

<!-- =========================================================
     ROW 2: SUBMISSION STATUS & COMPLETION INTELLIGENCE
========================================================= -->
<?php
$researchTotal = max(1, $researchTotalCompleted + $researchTotalOngoing);
$researchCompRate = round(($researchTotalCompleted / $researchTotal) * 100, 1);
$extensionTotal = max(1, $extensionTotalCompleted + $extensionTotalOngoing);
$extensionCompRate = round(($extensionTotalCompleted / $extensionTotal) * 100, 1);
$appPct = $trackedSubmissionStatusTotal > 0 ? round(($submissionStatusCounts['Approved'] / $trackedSubmissionStatusTotal) * 100, 1) : 0;
$revPct = $trackedSubmissionStatusTotal > 0 ? round(($submissionStatusCounts['Under Review'] / $trackedSubmissionStatusTotal) * 100, 1) : 0;
$penPct = $trackedSubmissionStatusTotal > 0 ? round(($submissionStatusCounts['Pending'] / $trackedSubmissionStatusTotal) * 100, 1) : 0;
$rvsPct = $trackedSubmissionStatusTotal > 0 ? round(($submissionStatusCounts['For Revision'] / $trackedSubmissionStatusTotal) * 100, 1) : 0;
$totalCompletedProjectsCount = array_sum(array_map(fn($c) => count($c['projects'] ?? []), $completedColleges));
?>
<section id="submissionCompletionAnalyticsSection" class="acad-analytics-section">
    <div class="acad-analytics-header">
        <div>
            <div class="acad-overline-pill">
                <span class="acad-overline-dot"></span>
                <span>Submission Intelligence</span>
            </div>
            <h2 class="acad-analytics-title">
                Submission Status &amp; Completion Analytics
            </h2>
        </div>
        <div class="acad-header-actions">
            <span class="acad-meta-pill">
                <i class="ri-verified-badge-line" style="color: #15803d; font-size: 0.95rem;"></i>
                <span>Approval Rate: <strong><?php echo number_format($appPct, 1); ?>%</strong></span>
            </span>
            <span class="acad-meta-pill">
                <span class="acad-live-dot"></span>
                <span>Live Workflow Tracking</span>
            </span>
        </div>
    </div>

    <div class="acad-analytics-grid">
        
        <!-- Card 1: Project Status Breakdown -->
        <article class="panel chart-panel acad-analytics-card">
            <div>
                <div class="acad-card-header">
                    <div class="acad-card-title-wrap">
                        <span class="acad-card-icon icon-emerald">
                            <i class="ri-donut-chart-line"></i>
                        </span>
                        <div>
                            <h2 class="acad-card-title">Project Status Breakdown</h2>
                        </div>
                    </div>
                    <span class="acad-card-badge badge-emerald">
                        <?php echo $trackedSubmissionStatusTotal; ?> Tracked Submissions
                    </span>
                </div>

                <div style="position: relative; height: 205px; width: 100%; max-width: 400px; margin: 4px auto 14px;">
                    <canvas id="submissionStatusDonutChart" aria-label="Donut chart of pending, under review, for revision, and approved submissions"></canvas>
                </div>
            </div>

            <!-- Structured Status Metrics 2x2 Grid -->
            <div class="acad-status-grid">
                <div class="acad-status-item">
                    <div class="acad-status-top">
                        <span class="acad-status-label">
                            <span class="acad-status-dot" style="background: #15803d;"></span> Approved
                        </span>
                        <strong class="acad-status-val">
                            <?php echo $submissionStatusCounts['Approved']; ?> <small>(<?php echo number_format($appPct, 1); ?>%)</small>
                        </strong>
                    </div>
                    <div class="acad-progress-track">
                        <div class="acad-progress-fill" style="width: <?php echo $appPct; ?>%; background: #15803d;"></div>
                    </div>
                </div>
                <div class="acad-status-item">
                    <div class="acad-status-top">
                        <span class="acad-status-label">
                            <span class="acad-status-dot" style="background: #0284c7;"></span> Under Review
                        </span>
                        <strong class="acad-status-val">
                            <?php echo $submissionStatusCounts['Under Review']; ?> <small>(<?php echo number_format($revPct, 1); ?>%)</small>
                        </strong>
                    </div>
                    <div class="acad-progress-track">
                        <div class="acad-progress-fill" style="width: <?php echo $revPct; ?>%; background: #0284c7;"></div>
                    </div>
                </div>
                <div class="acad-status-item">
                    <div class="acad-status-top">
                        <span class="acad-status-label">
                            <span class="acad-status-dot" style="background: #d97706;"></span> Pending
                        </span>
                        <strong class="acad-status-val">
                            <?php echo $submissionStatusCounts['Pending']; ?> <small>(<?php echo number_format($penPct, 1); ?>%)</small>
                        </strong>
                    </div>
                    <div class="acad-progress-track">
                        <div class="acad-progress-fill" style="width: <?php echo $penPct; ?>%; background: #f59e0b;"></div>
                    </div>
                </div>
                <div class="acad-status-item">
                    <div class="acad-status-top">
                        <span class="acad-status-label">
                            <span class="acad-status-dot" style="background: #e11d48;"></span> For Revision
                        </span>
                        <strong class="acad-status-val">
                            <?php echo $submissionStatusCounts['For Revision']; ?> <small>(<?php echo number_format($rvsPct, 1); ?>%)</small>
                        </strong>
                    </div>
                    <div class="acad-progress-track">
                        <div class="acad-progress-fill" style="width: <?php echo $rvsPct; ?>%; background: #f43f5e;"></div>
                    </div>
                </div>
            </div>
        </article>

        <!-- Card 2: Completed Colleges Directory -->
        <article class="panel chart-panel acad-analytics-card">
            <div class="acad-card-header">
                <div class="acad-card-title-wrap">
                    <span class="acad-card-icon icon-emerald">
                        <i class="ri-building-2-line"></i>
                    </span>
                    <div>
                        <h2 class="acad-card-title">Completed Colleges</h2>
                    </div>
                </div>
                <div class="acad-filter-box">
                    <label for="completedProjectTypeFilter">Stream:</label>
                    <select id="completedProjectTypeFilter" class="filter-select compact-select">
                        <?php if ($isFacultyExtensionist): ?>
                            <option value="extension">Extension Only</option>
                        <?php else: ?>
                            <option value="all">All Projects</option>
                            <option value="research">Research</option>
                            <option value="extension">Extension</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Custom Clean Scroll Container -->
            <div id="completedCollegesList" class="custom-clean-scrollbar" style="flex: 1; max-height: 256px; overflow-y: auto; padding-right: 4px; display: flex; flex-direction: column; gap: 9px;">
                <?php if (empty($completedColleges)): ?>
                    <div style="padding: 30px 10px; text-align: center; color: #64748b; font-size: 0.85rem;">
                        <i class="ri-inbox-line" style="font-size: 1.8rem; color: #cbd5e1; display: block; margin-bottom: 6px;"></i>
                        No completed projects found
                    </div>
                <?php else: ?>
                    <?php foreach ($completedColleges as $completedCollege): ?>
                        <div class="acad-college-group">
                            <div class="acad-college-header">
                                <div class="acad-college-name-wrap">
                                    <i class="ri-building-line" style="color: #15803d; font-size: 0.90rem; flex-shrink: 0;"></i>
                                    <h3 class="acad-college-name"><?php echo h($completedCollege['department_name']); ?></h3>
                                </div>
                                <span class="acad-college-count"><?php echo count($completedCollege['projects']); ?> completed</span>
                            </div>
                            <div class="acad-project-list">
                                <?php foreach ($completedCollege['projects'] as $completedProject): ?>
                                    <?php
                                        $isResearch = ($completedProject['type'] ?? '') === 'Research';
                                        $projId = (int) ($completedProject['id'] ?? 0);
                                        $targetUrl = $isResearch
                                            ? 'research.php?highlight=' . $projId . '#proposal-' . $projId
                                            : 'extension.php?highlight=' . $projId . '#project-' . $projId;
                                    ?>
                                    <div class="acad-project-row" role="button" tabindex="0" onclick='openQuickPreview(<?php echo htmlspecialchars(json_encode($completedProject), ENT_QUOTES, "UTF-8"); ?>, { submitter_name: <?php echo json_encode($completedProject["submitter_name"] ?? "Faculty Proponent"); ?>, department_name: <?php echo json_encode($completedCollege["department_name"]); ?> })' title="Click to view file & details for <?php echo h($completedProject['title']); ?>">
                                        <div style="display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1;">
                                            <span class="acad-project-type <?php echo $isResearch ? 'type-research' : 'type-extension'; ?>">
                                                <?php echo h($completedProject['type']); ?>
                                            </span>
                                            <span class="acad-project-title">
                                                <?php echo h($completedProject['title']); ?>
                                            </span>
                                        </div>
                                        <button type="button" class="acad-view-file-btn">
                                            <i class="ri-file-text-line" style="font-size: 0.76rem;"></i>
                                            <span>View File</span>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Card Bottom Bar -->
            <div class="acad-card-footer">
                <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600;"><i class="ri-shield-check-line" style="color: #15803d; font-size: 0.9rem;"></i> Institutional Completion Registry</span>
                <strong id="completedTotalBadge" style="color: #0f172a; font-weight: 800;"><?php echo count($completedColleges); ?> Active Colleges · <?php echo $totalCompletedProjectsCount; ?> Completed</strong>
            </div>
        </article>

        <!-- Card 3: Completion by Stream -->
        <article class="panel chart-panel acad-analytics-card">
            <div>
                <div class="acad-card-header">
                    <div class="acad-card-title-wrap">
                        <span class="acad-card-icon icon-indigo">
                            <i class="ri-bar-chart-grouped-line"></i>
                        </span>
                        <div>
                            <h2 class="acad-card-title">Completion by Stream</h2>
                        </div>
                    </div>
                    <span class="acad-card-badge badge-indigo">
                        <?php echo $isFacultyExtensionist ? 'Extension Stream' : 'Research &amp; Extension'; ?>
                    </span>
                </div>

                <div style="position: relative; height: 205px; width: 100%; margin: 4px auto 14px;">
                    <canvas id="completionByWingBarChart"></canvas>
                </div>
            </div>

            <!-- Stream Performance Comparative Cards -->
            <div class="acad-stream-grid">
                <?php if (!$isFacultyExtensionist): ?>
                <div class="acad-stream-card">
                    <div class="acad-stream-top">
                        <span class="acad-stream-name">Research</span>
                        <span class="acad-stream-rate rate-research"><?php echo number_format($researchCompRate, 1); ?>%</span>
                    </div>
                    <div class="acad-stream-stats">
                        <strong><?php echo (int) $researchTotalCompleted; ?></strong> completed <small>/ <?php echo (int) $researchTotalOngoing; ?> active</small>
                    </div>
                    <div class="acad-progress-track">
                        <div class="acad-progress-fill" style="width: <?php echo $researchCompRate; ?>%; background: #15803d;"></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="acad-stream-card" style="<?php echo $isFacultyExtensionist ? 'grid-column: 1 / -1;' : ''; ?>">
                    <div class="acad-stream-top">
                        <span class="acad-stream-name">Extension</span>
                        <span class="acad-stream-rate rate-extension"><?php echo number_format($extensionCompRate, 1); ?>%</span>
                    </div>
                    <div class="acad-stream-stats">
                        <strong><?php echo (int) $extensionTotalCompleted; ?></strong> completed <small>/ <?php echo (int) $extensionTotalOngoing; ?> active</small>
                    </div>
                    <div class="acad-progress-track">
                        <div class="acad-progress-fill" style="width: <?php echo $extensionCompRate; ?>%; background: #0284c7;"></div>
                    </div>
                </div>
            </div>
        </article>

    </div>
</section>

<section class="panel" style="margin-bottom: 24px;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-team-line"></i> Submission Performance by Person</h2>
        </div>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>Submitter</th>
                    <th>College</th>
                    <?php if (!$isFacultyExtensionist): ?>
                        <th style="text-align: center;">Research Submissions</th>
                    <?php endif; ?>
                    <th style="text-align: center;">Extension Submissions</th>
                    <th style="text-align: center;">Approved</th>
                    <th style="text-align: center;">All Submissions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissionPerformance)): ?>
                    <tr><td colspan="<?php echo $isFacultyExtensionist ? '5' : '6'; ?>" style="text-align: center; color: #64748b;">No submissions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($submissionPerformance as $person): ?>
                        <tr>
                            <td><strong><?php echo h($person['submitter_name']); ?></strong></td>
                            <td><?php echo h($person['department_name'] !== '' ? $person['department_name'] : 'Unassigned'); ?></td>
                            <?php if (!$isFacultyExtensionist): ?>
                                <td style="text-align: center; color: #0f172a; font-weight: 700;"><?php echo (int) $person['research_count']; ?></td>
                            <?php endif; ?>
                            <td style="text-align: center; color: #0f172a; font-weight: 700;"><?php echo (int) $person['extension_count']; ?></td>
                            <td style="text-align: center; color: #0f172a; font-weight: 700;"><?php echo (int) $person['approved_count']; ?></td>
                            <td style="text-align: center;">
                                <button type="button" class="ghost-btn" data-person-submissions="<?php echo (int) $person['submitter_id']; ?>" style="padding: 6px 10px; font-size: 0.78rem;">
                                    <i class="ri-eye-line"></i> View all (<?php echo count($person['submissions']); ?>)
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog id="personSubmissionsDialog" aria-labelledby="personSubmissionsTitle" style="width: min(940px, calc(100% - 32px)); max-width: 940px; max-height: 85vh; padding: 0; border: 0; border-radius: 16px; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.3); overflow: hidden; background: #ffffff;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
        <h3 id="personSubmissionsTitle" style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 800;">All Submissions</h3>
        <button type="button" id="closePersonSubmissionsDialog" aria-label="Close submissions" style="width: 32px; height: 32px; border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; color: #64748b; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#0f172a';" onmouseout="this.style.background='#ffffff';this.style.color='#64748b';">&times;</button>
    </div>
    <div class="person-dialog-body" style="padding: 20px 24px 24px; display: flex; flex-direction: column; gap: 14px; max-height: calc(85vh - 72px); overflow-y: auto;">
        <div class="table-scroll" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; padding: 0 !important; margin: 0;">
            <table class="data-table portal-table" style="margin: 0; width: 100%;">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 105px;">Type</th>
                        <th style="text-align: left;">Submission Title</th>
                        <th style="text-align: center; width: 125px;">Status</th>
                        <th style="text-align: center; width: 115px; white-space: nowrap;">Submitted</th>
                        <th style="text-align: center; width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody id="personSubmissionsTableBody"></tbody>
            </table>
        </div>
    </div>
</dialog>

<!-- QUICK PREVIEW MODAL FOR SUBMISSIONS -->
<dialog id="submissionQuickPreviewDialog" aria-labelledby="quickPreviewTitle" style="width: min(680px, calc(100% - 32px)); max-width: 680px; max-height: 85vh; padding: 0; border: 0; border-radius: 16px; box-shadow: 0 25px 70px rgba(15, 23, 42, 0.4); overflow: hidden; background: #ffffff;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 22px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span id="quickPreviewTypeBadge" class="badge" style="font-weight: 700; font-size: 0.78rem; padding: 3px 9px; border-radius: 6px;"></span>
            <span id="quickPreviewStatusBadge" class="badge" style="font-weight: 700; font-size: 0.78rem; padding: 3px 10px; border-radius: 999px;"></span>
        </div>
        <button type="button" id="closeQuickPreviewDialog" aria-label="Close preview" style="width: 30px; height: 30px; border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; color: #64748b; font-size: 1.25rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s;" onmouseover="this.style.background='#f1f5f9';this.style.color='#0f172a';" onmouseout="this.style.background='#ffffff';this.style.color='#64748b';">&times;</button>
    </div>
    <div style="padding: 22px 24px; display: flex; flex-direction: column; gap: 14px; max-height: calc(85vh - 72px); overflow-y: auto;">
        <div>
            <h3 id="quickPreviewTitle" style="margin: 0 0 6px; color: #0f172a; font-size: 1.18rem; font-weight: 800; line-height: 1.4;"></h3>
            <p id="quickPreviewMeta" style="margin: 0; color: #64748b; font-size: 0.84rem; line-height: 1.5;"></p>
        </div>
        <div id="quickPreviewDescBox" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; font-size: 0.88rem; color: #334155; line-height: 1.6;">
            <strong id="quickPreviewDescLabel" style="display: block; margin-bottom: 6px; color: #0f172a; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.03em;">Abstract / Details</strong>
            <p id="quickPreviewDesc" style="margin: 0; white-space: pre-wrap; word-break: break-word;"></p>
        </div>
        <!-- Attached File Showcase Card -->
        <div id="quickPreviewFileCard" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                <span style="width: 38px; height: 38px; border-radius: 8px; background: #dcfce7; color: #15803d; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="ri-file-pdf-fill"></i>
                </span>
                <div style="min-width: 0;">
                    <strong id="quickPreviewFileName" style="display: block; font-size: 0.85rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        Research-Documentation.pdf
                    </strong>
                    <span style="font-size: 0.72rem; color: #15803d; font-weight: 600;">
                        <i class="ri-checkbox-circle-fill"></i> Official Verified Academic Document
                    </span>
                </div>
            </div>
            <a id="quickPreviewViewFileBtn" href="#" target="_blank" rel="noopener" class="primary-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 0.78rem; font-weight: 700; color: #ffffff !important; background: #15803d !important; border: 1px solid #15803d !important; border-radius: 7px; text-decoration: none; white-space: nowrap;">
                <i class="ri-file-search-line"></i> <span>View File</span>
            </a>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #e2e8f0; flex-wrap: wrap;">
            <a id="quickPreviewDownloadBtn" href="#" target="_blank" rel="noopener" class="ghost-btn" style="display: none; align-items: center; gap: 6px; padding: 8px 14px; font-size: 0.82rem; font-weight: 600; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none;">
                <i class="ri-download-cloud-line"></i> <span>Download Attached File</span>
            </a>
            <a id="quickPreviewFullPageBtn" href="#" target="_blank" rel="noopener" class="primary-btn" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.84rem; font-weight: 700; color: #ffffff; background: #15803d; border: 1px solid #15803d; border-radius: 8px; text-decoration: none;">
                <i class="ri-external-link-line"></i> <span>Open Full Proposal</span>
            </a>
        </div>
    </div>
</dialog>

<script>
(() => {
    const submissionsByPerson = <?php echo json_encode($submissionPerformance, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    const dialog = document.getElementById('personSubmissionsDialog');
    const dialogTitle = document.getElementById('personSubmissionsTitle');
    const tableBody = document.getElementById('personSubmissionsTableBody');
    const closeButton = document.getElementById('closePersonSubmissionsDialog');

    const previewDialog = document.getElementById('submissionQuickPreviewDialog');
    const closePreviewBtn = document.getElementById('closeQuickPreviewDialog');
    if (!dialog || !dialogTitle || !tableBody || !closeButton) return;

    function openQuickPreview(submission, person) {
        if (!previewDialog) return;
        const isResearch = submission.type === 'Research';
        const subId = encodeURIComponent(submission.id || '');
        const targetUrl = isResearch ? `research.php?highlight=${subId}#proposal-${subId}` : `extension.php?highlight=${subId}#project-${subId}`;

        const typeBadge = document.getElementById('quickPreviewTypeBadge');
        typeBadge.textContent = submission.type;
        typeBadge.style.background = isResearch ? '#f0fdf4' : '#f3e8ff';
        typeBadge.style.color = isResearch ? '#15803d' : '#7c3aed';

        const statusEl = document.getElementById('quickPreviewStatusBadge');
        const status = submission.status || 'Pending';
        let statusBg = '#f0fdf4', statusColor = '#15803d', statusBorder = '#bbf7d0';
        if (status === 'Completed') {
            statusBg = '#eff6ff'; statusColor = '#1d4ed8'; statusBorder = '#bfdbfe';
        } else if (status === 'Pending' || status === 'Under Review') {
            statusBg = '#fffbeb'; statusColor = '#b45309'; statusBorder = '#fde68a';
        } else if (status === 'For Revision') {
            statusBg = '#fff7ed'; statusColor = '#ea580c'; statusBorder = '#fed7aa';
        } else if (status === 'Rejected' || status === 'Declined') {
            statusBg = '#fef2f2'; statusColor = '#dc2626'; statusBorder = '#fecaca';
        }
        statusEl.textContent = status;
        statusEl.style.background = statusBg;
        statusEl.style.color = statusColor;
        statusEl.style.border = `1px solid ${statusBorder}`;

        document.getElementById('quickPreviewTitle').textContent = submission.title;

        let metaText = `Submitter: ${person.submitter_name}`;
        if (person.department_name) metaText += ` (${person.department_name})`;
        if (submission.extra_info) {
            metaText += ` | ${isResearch ? 'Researchers' : 'Coordinator'}: ${submission.extra_info}`;
        }
        if (submission.submitted_at) {
            metaText += ` | Submitted: ${submission.submitted_at.slice(0, 10)}`;
        }
        document.getElementById('quickPreviewMeta').textContent = metaText;

        document.getElementById('quickPreviewDescLabel').textContent = isResearch ? 'Abstract / Proposal Overview' : 'Community Partner & Description';
        document.getElementById('quickPreviewDesc').textContent = submission.description ? submission.description : 'Completed academic project under the institutional repository.';

        const fileUrl = submission.document_url || submission.file_url;
        const fileName = submission.file_name || (submission.title ? submission.title.replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 32) + '-Report.pdf' : 'Academic-Document.pdf');
        
        const fileNameEl = document.getElementById('quickPreviewFileName');
        if (fileNameEl) {
            fileNameEl.textContent = fileName;
        }

        const viewFileBtn = document.getElementById('quickPreviewViewFileBtn');
        if (viewFileBtn) {
            if (fileUrl && fileUrl !== '#') {
                viewFileBtn.href = fileUrl;
                viewFileBtn.target = '_blank';
                viewFileBtn.style.display = 'inline-flex';
                viewFileBtn.innerHTML = '<i class="ri-file-search-line"></i> <span>View File</span>';
            } else {
                viewFileBtn.href = targetUrl;
                viewFileBtn.target = '_blank';
                viewFileBtn.style.display = 'inline-flex';
                viewFileBtn.innerHTML = '<i class="ri-file-text-line"></i> <span>View File Details</span>';
            }
        }

        const downloadBtn = document.getElementById('quickPreviewDownloadBtn');
        if (downloadBtn) {
            if (fileUrl && fileUrl !== '#') {
                downloadBtn.href = fileUrl;
                downloadBtn.style.display = 'inline-flex';
                downloadBtn.innerHTML = '<i class="ri-download-cloud-line"></i> <span>Download File</span>';
            } else {
                downloadBtn.style.display = 'none';
            }
        }

        const fullPageBtn = document.getElementById('quickPreviewFullPageBtn');
        fullPageBtn.href = targetUrl;
        fullPageBtn.querySelector('span').textContent = isResearch ? 'Open Full Proposal' : 'Open Full Project';

        previewDialog.showModal();
    }
    window.openQuickPreview = openQuickPreview;

    if (closePreviewBtn && previewDialog) {
        closePreviewBtn.addEventListener('click', () => previewDialog.close());
        previewDialog.addEventListener('click', event => {
            if (event.target === previewDialog) previewDialog.close();
        });
    }

    document.querySelectorAll('[data-person-submissions]').forEach(button => {
        button.addEventListener('click', () => {
            const person = submissionsByPerson.find(item => String(item.submitter_id) === button.dataset.personSubmissions);
            if (!person) return;
            dialogTitle.textContent = `All Submissions - ${person.submitter_name}`;
            tableBody.replaceChildren();

            if (!person.submissions || !person.submissions.length) {
                const emptyRow = document.createElement('tr');
                emptyRow.setAttribute('data-pagination-empty', 'true');
                const emptyCell = document.createElement('td');
                emptyCell.colSpan = 5;
                emptyCell.style.textAlign = 'center';
                emptyCell.style.color = '#64748b';
                emptyCell.style.padding = '24px';
                emptyCell.textContent = 'No submissions recorded for this person.';
                emptyRow.appendChild(emptyCell);
                tableBody.appendChild(emptyRow);
            } else {
                person.submissions.forEach(submission => {
                    const row = document.createElement('tr');
                    const isResearch = submission.type === 'Research';
                    const subId = encodeURIComponent(submission.id || '');
                    const targetUrl = isResearch ? `research.php?highlight=${subId}#proposal-${subId}` : `extension.php?highlight=${subId}#project-${subId}`;

                    // 1. Type
                    const typeCell = document.createElement('td');
                    typeCell.style.textAlign = 'left';
                    typeCell.style.verticalAlign = 'middle';
                    typeCell.innerHTML = `<span class="badge" style="background: ${isResearch ? '#f0fdf4' : '#f3e8ff'}; color: ${isResearch ? '#15803d' : '#7c3aed'}; font-weight: 700; font-size: 0.78rem; padding: 3px 9px; border-radius: 6px; display: inline-block;">${submission.type}</span>`;
                    typeCell.dataset.sortValue = submission.type;

                    // 2. Submission Title (Clickable link with hover effects)
                    const titleCell = document.createElement('td');
                    titleCell.style.textAlign = 'left';
                    titleCell.style.verticalAlign = 'middle';

                    const titleLink = document.createElement('a');
                    titleLink.href = targetUrl;
                    titleLink.target = '_blank';
                    titleLink.rel = 'noopener';
                    titleLink.title = 'Click to open in full pipeline';
                    titleLink.style.cssText = 'font-weight: 600; color: #1e293b; text-decoration: none; line-height: 1.45; display: inline-flex; align-items: center; gap: 5px; transition: color 0.15s;';
                    titleLink.onmouseover = () => { titleLink.style.color = '#15803d'; titleLink.style.textDecoration = 'underline'; };
                    titleLink.onmouseout = () => { titleLink.style.color = '#1e293b'; titleLink.style.textDecoration = 'none'; };

                    const titleSpan = document.createElement('span');
                    titleSpan.textContent = submission.title;
                    const linkIcon = document.createElement('i');
                    linkIcon.className = 'ri-external-link-line';
                    linkIcon.style.cssText = 'font-size: 0.78rem; color: #94a3b8; flex-shrink: 0;';
                    titleLink.append(titleSpan, linkIcon);
                    titleCell.appendChild(titleLink);
                    titleCell.dataset.sortValue = submission.title;

                    // 3. Status
                    const statusCell = document.createElement('td');
                    statusCell.style.textAlign = 'center';
                    statusCell.style.verticalAlign = 'middle';
                    const status = submission.status || 'Pending';
                    let statusBg = '#f0fdf4', statusColor = '#15803d', statusBorder = '#bbf7d0';
                    if (status === 'Completed') {
                        statusBg = '#eff6ff'; statusColor = '#1d4ed8'; statusBorder = '#bfdbfe';
                    } else if (status === 'Pending' || status === 'Under Review') {
                        statusBg = '#fffbeb'; statusColor = '#b45309'; statusBorder = '#fde68a';
                    } else if (status === 'For Revision') {
                        statusBg = '#fff7ed'; statusColor = '#ea580c'; statusBorder = '#fed7aa';
                    } else if (status === 'Rejected' || status === 'Declined') {
                        statusBg = '#fef2f2'; statusColor = '#dc2626'; statusBorder = '#fecaca';
                    }
                    statusCell.innerHTML = `<span class="badge" style="background: ${statusBg}; color: ${statusColor}; border: 1px solid ${statusBorder}; font-weight: 700; font-size: 0.78rem; padding: 3px 10px; border-radius: 999px; display: inline-block;">${status}</span>`;
                    statusCell.dataset.sortValue = status;

                    // 4. Submitted Date
                    const dateCell = document.createElement('td');
                    dateCell.style.textAlign = 'center';
                    dateCell.style.verticalAlign = 'middle';
                    dateCell.style.whiteSpace = 'nowrap';
                    dateCell.style.color = '#64748b';
                    dateCell.style.fontWeight = '600';
                    dateCell.style.fontVariantNumeric = 'tabular-nums';
                    const dateStr = (submission.submitted_at || '').slice(0, 10);
                    dateCell.textContent = dateStr || '—';
                    dateCell.dataset.sortValue = dateStr;

                    // 5. Action Cell (View button)
                    const actionCell = document.createElement('td');
                    actionCell.style.textAlign = 'center';
                    actionCell.style.verticalAlign = 'middle';
                    actionCell.style.whiteSpace = 'nowrap';
                    actionCell.style.width = '100px';

                    const viewBtn = document.createElement('button');
                    viewBtn.type = 'button';
                    viewBtn.className = 'ghost-btn action-btn';
                    viewBtn.title = 'View submission details';
                    viewBtn.style.cssText = 'display: inline-flex; align-items: center; justify-content: center; gap: 5px; width: auto; min-width: 68px; min-height: 30px; height: 30px; padding: 0 12px; font-size: 0.78rem; font-weight: 700; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; cursor: pointer; transition: all 0.15s ease; box-sizing: border-box;';
                    viewBtn.innerHTML = '<i class="ri-eye-line" style="font-size: 0.92rem;"></i> View';
                    viewBtn.onmouseover = () => {
                        viewBtn.style.background = '#15803d';
                        viewBtn.style.color = '#ffffff';
                        viewBtn.style.borderColor = '#15803d';
                    };
                    viewBtn.onmouseout = () => {
                        viewBtn.style.background = '#f0fdf4';
                        viewBtn.style.color = '#15803d';
                        viewBtn.style.borderColor = '#bbf7d0';
                    };
                    viewBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        openQuickPreview(submission, person);
                    });

                    actionCell.appendChild(viewBtn);

                    row.append(typeCell, titleCell, statusCell, dateCell, actionCell);
                    tableBody.appendChild(row);
                });
            }

            if (typeof window.reloadAdminTablePagination === 'function') {
                window.reloadAdminTablePagination(dialog.querySelector('table.data-table'));
            }
            dialog.showModal();
        });
    });
    closeButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
})();
</script>

<!-- =========================================================
     ROW 3: TACTICAL COLLEGES PERFORMANCE MATRIX (DATA TABLE)
========================================================= -->
<section class="panel" style="margin-bottom: 24px;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-table-line"></i> <?php echo ($isDeptScoped && !empty($userDeptName)) ? 'Tactical Department Submissions Matrix (' . h($userDeptName) . ')' : 'Tactical Colleges Submissions Matrix'; ?></h2>
        </div>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>College Name</th>
                    <th style="text-align: center;">Faculty</th>
                    <?php if (!$isFacultyExtensionist): ?>
                        <th style="text-align: center;">Research Proposals</th>
                        <th style="text-align: center;">Research Publications</th>
                    <?php endif; ?>
                    <th style="text-align: center;">Extension Proposals</th>
                    <th style="text-align: center;">Accomplishments</th>
                    <th style="text-align: center;">Total Submissions</th>
                    <th style="text-align: center;">Activity Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissionsBreakdown as $col): ?>
                    <?php
                    $displayedSubmissions = $isFacultyExtensionist
                        ? ((int) $col['ext_proposals'] + (int) $col['ext_accomplishments'])
                        : (int) $col['total_submissions'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo h($col['department_name']); ?></strong>
                        </td>
                        <td style="text-align: center; color: #0f172a; font-weight: 700;">
                            <?php echo (int) $col['total_faculty']; ?>
                        </td>
                        <?php if (!$isFacultyExtensionist): ?>
                            <td style="text-align: center; color: #0f172a; font-weight: 700;">
                                <?php echo (int) $col['res_proposals']; ?>
                            </td>
                            <td style="text-align: center; color: #0f172a; font-weight: 700;">
                                <?php echo (int) $col['res_publications']; ?>
                            </td>
                        <?php endif; ?>
                        <td style="text-align: center; color: #0f172a; font-weight: 700;">
                            <?php echo (int) $col['ext_proposals']; ?>
                        </td>
                        <td style="text-align: center; color: #0f172a; font-weight: 700;">
                            <?php echo (int) $col['ext_accomplishments']; ?>
                        </td>
                        <td style="text-align: center;">
                            <strong style="font-size: 0.95rem; color: #0f172a;"><?php echo $displayedSubmissions; ?></strong>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($displayedSubmissions > 0 || $col['total_faculty'] > 0): ?>
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

    // Monthly submission trends: modern multi-series area chart with subtle gradients & smooth splines
    const monthlyTrendCanvas = document.getElementById('monthlySubmissionTrendChart');
    if (monthlyTrendCanvas) {
        const monthlyTrend = <?php echo json_encode($monthlySubmissionTrend); ?>;

        // Dynamic vertical linear gradient generator for smooth, translucent area fills
        const createTrendGradient = (ctx, chartArea, colorStart, colorMid, colorEnd) => {
            if (!chartArea) return colorStart;
            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            gradient.addColorStop(0, colorStart);
            gradient.addColorStop(0.55, colorMid);
            gradient.addColorStop(1, colorEnd);
            return gradient;
        };

        new Chart(monthlyTrendCanvas, {
            type: 'line',
            data: {
                labels: monthlyTrend.labels,
                datasets: [
                    <?php if (!$isFacultyExtensionist): ?>
                    {
                        label: 'Research Proposals',
                        data: monthlyTrend.research,
                        borderColor: '#15803d',
                        backgroundColor: (context) => createTrendGradient(
                            context.chart.ctx,
                            context.chart.chartArea,
                            'rgba(21, 128, 61, 0.35)',
                            'rgba(21, 128, 61, 0.10)',
                            'rgba(21, 128, 61, 0.00)'
                        ),
                        borderWidth: 2.8,
                        fill: true,
                        tension: 0.38,
                        cubicInterpolationMode: 'monotone',
                        pointStyle: 'circle',
                        pointRadius: 4.5,
                        pointHoverRadius: 7.5,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#15803d',
                        pointBorderWidth: 2.5,
                        pointHoverBackgroundColor: '#15803d',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 3
                    },
                    <?php endif; ?>
                    {
                        label: 'Extension Programs',
                        data: monthlyTrend.extension,
                        borderColor: '#eab308',
                        backgroundColor: (context) => createTrendGradient(
                            context.chart.ctx,
                            context.chart.chartArea,
                            'rgba(234, 179, 8, 0.32)',
                            'rgba(234, 179, 8, 0.08)',
                            'rgba(234, 179, 8, 0.00)'
                        ),
                        borderWidth: 2.8,
                        fill: true,
                        tension: 0.38,
                        cubicInterpolationMode: 'monotone',
                        pointStyle: 'circle',
                        pointRadius: 4.5,
                        pointHoverRadius: 7.5,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#eab308',
                        pointBorderWidth: 2.5,
                        pointHoverBackgroundColor: '#eab308',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Accomplishment Reports',
                        data: monthlyTrend.accomplishments,
                        borderColor: '#ea580c',
                        backgroundColor: (context) => createTrendGradient(
                            context.chart.ctx,
                            context.chart.chartArea,
                            'rgba(234, 88, 12, 0.28)',
                            'rgba(234, 88, 12, 0.08)',
                            'rgba(234, 88, 12, 0.00)'
                        ),
                        borderWidth: 2.8,
                        fill: true,
                        tension: 0.38,
                        cubicInterpolationMode: 'monotone',
                        pointStyle: 'circle',
                        pointRadius: 4.5,
                        pointHoverRadius: 7.5,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#ea580c',
                        pointBorderWidth: 2.5,
                        pointHoverBackgroundColor: '#ea580c',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'center',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 9,
                            boxHeight: 9,
                            padding: 22,
                            color: '#334155',
                            font: { family: "'Inter', sans-serif", size: 12, weight: '600' }
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        titleColor: '#ffffff',
                        titleFont: { family: "'Inter', sans-serif", size: 12.5, weight: '700' },
                        titleSpacing: 4,
                        bodyColor: '#e2e8f0',
                        bodyFont: { family: "'Inter', sans-serif", size: 11.5, weight: '500' },
                        bodySpacing: 7,
                        padding: { top: 12, bottom: 12, left: 14, right: 14 },
                        cornerRadius: 10,
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        usePointStyle: true,
                        boxWidth: 8,
                        boxHeight: 8,
                        boxPadding: 6,
                        callbacks: {
                            title: (items) => {
                                if (!items || !items.length) return '';
                                const raw = items[0].label;
                                const monthsFull = {
                                    Jan: 'January', Feb: 'February', Mar: 'March', Apr: 'April',
                                    May: 'May', Jun: 'June', Jul: 'July', Aug: 'August',
                                    Sep: 'September', Oct: 'October', Nov: 'November', Dec: 'December'
                                };
                                const parts = String(raw).split(' ');
                                if (parts.length === 2 && monthsFull[parts[0]]) {
                                    return `${monthsFull[parts[0]]} ${parts[1]}`;
                                }
                                return raw;
                            },
                            label: (ctx) => {
                                const val = Number(ctx.raw) || 0;
                                return `  ${ctx.dataset.label}: ${val}`;
                            },
                            footer: (items) => {
                                const total = items.reduce((acc, it) => acc + (Number(it.raw) || 0), 0);
                                return `\nTotal Submissions: ${total}`;
                            }
                        },
                        footerFont: { family: "'Inter', sans-serif", size: 11.5, weight: '700' },
                        footerColor: '#86efac'
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: 'rgba(241, 245, 249, 0.85)',
                            lineWidth: 1
                        },
                        border: { display: false },
                        ticks: {
                            color: '#64748b',
                            font: { family: "'Inter', sans-serif", size: 11, weight: '600' },
                            padding: 10,
                            maxRotation: 0,
                            minRotation: 0,
                            callback: function(val) {
                                const label = this.getLabelForValue(val);
                                return typeof label === 'string' ? label.split(' ')[0] : label;
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            color: 'rgba(226, 232, 240, 0.85)',
                            lineWidth: 1,
                            borderDash: [4, 4]
                        },
                        border: { display: false },
                        ticks: {
                            precision: 0,
                            stepSize: 2,
                            color: '#64748b',
                            font: { family: "'Inter', sans-serif", size: 11, weight: '500' },
                            padding: 10
                        }
                    }
                }
            }
        });
    }

    // Submission status distribution across research and extension projects (Radial Segmented Doughnut).
    const statusDonutCanvas = document.getElementById('submissionStatusDonutChart');
    if (statusDonutCanvas) {
        const statusCounts = <?php echo json_encode(array_values($submissionStatusCounts)); ?>;
        const radialCenterTextPlugin = {
            id: 'radialCenterText',
            afterDraw(chart) {
                const { ctx, chartArea } = chart;
                if (!chartArea) return;
                const cx = (chartArea.left + chartArea.right) / 2;
                const cy = (chartArea.top + chartArea.bottom) / 2;

                ctx.save();
                const active = chart.getActiveElements();
                const dataset = chart.data.datasets[0];
                const data = dataset.data || [];
                const total = data.reduce((acc, v) => acc + (Number(v) || 0), 0);

                let mainVal = '';
                let subLbl = '';
                let mainColor = '#0f172a';
                let subColor = '#64748b';

                if (active.length > 0) {
                    const idx = active[0].index;
                    const val = Number(data[idx]) || 0;
                    const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                    const label = chart.data.labels[idx] || '';
                    const color = Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[idx] : '#15803d';

                    mainVal = String(val);
                    subLbl = `${label} (${pct}%)`;
                    mainColor = color;
                    subColor = color;
                } else {
                    mainVal = String(total);
                    subLbl = 'SUBMISSIONS';
                    mainColor = '#0f172a';
                    subColor = '#64748b';
                }

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                // Main count value
                ctx.font = "800 1.85rem 'Inter', -apple-system, BlinkMacSystemFont, sans-serif";
                ctx.fillStyle = mainColor;
                ctx.fillText(mainVal, cx, cy - 8);

                // Subtitle status label
                ctx.font = "700 0.68rem 'Inter', -apple-system, BlinkMacSystemFont, sans-serif";
                ctx.fillStyle = subColor;
                ctx.fillText(subLbl, cx, cy + 14);

                ctx.restore();
            }
        };

        new Chart(statusDonutCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Under Review', 'For Revision', 'Approved'],
                datasets: [{
                    data: statusCounts,
                    backgroundColor: ['#f59e0b', '#0284c7', '#ea580c', '#15803d'],
                    hoverBackgroundColor: ['#d97706', '#0369a1', '#c2410c', '#166534'],
                    borderWidth: 0,
                    borderRadius: 8,
                    spacing: 6,
                    hoverOffset: 9
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '74%',
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 900
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        titleColor: '#ffffff',
                        titleFont: { family: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif", size: 12, weight: '700' },
                        bodyColor: '#e2e8f0',
                        bodyFont: { family: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif", size: 11.5, weight: '500' },
                        padding: 12,
                        cornerRadius: 10,
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        usePointStyle: true,
                        callbacks: {
                            label: (ctx) => {
                                const total = statusCounts.reduce((sum, count) => sum + count, 0);
                                const count = Number(ctx.raw) || 0;
                                const percent = total ? ((count / total) * 100).toFixed(1) : 0;
                                return `  ${ctx.label}: ${count} submissions (${percent}%)`;
                            }
                        }
                    }
                }
            },
            plugins: [radialCenterTextPlugin]
        });
    }

    // Filter completed projects by type; rows are already restricted to actual Completed statuses.
    const completedProjectFilter = document.getElementById('completedProjectTypeFilter');
    const completedCollegesList = document.getElementById('completedCollegesList');
    const completedTotalBadgeEl = document.getElementById('completedTotalBadge');
    if (completedProjectFilter && completedCollegesList) {
        const completedCollegeRows = <?php echo json_encode($completedColleges, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const renderCompletedColleges = () => {
            const selectedType = completedProjectFilter.value;
            completedCollegesList.replaceChildren();
            let visibleCollegeCount = 0;
            let totalProjectsCount = 0;

            completedCollegeRows.forEach(college => {
                const projects = college.projects.filter(project =>
                    selectedType === 'all' || project.type.toLowerCase() === selectedType
                );
                if (!projects.length) return;
                visibleCollegeCount++;
                totalProjectsCount += projects.length;

                const card = document.createElement('div');
                card.className = 'acad-college-group';
                
                const heading = document.createElement('div');
                heading.className = 'acad-college-header';
                
                const collegeLeft = document.createElement('div');
                collegeLeft.className = 'acad-college-name-wrap';
                const bIcon = document.createElement('i');
                bIcon.className = 'ri-building-line';
                bIcon.style.cssText = 'color:#15803d;font-size:0.90rem;flex-shrink:0;';
                
                const collegeName = document.createElement('h3');
                collegeName.className = 'acad-college-name';
                collegeName.textContent = college.department_name;
                collegeLeft.append(bIcon, collegeName);

                const projectCount = document.createElement('span');
                projectCount.className = 'acad-college-count';
                projectCount.textContent = `${projects.length} completed`;
                heading.append(collegeLeft, projectCount);

                const projectContainer = document.createElement('div');
                projectContainer.className = 'acad-project-list';
                
                projects.forEach(project => {
                    const isResearch = project.type === 'Research';
                    const pill = document.createElement('div');
                    pill.className = 'acad-project-row';
                    pill.role = 'button';
                    pill.tabIndex = 0;
                    pill.title = `Click to view file & details for: ${project.title}`;

                    const leftWrap = document.createElement('div');
                    leftWrap.style.cssText = 'display:flex;align-items:center;gap:8px;min-width:0;flex:1;';

                    const typeBadge = document.createElement('span');
                    typeBadge.className = `acad-project-type ${isResearch ? 'type-research' : 'type-extension'}`;
                    typeBadge.textContent = project.type;

                    const titleSpan = document.createElement('span');
                    titleSpan.className = 'acad-project-title';
                    titleSpan.textContent = project.title;
                    leftWrap.append(typeBadge, titleSpan);

                    const viewFileBtn = document.createElement('button');
                    viewFileBtn.type = 'button';
                    viewFileBtn.className = 'acad-view-file-btn';
                    viewFileBtn.innerHTML = '<i class="ri-file-text-line" style="font-size:0.76rem;"></i><span>View File</span>';

                    pill.append(leftWrap, viewFileBtn);

                    const triggerPreview = (e) => {
                        if (e) e.stopPropagation();
                        if (typeof window.openQuickPreview === 'function') {
                            window.openQuickPreview(project, {
                                submitter_name: project.submitter_name || 'Faculty Proponent',
                                department_name: college.department_name
                            });
                        }
                    };

                    pill.addEventListener('click', triggerPreview);
                    pill.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            triggerPreview(e);
                        }
                    });

                    projectContainer.appendChild(pill);
                });

                card.append(heading, projectContainer);
                completedCollegesList.appendChild(card);
            });

            if (completedTotalBadgeEl) {
                completedTotalBadgeEl.textContent = `${visibleCollegeCount} Active College${visibleCollegeCount === 1 ? '' : 's'} · ${totalProjectsCount} Completed`;
            }

            if (!visibleCollegeCount) {
                const emptyMessage = document.createElement('div');
                emptyMessage.style.cssText = 'padding:30px 10px;text-align:center;color:#64748b;font-size:0.85rem;';
                emptyMessage.innerHTML = '<i class="ri-inbox-line" style="font-size:1.8rem;color:#cbd5e1;display:block;margin-bottom:6px;"></i>No completed projects found';
                completedCollegesList.appendChild(emptyMessage);
            }
        };
        completedProjectFilter.addEventListener('change', renderCompletedColleges);
    }

    // 3. Completion by Stream: Research vs Extension Bar Chart
    const wingCompCanvas = document.getElementById('completionByWingBarChart');
    if (wingCompCanvas) {
        new Chart(wingCompCanvas, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($isFacultyExtensionist ? ['Extension Wing'] : ['Research Wing', 'Extension Wing']); ?>,
                datasets: [
                    {
                        label: 'Completed',
                        data: <?php echo json_encode($isFacultyExtensionist ? [(int) $extensionTotalCompleted] : [(int) $researchTotalCompleted, (int) $extensionTotalCompleted]); ?>,
                        backgroundColor: '#16a34a',
                        borderColor: '#15803d',
                        borderWidth: 1,
                        borderRadius: 8,
                        barPercentage: 0.55
                    },
                    {
                        label: 'In Progress / Ongoing',
                        data: <?php echo json_encode($isFacultyExtensionist ? [(int) $extensionTotalOngoing] : [(int) $researchTotalOngoing, (int) $extensionTotalOngoing]); ?>,
                        backgroundColor: '#f97316',
                        borderColor: '#ea580c',
                        borderWidth: 1,
                        borderRadius: 8,
                        barPercentage: 0.55
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
                                const isResearch = <?php echo $isFacultyExtensionist ? 'false' : 'ctx.dataIndex === 0'; ?>;
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
        const allCollegeRows = <?php echo json_encode($submissionsBreakdown); ?>;
        const programRows = <?php echo json_encode($programAnalyticsRows); ?>;
        const isDeptScopedUser = <?php echo json_encode($isDeptScoped && !empty($userDeptName)); ?>;
        let visibleRows = isDeptScopedUser ? programRows : allCollegeRows;
        let showingAllColleges = !isDeptScopedUser;
        const chartWrap = diffSubCanvas.parentElement;
        const updateChartHeight = () => {
            if (chartWrap) chartWrap.style.height = '350px';
        };
        updateChartHeight(visibleRows);

        const collegeAcronymMap = {
            'College of Computer Studies and Technology': 'CCST',
            'Computer Studies and Technology': 'CCST',
            'College of Teacher Education': 'CTE',
            'Teacher Education': 'CTE',
            'College of Business Administration': 'CBA',
            'Business Administration': 'CBA',
            'College of Arts and Sciences': 'CAS',
            'Arts and Sciences': 'CAS',
            'College of Accountancy': 'COA',
            'Accountancy': 'COA',
            'College of Tourism and Hospitality Management': 'CTHM',
            'Tourism and Hospitality Management': 'CTHM',
            'College of Engineering': 'COE',
            'Engineering': 'COE',
            'College of Nursing and Allied Health Sciences': 'CNAHS',
            'Nursing and Allied Health Sciences': 'CNAHS',
            'College of Human Kinetics': 'CHK',
            'Human Kinetics': 'CHK'
        };

        const getCollegeAcronym = (name) => {
            if (!name) return '';
            const trimmed = name.trim();
            if (collegeAcronymMap[trimmed]) return collegeAcronymMap[trimmed];
            const stripped = trimmed.replace(/^College of\s+/i, '');
            if (collegeAcronymMap[stripped]) return collegeAcronymMap[stripped];
            return stripped;
        };

        const chartLabels = () => showingAllColleges
            ? visibleRows.map(row => getCollegeAcronym(row.department_name))
            : visibleRows.map(row => row.program_name);

        const chartScales = () => {
            const valueScale = {
                stacked: true,
                beginAtZero: true,
                grid: { color: '#f1f5f9', drawBorder: false },
                border: { display: false },
                ticks: { precision: 0, color: '#64748b', stepSize: 1, padding: 7, font: { size: 10.5, weight: 500 } }
            };
            const categoryScale = {
                stacked: true,
                grid: { display: false },
                border: { display: false },
                ticks: {
                    color: '#0f172a',
                    padding: 9,
                    font: { size: 12, weight: 700, family: "'Inter', sans-serif" },
                    maxRotation: 0,
                    minRotation: 0,
                    autoSkip: false
                }
            };
            if (!showingAllColleges) {
                categoryScale.ticks.callback = function(value) {
                    const label = this.getLabelForValue(value);
                    const words = label.split(/\s+/);
                    const programLines = [];
                    let programLine = '';
                    words.forEach(word => {
                        if (programLine && `${programLine} ${word}`.length > 16) {
                            programLines.push(programLine);
                            programLine = word;
                        } else {
                            programLine = programLine ? `${programLine} ${word}` : word;
                        }
                    });
                    if (programLine) programLines.push(programLine);
                    return programLines;
                };
            }
            return { x: categoryScale, y: valueScale };
        };

        const datasetConfig = <?php if ($isFacultyExtensionist): ?>{
            ext_proposals: {
                label: 'Extension Proposals',
                backgroundColor: '#0284c7',
                borderColor: '#0369a1',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            },
            ext_accomplishments: {
                label: 'Accomplishment Reports',
                backgroundColor: '#ea580c',
                borderColor: '#c2410c',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            }
        }<?php else: ?>{
            res_proposals: {
                label: 'Research Proposals',
                backgroundColor: '#15803d',
                borderColor: '#166534',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            },
            res_publications: {
                label: 'Research Publications',
                backgroundColor: '#7c3aed',
                borderColor: '#6d28d9',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            },
            ext_proposals: {
                label: 'Extension Proposals',
                backgroundColor: '#0284c7',
                borderColor: '#0369a1',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            },
            ext_accomplishments: {
                label: 'Accomplishment Reports',
                backgroundColor: '#ea580c',
                borderColor: '#c2410c',
                borderWidth: 1,
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 56,
                barPercentage: 0.7,
                categoryPercentage: 0.8
            }
        }<?php endif; ?>;

        function getProgramDatasets(rows) {
            return Object.entries(datasetConfig).map(([key, dataset]) => ({
                ...dataset,
                data: rows.map(row => Number(row[key]))
            }));
        }

        const deptChart = new Chart(diffSubCanvas, {
            type: 'bar',
            data: {
                labels: chartLabels(),
                datasets: getProgramDatasets(visibleRows)
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'x',
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                layout: { padding: { top: 2, right: 8, bottom: 2 } },
                animation: { duration: 350 },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 9, boxHeight: 9, padding: 10, font: { size: 11, weight: 600 }, color: '#334155' }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        titleColor: '#ffffff',
                        titleFont: { size: 12, weight: '700' },
                        bodyColor: '#e2e8f0',
                        bodyFont: { size: 11, weight: '500' },
                        padding: 12,
                        cornerRadius: 10,
                        borderColor: 'rgba(255, 255, 255, 0.12)',
                        borderWidth: 1,
                        callbacks: {
                            title: items => {
                                if (!items.length) return '';
                                const row = visibleRows[items[0].dataIndex];
                                if (!row) return '';
                                return showingAllColleges
                                    ? `${row.department_name} (${getCollegeAcronym(row.department_name)})`
                                    : `${row.department_name} - ${row.program_name}`;
                            }
                        }
                    }
                },
                scales: chartScales()
            }
        });

        if (filterSelect) {
            filterSelect.addEventListener('change', () => {
                const val = filterSelect.value;
                showingAllColleges = val === 'all';
                visibleRows = showingAllColleges
                    ? allCollegeRows
                    : programRows.filter(row => `college-${row.college_index}` === val);
                updateChartHeight();
                deptChart.data.labels = chartLabels();
                deptChart.data.datasets = getProgramDatasets(visibleRows);
                deptChart.options.indexAxis = 'x';
                deptChart.options.interaction.axis = 'x';
                deptChart.options.scales = chartScales();
                if (titleEl && subtitleEl) {
                    const selectedProgram = visibleRows.length === 1 && val.startsWith('program-') ? visibleRows[0] : null;
                    const selectedCollege = val.startsWith('college-') ? (visibleRows[0]?.department_name ?? null) : null;
                    titleEl.innerHTML = selectedProgram
                        ? `<i class="ri-graduation-cap-line" style="color: #15803d;"></i> ${selectedProgram.program_name} <?php echo $isFacultyExtensionist ? 'Extension Analytics' : 'Submission Analytics'; ?>`
                        : selectedCollege
                            ? `<i class="ri-building-line" style="color: #15803d;"></i> ${selectedCollege} <?php echo $isFacultyExtensionist ? 'Extension Program Analytics' : 'Program Analytics'; ?>`
                            : '<i class="ri-building-line" style="color: #15803d;"></i> <?php echo $isFacultyExtensionist ? 'Extension Submission Analytics by College and Program' : 'Submission Analytics by College and Program'; ?>';
                    subtitleEl.textContent = 'Submission records are grouped by college; college totals repeat for each listed program because records are not tagged by program.';
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
