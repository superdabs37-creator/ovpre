<?php
require_once 'includes/student-layout.php';
$user = student_render_header('Announcements', 'announcements');
$announcements = student_announcements();
$featured = $announcements[0] ?? null;
$categories = array_values(array_unique(array_map(static fn($item) => $item['audience'], $announcements)));
?>
<section class="module-header"><div class="module-icon"><i class="ri-megaphone-line"></i></div><div><p>OVPRE Bulletin</p><h2>Announcements</h2><span>Read official notices, research calls, extension updates, and system advisories.</span></div></section>
<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input id="announcementSearch" type="search" placeholder="Search announcements"></div>
    <select class="filter-select" id="categoryFilter"><option value="All">All Categories</option><?php foreach ($categories as $category): ?><option><?php echo h($category); ?></option><?php endforeach; ?></select>
</section>
<?php if ($featured): ?>
<section class="featured-card">
    <div class="featured-content">
        <span class="featured-badge"><i class="ri-star-line"></i> Featured</span>
        <h3><?php echo h($featured['title']); ?></h3>
        <p><?php echo h(mb_strimwidth(strip_tags($featured['content']), 0, 220, '...')); ?></p>
        <div class="announcement-meta"><span><i class="ri-calendar-line"></i><?php echo h(date('M d, Y', strtotime($featured['created_at']))); ?></span><span><i class="ri-user-line"></i><?php echo h($featured['author']); ?></span></div>
        <button class="primary-btn read-more-btn" type="button" data-title="<?php echo h($featured['title']); ?>" data-category="<?php echo h($featured['audience']); ?>" data-date="<?php echo h(date('M d, Y', strtotime($featured['created_at']))); ?>" data-author="<?php echo h($featured['author']); ?>" data-content="<?php echo h($featured['content']); ?>"><i class="ri-book-open-line"></i> Read More</button>
    </div>
</section>
<?php endif; ?>
<section class="announcement-grid">
    <?php foreach ($announcements as $item): ?>
    <article class="announcement-card" data-announcement-card data-category="<?php echo h($item['audience']); ?>">
        <div class="announcement-card-head"><span class="category-badge"><?php echo h($item['audience']); ?></span><i class="ri-megaphone-line"></i></div>
        <h3><?php echo h($item['title']); ?></h3>
        <p><?php echo h(mb_strimwidth(strip_tags($item['content']), 0, 150, '...')); ?></p>
        <div class="announcement-meta"><span><i class="ri-calendar-line"></i><?php echo h(date('M d, Y', strtotime($item['created_at']))); ?></span><span><i class="ri-user-line"></i><?php echo h($item['author']); ?></span></div>
        <button class="ghost-btn read-more-btn" type="button" data-title="<?php echo h($item['title']); ?>" data-category="<?php echo h($item['audience']); ?>" data-date="<?php echo h(date('M d, Y', strtotime($item['created_at']))); ?>" data-author="<?php echo h($item['author']); ?>" data-content="<?php echo h($item['content']); ?>"><i class="ri-book-open-line"></i> Read More</button>
    </article>
    <?php endforeach; ?>
</section>
<section class="announcement-empty" id="announcementEmpty"><i class="ri-search-line"></i><h3>No announcements found</h3><p>Try a different search or category.</p></section>
<div class="modal" id="announcementModal" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><article class="modal-card"><button class="modal-close" type="button" data-close-modal><i class="ri-close-line"></i></button><span class="category-badge" id="modalCategory"></span><h2 id="modalTitle"></h2><div class="announcement-meta"><span id="modalDate"></span><span id="modalAuthor"></span></div><p id="modalContent"></p></article></div>
<?php student_render_footer(); ?>
