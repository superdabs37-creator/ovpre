<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function student_status_label(string $status): string {
    return $status === 'Declined' ? 'Rejected' : $status;
}

function student_status_db(string $status): string {
    return $status === 'Rejected' ? 'Declined' : $status;
}

function student_status_class(string $status): string {
    return statusClass(student_status_label($status));
}

function student_tracking_number(int $id): string {
    return 'OVPRE-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

function student_file_url(string $filename): string {
    $parts = array_map('rawurlencode', explode('/', $filename));
    return '../uploads/records/' . implode('/', $parts);
}

function student_badge(string $status): string {
    return '<span class="status-pill status-' . h(student_status_class($status)) . '">' . h(student_status_label($status)) . '</span>';
}

function student_notice(?array $notice): void {
    if (!$notice) {
        return;
    }
    echo '<div class="notice ' . h($notice['type']) . '"><i class="ri-information-line"></i>' . h($notice['message']) . '</div>';
}

function student_documents(int $userId, ?string $status = null): array {
    $where = 'd.uploaded_by = ? AND d.archived_at IS NULL';
    $params = [$userId];
    if ($status && $status !== 'All') {
        $where .= ' AND d.status = ?';
        $params[] = student_status_db($status);
    }
    $stmt = student_db()->prepare(
        'SELECT d.*, c.category_name, dep.department_name
         FROM documents d
         JOIN categories c ON c.id = d.category_id
         JOIN departments dep ON dep.id = d.department_id
         WHERE ' . $where . '
         ORDER BY d.created_at DESC'
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function student_document(int $id, int $userId): ?array {
    $stmt = student_db()->prepare(
        'SELECT d.*, c.category_name, dep.department_name
         FROM documents d
         JOIN categories c ON c.id = d.category_id
         JOIN departments dep ON dep.id = d.department_id
         WHERE d.id = ? AND d.uploaded_by = ? AND d.archived_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$id, $userId]);
    $doc = $stmt->fetch();
    return $doc ?: null;
}

function student_dashboard_stats(int $userId): array {
    $stmt = student_db()->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "Pending") AS pending,
            SUM(status = "Approved") AS approved,
            SUM(status = "Declined") AS rejected
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

function student_chart_data(int $userId): array {
    $monthly = student_db()->prepare(
        'SELECT DATE_FORMAT(created_at, "%b %Y") AS label, COUNT(*) AS total
         FROM documents
         WHERE uploaded_by = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY YEAR(created_at), MONTH(created_at)
         ORDER BY YEAR(created_at), MONTH(created_at)'
    );
    $monthly->execute([$userId]);

    $status = student_db()->prepare(
        'SELECT status AS label, COUNT(*) AS total
         FROM documents
         WHERE uploaded_by = ? AND archived_at IS NULL
         GROUP BY status'
    );
    $status->execute([$userId]);

    return ['monthly' => $monthly->fetchAll(), 'status' => $status->fetchAll()];
}

function student_announcements(int $limit = 0): array {
    $sql = 'SELECT a.*, u.fullname AS author
            FROM announcements a
            JOIN users u ON u.id = a.posted_by
            WHERE a.status = "Published" AND a.audience IN ("All", "Faculty", "Researchers")
            ORDER BY a.created_at DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return student_db()->query($sql)->fetchAll();
}

function student_notifications(int $userId): array {
    $stmt = student_db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function student_log_document(int $userId, ?int $documentId, string $action, string $remarks = ''): void {
    if (student_table_exists('document_logs')) {
        $columns = [];
        foreach (['document_id', 'user_id', 'action', 'remarks', 'created_at'] as $column) {
            if (student_column_exists('document_logs', $column)) {
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
                } elseif ($column === 'created_at') {
                    $values[] = date('Y-m-d H:i:s');
                } else {
                    $values[] = null;
                }
            }
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            student_db()->prepare('INSERT INTO document_logs (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')')->execute($values);
            return;
        }
    }

    log_action($userId, trim($action . ($documentId ? ' document #' . $documentId : '') . ($remarks ? ': ' . $remarks : '')));
}

function student_history(int $userId): array {
    if (student_table_exists('document_logs')) {
        $hasDocumentId = student_column_exists('document_logs', 'document_id');
        $hasUserId = student_column_exists('document_logs', 'user_id');
        $hasAction = student_column_exists('document_logs', 'action');
        $hasRemarks = student_column_exists('document_logs', 'remarks');
        $hasCreated = student_column_exists('document_logs', 'created_at');
        if ($hasDocumentId && $hasAction) {
            $sql = 'SELECT l.id, d.title, ' . ($hasAction ? 'l.action' : '"Activity" AS action') . ', ' . ($hasRemarks ? 'l.remarks' : '"" AS remarks') . ', ' . ($hasCreated ? 'l.created_at' : 'NOW() AS created_at') . '
                    FROM document_logs l
                    LEFT JOIN documents d ON d.id = l.document_id
                    WHERE ' . ($hasUserId ? 'l.user_id = ?' : 'd.uploaded_by = ?') . '
                    ORDER BY ' . ($hasCreated ? 'l.created_at' : 'l.id') . ' DESC';
            $stmt = student_db()->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }
    }

    $stmt = student_db()->prepare(
        'SELECT a.id, COALESCE(d.title, "Account Activity") AS title, a.action, "" AS remarks, a.created_at
         FROM audit_logs a
         LEFT JOIN documents d ON a.action LIKE CONCAT("%document #", d.id, "%")
         WHERE a.user_id = ?
         ORDER BY a.created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function student_save_uploaded_document(array $post, array $file, array $user): int {
    $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    $allowedMime = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'application/octet-stream',
    ];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please choose a valid document file.');
    }
    if (($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File exceeds the 15 MB limit.');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Allowed files: PDF, DOC, DOCX, XLS, XLSX, JPG, and PNG.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if ($mime && !in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('The uploaded file type could not be verified.');
    }

    $categoryId = (int) $post['category_id'];
    $departmentId = (int) ($user['department_id'] ?: ($post['department_id'] ?? 0));
    if ($categoryId <= 0 || $departmentId <= 0) {
        throw new RuntimeException('Please choose a valid category and department.');
    }

    $folder = UPLOAD_DIR . '/' . $categoryId;
    if (!is_dir($folder)) {
        mkdir($folder, 0775, true);
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file((string) $file['tmp_name'], $folder . '/' . $stored)) {
        throw new RuntimeException('Upload failed. Please check folder permissions.');
    }

    $stmt = student_db()->prepare(
        'INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", ?, NOW())'
    );
    $stmt->execute([
        trim((string) $post['title']),
        $categoryId,
        trim((string) ($post['description'] ?? '')),
        trim((string) ($post['author'] ?: $user['fullname'])),
        $departmentId,
        $categoryId . '/' . $stored,
        basename((string) $file['name']),
        (int) $user['id'],
        $post['submission_date'] ?: date('Y-m-d'),
    ]);

    $documentId = (int) student_db()->lastInsertId();
    student_db()->prepare('INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, "Student Upload", ?, ?, NOW())')
        ->execute([$documentId, $categoryId . '/' . $stored, (int) $user['id']]);
    student_log_document((int) $user['id'], $documentId, 'Uploaded', 'Document submitted by student portal.');
    notify_user((int) $user['id'], 'Your document ' . trim((string) $post['title']) . ' was submitted and is pending review.');

    return $documentId;
}
