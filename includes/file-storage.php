<?php
function safeDepartment($department, $departments) {
    return in_array($department, $departments, true) ? $department : $departments[0];
}

function storageRoot($storageType) {
    return __DIR__ . '/../uploads/' . $storageType;
}

function ensureStorageFolders($storageType, $departments) {
    foreach ($departments as $department) {
        $path = storageRoot($storageType) . '/' . $department;
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function normalizeFileName($name) {
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '', basename($name));
    return trim($name) !== '' ? $name : 'uploaded-file';
}

function handleFileUpload($storageType, $departments) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'upload') {
        return null;
    }

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        return ['type' => 'error', 'text' => 'Please choose a valid file to upload.'];
    }

    $department = safeDepartment($_POST['department'] ?? '', $departments);
    ensureStorageFolders($storageType, $departments);
    $targetName = time() . '-' . normalizeFileName($_FILES['document']['name']);
    $targetPath = storageRoot($storageType) . '/' . $department . '/' . $targetName;

    if (!move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
        return ['type' => 'error', 'text' => 'Upload failed. Check folder permissions.'];
    }

    return ['type' => 'success', 'text' => 'File uploaded successfully.'];
}

function handleFileDelete($storageType, $departments) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'delete') {
        return null;
    }

    $department = safeDepartment($_POST['department'] ?? '', $departments);
    $file = normalizeFileName($_POST['file'] ?? '');
    $path = storageRoot($storageType) . '/' . $department . '/' . $file;

    if (is_file($path)) {
        unlink($path);
        return ['type' => 'success', 'text' => 'File deleted successfully.'];
    }

    return ['type' => 'error', 'text' => 'File was not found.'];
}

function uploadedFiles($storageType, $departments) {
    ensureStorageFolders($storageType, $departments);
    $files = [];

    foreach ($departments as $department) {
        $folder = storageRoot($storageType) . '/' . $department;
        foreach (glob($folder . '/*') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }

            $files[] = [
                'name' => basename($path),
                'user' => 'Administrator',
                'date' => date('F d, Y', filemtime($path)),
                'department' => $department,
                'url' => 'uploads/' . $storageType . '/' . rawurlencode($department) . '/' . rawurlencode(basename($path)),
                'stored' => true,
            ];
        }
    }

    return array_reverse($files);
}

function folderFileCount($storageType, $department) {
    $folder = storageRoot($storageType) . '/' . $department;
    return count(array_filter(glob($folder . '/*') ?: [], 'is_file'));
}
?>
