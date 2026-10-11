<?php
$user = portal_header('Announcements', 'announcements');
$announcements = portal_announcements();
?>
<section class="panel">
    <div class="panel-heading">
        <h2>Announcements</h2>
        <label class="table-search"><span class="sr-only">Search announcements</span><i class="ri-search-line"></i><input type="search" data-table-search="portalAnnouncementsTable" placeholder="Search announcements"></label>
    </div>
    <div class="table-scroll">
        <table class="data-table" id="portalAnnouncementsTable" data-paginate="10">
            <thead><tr><th>Audience</th><th>Announcement</th><th>Published</th><th>Posted By</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($announcements as $item): ?>
                    <tr><td><span class="tag"><?php echo h($item['audience']); ?></span></td><td><?php echo h($item['title']); ?></td><td><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></td><td><?php echo h($item['fullname']); ?></td><td><button class="ghost-btn announcement-detail-btn" type="button" data-open-modal="announcement-detail-<?php echo (int) $item['id']; ?>"><i class="ri-eye-line"></i> View Details</button></td></tr>
                <?php endforeach; ?>
                <?php if (!$announcements): ?><tr data-pagination-empty><td colspan="5">No announcements yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <p class="search-empty" data-search-empty="portalAnnouncementsTable" hidden>No matching announcements.</p>
    </div>
</section>
<?php foreach ($announcements as $item): ?>
    <div class="modal" id="announcement-detail-<?php echo (int) $item['id']; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="announcement-title-<?php echo (int) $item['id']; ?>">
            <button class="modal-close" type="button" data-close-modal aria-label="Close announcement details"><i class="ri-close-line"></i></button>
            <span class="tag"><?php echo h($item['audience']); ?></span>
            <h2 id="announcement-title-<?php echo (int) $item['id']; ?>"><?php echo h($item['title']); ?></h2>
            <p><?php echo nl2br(h($item['content'])); ?></p>
            <p><small><?php echo h(date('M d, Y', strtotime($item['created_at'])) . ' | ' . $item['fullname']); ?></small></p>
        </section>
    </div>
<?php endforeach; ?>
<?php portal_footer(); ?>
