<?php
$user = portal_header('Submission Status', 'status');
$research = portal_research_projects();
$documents = portal_documents();
?>
<section class="panel"><div class="panel-heading"><h2>Research Submission Status</h2><span>Pending | Under Review | Approved | Rejected</span></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Submission</th><th>Submitted By</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($research as $row): ?><tr><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><div class="panel-heading"><h2>Supporting Document Status</h2></div><div class="table-scroll"><table class="data-table"><thead><tr><th>Document</th><th>Uploaded By</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($documents as $row): ?><tr><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php portal_footer(); ?>
