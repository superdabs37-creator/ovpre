<?php
$user = portal_header('Reports', 'reports');
$isExtensionCoordinator = PORTAL_ROLE === 'Extension Coordinator';
 $isResearchCoordinator = PORTAL_ROLE === 'Research Coordinator';
if ($isExtensionCoordinator) {
    $extension = portal_extension_projects();
} elseif ($isResearchCoordinator) {
    $research = portal_research_projects();
} else {
    $extension = portal_extension_projects();
    $research = portal_research_projects();
    $documents = portal_documents();
}
?>
<section class="report-actions"><button class="primary-btn" data-print-page><i class="ri-printer-line"></i> Print / Save PDF</button><button class="ghost-btn" data-export-table="portalReportTable"><i class="ri-file-excel-2-line"></i> Export CSV</button></section>
<section class="panel">
    <div class="panel-heading"><h2><?php echo $isExtensionCoordinator ? 'Extension Project Reports' : ($isResearchCoordinator ? 'Research Submission Reports' : 'Research and Extension Reports'); ?></h2><span><?php echo $isExtensionCoordinator ? count($extension) : ($isResearchCoordinator ? count($research) : count($research) + count($extension) + count($documents)); ?> records</span></div>
    <div class="table-scroll">
        <table class="data-table" id="portalReportTable" data-paginate="10">
            <thead><tr><?php if ($isExtensionCoordinator): ?><th>Project</th><th>Community Partner</th><?php elseif ($isResearchCoordinator): ?><th>Submission</th><th>Researchers</th><?php else: ?><th>Type</th><th>Title</th><?php endif; ?><th>Owner</th><th>Status</th><th>Date</th><?php if ($isResearchCoordinator): ?><th>Print</th><?php endif; ?></tr></thead>
            <tbody>
                <?php if ($isResearchCoordinator): foreach ($research as $row): ?>
                    <tr><td><?php echo h($row['title']); ?></td><td><?php echo h($row['researchers']); ?></td><td><?php echo h($row['fullname']); ?></td><td><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td><td><button class="ghost-btn mini-btn" type="button" data-print-report aria-label="Print report for <?php echo h($row['title']); ?>"><i class="ri-printer-line"></i> Print</button></td></tr>
                <?php endforeach; elseif (!$isExtensionCoordinator): foreach ($research as $row): ?>
                    <tr><td>Research</td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><?php echo h($row['status']); ?></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr>
                <?php endforeach; endif; ?>
                <?php if (!$isResearchCoordinator): foreach ($extension as $row): ?>
                    <tr><td><?php echo $isExtensionCoordinator ? h($row['project_title']) : 'Extension'; ?></td><td><?php echo h($isExtensionCoordinator ? $row['community_partner'] : $row['project_title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><?php echo h($row['progress_status']); ?></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr>
                <?php endforeach; endif; ?>
                <?php if (!$isExtensionCoordinator && !$isResearchCoordinator): foreach ($documents as $row): ?>
                    <tr><td>Accomplishment / Document</td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><?php echo h($row['status']); ?></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td></tr>
                <?php endforeach; endif; ?>
                <?php if ($isResearchCoordinator && !$research): ?><tr data-pagination-empty><td colspan="6">No research submissions to report yet.</td></tr><?php elseif ($isExtensionCoordinator && !$extension): ?><tr><td colspan="5">No extension projects to report yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php portal_footer(); ?>
