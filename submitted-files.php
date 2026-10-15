<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$role = role_access_group((string) ($user['role'] ?? ''));
$params = [];
$scope = '';
if (in_array($role, [ROLE_RESEARCHER, ROLE_FACULTY_EXTENSIONIST], true)) {
    $scope = ' AND d.uploaded_by = ?';
    $params[] = (int) $user['id'];
} elseif (in_array($role, [ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR], true)) {
    if (!empty($user['department_id'])) {
        $scope = ' AND d.department_id = ?';
        $params[] = (int) $user['department_id'];
    } else {
        $scope = ' AND d.uploaded_by = ?';
        $params[] = (int) $user['id'];
    }
} elseif (in_array($role, [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN, ROLE_RESEARCH_DIRECTOR, ROLE_EXTENSION_DIRECTOR], true)) {
    $scope = '';
} else {
    http_response_code(403);
    exit('You do not have permission to view submitted files.');
}
$stmt = db()->prepare('SELECT d.id, d.title, d.description, d.filename, d.original_filename, d.status, d.created_at, c.category_name, u.fullname AS uploader
    FROM documents d
    LEFT JOIN categories c ON c.id = d.category_id
    LEFT JOIN users u ON u.id = d.uploaded_by
WHERE d.archived_at IS NULL' . $scope . '
    ORDER BY d.created_at DESC');
$stmt->execute($params);
$documents = $stmt->fetchAll();

renderHeader('Submitted Files', 'documents');
?>
<section class="module-header">
    <div class="module-icon"><i class="ri-folder-open-line"></i></div>
    <div><p>Shared file list</p><h2>Submitted Files</h2><span>Browse files available to your account and department.</span></div>
</section>
<section class="panel">
    <div class="panel-heading">
        <h2>Files Available to You</h2>
        <label class="table-search"><span class="sr-only">Search files</span><i class="ri-search-line"></i><input type="search" data-table-search="submittedFilesTable" placeholder="Search files"></label>
    </div>
    <div class="table-scroll">
        <table class="data-table" id="submittedFilesTable" data-paginate="10">
            <thead><tr><th>Title</th><th>Category</th><th>Submitted By</th><th>Status</th><th>Date</th><th>File</th></tr></thead>
            <tbody>
                <?php foreach ($documents as $document): ?>
                    <tr id="document-<?php echo (int) $document['id']; ?>">
                        <td><?php echo h($document['title']); ?><?php if (!empty($document['description'])): ?><br><small><?php echo h($document['description']); ?></small><?php endif; ?></td>
                        <td><?php echo h($document['category_name'] ?? 'Uncategorized'); ?></td>
                        <td><?php echo h($document['uploader'] ?? 'Unknown'); ?></td>
                        <td><span class="status-pill status-<?php echo h(statusClass($document['status'])); ?>"><?php echo h($document['status']); ?></span></td>
                        <td><?php echo h(date('M d, Y', strtotime($document['created_at']))); ?></td>
                        <td><a class="ghost-btn mini-btn" href="<?php echo h(record_access_url($document['filename'], '', (int) $document['id'])); ?>" target="_blank" rel="noopener"><i class="ri-eye-line"></i> View File</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$documents): ?><tr data-pagination-empty><td colspan="6">No submitted files yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <p class="search-empty" data-search-empty="submittedFilesTable" hidden>No matching files.</p>
    </div>
</section>
<?php renderFooter(); ?>
