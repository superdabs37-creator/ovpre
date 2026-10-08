<?php
require_once __DIR__ . '/includes/layout.php';
$actor = require_super_admin();
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $targetId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

    if (!in_array($action, ['approve', 'reject'], true) || !$targetId || $targetId <= 0) {
        flash('error', 'Choose a valid pending account action.');
        redirect_to('admin-pending-users.php');
    }
    if ($action === 'reject' && ($reason === '' || strlen($reason) > 2000)) {
        flash('error', 'Enter a rejection reason of no more than 2,000 characters.');
        redirect_to('admin-pending-users.php');
    }
    if ((int) $targetId === (int) $actor['id']) {
        flash('error', 'You cannot approve or reject your own account.');
        redirect_to('admin-pending-users.php');
    }

    try {
        db()->beginTransaction();
        $stmt = db()->prepare('SELECT id, fullname, username, email, role, status FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([(int) $targetId]);
        $target = $stmt->fetch();

        if (!$target || $target['status'] !== 'Pending' || !in_array($target['role'], SELF_REGISTERABLE_ROLES, true)) {
            db()->rollBack();
            flash('error', 'That account is no longer an eligible pending registration.');
            redirect_to('admin-pending-users.php');
        }

        if ($action === 'approve') {
            db()->prepare('UPDATE users SET status = "Active", approved_at = NOW(), approved_by = ?, rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL, login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ? AND status = "Pending"')
                ->execute([(int) $actor['id'], (int) $targetId]);
            log_action((int) $actor['id'], 'Approved user account: ' . $target['username']);
            $success = 'Approved account for ' . $target['username'] . '.';
        } else {
            db()->prepare('UPDATE users SET status = "Rejected", approved_at = NULL, approved_by = NULL, rejected_at = NOW(), rejected_by = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status = "Pending"')
                ->execute([(int) $actor['id'], $reason, (int) $targetId]);
            $auditReason = function_exists('mb_substr') ? mb_substr(preg_replace('/\s+/', ' ', $reason), 0, 130) : substr(preg_replace('/\s+/', ' ', $reason), 0, 130);
            log_action((int) $actor['id'], 'Rejected user account: ' . $target['username'] . '; reason: ' . $auditReason);
            $success = 'Rejected account for ' . $target['username'] . '.';
        }
        db()->commit();
        flash('success', $success);
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        error_log('Account approval action failed: ' . $exception->getMessage());
        flash('error', 'The account action could not be completed. Please try again.');
    }
    redirect_to('admin-pending-users.php');
}

$pendingUsers = db()->query('SELECT id, fullname, username, email, role, created_at FROM users WHERE status = "Pending" AND deleted_at IS NULL AND role IN ("Research Coordinator", "Extension Coordinator") ORDER BY created_at ASC')->fetchAll();
$notice = flash();
renderHeader('Pending Account Requests', 'pending-users');
?>
<section class="module-header"><div class="module-icon"><i class="ri-user-follow-line"></i></div><div><p>Access Control</p><h2>Pending Account Requests</h2><span>Only the System Administrator can approve or reject coordinator registrations.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-information-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2>Awaiting Review</h2><span><?php echo count($pendingUsers); ?> request<?php echo count($pendingUsers) === 1 ? '' : 's'; ?></span></div>
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Requested Role</th><th>Registration Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pendingUsers as $pending): ?>
                <tr>
                    <td><?php echo h($pending['fullname']); ?></td>
                    <td><?php echo h($pending['username']); ?></td>
                    <td><?php echo h($pending['email']); ?></td>
                    <td><?php echo h($pending['role']); ?></td>
                    <td><?php echo h(date('M d, Y h:i A', strtotime($pending['created_at']))); ?></td>
                    <td><span class="status-pill status-pending">Pending</span></td>
                    <td>
                        <div class="action-row compact">
                            <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $pending['id']; ?>"><input type="hidden" name="action" value="approve"><button class="primary-btn mini-btn" type="submit"><i class="ri-check-line"></i> Approve</button></form>
                            <details><summary class="danger-btn mini-btn">Reject</summary><form class="editor-panel" method="post"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $pending['id']; ?>"><input type="hidden" name="action" value="reject"><label class="field"><span>Reason</span><textarea name="rejection_reason" rows="3" maxlength="2000" required></textarea></label><button class="danger-btn mini-btn" type="submit">Confirm Rejection</button></form></details>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pendingUsers): ?><tr><td colspan="7">There are no pending coordinator registrations.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter(); ?>
