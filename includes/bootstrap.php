<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

function redirect_to(string $path): never {
    header('Location: ' . $path);
    exit;
}

function flash(?string $type = null, ?string $message = null): ?array {
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "Active" LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(array $roles = []): array {
    $user = current_user();
    if (!$user) {
        redirect_to('index.php');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have access to this page.');
    }
    return $user;
}

function log_action(?int $userId, string $action): void {
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $action, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
}

function notify_user(int $userId, string $message): void {
    $stmt = db()->prepare('INSERT INTO notifications (user_id, message, status, created_at) VALUES (?, ?, "Unread", NOW())');
    $stmt->execute([$userId, $message]);
}

function statusClass($status): string {
    return strtolower(str_replace([' ', '/'], '-', (string) $status));
}

function departments(): array {
    return db()->query('SELECT * FROM departments ORDER BY department_name')->fetchAll();
}

function categories(): array {
    return db()->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
}

function dashboard_stats(): array {
    return [
        'total_records' => (int) db()->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
        'active_records' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE archived_at IS NULL')->fetchColumn(),
        'archived_records' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE archived_at IS NOT NULL')->fetchColumn(),
        'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'pending' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE status = "Pending"')->fetchColumn(),
    ];
}

function document_list(bool $archived = false): array {
    $sql = 'SELECT d.*, c.category_name, u.fullname AS uploader, dep.department_name
            FROM documents d
            JOIN categories c ON c.id = d.category_id
            JOIN users u ON u.id = d.uploaded_by
            JOIN departments dep ON dep.id = d.department_id
            WHERE d.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL') . '
            ORDER BY d.created_at DESC';
    return db()->query($sql)->fetchAll();
}

function save_uploaded_document(array $post, array $file, int $userId): void {
    $allowed = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please choose a valid file.');
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File exceeds the 15 MB limit.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('Only PDF, DOCX, XLSX, and PPTX files are accepted.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== $allowed[$ext] && $ext !== 'docx' && $ext !== 'xlsx' && $ext !== 'pptx') {
        throw new RuntimeException('The uploaded file type could not be verified.');
    }

    $category = (int) $post['category_id'];
    $department = (int) $post['department_id'];
    $folder = UPLOAD_DIR . '/' . $category;
    if (!is_dir($folder)) {
        mkdir($folder, 0775, true);
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $stored)) {
        throw new RuntimeException('Upload failed. Check folder permissions.');
    }

    $stmt = db()->prepare('INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", ?, NOW())');
    $stmt->execute([
        trim($post['title']),
        $category,
        trim($post['description'] ?? ''),
        trim($post['author']),
        $department,
        $category . '/' . $stored,
        $file['name'],
        $userId,
        $post['submission_date'] ?: date('Y-m-d'),
    ]);
    $documentId = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, "Original Version", ?, ?, NOW())')
        ->execute([$documentId, $category . '/' . $stored, $userId]);
    log_action($userId, 'Uploaded document: ' . trim($post['title']));
}
