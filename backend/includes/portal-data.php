<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('h')) {
function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('db')) {
function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $database = getenv('DB_NAME') ?: 'ovpre';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $error) {
        $pdo = false;
    }

    return $pdo;
}
}

function currentUser($role = 'student') {
    return $_SESSION['user'] ?? [
        'id' => $role === 'admin' ? 1 : 18,
        'role' => $role,
        'name' => $role === 'admin' ? 'Administrator' : 'Maria Santos',
        'email' => $role === 'admin' ? 'admin@college.edu' : 'maria.santos@college.edu',
        'student_id' => '2026-0148',
        'department' => 'CCS',
        'program' => 'BS Information Technology',
        'year' => '3rd Year',
        'section' => 'BSIT 3A',
        'initials' => $role === 'admin' ? 'AD' : 'MS',
    ];
}

if (!function_exists('statusClass')) {
function statusClass($status) {
    return strtolower(str_replace([' ', '/'], '-', $status));
}
}

if (!function_exists('renderModuleHeader')) {
    function renderModuleHeader($title, $subtitle, $icon = 'ri-dashboard-line') {
?>
        <section class="module-header">
            <div class="module-icon"><i class="<?php echo h($icon); ?>"></i></div>
            <div>
                <p>College Document Management System</p>
                <h2><?php echo h($title); ?></h2>
                <span><?php echo h($subtitle); ?></span>
            </div>
        </section>
<?php
    }
}

if (!function_exists('renderStatusPill')) {
    function renderStatusPill($status) {
        echo '<span class="status-pill status-' . h(statusClass($status)) . '">' . h($status) . '</span>';
    }
}

function sampleDocuments() {
    return [
        ['tracking' => 'DMS-2026-1001', 'title' => 'Smart Queue Proposal', 'type' => 'Proposal', 'department' => 'CCS', 'student' => 'Maria Santos', 'date' => 'June 04, 2026', 'status' => 'Pending', 'remarks' => 'Awaiting adviser review', 'file' => '#'],
        ['tracking' => 'DMS-2026-1002', 'title' => 'Community Outreach Report', 'type' => 'Accomplishment Report', 'department' => 'CTE', 'student' => 'Ella Garcia', 'date' => 'June 03, 2026', 'status' => 'Under Review', 'remarks' => 'Checking attachments', 'file' => '#'],
        ['tracking' => 'DMS-2026-1003', 'title' => 'Business Plan Feasibility Study', 'type' => 'Proposal', 'department' => 'CBA', 'student' => 'Marco Santos', 'date' => 'June 01, 2026', 'status' => 'Approved', 'remarks' => 'Approved for final defense', 'file' => '#'],
        ['tracking' => 'DMS-2026-1004', 'title' => 'Engineering Lab Safety Report', 'type' => 'Accomplishment Report', 'department' => 'COE', 'student' => 'Carlo Lim', 'date' => 'May 30, 2026', 'status' => 'Rejected', 'remarks' => 'Revise missing signatures', 'file' => '#'],
        ['tracking' => 'DMS-2026-1005', 'title' => 'Scholarship Renewal Packet', 'type' => 'Registrar', 'department' => 'CAS', 'student' => 'Leah Reyes', 'date' => 'May 28, 2026', 'status' => 'Approved', 'remarks' => 'Released to registrar', 'file' => '#'],
    ];
}

function sampleAnnouncements() {
    return [
        ['title' => 'Final Document Submission Guidelines Released', 'category' => 'Academic', 'date' => 'June 04, 2026', 'author' => 'Academic Affairs', 'body' => 'Students must follow the updated naming, formatting, and adviser approval requirements for final submissions.'],
        ['title' => 'Research Proposal Defense Schedule', 'category' => 'Research', 'date' => 'June 03, 2026', 'author' => 'Research Office', 'body' => 'Departments will release panel assignments and defense rooms this week.'],
        ['title' => 'Scholarship Renewal Applications', 'category' => 'Scholarship', 'date' => 'May 30, 2026', 'author' => 'Scholarship Committee', 'body' => 'Renewal forms and grade reports must be uploaded before the deadline.'],
    ];
}

function sampleNotifications() {
    return [
        ['title' => 'Document moved to review', 'type' => 'Status', 'date' => 'June 04, 2026', 'read' => false, 'body' => 'Your proposal is now under department review.'],
        ['title' => 'New announcement posted', 'type' => 'Announcement', 'date' => 'June 03, 2026', 'read' => false, 'body' => 'Research defense schedules are now available.'],
        ['title' => 'Profile update confirmed', 'type' => 'Security', 'date' => 'June 01, 2026', 'read' => true, 'body' => 'Your account profile was updated successfully.'],
    ];
}

function sampleUsers() {
    return [
        ['id' => '2026-0148', 'name' => 'Maria Santos', 'email' => 'maria.santos@college.edu', 'department' => 'CCS', 'section' => 'BSIT 3A', 'role' => 'Student', 'status' => 'Active'],
        ['id' => '2026-0192', 'name' => 'Marco Santos', 'email' => 'marco.santos@college.edu', 'department' => 'CBA', 'section' => 'BSBA 2B', 'role' => 'Student', 'status' => 'Active'],
        ['id' => '2026-0224', 'name' => 'Ella Garcia', 'email' => 'ella.garcia@college.edu', 'department' => 'CTE', 'section' => 'BSED 2A', 'role' => 'Student', 'status' => 'Locked'],
        ['id' => 'ADM-001', 'name' => 'Administrator', 'email' => 'admin@college.edu', 'department' => 'Registrar', 'section' => 'Office', 'role' => 'Admin', 'status' => 'Active'],
    ];
}

function sampleLogs() {
    return [
        ['date' => 'June 04, 2026 09:12 AM', 'actor' => 'Maria Santos', 'action' => 'Uploaded Smart Queue Proposal', 'module' => 'Documents'],
        ['date' => 'June 04, 2026 10:45 AM', 'actor' => 'Administrator', 'action' => 'Marked document as Under Review', 'module' => 'Workflow'],
        ['date' => 'June 03, 2026 02:30 PM', 'actor' => 'Research Office', 'action' => 'Posted announcement', 'module' => 'Announcements'],
    ];
}
?>
