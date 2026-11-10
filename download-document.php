<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
session_write_close();
$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$versionId = filter_input(INPUT_GET, 'version', FILTER_VALIDATE_INT) ?: 0;
if ($documentId <= 0) {
    http_response_code(404);
    exit('File not found.');
}
$stmt = db()->prepare('SELECT d.id, d.filename, d.original_filename, d.uploaded_by, d.department_id, d.archived_at FROM documents d WHERE d.id = ? LIMIT 1');
$stmt->execute([$documentId]);
$document = $stmt->fetch();
if (!$document) {
    http_response_code(404);
    exit('File not found.');
}
$role = (string) $user['role'];
$isAdmin = in_array($role, [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN], true);
$isOwner = (int) $document['uploaded_by'] === (int) $user['id'];
$isDepartmentCoordinator = in_array($role, [ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR], true)
    && !empty($user['department_id']) && (int) $document['department_id'] === (int) $user['department_id'];
$isDirectorOrExecutive = in_array($role, [
    ROLE_RESEARCH_DIRECTOR,
    ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR,
    ROLE_EXTENSION_DIRECTOR,
    'Vice President',
    ROLE_OVPRE_SECRETARIAT,
], true) || in_array(role_access_group($role), [ROLE_RESEARCH_DIRECTOR, ROLE_EXTENSION_DIRECTOR, ROLE_OVPRE_ADMIN], true);

if (!$isAdmin && !$isDirectorOrExecutive && !$isOwner && !$isDepartmentCoordinator) {
    http_response_code(403);
    exit('You do not have access to this file.');
}
$filename = (string) $document['filename'];
$originalName = (string) $document['original_filename'];
if ($versionId > 0) {
    $versionStmt = db()->prepare('SELECT filename FROM document_versions WHERE id = ? AND document_id = ? LIMIT 1');
    $versionStmt->execute([$versionId, $documentId]);
    $versionFilename = $versionStmt->fetchColumn();
    if (!$versionFilename) {
        http_response_code(404);
        exit('File version not found.');
    }
    $filename = (string) $versionFilename;
}

$ext = strtolower(pathinfo($originalName ?: $filename, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'txt' => 'text/plain; charset=UTF-8',
    'html' => 'text/html; charset=UTF-8',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $mimeMap[$ext] ?? 'application/pdf';
$safeName = str_replace(["\r", "\n", '"'], '', basename($originalName ?: $filename));
$isDownload = isset($_GET['download']) && $_GET['download'] === '1';
$disposition = $isDownload ? 'attachment' : 'inline';

if (str_starts_with($filename, 'storage:')) {
    $objectPath = substr($filename, 8);
    $signedUrl = supabase_storage_signed_url($objectPath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    if (function_exists('curl_init')) {
        $ch = curl_init($signedUrl);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HEADER => false,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } else {
        readfile($signedUrl);
    }
    exit;
}

$root = realpath(UPLOAD_DIR);
$path = realpath(UPLOAD_DIR . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename));
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$finfoMime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$localMime = $mimeMap[$ext] ?? ($finfoMime && $finfoMime !== 'application/octet-stream' ? $finfoMime : 'application/pdf');

header('Content-Type: ' . $localMime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
