<?php
require_once 'includes/user-layout.php';
$user = user_render_header('Welcome', 'dashboard');
$stats = user_stats((int) $user['id']);
$files = array_slice(user_documents((int) $user['id']), 0, 5);
$notes = user_notifications((int) $user['id'], 3);
?>
<section class="hero-card">
    <div>
        <p>Hello, <?php echo h($user['fullname']); ?></p>
        <h2>Your files are easy to upload, track, and download here.</h2>
    </div>
    <a class="big-action" href="upload.php"><i class="ri-upload-cloud-line"></i> Upload File</a>
</section>
<section class="stats-grid">
    <article><i class="ri-folder-3-line"></i><span>My Documents</span><strong><?php echo $stats['total']; ?></strong></article>
    <article><i class="ri-time-line"></i><span>Pending</span><strong><?php echo $stats['pending']; ?></strong></article>
    <article><i class="ri-check-line"></i><span>Approved</span><strong><?php echo $stats['approved']; ?></strong></article>
    <article><i class="ri-close-line"></i><span>Rejected</span><strong><?php echo $stats['rejected']; ?></strong></article>
</section>
<section class="two-column">
    <article class="panel">
        <div class="panel-head"><h2>Recent Files</h2><a href="my-files.php">View all</a></div>
        <div class="file-list">
            <?php foreach ($files as $file): ?>
                <a class="file-row" href="track.php?tracking=<?php echo h(user_tracking_number((int) $file['id'])); ?>"><i class="ri-file-text-line"></i><div><strong><?php echo h($file['title']); ?></strong><span><?php echo h(date('M d, Y', strtotime($file['created_at']))); ?></span></div><?php echo user_badge($file['status']); ?></a>
            <?php endforeach; ?>
            <?php if (!$files): ?><p class="empty">No files yet. Start with Upload File.</p><?php endif; ?>
        </div>
    </article>
    <article class="panel">
        <div class="panel-head"><h2>Notifications</h2><a href="notifications.php">Open</a></div>
        <div class="note-list">
            <?php foreach ($notes as $note): ?><article class="note <?php echo h(strtolower($note['status'])); ?>"><span></span><div><p><?php echo h($note['message']); ?></p><small><?php echo h(date('M d, Y', strtotime($note['created_at']))); ?></small></div></article><?php endforeach; ?>
            <?php if (!$notes): ?><p class="empty">No notifications right now.</p><?php endif; ?>
        </div>
    </article>
</section>
<?php user_render_footer(); ?>
