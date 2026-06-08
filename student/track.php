<?php
require_once 'includes/student-layout.php';
$user = student_render_header('Track Status', 'track');
$documents = student_documents((int) $user['id']);
$selectedId = (int) ($_GET['id'] ?? ($documents[0]['id'] ?? 0));
$doc = $selectedId ? student_document($selectedId, (int) $user['id']) : null;
$steps = ['Uploaded', 'Under Review', student_status_label($doc['status'] ?? 'Pending')];
?>
<section class="module-header"><div class="module-icon"><i class="ri-route-line"></i></div><div><p>Tracking Center</p><h2>Track Status</h2><span>Follow your document from upload through OVPRE approval or rejection.</span></div></section>
<section class="tool-panel">
    <form class="search-control track-form" method="get"><i class="ri-search-line"></i><select name="id" onchange="this.form.submit()"><option value="0">Choose tracking number</option><?php foreach ($documents as $item): ?><option value="<?php echo (int) $item['id']; ?>" <?php echo (int) $item['id'] === $selectedId ? 'selected' : ''; ?>><?php echo h(student_tracking_number((int) $item['id']) . ' - ' . $item['title']); ?></option><?php endforeach; ?></select></form>
    <a class="primary-btn" href="documents.php"><i class="ri-folder-3-line"></i> My Documents</a>
</section>
<?php if ($doc): ?>
<section class="status-hero">
    <div><p>Tracking Number</p><h2><?php echo h(student_tracking_number((int) $doc['id'])); ?></h2><span><?php echo h($doc['title']); ?></span></div>
    <?php echo student_badge($doc['status']); ?>
</section>
<section class="timeline-panel">
    <article class="timeline-step done"><span><i class="ri-upload-cloud-line"></i></span><div><h3>Uploaded</h3><p>Your file was submitted to the record system.</p><small><?php echo h(date('M d, Y h:i A', strtotime($doc['created_at']))); ?></small></div></article>
    <article class="timeline-step <?php echo in_array($doc['status'], ['Under Review', 'Approved', 'Declined'], true) ? 'done' : ''; ?>"><span><i class="ri-search-eye-line"></i></span><div><h3>Under Review</h3><p>OVPRE staff evaluates completeness, category, and supporting details.</p><small><?php echo $doc['status'] === 'Pending' ? 'Waiting for reviewer action' : 'Review activity recorded'; ?></small></div></article>
    <article class="timeline-step <?php echo in_array($doc['status'], ['Approved', 'Declined'], true) ? 'done' : ''; ?>"><span><i class="<?php echo $doc['status'] === 'Declined' ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'; ?>"></i></span><div><h3><?php echo h(student_status_label($doc['status'])); ?></h3><p><?php echo $doc['status'] === 'Declined' ? 'Please check remarks and upload a corrected version when required.' : 'Final review result appears here once posted by the office.'; ?></p><small>Admin notes: <?php echo h($doc['description'] ?: 'No remarks available.'); ?></small></div></article>
</section>
<?php else: ?>
<section class="panel empty-state"><i class="ri-route-line"></i><h2>No tracking record selected</h2><p>Upload or select a document to view its timeline.</p></section>
<?php endif; ?>
<?php student_render_footer(); ?>
