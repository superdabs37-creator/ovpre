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
$submissionStatusRows = db()->query("
    SELECT status FROM research_projects
    UNION ALL
    SELECT status FROM extension_projects
")->fetchAll(PDO::FETCH_COLUMN);
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
$completedProjectRows = db()->query("
    SELECT
        dep.id AS department_id,
        dep.department_name,
        'Research' AS project_type,
        r.id AS project_id,
        r.title AS project_title
    FROM research_projects r
    JOIN users u ON u.id = r.submitted_by
    JOIN departments dep ON dep.id = u.department_id
    WHERE r.workflow_stage = 'Completed'

    UNION ALL

    SELECT
        dep.id AS department_id,
        dep.department_name,
        'Extension' AS project_type,
        e.id AS project_id,
        e.project_title AS project_title
    FROM extension_projects e
    JOIN users u ON u.id = e.submitted_by
    JOIN departments dep ON dep.id = u.department_id
    WHERE e.progress_status = 'Completed'
    ORDER BY department_name ASC, project_type ASC, project_title ASC
")->fetchAll(PDO::FETCH_ASSOC);
$completedColleges = [];
foreach ($completedProjectRows as $completedProjectRow) {
    $departmentId = (int) $completedProjectRow['department_id'];
    if (!isset($completedColleges[$departmentId])) {
        $completedColleges[$departmentId] = [
            'department_name' => (string) $completedProjectRow['department_name'],
            'projects' => [],
        ];
    }
    $completedColleges[$departmentId]['projects'][] = [
        'id' => (int) $completedProjectRow['project_id'],
        'type' => (string) $completedProjectRow['project_type'],
        'title' => (string) $completedProjectRow['project_title'],
    ];
}
$completedColleges = array_values($completedColleges);

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
usort($submissionPerformance, static function (array $a, array $b): int {
    $totalCompare = ($b['research_count'] + $b['extension_count']) <=> ($a['research_count'] + $a['extension_count']);
    return $totalCompare !== 0 ? $totalCompare : strcasecmp($a['submitter_name'], $b['submitter_name']);
});

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
</section>

<!-- =========================================================
     ANALYTICAL KPI SUMMARY CAPSULES
========================================================= -->
<section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 24px;">
    <!-- Records -->
    <div class="panel kpi-solid-card" style="margin: 0; padding: 18px 20px; border-radius: 16px; border: 0; background: #15803d; color: #ffffff; box-shadow: 0 8px 22px rgba(21, 128, 61, 0.24); transition: transform 0.2s ease, box-shadow 0.2s ease;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.9);">Academic Records</span>
            <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.2); display: inline-grid; place-items: center;">
                <i class="ri-file-text-line" style="font-size: 1.18rem; color: #ffffff;"></i>
            </span>
        </div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 4px;"><?php echo $totalDocuments; ?></div>
        <small style="color: rgba(255, 255, 255, 0.82); font-size: 0.8rem;">Cataloged in repository</small>
    </div>

    <!-- Research Proposals -->
    <div class="panel kpi-solid-card" style="margin: 0; padding: 18px 20px; border-radius: 16px; border: 0; background: #0284c7; color: #ffffff; box-shadow: 0 8px 22px rgba(2, 132, 199, 0.24); transition: transform 0.2s ease, box-shadow 0.2s ease;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.9);">Research Proposals</span>
            <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.2); display: inline-grid; place-items: center;">
                <i class="ri-flask-line" style="font-size: 1.18rem; color: #ffffff;"></i>
            </span>
        </div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 4px;"><?php echo $totalResearch; ?></div>
        <small style="color: rgba(255, 255, 255, 0.82); font-size: 0.8rem;">Faculty submissions</small>
    </div>

    <!-- Extension Projects -->
    <div class="panel kpi-solid-card" style="margin: 0; padding: 18px 20px; border-radius: 16px; border: 0; background: #0d9488; color: #ffffff; box-shadow: 0 8px 22px rgba(13, 148, 136, 0.24); transition: transform 0.2s ease, box-shadow 0.2s ease;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.9);">Extension Initiatives</span>
            <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.2); display: inline-grid; place-items: center;">
                <i class="ri-community-line" style="font-size: 1.18rem; color: #ffffff;"></i>
            </span>
        </div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 4px;"><?php echo $totalExtension; ?></div>
        <small style="color: rgba(255, 255, 255, 0.82); font-size: 0.8rem;">Community outreach</small>
    </div>

    <!-- Colleges Participation -->
    <div class="panel kpi-solid-card" style="margin: 0; padding: 18px 20px; border-radius: 16px; border: 0; background: #7c3aed; color: #ffffff; box-shadow: 0 8px 22px rgba(124, 58, 237, 0.24); transition: transform 0.2s ease, box-shadow 0.2s ease;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.9);">Colleges Active</span>
            <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.2); display: inline-grid; place-items: center;">
                <i class="ri-government-line" style="font-size: 1.18rem; color: #ffffff;"></i>
            </span>
        </div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 4px;"><?php echo $activeCollegesCount; ?> <span style="font-size: 1rem; font-weight: 600; opacity: 0.88;">/ <?php echo $totalColleges; ?></span></div>
        <small style="color: rgba(255, 255, 255, 0.82); font-size: 0.8rem;">Colleges with submissions</small>
    </div>

    <!-- Active Faculty -->
    <div class="panel kpi-solid-card" style="margin: 0; padding: 18px 20px; border-radius: 16px; border: 0; background: #d97706; color: #ffffff; box-shadow: 0 8px 22px rgba(217, 119, 6, 0.24); transition: transform 0.2s ease, box-shadow 0.2s ease;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.9);">Registered Users</span>
            <span style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.2); display: inline-grid; place-items: center;">
                <i class="ri-team-line" style="font-size: 1.18rem; color: #ffffff;"></i>
            </span>
        </div>
        <div style="font-size: 1.7rem; font-weight: 800; color: #ffffff; line-height: 1.1; margin-bottom: 4px;"><?php echo $activeUsers; ?> <span style="font-size: 1rem; font-weight: 600; opacity: 0.88;">/ <?php echo $totalUsers; ?></span></div>
        <small style="color: rgba(255, 255, 255, 0.82); font-size: 0.8rem;">Active accounts</small>
    </div>
</section>

<!-- =========================================================
     TOP SPOTLIGHT: SUBMISSIONS BY ACADEMIC DEPARTMENT (HIGHLIGHTED HERO)
========================================================= -->
<article class="panel chart-panel featured-spotlight-card" style="margin-bottom: 24px; border: 1px solid #dbe7df; border-radius: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06); background: #ffffff; padding: 22px 24px;">
    <div class="panel-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px; margin-bottom: 18px;">
        <div>
            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; background: #dcfce7; border: 1px solid #86efac; border-radius: 999px; color: #15803d; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                <i class="ri-sparkling-fill"></i> Primary University Benchmark
            </div>
            <h2 id="deptChartTitle" style="color: #166534; font-size: clamp(1.15rem, 2vw, 1.45rem); font-weight: 800; display: flex; align-items: center; gap: 9px; margin: 0;">
                <i class="ri-building-line" style="color: #15803d; font-size: 1.45rem;"></i> Submission Analytics by College and Program
            </h2>
            <span id="deptChartSubtitle" style="font-size: 0.84rem; color: #64748b; font-weight: 500; display: block; margin-top: 5px;">Programs are grouped under their college; college submission totals repeat for each listed program</span>
        </div>
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <label for="submissionKindFilter" style="font-size: 0.8rem; font-weight: 700; color: #475569;">College:</label>
                <select id="submissionKindFilter" class="filter-select compact-select" style="font-size: 0.82rem; font-weight: 600; padding: 8px 12px; border-radius: 9px; border: 1px solid #cbd5e1; background: #ffffff; color: #1e293b; cursor: pointer;">
                    <option value="all">All College Departments</option>
                    <?php $selectCollegeIndex = 0; foreach ($programsByCollege as $collegeName => $programNames): ?>
                        <option value="college-<?php echo $selectCollegeIndex++; ?>"><?php echo h($collegeName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <span class="badge" style="background: #f1f5f9; color: #334155; font-weight: 700; font-size: 0.82rem; padding: 8px 12px; border-radius: 999px;">
                <?php echo $overallSubmissionsCount; ?> Total Submissions
            </span>
        </div>
    </div>
    <div style="position: relative; height: 660px; width: 100%; padding: 12px 14px 10px 4px; border: 1px solid #eef2f7; border-radius: 12px; background: #fcfdfc;">
        <canvas id="differentiatedSubmissionsChart" role="img" aria-label="Horizontal bar chart of submissions by college and program"></canvas>
    </div>
    <section aria-labelledby="programsByCollegeHeading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
        <div>
            <h3 id="programsByCollegeHeading" style="margin: 0 0 3px; color: #0f172a; font-size: 1.05rem; font-weight: 800;">Programs by College</h3>
            <span style="font-size: 0.78rem; color: #64748b;">Programs are sorted under their college; majors are excluded</span>
        </div>
        <button type="button" id="openProgramsModal" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border: 0; border-radius: 9px; background: #15803d; color: #ffffff; font-weight: 700; cursor: pointer;">
            <i class="ri-list-check-3"></i> View Programs
        </button>
    </section>

    <dialog id="programsByCollegeModal" aria-labelledby="programsModalTitle" style="width: min(900px, calc(100% - 32px)); max-width: 900px; max-height: 85vh; padding: 0; border: 0; border-radius: 16px; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.3); padding: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 18px 22px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
            <div>
                <h3 id="programsModalTitle" style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 800;">Programs by College</h3>
                <p style="margin: 4px 0 0; color: #64748b; font-size: 0.82rem;">Each program is listed under its appropriate college. Majors are not included.</p>
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
            if (!modal || !openButton || !closeButton) return;
            openButton.addEventListener('click', () => modal.showModal());
            closeButton.addEventListener('click', () => modal.close());
            modal.addEventListener('click', event => {
                if (event.target === modal) modal.close();
            });
        })();
    </script>
</article>



<article class="panel chart-panel" style="margin: 0 0 24px; border: 1px solid #dbe7df; border-radius: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); background: #ffffff; padding: 20px 22px;">
    <div class="panel-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 12px;">
        <div>
            <h2 style="margin: 0; color: #166534; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <i class="ri-line-chart-line" style="font-size: 1.4rem;"></i> Monthly Submission Trends
            </h2>
            <span style="display: block; margin-top: 4px; color: #64748b; font-size: 0.85rem;">Research proposals, extension programs, and accomplishment reports submitted each month</span>
        </div>
        <span class="badge" style="background: #f0fdf4; color: #166534; padding: 6px 11px; border-radius: 999px; font-size: 0.78rem; font-weight: 700;">Last 12 months</span>
    </div>
    <div style="position: relative; width: 100%; height: 330px;">
        <canvas id="monthlySubmissionTrendChart" role="img" aria-label="Line chart showing monthly research proposals, extension programs, and accomplishment reports"></canvas>
    </div>
</article>

<!-- =========================================================
     ROW 2: SUBMISSION COMPLETION INTELLIGENCE (COMPLETED VS. IN-PROGRESS)
========================================================= -->
<div style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <i class="ri-pie-chart-2-line" style="color: #15803d; font-size: 1.3rem;"></i> Submission Status Distribution
        </h3>
        <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Distribution of pending, review, revision, and approved research and extension submissions</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px;">
        
        <!-- Card 1: Research and extension status distribution -->
        <article class="panel chart-panel" style="order: 2; margin: 0; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #ffffff;">
            <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px; margin: 0;">
                        <i class="ri-pie-chart-2-line" style="color: #15803d;"></i> Project Status Breakdown
                    </h2>
                    <span style="font-size: 0.8rem; color: #64748b;">Research and extension submissions by current review status</span>
                </div>
                <span class="badge" style="background: #f0fdf4; color: #166534; font-weight: 800; font-size: 0.78rem; padding: 4px 10px; border-radius: 999px;">
                    <?php echo $trackedSubmissionStatusTotal; ?> Tracked Submissions
                </span>
            </div>
            <div style="position: relative; height: 270px; width: 100%; max-width: 520px; margin: 0 auto;">
                <canvas id="submissionStatusDonutChart" aria-label="Donut chart of pending, under review, for revision, and approved submissions"></canvas>
            </div>
        </article>

        <!-- Card 2: Colleges with actually completed projects -->
        <article class="panel chart-panel" style="order: 1; margin: 0; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #ffffff;">
            <div class="panel-heading" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 10px;">
                <div>
                    <h2 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px; margin: 0;">
                        <i class="ri-checkbox-circle-line" style="color: #15803d;"></i> Completed Colleges
                    </h2>
                    <span style="font-size: 0.8rem; color: #64748b;">Colleges with research or extension projects marked Completed</span>
                </div>
                <label style="display: flex; align-items: center; gap: 7px; color: #475569; font-size: 0.78rem; font-weight: 700;">
                    Project Type
                    <select id="completedProjectTypeFilter" class="filter-select compact-select" style="padding: 6px 9px; border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; color: #1e293b; font-size: 0.78rem;">
                        <option value="all">All Projects</option>
                        <option value="research">Research</option>
                        <option value="extension">Extension</option>
                    </select>
                </label>
            </div>
            <div id="completedCollegesList" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; padding-top: 12px;">
                <?php if (empty($completedColleges)): ?>
                    <p style="grid-column: 1 / -1; margin: 8px 0; color: #64748b; text-align: center;">No completed projects found</p>
                <?php else: ?>
                    <?php foreach ($completedColleges as $completedCollege): ?>
                        <section style="padding: 11px 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 7px;">
                                <h3 style="margin: 0; color: #166534; font-size: 0.86rem; font-weight: 800;"><?php echo h($completedCollege['department_name']); ?></h3>
                                <span style="white-space: nowrap; color: #166534; font-size: 0.72rem; font-weight: 700;"><?php echo count($completedCollege['projects']); ?> completed</span>
                            </div>
                            <ul style="margin: 0; padding-left: 18px; color: #334155; font-size: 0.78rem; line-height: 1.6;">
                                <?php foreach ($completedCollege['projects'] as $completedProject): ?>
                                    <?php
                                        $isResearch = ($completedProject['type'] ?? '') === 'Research';
                                        $targetUrl = $isResearch
                                            ? 'research.php#proposal-' . (int) ($completedProject['id'] ?? 0)
                                            : 'extension.php#project-' . (int) ($completedProject['id'] ?? 0);
                                    ?>
                                    <li style="margin-bottom: 4px;">
                                        <span style="color: <?php echo $isResearch ? '#15803d' : '#7c3aed'; ?>; font-weight: 700;"><?php echo h($completedProject['type']); ?>:</span>
                                        <a href="<?php echo h($targetUrl); ?>" style="color: #1e293b; text-decoration: none; font-weight: 500; transition: color 0.15s;" onmouseover="this.style.color='#15803d';this.style.textDecoration='underline';" onmouseout="this.style.color='#1e293b';this.style.textDecoration='none';" title="View completed work">
                                            <?php echo h($completedProject['title']); ?>
                                            <i class="ri-external-link-line" style="font-size: 0.72rem; color: #94a3b8; vertical-align: middle; margin-left: 2px;"></i>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>

        <!-- Card 3: Research vs Extension Breakdown -->
        <article class="panel chart-panel" style="order: 3; margin: 0; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #ffffff;">
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
        </article>

    </div>
</div>

<section class="panel" style="margin-bottom: 24px;">
    <div class="panel-heading">
        <div>
            <h2><i class="ri-team-line"></i> Submission Performance by Person</h2>
            <small style="color: #64748b;">Research and extension submissions grouped by submitter, with approved totals.</small>
        </div>
    </div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>Submitter</th>
                    <th>College</th>
                    <th style="text-align: center;">Research Submissions</th>
                    <th style="text-align: center;">Extension Submissions</th>
                    <th style="text-align: center;">Approved</th>
                    <th style="text-align: center;">All Submissions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($submissionPerformance)): ?>
                    <tr><td colspan="6" style="text-align: center; color: #64748b;">No submissions found.</td></tr>
                <?php else: ?>
                    <?php foreach ($submissionPerformance as $person): ?>
                        <tr>
                            <td><strong><?php echo h($person['submitter_name']); ?></strong></td>
                            <td><?php echo h($person['department_name'] !== '' ? $person['department_name'] : 'Unassigned'); ?></td>
                            <td style="text-align: center;"><span class="badge" style="background: #dcfce7; color: #166534;"><?php echo (int) $person['research_count']; ?></span></td>
                            <td style="text-align: center;"><span class="badge" style="background: #e0f2fe; color: #0369a1;"><?php echo (int) $person['extension_count']; ?></span></td>
                            <td style="text-align: center;"><span class="badge" style="background: #f3e8ff; color: #6b21a8;"><?php echo (int) $person['approved_count']; ?></span></td>
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
        const targetUrl = isResearch ? `research.php#proposal-${submission.id}` : `extension.php#project-${submission.id}`;

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
        document.getElementById('quickPreviewDesc').textContent = submission.description ? submission.description : 'No additional description provided.';

        const downloadBtn = document.getElementById('quickPreviewDownloadBtn');
        if (submission.document_url) {
            downloadBtn.href = submission.document_url;
            downloadBtn.style.display = 'inline-flex';
        } else {
            downloadBtn.style.display = 'none';
        }

        const fullPageBtn = document.getElementById('quickPreviewFullPageBtn');
        fullPageBtn.href = targetUrl;
        fullPageBtn.querySelector('span').textContent = isResearch ? 'Open in Research Pipeline' : 'Open in Extension Pipeline';

        previewDialog.showModal();
    }

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
                    const targetUrl = isResearch ? `research.php#proposal-${submission.id}` : `extension.php#project-${submission.id}`;

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
            <h2><i class="ri-table-line"></i> Tactical Colleges Submissions Matrix</h2>
            <small style="color: #64748b;">Granular breakdown differentiating Research Proposals, Publications, Extension Proposals, and Accomplishment Reports per college.</small>
        </div>
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

    // Monthly submission trends: proposals, extension programs, and reports.
    const monthlyTrendCanvas = document.getElementById('monthlySubmissionTrendChart');
    if (monthlyTrendCanvas) {
        const monthlyTrend = <?php echo json_encode($monthlySubmissionTrend); ?>;
        new Chart(monthlyTrendCanvas, {
            type: 'line',
            data: {
                labels: monthlyTrend.labels,
                datasets: [
                    {
                        label: 'Research Proposals',
                        data: monthlyTrend.research,
                        borderColor: '#15803d',
                        backgroundColor: 'rgba(21, 128, 61, 0.12)',
                        pointBackgroundColor: '#15803d',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 3,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Extension Programs',
                        data: monthlyTrend.extension,
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.10)',
                        pointBackgroundColor: '#0284c7',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 3,
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: 'Accomplishment Reports',
                        data: monthlyTrend.accomplishments,
                        borderColor: '#ea580c',
                        backgroundColor: 'rgba(234, 88, 12, 0.10)',
                        pointBackgroundColor: '#ea580c',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 3,
                        tension: 0.3,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 18, color: '#334155', font: { size: 11, weight: 600 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: '#64748b', maxRotation: 0, minRotation: 0, font: { size: 10, weight: 600 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2f7' },
                        border: { display: false },
                        ticks: { precision: 0, stepSize: 1, color: '#64748b', font: { size: 10 } }
                    }
                }
            }
        });
    }

    // Submission status distribution across research and extension projects.
    const statusDonutCanvas = document.getElementById('submissionStatusDonutChart');
    if (statusDonutCanvas) {
        const statusCounts = <?php echo json_encode(array_values($submissionStatusCounts)); ?>;
        new Chart(statusDonutCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Under Review', 'For Revision', 'Approved'],
                datasets: [{
                    data: statusCounts,
                    backgroundColor: ['#f59e0b', '#0284c7', '#ea580c', '#15803d'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 9, padding: 14, color: '#334155', font: { size: 11, weight: 600 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const total = statusCounts.reduce((sum, count) => sum + count, 0);
                                const count = Number(ctx.raw) || 0;
                                const percent = total ? Math.round((count / total) * 100) : 0;
                                return ` ${ctx.label}: ${count} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Filter completed projects by type; rows are already restricted to actual Completed statuses.
    const completedProjectFilter = document.getElementById('completedProjectTypeFilter');
    const completedCollegesList = document.getElementById('completedCollegesList');
    if (completedProjectFilter && completedCollegesList) {
        const completedCollegeRows = <?php echo json_encode($completedColleges, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const renderCompletedColleges = () => {
            const selectedType = completedProjectFilter.value;
            completedCollegesList.replaceChildren();
            let visibleCollegeCount = 0;

            completedCollegeRows.forEach(college => {
                const projects = college.projects.filter(project =>
                    selectedType === 'all' || project.type.toLowerCase() === selectedType
                );
                if (!projects.length) return;
                visibleCollegeCount++;

                const card = document.createElement('section');
                card.style.cssText = 'padding:11px 12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;';
                const heading = document.createElement('div');
                heading.style.cssText = 'display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:7px;';
                const collegeName = document.createElement('h3');
                collegeName.textContent = college.department_name;
                collegeName.style.cssText = 'margin:0;color:#166534;font-size:.86rem;font-weight:800;';
                const projectCount = document.createElement('span');
                projectCount.textContent = `${projects.length} completed`;
                projectCount.style.cssText = 'white-space:nowrap;color:#166534;font-size:.72rem;font-weight:700;';
                heading.append(collegeName, projectCount);

                const projectList = document.createElement('ul');
                projectList.style.cssText = 'margin:0;padding-left:18px;color:#334155;font-size:.78rem;line-height:1.5;';
                projects.forEach(project => {
                    const item = document.createElement('li');
                    item.style.cssText = 'margin-bottom:4px;';
                    const isResearch = project.type === 'Research';
                    const typeLabel = document.createElement('span');
                    typeLabel.textContent = `${project.type}: `;
                    typeLabel.style.cssText = `color:${isResearch ? '#15803d' : '#7c3aed'};font-weight:700;`;

                    const link = document.createElement('a');
                    link.href = isResearch ? `research.php#proposal-${project.id}` : `extension.php#project-${project.id}`;
                    link.title = 'View completed work';
                    link.style.cssText = 'color:#1e293b;text-decoration:none;font-weight:500;transition:color 0.15s;';
                    link.onmouseover = () => { link.style.color = '#15803d'; link.style.textDecoration = 'underline'; };
                    link.onmouseout = () => { link.style.color = '#1e293b'; link.style.textDecoration = 'none'; };

                    const titleText = document.createTextNode(project.title + ' ');
                    const icon = document.createElement('i');
                    icon.className = 'ri-external-link-line';
                    icon.style.cssText = 'font-size:0.72rem;color:#94a3b8;vertical-align:middle;margin-left:2px;';

                    link.append(titleText, icon);
                    item.append(typeLabel, link);
                    projectList.appendChild(item);
                });
                card.append(heading, projectList);
                completedCollegesList.appendChild(card);
            });

            if (!visibleCollegeCount) {
                const emptyMessage = document.createElement('p');
                emptyMessage.textContent = 'No completed projects found';
                emptyMessage.style.cssText = 'grid-column:1/-1;margin:8px 0;color:#64748b;text-align:center;';
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
        const allCollegeRows = <?php echo json_encode($submissionsBreakdown); ?>;
        const programRows = <?php echo json_encode($programAnalyticsRows); ?>;
        let visibleRows = allCollegeRows;
        let showingAllColleges = true;
        const chartWrap = diffSubCanvas.parentElement;
        const updateChartHeight = () => {
            if (chartWrap) chartWrap.style.height = '370px';
        };
        updateChartHeight(visibleRows);
        const chartLabels = () => showingAllColleges
            ? visibleRows.map(row => row.department_name)
            : visibleRows.map(row => row.program_name);
        const chartScales = () => {
            const valueScale = {
                beginAtZero: true,
                grid: { color: '#e9eef3', drawBorder: false },
                border: { display: false },
                ticks: { precision: 0, color: '#64748b', stepSize: 1, padding: 7, font: { size: 10, weight: 500 } }
            };
            const categoryScale = {
                grid: { display: false },
                border: { display: false },
                ticks: { color: '#1e293b', padding: 9, font: { size: 11, weight: 700 } }
            };
            categoryScale.ticks.callback = function(value) {
                const label = this.getLabelForValue(value);
                if (!showingAllColleges) {
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
                }
                const words = label.replace(/^College of\s*/i, '').split(/\s+/);
                const lines = ['College of'];
                let line = '';
                words.forEach(word => {
                    if (line && `${line} ${word}`.length > 17) {
                        lines.push(line);
                        line = word;
                    } else {
                        line = line ? `${line} ${word}` : word;
                    }
                });
                if (line) lines.push(line);
                return lines;
            };
            if (showingAllColleges) {
                categoryScale.ticks.maxRotation = 0;
                categoryScale.ticks.minRotation = 0;
                categoryScale.ticks.autoSkip = false;
                categoryScale.ticks.padding = 10;
                categoryScale.ticks.font = { size: 9, weight: 700 };
                return { x: categoryScale, y: valueScale };
            }
            return { x: categoryScale, y: valueScale };
        };

        const datasetConfig = {
            res_proposals: {
                label: 'Research Proposals',
                backgroundColor: '#15803d',
                borderColor: '#166534',
                borderWidth: 1,
                borderRadius: 5,
                borderSkipped: false,
                maxBarThickness: 20
            },
            res_publications: {
                label: 'Research Publications & Reports',
                backgroundColor: '#7c3aed',
                borderColor: '#6d28d9',
                borderWidth: 1,
                borderRadius: 5,
                borderSkipped: false,
                maxBarThickness: 20
            },
            ext_proposals: {
                label: 'Extension Proposals',
                backgroundColor: '#0284c7',
                borderColor: '#0369a1',
                borderWidth: 1,
                borderRadius: 5,
                borderSkipped: false,
                maxBarThickness: 20
            },
            ext_accomplishments: {
                label: 'Accomplishment Reports',
                backgroundColor: '#ea580c',
                borderColor: '#c2410c',
                borderWidth: 1,
                borderRadius: 5,
                borderSkipped: false,
                maxBarThickness: 20
            }
        };

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
                        titleFont: { size: 12, weight: '700' },
                        bodyFont: { size: 11 },
                        padding: 11,
                        cornerRadius: 8,
                        callbacks: {
                            title: items => {
                                const row = visibleRows[items[0].dataIndex];
                                return showingAllColleges
                                    ? row.department_name
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
                        ? `<i class="ri-graduation-cap-line" style="color: #15803d;"></i> ${selectedProgram.program_name} Submission Analytics`
                        : selectedCollege
                            ? `<i class="ri-building-line" style="color: #15803d;"></i> ${selectedCollege} Program Analytics`
                            : '<i class="ri-building-line" style="color: #15803d;"></i> Submission Analytics by College and Program';
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
