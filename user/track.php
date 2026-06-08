<?php
require_once 'includes/user-layout.php';
$user = user_render_header('Track Status', 'track');
$tracking = trim((string) ($_GET['tracking'] ?? ''));
$doc = $tracking ? user_document_by_tracking($tracking, (int) $user['id']) : null;
?>
<section class="simple-header"><div><h2>Track Document</h2><p>Enter your tracking number to see the latest status.</p></div></section>
<form class="track-box" method="get"><i class="ri-route-line"></i><input name="tracking" value="<?php echo h($tracking); ?>" placeholder="Example: REC-000123" required><button class="primary-btn">Track</button></form>
<?php if ($tracking && !$doc): ?><div class="notice error"><i class="ri-error-warning-line"></i>No document found for that tracking number.</div><?php endif; ?>
<?php if ($doc): ?>
<section class="result-card">
    <div class="result-head"><div><span>Tracking Number</span><h2><?php echo h(user_tracking_number((int) $doc['id'])); ?></h2><p><?php echo h($doc['title']); ?></p></div><?php echo user_badge($doc['status']); ?></div>
    <div class="timeline">
        <article class="done"><i class="ri-upload-cloud-line"></i><div><h3>Uploaded</h3><p><?php echo h(date('M d, Y h:i A', strtotime($doc['created_at']))); ?></p></div></article>
        <article class="<?php echo in_array($doc['status'], ['Under Review', 'Approved', 'Declined'], true) ? 'done' : ''; ?>"><i class="ri-search-eye-line"></i><div><h3>Under Review</h3><p><?php echo $doc['status'] === 'Pending' ? 'Waiting for office review.' : 'Review has started.'; ?></p></div></article>
        <article class="<?php echo in_array($doc['status'], ['Approved', 'Declined'], true) ? 'done' : ''; ?>"><i class="ri-flag-line"></i><div><h3><?php echo h(user_status_label($doc['status'])); ?></h3><p>Remarks: <?php echo h($doc['description'] ?: 'No remarks available.'); ?></p></div></article>
    </div>
</section>
<?php endif; ?>
<?php user_render_footer(); ?>
