<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function user_status_label(string $status): string {
    return $status === 'Declined' ? 'Rejected' : $status;
}

function user_status_db(string $status): string {
    return $status === 'Rejected' ? 'Declined' : $status;
}

function user_status_class(string $status): string {
    return strtolower(str_replace(' ', '-', user_status_label($status)));
}

function user_tracking_number(int $id): string {
    return 'REC-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

function user_badge(string $status): string {
    return '<span class="status-badge status-' . h(user_status_class($status)) . '">' . h(user_status_label($status)) . '</span>';
}

function user_file_url(string $filename): string {
    return '../uploads/records/' . implode('/', array_map('rawurlencode', explode('/', $filename)));
}

function user_notice(?array $notice): void {
    if ($notice) {
        echo '<div class="notice ' . h($notice['type']) . '"><i class="ri-information-line"></i>' . h($notice['message']) . '</div>';
    }
}

function user_documents(int $userId, ?string $status = null): array {
    $where = 'd.uploaded_by = ? AND d.archived_at IS NULL';
    $params = [$userId];
    if ($status && $status !== 'All') {
        $where .= ' AND d.status = ?';
        $params[] = user_status_db($status);
    }
    $stmt = user_db()->prepare(
        'SELECT d.*, c.category_name
         FROM documents d
         JOIN categories c ON c.id = d.category_id
         WHERE ' . $where . '
         ORDER BY d.created_at DESC'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function user_document_by_id(int $id, int $userId): ?array {
    $stmt = user_db()->prepare(
        'SELECT d.*, c.category_name
         FROM documents d
         JOIN categories c ON c.id = d.category_id
         WHERE d.id = ? AND d.uploaded_by = ? AND d.archived_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$id, $userId]);
    $doc = $stmt->fetch();
    return $doc ?: null;
}

function user_document_by_tracking(string $tracking, int $userId): ?array {
    $id = (int) preg_replace('/\D+/', '', $tracking);
    return $id > 0 ? user_document_by_id($id, $userId) : null;
}

function user_stats(int $userId): array {
    $stmt = user_db()->prepare(
        'SELECT COUNT(*) total,
                SUM(status = "Pending") pending,
                SUM(status = "Approved") approved,
                SUM(status = "Declined") rejected
         FROM documents
         WHERE uploaded_by = ? AND archived_at IS NULL'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch() ?: [];
    return [
        'total' => (int) ($row['total'] ?? 0),
        'pending' => (int) ($row['pending'] ?? 0),
        'approved' => (int) ($row['approved'] ?? 0),
        'rejected' => (int) ($row['rejected'] ?? 0),
    ];
}

function user_notifications(int $userId, int $limit = 0): array {
    $sql = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $stmt = user_db()->prepare($sql);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function user_announcements(): array {
    return user_db()->query(
        'SELECT a.*, u.fullname AS author
         FROM announcements a
         JOIN users u ON u.id = a.posted_by
         WHERE a.status = "Published"
         ORDER BY a.created_at DESC'
    )->fetchAll();
}

function user_log(int $userId, ?int $documentId, string $action, string $remarks = ''): void {
    if (user_table_exists('document_logs')) {
        $columns = [];
        foreach (['document_id', 'user_id', 'action', 'remarks', 'created_at'] as $column) {
            if (user_column_exists('document_logs', $column)) {
                $columns[] = $column;
            }
        }
        if ($columns) {
            $values = [];
            foreach ($columns as $column) {
                if ($column === 'document_id') {
                    $values[] = $documentId;
                } elseif ($column === 'user_id') {
                    $values[] = $userId;
                } elseif ($column === 'action') {
                    $values[] = $action;
                } elseif ($column === 'remarks') {
                    $values[] = $remarks;
                } else {
                    $values[] = date('Y-m-d H:i:s');
                }
            }
            user_db()->prepare('INSERT INTO document_logs (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')')->execute($values);
            return;
        }
    }
    log_action($userId, $action . ($documentId ? ' document #' . $documentId : ''));
}

function user_save_upload(array $post, array $file, array $user): int {
    $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    $allowedMime = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png', 'application/octet-stream'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please choose a file to upload.');
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File is too large. Maximum size is 15 MB.');
    }
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Allowed files: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if ($mime && !in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('The uploaded file type could not be verified.');
    }

    $categoryId = (int) ($post['category_id'] ?? 0);
    $departmentId = (int) ($user['department_id'] ?? 0);
    if ($departmentId <= 0) {
        $dept = user_db()->query('SELECT id FROM departments ORDER BY id LIMIT 1')->fetch();
        $departmentId = (int) ($dept['id'] ?? 0);
    }
    if ($categoryId <= 0 || $departmentId <= 0) {
        throw new RuntimeException('Please choose a valid category.');
    }

    $folder = UPLOAD_DIR . '/' . $categoryId;
    if (!is_dir($folder)) {
        mkdir($folder, 0775, true);
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file((string) $file['tmp_name'], $folder . '/' . $stored)) {
        throw new RuntimeException('Upload failed. Please try again.');
    }

    $stmt = user_db()->prepare(
        'INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", CURDATE(), NOW())'
    );
    $stmt->execute([
        trim((string) $post['title']),
        $categoryId,
        trim((string) ($post['description'] ?? '')),
        $user['fullname'],
        $departmentId,
        $categoryId . '/' . $stored,
        basename((string) $file['name']),
        (int) $user['id'],
    ]);
    $documentId = (int) user_db()->lastInsertId();
    user_db()->prepare('INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, "User Upload", ?, ?, NOW())')
        ->execute([$documentId, $categoryId . '/' . $stored, (int) $user['id']]);
    notify_user((int) $user['id'], 'Your file "' . trim((string) $post['title']) . '" was uploaded and is pending review.');
    user_log((int) $user['id'], $documentId, 'Uploaded', 'Submitted through user portal.');
    return $documentId;
}
