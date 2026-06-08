<?php
require_once 'includes/layout.php';
$actor = require_login(['Super Admin']);
verify_csrf();

$rolePurposes = [
    'Super Admin' => 'System Administrator',
    'Vice President' => 'Final Approval Authority',
    'OVPRE Staff' => 'Records Management',
    'Research Coordinator' => 'Research Monitoring',
    'Extension Coordinator' => 'Extension Monitoring',
    'Faculty Researcher' => 'Submit Research',
    'Evaluator/Reviewer' => 'Review Proposals',
    'User' => 'Friendly User Portal',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        try {
            $role = $_POST['role'] ?? 'OVPRE Staff';
            if (!array_key_exists($role, $rolePurposes)) {
                $role = 'OVPRE Staff';
            }

            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $status = in_array($_POST['status'] ?? 'Active', ['Active', 'Inactive', 'Locked'], true) ? $_POST['status'] : 'Active';

            $existing = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
            $existing->execute([$username, $email]);
            $duplicate = $existing->fetch();

            if ($duplicate) {
                if (strcasecmp((string) $duplicate['username'], $username) === 0) {
                    flash('error', 'Username "' . $username . '" already exists. Please choose another username.');
                } else {
                    flash('error', 'Email "' . $email . '" already exists. Please use another email address.');
                }
            } else {
                $stmt = db()->prepare('INSERT INTO users (fullname, email, username, password, role, department_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([
                    $fullname,
                    $email,
                    $username,
                    password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                    $role,
                    (int) $_POST['department_id'],
                    $status,
                ]);
                log_action((int) $actor['id'], 'Created user account: ' . $username);
                flash('success', 'User account created.');
            }
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                flash('error', 'That username or email is already registered. Please use unique account details.');
            } else {
                throw $error;
            }
        }
    } elseif ($action === 'status') {
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$_POST['status'], (int) $_POST['id']]);
        log_action((int) $actor['id'], 'Updated user status #' . (int) $_POST['id']);
        flash('success', 'User status updated.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === (int) $actor['id']) {
            flash('error', 'You cannot remove your own admin account.');
        } else {
            $stmt = db()->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                flash('error', 'User account was not found.');
            } else {
                try {
                    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                    log_action((int) $actor['id'], 'Removed user account: ' . $target['username']);
                    flash('success', 'User account removed.');
                } catch (PDOException $error) {
                    if (($error->errorInfo[1] ?? null) === 1451) {
                        db()->prepare('UPDATE users SET status = "Inactive" WHERE id = ?')->execute([$id]);
                        log_action((int) $actor['id'], 'Deactivated user account with linked records: ' . $target['username']);
                        flash('error', 'This user has linked records, so the account was set to Inactive instead of being permanently deleted.');
                    } else {
                        throw $error;
                    }
                }
            }
        }
    }
    redirect_to('admin-users.php');
}

$users = db()->query('SELECT u.*, d.department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id ORDER BY u.created_at DESC')->fetchAll();
$notice = flash();
renderHeader('User Management', 'users');
?>
<section class="module-header"><div class="module-icon"><i class="ri-team-line"></i></div><div><p>Access Control</p><h2>User Management</h2><span>Manage user roles, responsibilities, departments, and account access.</span></div></section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-checkbox-circle-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel">
    <div class="panel-heading"><h2>Create User</h2>
</div>
    <form class="editor-panel" method="post">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
        <div class="form-grid">
            <label class="field"><span>Full Name</span><input name="fullname" required></label>
            <label class="field"><span>Email</span><input name="email" type="email" required></label>
            <label class="field"><span>Username</span><input name="username" required></label>
            <label class="field"><span>Password</span><input name="password" type="password" minlength="8" required></label>
            <label class="field"><span>Role</span><select name="role" required><?php foreach ($rolePurposes as $role => $purpose): ?><option value="<?php echo h($role); ?>"><?php echo h($role); ?> - <?php echo h($purpose); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Department</span><select name="department_id"><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>"><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Status</span><select name="status"><option>Active</option><option>Inactive</option><option>Locked</option></select></label>
        </div>
        <button class="primary-btn post-btn" type="submit"><i class="ri-user-add-line"></i> Add User</button>
    </form>
</section>
<section class="tool-panel"><div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search users"></div></section>
<section class="panel">
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Department</th><th>Role</th><th>Purpose</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr data-table-row>
                    <td><?php echo (int) $user['id']; ?></td><td><?php echo h($user['fullname']); ?></td><td><?php echo h($user['email']); ?></td><td><?php echo h($user['username']); ?></td><td><?php echo h($user['department_name'] ?? 'Unassigned'); ?></td><td><?php echo h($user['role']); ?></td><td><?php echo h($rolePurposes[$user['role']] ?? 'Assigned User'); ?></td><td><span class="status-pill status-<?php echo h(statusClass($user['status'])); ?>"><?php echo h($user['status']); ?></span></td>
                    <td>
                        <div class="action-row">
                            <form class="action-row" method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><select class="filter-select" name="status"><?php foreach (['Active', 'Inactive', 'Locked'] as $status): ?><option <?php echo $user['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select><button class="primary-btn mini-btn" title="Save status"><i class="ri-save-line"></i></button></form>
                            <?php if ((int) $user['id'] !== (int) $actor['id']): ?>
                                <form method="post" onsubmit="return confirm('Remove this user account? If linked records exist, the account will be set to Inactive instead.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="danger-btn mini-btn" title="Remove user"><i class="ri-delete-bin-line"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php renderFooter(); ?>
