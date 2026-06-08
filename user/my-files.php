<?php
require_once 'includes/user-layout.php';
$user = user_render_header('My Files', 'files');
$files = user_documents((int) $user['id']);
?>
<section class="simple-header"><div><h2>My Files</h2><p>Search, view, download, and track your submitted documents.</p></div><a class="primary-btn" href="upload.php"><i class="ri-add-line"></i> Upload</a></section>
<section class="filters"><label><i class="ri-search-line"></i><input data-search type="search" placeholder="Search file name"></label><select data-status><option value="All">All Status</option><option>Pending</option><option>Approved</option><option>Rejected</option></select></section>
<section class="file-cards">
    <?php foreach ($files as $file): ?>
    <article class="file-card" data-file data-status="<?php echo h(user_status_label($file['status'])); ?>">
        <i class="ri-file-text-line file-icon"></i>
        <div><h3><?php echo h($file['title']); ?></h3><p><?php echo h($file['category_name']); ?> • <?php echo h(date('M d, Y', strtotime($file['created_at']))); ?></p></div>
        <?php echo user_badge($file['status']); ?>
        <div class="card-actions"><button class="soft-btn" type="button" data-preview-title="<?php echo h($file['title']); ?>" data-preview-body="<?php echo h($file['description'] ?: 'No description provided.'); ?>"><i class="ri-eye-line"></i> View</button><a class="soft-btn" href="<?php echo h(user_file_url($file['filename'])); ?>" download><i class="ri-download-line"></i> Download</a></div>
    </article>
    <?php endforeach; ?>
    <?php if (!$files): ?><article class="empty-card"><i class="ri-folder-open-line"></i><h3>No files found</h3><p>Your uploads will appear here.</p></article><?php endif; ?>
</section>
<div class="modal" id="previewModal"><div class="modal-bg" data-close-modal></div><article class="modal-card"><button data-close-modal><i class="ri-close-line"></i></button><h2 id="previewTitle"></h2><p id="previewBody"></p></article></div>
<?php user_render_footer(); ?>
