<?php
$user = portal_header('Reports', 'reports');
$isExtensionRole = in_array(PORTAL_ROLE, ['Extension Director', 'Extension Coordinator', 'Faculty Extensionist'], true);
$isResearchRole = in_array(PORTAL_ROLE, ['Research Director', 'Research Coordinator', 'Researcher'], true);
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
$statusFilter = (string) ($_GET['status'] ?? 'All');
$departmentFilter = (string) ($_GET['department'] ?? 'All');
$dateIsValid = static function (string $date): bool { $parsed = DateTime::createFromFormat('!Y-m-d', $date); return $parsed && $parsed->format('Y-m-d') === $date; };
if (!$dateIsValid($from)) $from = '';
if (!$dateIsValid($to)) $to = '';
$departments = departments();
if ($isExtensionRole) {
    $extension = portal_extension_projects();
} elseif ($isResearchRole) {
    $research = portal_research_projects();
} else {
    $extension = portal_extension_projects();
    $research = portal_research_projects();
    $documents = portal_documents();
}
$matchesReportFilters = static function (array $row) use ($from, $to, $statusFilter, $departmentFilter): bool {
    $recordDate = substr((string) ($row['created_at'] ?? ''), 0, 10);
    $recordStatus = (string) ($row['status'] ?? $row['progress_status'] ?? '');
    if ($statusFilter !== 'All' && $recordStatus !== $statusFilter) return false;
    if ($from !== '' && $recordDate < $from) return false;
    if ($to !== '' && $recordDate > $to) return false;
    if ($departmentFilter !== 'All' && (string) ($row['department_name'] ?? '') !== $departmentFilter) return false;
    return true;
};
if (isset($research)) $research = array_values(array_filter($research, $matchesReportFilters));
if (isset($extension)) $extension = array_values(array_filter($extension, $matchesReportFilters));
if (isset($documents)) $documents = array_values(array_filter($documents, $matchesReportFilters));
?>
<section class="report-actions"><button class="primary-btn" data-print-page><i class="ri-printer-line"></i> Print / Save PDF</button><button class="ghost-btn" data-export-table="portalReportTable"><i class="ri-file-excel-2-line"></i> Export CSV</button></section>
<section class="panel"><form method="get" class="report-filter-form"><label class="field"><span>From</span><input type="date" name="from" value="<?php echo h($from); ?>"></label><label class="field"><span>To</span><input type="date" name="to" value="<?php echo h($to); ?>"></label><label class="field"><span>Status</span><select name="status"><option>All</option><?php foreach (['Pending Coordinator Review', 'Pending Director Review', 'Pending Executive Approval', 'Approved', 'Needs Revision', 'Declined', 'Planning', 'Ongoing', 'Completed'] as $status): ?><option <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label><label class="field"><span>College</span><select name="department"><option>All</option><?php foreach ($departments as $department): ?><option <?php echo $departmentFilter === $department['department_name'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label><button class="primary-btn" type="submit"><i class="ri-filter-line"></i> Apply Filters</button></form></section>
<section class="panel">
    <div class="panel-heading"><h2><?php echo $isExtensionRole ? 'Extension Project Reports' : ($isResearchRole ? 'Research Submission Reports' : 'Research and Extension Reports'); ?></h2><span><?php echo $isExtensionRole ? count($extension) : ($isResearchRole ? count($research) : count($research) + count($extension) + count($documents)); ?> records</span></div>
    <div class="table-scroll">
        <table class="data-table" id="portalReportTable" data-paginate="10">
            <thead><tr><?php if ($isExtensionRole): ?><th>Project</th><th>Community Partner</th><?php elseif ($isResearchRole): ?><th>Submission</th><th>Researchers</th><?php else: ?><th>Type</th><th>Title</th><?php endif; ?><th>Owner</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($isResearchRole && isset($research)): foreach ($research as $row): ?>
                    <tr><td><?php echo h($row['title']); ?></td><td><?php echo h($row['researchers'] ?? 'N/A'); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status'] === 'Declined' ? 'Rejected' : $row['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td><td><button class="ghost-btn mini-btn" type="button" data-print-report aria-label="Print report for <?php echo h($row['title']); ?>"><i class="ri-printer-line"></i> Print</button></td></tr>
                <?php endforeach; endif; ?>
                <?php if ($isExtensionRole && isset($extension)): foreach ($extension as $row): ?><?php $extStatus = $row['status'] ?? $row['progress_status'] ?? 'Pending Coordinator Review'; ?>
                    <tr><td><?php echo h($row['project_title']); ?></td><td><?php echo h($row['community_partner'] ?? 'N/A'); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($extStatus)); ?>"><?php echo h($extStatus === 'Declined' ? 'Rejected' : $extStatus); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td><td><button class="ghost-btn mini-btn" type="button" data-print-report aria-label="Print report for <?php echo h($row['project_title']); ?>"><i class="ri-printer-line"></i> Print</button></td></tr>
                <?php endforeach; endif; ?>
                <?php if (!$isExtensionRole && !$isResearchRole): ?>
                    <?php foreach ($research as $row): ?>
                        <tr><td>Research</td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td><td><button class="ghost-btn mini-btn" type="button" data-print-report><i class="ri-printer-line"></i> Print</button></td></tr>
                    <?php endforeach; ?>
                    <?php foreach ($extension as $row): ?><?php $extStatus = $row['status'] ?? $row['progress_status'] ?? 'Pending Coordinator Review'; ?>
                        <tr><td>Extension</td><td><?php echo h($row['project_title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($extStatus)); ?>"><?php echo h($extStatus); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td><td><button class="ghost-btn mini-btn" type="button" data-print-report><i class="ri-printer-line"></i> Print</button></td></tr>
                    <?php endforeach; ?>
                    <?php foreach ($documents as $row): ?>
                        <tr><td>Document</td><td><?php echo h($row['title']); ?></td><td><?php echo h($row['fullname']); ?></td><td><span class="status-pill status-<?php echo h(statusClass($row['status'])); ?>"><?php echo h($row['status']); ?></span></td><td><?php echo h(date('M d, Y', strtotime($row['created_at']))); ?></td><td><button class="ghost-btn mini-btn" type="button" data-print-report><i class="ri-printer-line"></i> Print</button></td></tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($isResearchRole && empty($research)): ?><tr data-pagination-empty><td colspan="6">No research submissions to report yet.</td></tr><?php elseif ($isExtensionRole && empty($extension)): ?><tr data-pagination-empty><td colspan="6">No extension projects to report yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php portal_footer(); ?>
