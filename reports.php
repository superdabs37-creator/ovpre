<?php
require_once 'includes/data.php';
require_once 'includes/layout.php';
require_once 'includes/file-storage.php';
$storageType = 'reports';
$notice = handleFileDelete($storageType, $departments) ?: handleFileUpload($storageType, $departments);
renderHeader('Accomplishment Reports', 'reports');
$files = array_merge(uploadedFiles($storageType, $departments), $reportFiles);
$pageType = 'Accomplishment Report';
include 'includes/file-page.php';
renderFooter();
?>
