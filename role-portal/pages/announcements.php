<?php
$user = portal_header('Announcements', 'announcements');
$announcements = portal_announcements();
?>
<section class="announcement-grid"><?php foreach ($announcements as $item): ?><article class="panel announcement-card"><span class="tag"><?php echo h($item['audience']); ?></span><h2><?php echo h($item['title']); ?></h2><p><?php echo h($item['content']); ?></p><small><?php echo h(date('M d, Y', strtotime($item['created_at'])) . ' | ' . $item['fullname']); ?></small></article><?php endforeach; ?><?php if (!$announcements): ?><article class="panel"><p>No announcements yet.</p></article><?php endif; ?></section>
<?php portal_footer(); ?>
