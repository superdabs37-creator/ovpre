<?php
require_once 'includes/data.php';
require_once 'includes/layout.php';
require_once 'includes/file-storage.php';
$storageType = 'proposals';
$notice = handleFileDelete($storageType, $departments) ?: handleFileUpload($storageType, $departments);
renderHeader('Proposals', 'proposals');
$files = array_merge(uploadedFiles($storageType, $departments), $proposalFiles);
$pageType = 'Proposal';
include 'includes/file-page.php';
renderFooter();
?>
