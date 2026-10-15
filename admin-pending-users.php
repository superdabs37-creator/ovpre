<?php
require_once __DIR__ . '/includes/layout.php';
$actor = require_super_admin();
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0 || (int) $id === (int) $actor['id']) {
        flash('error', 'Choose a valid account to approve.');
        redirect_to('admin-pending-users.php');
    }
    $stmt = db()->prepare('SELECT id, username, role, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $id]);
    $target = $stmt->fetch();
    if (!$target || $target['status'] !== 'Pending Approval' || $target['role'] === ROLE_SUPER_ADMIN) {
        flash('error', 'That account is no longer awaiting approval.');
    } elseif (($_POST['decision'] ?? 'accept') === 'reject') {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        db()->prepare('UPDATE users SET status = "Rejected", rejected_at = NOW(), rejected_by = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status = "Pending Approval"')
            ->execute([(int) $actor['id'], $reason !== '' ? $reason : null, (int) $id]);
        log_action((int) $actor['id'], 'Rejected invited account: ' . $target['username']);
        flash('success', 'Rejected account for ' . $target['username'] . '.');
    } else {
        db()->prepare('UPDATE users SET status = "Active", approved_at = NOW(), approved_by = ?, login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ? AND status = "Pending Approval"')
            ->execute([(int) $actor['id'], (int) $id]);
        log_action((int) $actor['id'], 'Approved invited account: ' . $target['username']);
        flash('success', 'Accepted account for ' . $target['username'] . '.');
    }
    redirect_to('admin-pending-users.php');
}
$pendingUsers = db()->query('SELECT id, fullname, username, email, role, department_id, created_at FROM users WHERE status = "Pending Approval" AND deleted_at IS NULL ORDER BY created_at ASC')->fetchAll();
$notice = flash();
renderHeader('Pending Account Approvals', 'pending-users');
?>
<section class="module-header"><div class="module-icon"><i class="ri-user-follow-line"></i></div><div><p>Access Control</p><h2>Pending Account Approvals</h2><span>Review invited profiles after users set their passwords.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-information-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel"><div class="panel-heading"><span><?php echo count($pendingUsers); ?> account<?php echo count($pendingUsers) === 1 ? '' : 's'; ?></span></div><div class="table-scroll"><table class="data-table portal-table"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Requested Role</th><th>Invited</th><th>Status</th><th style="min-width: 220px;">Action</th></tr></thead><tbody><?php foreach ($pendingUsers as $pending): ?><tr><td><?php echo h($pending['fullname']); ?></td><td><?php echo h($pending['username']); ?></td><td><?php echo h($pending['email']); ?></td><td><?php echo h($pending['role']); ?></td><td><?php echo h(date('M d, Y', strtotime($pending['created_at']))); ?></td><td><span class="status-pill status-pending">Pending Approval</span></td><td><div class="archive-record-actions" style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap;"><form method="post" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $pending['id']; ?>"><input type="hidden" name="decision" value="accept"><button class="primary-btn action-btn" style="background: linear-gradient(135deg, #2E7D32 0%, #4CAF50 100%); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.24); box-shadow: 0 4px 12px rgba(46, 125, 50, 0.22); border-radius: 12px; min-height: 36px; padding: 0 14px; font-size: 0.86rem; font-weight: 700;" type="submit"><i class="ri-check-line"></i> <span>Accepted</span></button></form><form method="post" style="margin:0;" onsubmit="const r = prompt('Reason for rejection (optional):'); if (r === null) return false; this.reason.value = r; return true;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $pending['id']; ?>"><input type="hidden" name="decision" value="reject"><input type="hidden" name="reason" value=""><button class="danger-btn action-btn" style="background: #fee2e2; color: var(--danger); border: 1px solid rgba(220, 38, 38, 0.25); border-radius: 12px; min-height: 36px; padding: 0 14px; font-size: 0.86rem; font-weight: 700;" type="submit"><i class="ri-close-line"></i> <span>Rejected</span></button></form></div></td></tr><?php endforeach; ?><?php if (!$pendingUsers): ?><tr><td colspan="7">There are no accounts awaiting approval.</td></tr><?php endif; ?></tbody></table></div></section>
<?php renderFooter(); ?>
