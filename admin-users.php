<?php
require_once __DIR__ . '/includes/layout.php';
$actor = require_super_admin();
verify_csrf();

$rolePurposes = [
    ROLE_VICE_PRESIDENT => 'Institutional oversight',
    ROLE_OVPRE_STAFF => 'Records management',
    ROLE_RESEARCH_COORDINATOR => 'Research monitoring',
    ROLE_EXTENSION_COORDINATOR => 'Extension monitoring',
    'Faculty Researcher' => 'Research submissions',
    'Evaluator/Reviewer' => 'Proposal evaluation',
    'User' => 'General access',
];
$availableRolePurposes = array_diff_key($rolePurposes, [
    'Evaluator/Reviewer' => true,
    'User' => true,
]);

function user_profile_fields(array $input): array {
    $allowedGenders = ['Male', 'Female', 'Other', 'Prefer not to say'];
    $gender = trim((string) ($input['gender'] ?? ''));
    $gender = $gender === '' ? null : $gender;
    if ($gender !== null && !in_array($gender, $allowedGenders, true)) {
        throw new InvalidArgumentException('Choose a valid gender option.');
    }

    $birthday = trim((string) ($input['birthday'] ?? ''));
    if ($birthday !== '') {
        $parsedBirthday = DateTime::createFromFormat('!Y-m-d', $birthday);
        if (!$parsedBirthday || $parsedBirthday->format('Y-m-d') !== $birthday || $parsedBirthday > new DateTime('today')) {
            throw new InvalidArgumentException('Enter a valid birthday that is not in the future.');
        }
    } else {
        $birthday = null;
    }

    $phone = trim((string) ($input['phone'] ?? ''));
    if ($phone !== '' && !preg_match('/^[0-9+().\s-]{7,30}$/', $phone)) {
        throw new InvalidArgumentException('Enter a valid phone number.');
    }
    return [$gender, $birthday, $phone === '' ? null : $phone];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if (in_array($action, ['suspend', 'reactivate'], true)) {
            if (!$id || $id <= 0) {
                throw new InvalidArgumentException('Choose a valid user account.');
            }
            $stmt = db()->prepare('SELECT id, username, role, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([(int) $id]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('User account was not found.');
            }
            if ((int) $target['id'] === (int) $actor['id'] || $target['role'] === ROLE_SUPER_ADMIN) {
                throw new InvalidArgumentException('You cannot suspend or reactivate this administrator account here.');
            }
            if ($action === 'suspend') {
                if ($target['status'] !== 'Active') {
                    throw new InvalidArgumentException('Only active accounts can be suspended.');
                }
                db()->prepare('UPDATE users SET status = "Suspended", updated_at = NOW() WHERE id = ? AND status = "Active"')->execute([(int) $id]);
                log_action((int) $actor['id'], 'Suspended user account: ' . $target['username']);
                flash('success', 'User account suspended.');
            } else {
                if (!in_array($target['status'], ['Suspended', 'Inactive', 'Locked'], true)) {
                    throw new InvalidArgumentException('This account cannot be reactivated from its current status.');
                }
                db()->prepare('UPDATE users SET status = "Active", login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?')->execute([(int) $id]);
                log_action((int) $actor['id'], 'Reactivated user account: ' . $target['username']);
                flash('success', 'User account reactivated.');
            }
        } elseif ($action === 'reset_password') {
            if (!$id || $id <= 0) {
                throw new InvalidArgumentException('Choose a valid user account.');
            }
            $stmt = db()->prepare('SELECT username, role FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([(int) $id]);
            $target = $stmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('User account was not found.');
            }
            if ($target['role'] === ROLE_SUPER_ADMIN) {
                throw new InvalidArgumentException('Reset a Super Admin password through the account recovery process.');
            }
            $temporaryPassword = 'OVPRE-' . bin2hex(random_bytes(8)) . '!';
            db()->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?')
                ->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), (int) $id]);
            log_action((int) $actor['id'], 'Reset password for user account: ' . $target['username']);
            flash('success', 'Temporary password for ' . $target['username'] . ': ' . $temporaryPassword);
        } elseif ($action === 'edit') {
            if (!$id || $id <= 0) {
                throw new InvalidArgumentException('Choose a valid user account.');
            }
            $targetStmt = db()->prepare('SELECT id, username, role, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $targetStmt->execute([(int) $id]);
            $target = $targetStmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('User account was not found.');
            }

            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            [$gender, $birthday, $phone] = user_profile_fields($_POST);
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $submittedRole = (string) ($_POST['role'] ?? '');

            if ($fullname === '' || strlen($fullname) > 160 || !preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
                throw new InvalidArgumentException('Enter a valid full name and a 3–80 character username.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $duplicate = db()->prepare('SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
            $duplicate->execute([$username, $email, (int) $id]);
            $existing = $duplicate->fetch();
            if ($existing) {
                throw new InvalidArgumentException(strcasecmp((string) $existing['username'], $username) === 0 ? 'That username is already in use.' : 'That email address is already registered.');
            }

            $role = $target['role'];
            if ($target['role'] !== ROLE_SUPER_ADMIN && !in_array($target['status'], ['Pending', 'Rejected'], true) && (int) $target['id'] !== (int) $actor['id']) {
                if ($submittedRole !== $target['role'] && !array_key_exists($submittedRole, $availableRolePurposes)) {
                    throw new InvalidArgumentException('Choose a valid user role.');
                }
                $role = $submittedRole;
            }
            if ($password !== '' && (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password))) {
                throw new InvalidArgumentException('Use a password with at least 12 characters, uppercase and lowercase letters, a number, and a symbol.');
            }

            if ($password !== '') {
                db()->prepare('UPDATE users SET fullname = ?, gender = ?, birthday = ?, email = ?, phone = ?, username = ?, password = ?, role = ?, department_id = ?, login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?')
                    ->execute([$fullname, $gender, $birthday, $email, $phone, $username, password_hash($password, PASSWORD_DEFAULT), $role, $departmentId > 0 ? $departmentId : null, (int) $id]);
            } else {
                db()->prepare('UPDATE users SET fullname = ?, gender = ?, birthday = ?, email = ?, phone = ?, username = ?, role = ?, department_id = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$fullname, $gender, $birthday, $email, $phone, $username, $role, $departmentId > 0 ? $departmentId : null, (int) $id]);
            }
            if ($role !== $target['role']) {
                log_action((int) $actor['id'], 'Changed role for ' . $target['username'] . ' from ' . $target['role'] . ' to ' . $role);
            }
            log_action((int) $actor['id'], ($password !== '' ? 'Edited account and reset password: ' : 'Edited user account: ') . $username);
            flash('success', 'User account updated. Its approval status was not changed.');
        } elseif ($action === 'delete') {
            if (!$id || $id <= 0 || (int) $id === (int) $actor['id']) {
                throw new InvalidArgumentException('You cannot deactivate your own administrator account.');
            }
            $stmt = db()->prepare('SELECT username, role FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([(int) $id]);
            $target = $stmt->fetch();
            if (!$target || $target['role'] === ROLE_SUPER_ADMIN) {
                throw new InvalidArgumentException('That account cannot be deactivated here.');
            }
            db()->prepare('UPDATE users SET status = "Inactive", deleted_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([(int) $id]);
            log_action((int) $actor['id'], 'Deactivated user account: ' . $target['username']);
            flash('success', 'User account deactivated. Existing records remain intact.');
        } else {
            throw new InvalidArgumentException('Choose a valid user management action.');
        }
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) === 1062) {
            flash('error', 'That username or email address is already registered.');
        } else {
            error_log('User management action failed: ' . $exception->getMessage());
            flash('error', 'The user account change could not be completed. Please try again.');
        }
    } catch (Throwable $exception) {
        error_log('User management action failed: ' . $exception->getMessage());
        flash('error', 'The user account change could not be completed. Please try again.');
    }
    redirect_to('admin-users.php');
}

$statusOptions = ['All', 'Active', 'Pending', 'Rejected', 'Suspended', 'Inactive', 'Locked'];
$statusFilter = (string) ($_GET['status'] ?? 'All');
if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = 'All';
}
$sql = 'SELECT u.*, d.department_name, creator.fullname AS created_by_name, approver.fullname AS approved_by_name, rejecter.fullname AS rejected_by_name FROM users u LEFT JOIN departments d ON d.id = u.department_id LEFT JOIN users creator ON creator.id = u.created_by LEFT JOIN users approver ON approver.id = u.approved_by LEFT JOIN users rejecter ON rejecter.id = u.rejected_by WHERE u.deleted_at IS NULL';
$params = [];
if ($statusFilter !== 'All') {
    $sql .= ' AND u.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY u.created_at DESC';
$usersStmt = db()->prepare($sql);
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();
$notice = flash();
$departments = departments();
renderHeader('User Management', 'users');
?>
<section class="module-header"><div class="module-icon"><i class="ri-team-line"></i></div><div><p>Access Control</p><h2>User Management</h2><span>Manage roles and access. Registration approval is handled in Pending Account Requests.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-information-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search users"></div>
    <form method="get" class="action-row"><label class="field"><span>Account Status</span><select class="filter-select" name="status" onchange="this.form.submit()"><?php foreach ($statusOptions as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label></form>
</section>
<section class="panel">
    <div class="table-scroll"><table class="data-table portal-table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Department</th><th>Role</th><th>Registered</th><th>Review</th><th>Last Login</th><th>Status</th><th>Manage</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr data-table-row>
                <td><?php echo (int) $user['id']; ?></td>
                <td><?php echo h($user['fullname']); ?></td>
                <td><?php echo h($user['email']); ?></td>
                <td><?php echo h($user['username']); ?></td>
                <td><?php echo h($user['department_name'] ?? 'Unassigned'); ?></td>
                <td><?php echo h($user['role']); ?></td>
                <td><?php echo h(date('M d, Y', strtotime($user['created_at']))); ?></td>
                <td><?php if ($user['status'] === 'Rejected'): ?><?php echo h($user['rejection_reason'] ?? 'No reason recorded'); ?><br><small><?php echo h($user['rejected_by_name'] ?? 'Administrator'); ?> · <?php echo $user['rejected_at'] ? h(date('M d, Y', strtotime($user['rejected_at']))) : ''; ?></small><?php elseif ($user['status'] === 'Active' && $user['approved_at']): ?>Approved by <?php echo h($user['approved_by_name'] ?? 'Administrator'); ?><br><small><?php echo h(date('M d, Y', strtotime($user['approved_at']))); ?></small><?php else: ?>—<?php endif; ?></td>
                <td><?php echo $user['last_login_at'] ? h(date('M d, Y h:i A', strtotime($user['last_login_at']))) : 'Never'; ?></td>
                <td><span class="status-pill status-<?php echo h(statusClass($user['status'])); ?>"><?php echo h($user['status']); ?></span></td>
                <td><details class="user-actions-dropdown">
                    <summary class="user-actions-trigger"><i class="ri-more-2-fill"></i><span>Manage</span><i class="ri-arrow-down-s-line user-actions-chevron"></i></summary>
                    <div class="user-action-menu">
                        <button class="ghost-btn action-btn" type="button" data-open-modal="edit-user-<?php echo (int) $user['id']; ?>"><i class="ri-edit-line"></i><span>Edit</span></button>
                        <?php if ($user['role'] !== ROLE_SUPER_ADMIN && (int) $user['id'] !== (int) $actor['id']): ?>
                            <?php if ($user['status'] === 'Active'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="danger-btn action-btn" type="submit"><i class="ri-forbid-line"></i><span>Suspend</span></button></form>
                            <?php elseif (in_array($user['status'], ['Suspended', 'Inactive', 'Locked'], true)): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reactivate"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="primary-btn action-btn" type="submit"><i class="ri-user-follow-line"></i><span>Reactivate</span></button></form><?php endif; ?>
                            <?php if (in_array($user['status'], ['Active', 'Suspended', 'Inactive', 'Locked'], true)): ?><form method="post" onsubmit="return confirm('Generate a temporary password for this user?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="ghost-btn action-btn" type="submit"><i class="ri-key-2-line"></i><span>Reset Password</span></button></form><?php endif; ?>
                            <form method="post" onsubmit="return confirm('Deactivate this account? Existing records remain intact.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="danger-btn action-btn" type="submit"><i class="ri-delete-bin-line"></i><span>Deactivate</span></button></form>
                        <?php endif; ?>
                    </div>
                </details></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="11">No user accounts found for this status.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
<?php foreach ($users as $user): ?>
    <div class="modal" id="edit-user-<?php echo (int) $user['id']; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post">
            <button class="modal-close" type="button" data-close-modal aria-label="Close edit user form"><i class="ri-close-line"></i></button>
            <span class="category-badge">Edit User</span><h2><?php echo h($user['fullname']); ?></h2>
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
            <div class="form-grid">
                <label class="field"><span>Full Name</span><input name="fullname" maxlength="160" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Email</span><input name="email" type="email" maxlength="190" value="<?php echo h($user['email']); ?>" required></label>
                <label class="field"><span>Gender</span><select name="gender"><option value="">Not specified</option><?php foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $genderOption): ?><option value="<?php echo h($genderOption); ?>" <?php echo ($user['gender'] ?? '') === $genderOption ? 'selected' : ''; ?>><?php echo h($genderOption); ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Birthday</span><input name="birthday" type="date" max="<?php echo h(date('Y-m-d')); ?>" value="<?php echo h($user['birthday'] ?? ''); ?>"></label>
                <label class="field"><span>Phone</span><input name="phone" type="tel" maxlength="30" value="<?php echo h($user['phone'] ?? ''); ?>"></label>
                <label class="field"><span>Username</span><input name="username" maxlength="80" value="<?php echo h($user['username']); ?>" required></label>
                <label class="field"><span>New Password</span><input name="password" type="password" minlength="12" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
                <label class="field"><span>Department</span><select name="department_id"><option value="0">Unassigned</option><?php foreach ($departments as $department): ?><option value="<?php echo (int) $department['id']; ?>" <?php echo (int) ($user['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
                <?php if ($user['role'] === ROLE_SUPER_ADMIN || in_array($user['status'], ['Pending', 'Rejected'], true) || (int) $user['id'] === (int) $actor['id']): ?><div class="field"><span>Role</span><strong><?php echo h($user['role']); ?></strong><small>Role changes are disabled for this account in its current state.</small></div>
                <?php elseif (!array_key_exists($user['role'], $availableRolePurposes)): ?><label class="field"><span>Role</span><strong><?php echo h($user['role']); ?></strong><small>This legacy role is no longer available for assignment.</small><input type="hidden" name="role" value="<?php echo h($user['role']); ?>"></label>
                <?php else: ?><label class="field"><span>Role</span><select name="role" required><?php foreach ($availableRolePurposes as $role => $purpose): ?><option value="<?php echo h($role); ?>" <?php echo $user['role'] === $role ? 'selected' : ''; ?>><?php echo h($role); ?> — <?php echo h($purpose); ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <div class="field"><span>Account Status</span><strong><?php echo h($user['status']); ?></strong><small>Use the account action buttons to suspend or reactivate. Registration status is not changed here.</small></div>
            </div>
            <button class="primary-btn" type="submit"><i class="ri-save-line"></i> Save User</button>
        </form>
    </div>
<?php endforeach; ?>
<?php renderFooter(); ?>
