<?php
require_once 'includes/user-layout.php';
$user = user_render_header('Announcements', 'announcements');
$announcements = user_announcements();
$featured = $announcements[0] ?? null;
$categories = ['Academic', 'Research', 'Events', 'System Updates'];
?>
<section class="simple-header"><div><h2>Announcements</h2><p>Friendly updates from the Office of the Vice President for Research and Extension.</p></div></section>
<section class="filters"><select data-announcement-filter><option value="All">All Categories</option><?php foreach ($categories as $category): ?><option><?php echo h($category); ?></option><?php endforeach; ?></select></section>
<?php if ($featured): ?><section class="featured"><span>Featured</span><h2><?php echo h($featured['title']); ?></h2><p><?php echo h(mb_strimwidth(strip_tags($featured['content']), 0, 180, '...')); ?></p><button class="primary-btn read-more" data-title="<?php echo h($featured['title']); ?>" data-content="<?php echo h($featured['content']); ?>">Read more</button></section><?php endif; ?>
<section class="announcement-list">
    <?php foreach ($announcements as $item): $category = $item['audience'] === 'All' ? 'System Updates' : ($item['audience'] === 'Researchers' ? 'Research' : 'Academic'); ?>
    <article data-announcement data-category="<?php echo h($category); ?>"><span><?php echo h($category); ?></span><h3><?php echo h($item['title']); ?></h3><p><?php echo h(mb_strimwidth(strip_tags($item['content']), 0, 130, '...')); ?></p><button class="soft-btn read-more" data-title="<?php echo h($item['title']); ?>" data-content="<?php echo h($item['content']); ?>">Read more</button></article>
    <?php endforeach; ?>
</section>
<div class="modal" id="readModal"><div class="modal-bg" data-close-modal></div><article class="modal-card"><button data-close-modal><i class="ri-close-line"></i></button><h2 id="modalTitle"></h2><p id="modalContent"></p></article></div>
<?php user_render_footer(); ?>
