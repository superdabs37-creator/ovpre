<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$query = trim((string) ($_GET['q'] ?? ''));
$query = mb_substr($query, 0, 100);
$results = [];
$like = '%' . $query . '%';
$role = (string) $user['role'];
$dashboard = role_dashboard_path($role) ?? 'dashboard.php';
$portalBase = dirname($dashboard);
$portalBase = $portalBase === '.' ? '' : $portalBase . '/';

if ($query !== '') {
    if (role_can($role, 'research')) {
        $scope = '';
        $scopeParams = [];
        if ($role === ROLE_RESEARCHER) { $scope = ' AND r.submitted_by = ?'; $scopeParams[] = (int) $user['id']; }
        elseif ($role === ROLE_RESEARCH_COORDINATOR) { $scope = !empty($user['department_id']) ? ' AND owner.department_id = ?' : ' AND r.submitted_by = ?'; $scopeParams[] = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id']; }
        $stmt = db()->prepare('SELECT r.id, r.title AS label, r.status, r.created_at FROM research_projects r JOIN users owner ON owner.id = r.submitted_by WHERE (r.title LIKE ? OR r.researchers LIKE ? OR r.abstract LIKE ?)' . $scope . ' ORDER BY r.created_at DESC LIMIT 10');
        $stmt->execute([$like, $like, $like, ...$scopeParams]);
        $researchUrl = in_array($role, [ROLE_RESEARCH_COORDINATOR, ROLE_OVPRE_ADMIN], true) ? $portalBase . 'research.php' : 'research.php';
        foreach ($stmt->fetchAll() as $row) $results[] = ['type' => 'Research proposal', 'label' => $row['label'], 'detail' => $row['status'], 'date' => $row['created_at'], 'url' => $researchUrl];
    }
    if (role_can($role, 'extension')) {
        $scope = '';
        $scopeParams = [];
        if ($role === ROLE_FACULTY_EXTENSIONIST) { $scope = ' AND e.submitted_by = ?'; $scopeParams[] = (int) $user['id']; }
        elseif ($role === ROLE_EXTENSION_COORDINATOR) { $scope = !empty($user['department_id']) ? ' AND owner.department_id = ?' : ' AND e.submitted_by = ?'; $scopeParams[] = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id']; }
        $stmt = db()->prepare('SELECT e.id, e.project_title AS label, e.progress_status AS status, e.created_at FROM extension_projects e JOIN users owner ON owner.id = e.submitted_by WHERE (e.project_title LIKE ? OR e.coordinator LIKE ? OR e.community_partner LIKE ?)' . $scope . ' ORDER BY e.created_at DESC LIMIT 10');
        $stmt->execute([$like, $like, $like, ...$scopeParams]);
        $extensionUrl = in_array($role, [ROLE_EXTENSION_COORDINATOR, ROLE_FACULTY_EXTENSIONIST, ROLE_OVPRE_ADMIN], true) ? $portalBase . 'extension.php' : 'extension.php';
        foreach ($stmt->fetchAll() as $row) $results[] = ['type' => 'Extension project', 'label' => $row['label'], 'detail' => $row['status'], 'date' => $row['created_at'], 'url' => $extensionUrl];
    }
    if (role_can($role, 'documents')) {
        $scope = '';
        $scopeParams = [];
        if (in_array($role, [ROLE_RESEARCHER, ROLE_FACULTY_EXTENSIONIST], true)) { $scope = ' AND d.uploaded_by = ?'; $scopeParams[] = (int) $user['id']; }
        elseif (in_array($role, [ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR], true)) { $scope = !empty($user['department_id']) ? ' AND d.department_id = ?' : ' AND d.uploaded_by = ?'; $scopeParams[] = !empty($user['department_id']) ? (int) $user['department_id'] : (int) $user['id']; }
        $stmt = db()->prepare('SELECT d.id, d.title AS label, d.status, d.created_at FROM documents d WHERE d.archived_at IS NULL AND (d.title LIKE ? OR d.author LIKE ? OR d.description LIKE ? OR d.original_filename LIKE ?)' . $scope . ' ORDER BY d.created_at DESC LIMIT 10');
        $stmt->execute([$like, $like, $like, $like, ...$scopeParams]);
        $recordsUrl = in_array($role, [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN], true) ? 'admin-documents.php' : $portalBase . 'status.php';
        foreach ($stmt->fetchAll() as $row) $results[] = ['type' => 'Record', 'label' => $row['label'], 'detail' => $row['status'], 'date' => $row['created_at'], 'url' => $recordsUrl];
    }
    if (role_can($role, 'announcements')) {
        $audience = match ($role) {
            ROLE_RESEARCH_COORDINATOR => ' AND audience IN ("All", "Researchers")',
            ROLE_EXTENSION_COORDINATOR => ' AND audience IN ("All", "Staff")',
            ROLE_FACULTY_EXTENSIONIST => ' AND audience IN ("All", "Staff")',
            ROLE_RESEARCHER => ' AND audience IN ("All", "Researchers")',
            default => '',
        };
        $stmt = db()->prepare('SELECT id, title AS label, created_at FROM announcements WHERE status = "Published"' . $audience . ' AND (title LIKE ? OR content LIKE ?) ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$like, $like]);
        $announcementUrl = in_array($role, [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN], true) ? 'announcements.php' : $portalBase . 'announcements.php';
        foreach ($stmt->fetchAll() as $row) $results[] = ['type' => 'Announcement', 'label' => $row['label'], 'detail' => 'Published', 'date' => $row['created_at'], 'url' => $announcementUrl];
    }
    if (role_can($role, 'activities')) {
        ensure_activity_archived_at_column();
        $stmt = db()->prepare('SELECT id, title AS label, activity_type, date FROM activities WHERE approval_status = "Approved" AND archived_at IS NULL AND (title LIKE ? OR description LIKE ? OR venue LIKE ?) ORDER BY date DESC LIMIT 10');
        $stmt->execute([$like, $like, $like]);
        $activityUrl = in_array($role, [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN, ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR], true) ? 'activities.php' : $portalBase . 'calendar.php';
        foreach ($stmt->fetchAll() as $row) $results[] = ['type' => 'Activity', 'label' => $row['label'], 'detail' => $row['activity_type'], 'date' => $row['date'], 'url' => $activityUrl];
    }
}
usort($results, static fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
renderHeader('Search Results', 'search');
?>
<section class="panel">
    <div class="panel-heading"><h2>System Search</h2><span><?php echo count($results); ?> result<?php echo count($results) === 1 ? '' : 's'; ?></span></div>
    <form method="get" class="search-results-form"><label class="field"><span>Search all accessible records</span><input type="search" name="q" value="<?php echo h($query); ?>" maxlength="100" required></label><button class="primary-btn" type="submit"><i class="ri-search-line"></i> Search</button></form>
    <?php if ($query === ''): ?><p>Enter a title, researcher, partner, announcement, or activity to search.</p>
    <?php elseif (!$results): ?><p>No accessible results matched “<?php echo h($query); ?>”.</p>
    <?php else: ?><div class="table-scroll"><table class="data-table"><thead><tr><th>Type</th><th>Title</th><th>Status / Details</th><th>Date</th><th></th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><?php echo h($result['type']); ?></td><td><?php echo h($result['label']); ?></td><td><?php echo h($result['detail'] ?? ''); ?></td><td><?php echo h(date('M d, Y', strtotime($result['date']))); ?></td><td><a class="ghost-btn mini-btn" href="<?php echo h($result['url']); ?>">Open</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php renderFooter(); ?>
