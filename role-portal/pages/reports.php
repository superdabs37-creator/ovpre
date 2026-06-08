<?php
$user = portal_header('Reports', 'reports');
$research = portal_research_projects();
$extension = portal_extension_projects();
$documents = portal_documents();
?>
<section class="report-actions"><button class="primary-btn" data-print-page><i class="ri-printer-line"></i> Print / Save PDF</button><button class="ghost-btn" data-export-table="portalReportTable"><i class="ri-file-excel-2-line"></i> Export CSV</button></section>
<section class="panel"><div class="panel-heading"><h2>Research Reports</h2><span><?php echo count($research); ?> records</span></div><div class="table-scroll"><table class="data-table" id="portalReportTable"><thead><tr><th>Type</th><th>Title</th><th>Owner</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($research as $row): ?><tr><td>Research</td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><?php echo h($row['status']); ?></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?><?php foreach ($extension as $row): ?><tr><td>Extension</td><td><?php echo h($row['project_title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><?php echo h($row['progress_status']); ?></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?><?php foreach ($documents as $row): ?><tr><td>Accomplishment / Document</td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><?php echo h($row['status']); ?></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php portal_footer(); ?>
