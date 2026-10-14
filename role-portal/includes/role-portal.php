<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

const ROLE_PORTALS = [
    'OVPRE Admin' => [
        'slug' => 'ovpre-admin',
        'title' => 'OVPRE Admin Portal',
        'purpose' => 'Manage authorized research and extension office operations',
        'nav' => ['dashboard', 'research', 'extension', 'analytics', 'calendar', 'reports', 'announcements', 'notifications', 'status'],
    ],
    'Research Coordinator' => [
        'slug' => 'research-coordinator',
        'title' => 'Research Coordinator Portal',
        'purpose' => 'Research monitoring and submissions',
        'nav' => ['dashboard', 'research', 'calendar', 'reports', 'announcements', 'notifications', 'status'],
    ],
    'Extension Coordinator' => [
        'slug' => 'extension-coordinator',
        'title' => 'Extension Coordinator Portal',
        'purpose' => 'Extension monitoring and submissions',
        'nav' => ['dashboard', 'extension', 'calendar', 'reports', 'announcements', 'notifications'],
    ],
    'Faculty Extensionist' => [
        'slug' => 'faculty-extensionist',
        'title' => 'Faculty Extensionist Portal',
        'purpose' => 'Submit extension work and follow your authorized records',
        'nav' => ['dashboard', 'extension', 'notifications', 'status'],
    ],
    'Researcher' => [
        'slug' => 'researcher',
        'title' => 'Researcher Portal',
        'purpose' => 'Submit research work and follow your authorized records',
        'nav' => ['dashboard', 'research', 'notifications', 'status'],
    ],
];

function portal_config(): array
{
    $role = PORTAL_ROLE;
    return ROLE_PORTALS[$role] ?? ROLE_PORTALS['Researcher'];
}

function portal_require_user(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to('../index.php');
    }
    if (($user['role'] ?? '') !== PORTAL_ROLE) {
        http_response_code(403);
        exit('This portal is not available for your role.');
    }
    return $user;
}

function portal_active(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

function portal_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return implode('', array_map(static fn($part) => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2))) ?: 'IR';
}

function portal_slug_for_role(string $role): string
{
    return ROLE_PORTALS[$role]['slug'] ?? 'dashboard';
}

function portal_can(string $page): bool
{
    return in_array($page, portal_config()['nav'], true);
}

function portal_nav_items(): array
{
    $items = [
        'dashboard' => ['Dashboard', 'dashboard.php', 'ri-dashboard-line'],
        'research' => ['Research Submission', 'research.php', 'ri-flask-line'],
        'extension' => ['Extension Submission', 'extension.php', 'ri-community-line'],
        'analytics' => ['Analytics', 'analytics.php', 'ri-bar-chart-box-line'],
        'calendar' => ['Calendar', 'calendar.php', 'ri-calendar-event-line'],
        'reports' => ['Reports', 'reports.php', 'ri-file-chart-line'],
        'announcements' => ['Announcements', 'announcements.php', 'ri-megaphone-line'],
        'notifications' => ['Notifications', 'notifications.php', 'ri-notification-3-line'],
        'status' => ['Submission Status', 'status.php', 'ri-route-line'],
    ];
    if (PORTAL_ROLE === 'Extension Coordinator' || PORTAL_ROLE === 'Faculty Extensionist') {
        $items['status'][0] = 'Extension Submission Status';
    } elseif (PORTAL_ROLE === 'Research Coordinator' || PORTAL_ROLE === 'Researcher') {
        $items['status'][0] = 'Research Submission Status';
        $items['calendar'][0] = 'Activities';
    }
    return $items;
}

function portal_header(string $title, string $current): array
{
    $user = portal_require_user();
    ensure_activity_archived_at_column();
    $config = portal_config();
    $name = $user['fullname'] ?? PORTAL_ROLE;
    $savedProfilePhoto = (string) ($user['profile_photo'] ?? '');
    $profilePhotoUrl = preg_match('#^uploads/profile-photos/[A-Za-z0-9._-]+\.(jpg|png|webp)$#', $savedProfilePhoto)
        ? '../' . $savedProfilePhoto
        : '';
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo h($title); ?> | <?php echo h($config['title']); ?></title>
        <link rel="icon" type="image/jpeg" href="../assets/img/ovpre-logo.jpg?v=20261012">
        <link rel="stylesheet" href="../role-portal/assets/css/portal.css?v=20261015-hidden-sidebar">
        <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    </head>

    <body>
        <div class="role-shell">
            <aside class="role-sidebar" id="roleSidebar">
                <a class="role-brand" href="dashboard.php"><span><i class="ri-building-4-line"></i></span><strong><?php echo h($config['title']); ?></strong></a>
                <p class="role-purpose"><?php echo h($config['purpose']); ?></p>
                <nav class="role-nav">
                    <?php foreach (portal_nav_items() as $key => [$label, $href, $icon]): ?>
                        <?php if (portal_can($key)): ?><a class="<?php echo portal_active($key, $current); ?>" href="<?php echo h($href); ?>"><i class="<?php echo h($icon); ?>"></i><span><?php echo h($label); ?></span></a><?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <div class="role-page">
                <header class="role-topbar">
                    <button class="icon-btn" type="button" aria-label="Open navigation menu" aria-controls="roleSidebar" aria-expanded="false" data-role-sidebar><i class="ri-menu-line"></i></button>
                    <div>
                        <h1><?php echo h($title); ?></h1>
                    </div>
                    <form class="role-search" action="../search.php" method="get" role="search"><i class="ri-search-line" aria-hidden="true"></i><input type="search" name="q" placeholder="Search all records" aria-label="Search all accessible records"></form>
                    <span class="role-chip"><?php echo h(PORTAL_ROLE); ?></span>
                    <div class="role-profile-menu">
                        <button class="role-profile-button" type="button" data-role-profile-toggle aria-expanded="false" aria-haspopup="true" aria-label="Open profile menu">
                            <span class="role-avatar"><?php if ($profilePhotoUrl): ?><img src="<?php echo h($profilePhotoUrl); ?>" alt=""><?php else: ?><?php echo h(portal_initials($name)); ?><?php endif; ?></span>
                            <i class="ri-arrow-down-s-line"></i>
                        </button>
                        <div class="role-profile-dropdown" data-role-profile-dropdown>
                            <div class="role-profile-identity">
                                <span class="role-profile-large-avatar"><?php if ($profilePhotoUrl): ?><img src="<?php echo h($profilePhotoUrl); ?>" alt=""><?php else: ?><i class="ri-user-3-fill"></i><?php endif; ?></span>
                                <strong><?php echo h($name); ?></strong>
                                <small><?php echo h(PORTAL_ROLE); ?></small>
                            </div>
                            <a href="../profile.php"><i class="ri-user-settings-line"></i> Edit Profile</a>
                            <a href="../profile.php?section=password"><i class="ri-lock-password-line"></i> Change Password</a>
                            <a href="../logout.php"><i class="ri-logout-circle-line"></i> Logout</a>
                        </div>
                    </div>
                </header>
                <main class="role-content">
                <?php
                return $user;
            }

            function portal_footer(): void
            {
                ?>
                </main>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="../role-portal/assets/js/portal.js?v=20261015-hidden-sidebar"></script>
    </body>

    </html>
<?php
            }

            function portal_notice(?array $notice): void
            {
                if ($notice) {
                    echo '<div class="notice ' . h($notice['type']) . '"><i class="ri-information-line"></i>' . h($notice['message']) . '</div>';
                }
            }

            function portal_stats(): array
            {
                $userId = (int) (current_user()['id'] ?? 0);
                $departmentId = (int) (current_user()['department_id'] ?? 0);
                if (in_array(PORTAL_ROLE, ['Researcher', 'Faculty Extensionist'], true)) {
                    $stmt = db()->prepare('SELECT COUNT(*) FROM research_projects WHERE submitted_by = ?');
                    $stmt->execute([$userId]);
                    $researchTotal = (int) $stmt->fetchColumn();
                    $stmt = db()->prepare('SELECT COUNT(*) FROM extension_projects WHERE submitted_by = ?');
                    $stmt->execute([$userId]);
                    $extensionTotal = (int) $stmt->fetchColumn();
                    return ['research_total' => $researchTotal, 'extension_total' => $extensionTotal, 'pending_research' => 0, 'approved_research' => 0, 'pending_documents' => 0, 'approved_documents' => 0];
                }
                if (in_array(PORTAL_ROLE, ['Research Coordinator', 'Extension Coordinator'], true)) {
                    $deptFilter = $departmentId > 0 ? ' AND submitter.department_id = ?' : ' AND submitter.id = ?';
                    $scopeId = $departmentId > 0 ? $departmentId : $userId;
                    $researchStmt = db()->prepare('SELECT COUNT(*) total, COUNT(CASE WHEN r.status IN ("Pending", "Under Review") THEN 1 END) pending, COUNT(CASE WHEN r.status = "Approved" THEN 1 END) approved FROM research_projects r JOIN users submitter ON submitter.id = r.submitted_by WHERE 1=1' . $deptFilter);
                    $researchStmt->execute([$scopeId]);
                    $researchStats = $researchStmt->fetch();
                    $extensionStmt = db()->prepare('SELECT COUNT(*) total FROM extension_projects e JOIN users submitter ON submitter.id = e.submitted_by WHERE 1=1' . $deptFilter);
                    $extensionStmt->execute([$scopeId]);
                    $extensionTotal = (int) $extensionStmt->fetchColumn();
                    return ['research_total' => (int) $researchStats['total'], 'extension_total' => $extensionTotal, 'pending_research' => (int) $researchStats['pending'], 'approved_research' => (int) $researchStats['approved'], 'pending_documents' => 0, 'approved_documents' => 0];
                }
                return [
                    'research_total' => (int) db()->query('SELECT COUNT(*) FROM research_projects')->fetchColumn(),
                    'extension_total' => (int) db()->query('SELECT COUNT(*) FROM extension_projects')->fetchColumn(),
                    'pending_research' => (int) db()->query('SELECT COUNT(*) FROM research_projects WHERE status = "Pending"')->fetchColumn(),
                    'approved_research' => (int) db()->query('SELECT COUNT(*) FROM research_projects WHERE status = "Approved"')->fetchColumn(),
                    'pending_documents' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE status IN ("Pending","Under Review") AND archived_at IS NULL')->fetchColumn(),
                    'approved_documents' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE status = "Approved" AND archived_at IS NULL')->fetchColumn(),
                ];
            }

            function portal_recent_activities(): array
            {
                return db()->query('SELECT * FROM activities WHERE approval_status = "Approved" AND archived_at IS NULL ORDER BY date DESC LIMIT 6')->fetchAll();
            }

            function portal_research_projects(): array
            {
                $sql = 'SELECT r.*, u.fullname, dep.department_name, d.filename AS attachment_filename, d.original_filename AS attachment_original_filename FROM research_projects r JOIN users u ON u.id = r.submitted_by LEFT JOIN departments dep ON dep.id = u.department_id LEFT JOIN documents d ON d.id = r.document_id';
                $params = [];
                if (PORTAL_ROLE === 'Researcher') {
                    $sql .= ' WHERE r.submitted_by = ?';
                    $params[] = (int) current_user()['id'];
                } elseif (PORTAL_ROLE === 'Research Coordinator') {
                    $departmentId = (int) (current_user()['department_id'] ?? 0);
                    $sql .= $departmentId > 0 ? ' WHERE u.department_id = ?' : ' WHERE r.submitted_by = ?';
                    $params[] = $departmentId > 0 ? $departmentId : (int) current_user()['id'];
                }
                $sql .= ' ORDER BY r.created_at DESC';
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            }

            function portal_extension_projects(): array
            {
                try {
                    $sql = 'SELECT e.*, u.fullname, dep.department_name, d.filename AS attachment_filename, d.original_filename AS attachment_original_filename FROM extension_projects e JOIN users u ON u.id = e.submitted_by LEFT JOIN departments dep ON dep.id = u.department_id LEFT JOIN documents d ON d.id = e.document_id';
                    $params = [];
                    if (PORTAL_ROLE === 'Faculty Extensionist') {
                        $sql .= ' WHERE e.submitted_by = ?';
                        $params[] = (int) current_user()['id'];
                    } elseif (PORTAL_ROLE === 'Extension Coordinator') {
                        $departmentId = (int) (current_user()['department_id'] ?? 0);
                        $sql .= $departmentId > 0 ? ' WHERE u.department_id = ?' : ' WHERE e.submitted_by = ?';
                        $params[] = $departmentId > 0 ? $departmentId : (int) current_user()['id'];
                    }
                    $sql .= ' ORDER BY e.created_at DESC';
                    $stmt = db()->prepare($sql);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                } catch (PDOException $error) {
                    if (stripos($error->getMessage(), 'document_id') === false) {
                        throw $error;
                    }
                    $sql = 'SELECT e.*, u.fullname, dep.department_name, NULL AS attachment_filename, NULL AS attachment_original_filename FROM extension_projects e JOIN users u ON u.id = e.submitted_by LEFT JOIN departments dep ON dep.id = u.department_id';
                    $params = [];
                    if (PORTAL_ROLE === 'Faculty Extensionist') { $sql .= ' WHERE e.submitted_by = ?'; $params[] = (int) current_user()['id']; }
                    elseif (PORTAL_ROLE === 'Extension Coordinator') {
                        $departmentId = (int) (current_user()['department_id'] ?? 0);
                        $sql .= $departmentId > 0 ? ' WHERE u.department_id = ?' : ' WHERE e.submitted_by = ?';
                        $params[] = $departmentId > 0 ? $departmentId : (int) current_user()['id'];
                    }
                    $sql .= ' ORDER BY e.created_at DESC';
                    $stmt = db()->prepare($sql);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                }
            }

            function portal_documents(): array
            {
                $sql = 'SELECT d.*, c.category_name, u.fullname, dep.department_name FROM documents d JOIN categories c ON c.id = d.category_id JOIN users u ON u.id = d.uploaded_by LEFT JOIN departments dep ON dep.id = d.department_id WHERE d.archived_at IS NULL';
                $params = [];
                if (in_array(PORTAL_ROLE, ['Researcher', 'Faculty Extensionist'], true)) {
                    $sql .= ' AND d.uploaded_by = ?';
                    $params[] = (int) current_user()['id'];
                } elseif (in_array(PORTAL_ROLE, ['Research Coordinator', 'Extension Coordinator'], true)) {
                    $departmentId = (int) (current_user()['department_id'] ?? 0);
                    $sql .= $departmentId > 0 ? ' AND d.department_id = ?' : ' AND d.uploaded_by = ?';
                    $params[] = $departmentId > 0 ? $departmentId : (int) current_user()['id'];
                }
                $sql .= ' ORDER BY d.created_at DESC';
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            }

            function portal_announcements(): array
            {
                $audienceFilter = match (PORTAL_ROLE) {
                    'Extension Coordinator' => ' AND a.audience IN ("All", "Staff")',
                    'Faculty Extensionist' => ' AND a.audience IN ("All", "Staff")',
                    'Research Coordinator' => ' AND a.audience IN ("All", "Researchers")',
                    'Researcher' => ' AND a.audience IN ("All", "Researchers")',
                    default => '',
                };
                return db()->query('SELECT a.*, u.fullname FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.status = "Published"' . $audienceFilter . ' ORDER BY a.created_at DESC')->fetchAll();
            }

            function portal_notifications(int $userId): array
            {
                $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
                $stmt->execute([$userId]);
                return $stmt->fetchAll();
            }

            function portal_save_document(array $post, array $file, array $user): ?int
            {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    return null;
                }
                $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true) || ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
                    throw new RuntimeException('Upload must be PDF, DOC, DOCX, XLS, XLSX, JPG, or PNG up to 15 MB.');
                }
                $category = (int) ($post['category_id'] ?? 0);
                $department = (int) ($user['department_id'] ?? 0);
                if ($department <= 0) {
                    $department = (int) (db()->query('SELECT id FROM departments ORDER BY id LIMIT 1')->fetch()['id'] ?? 0);
                }
                $stored = bin2hex(random_bytes(16)) . '.' . $ext;
                $objectPath = $category . '/' . $stored;
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
                supabase_storage_upload((string) $file['tmp_name'], $objectPath, $mime);
                $storedName = 'storage:' . $objectPath;
                db()->prepare('INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", CURDATE(), NOW())')
                    ->execute([trim((string) $post['document_title']), $category, trim((string) ($post['document_description'] ?? '')), $user['fullname'], $department, $storedName, basename((string) $file['name']), $user['id']]);
                $documentId = (int) db()->lastInsertId();
                db()->prepare('INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, "Portal Upload", ?, ?, NOW())')
                    ->execute([$documentId, $storedName, $user['id']]);
                return $documentId;
            }

            function portal_analytics_data(): array
            {
                return [
                    'research' => db()->query('SELECT status AS label, COUNT(*) AS total FROM research_projects GROUP BY status')->fetchAll(),
                    'documents' => db()->query('SELECT status AS label, COUNT(*) AS total FROM documents WHERE archived_at IS NULL GROUP BY status')->fetchAll(),
                    'extension' => db()->query('SELECT progress_status AS label, COUNT(*) AS total FROM extension_projects GROUP BY progress_status')->fetchAll(),
                ];
            }
