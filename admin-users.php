<?php
require_once 'includes/layout.php';
$actor = require_login(['Super Admin']);
verify_csrf();

$rolePurposes = [
    'Super Admin' => 'System Administrator',
    'Research Coordinator' => 'Research Monitoring',
    'Extension Coordinator' => 'Extension Monitoring',
];

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
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        try {
            $role = $_POST['role'] ?? 'OVPRE Staff';
            if (!array_key_exists($role, $rolePurposes)) {
                $role = 'OVPRE Staff';
            }

            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            [$gender, $birthday, $phone] = user_profile_fields($_POST);
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
                $stmt = db()->prepare('INSERT INTO users (fullname, gender, birthday, email, phone, username, password, role, department_id, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([
                    $fullname,
                    $gender,
                    $birthday,
                    $email,
                    $phone,
                    $username,
                    password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                    $role,
                    (int) $_POST['department_id'],
                    $status,
                    (int) $actor['id'],
                ]);
                log_action((int) $actor['id'], 'Created user account: ' . $username);
                flash('success', 'User account created.');
            }
        } catch (InvalidArgumentException $error) {
            flash('error', $error->getMessage());
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
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT username FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if (!$target) {
            flash('error', 'User account was not found.');
        } else {
            $temporaryPassword = 'OVPRE-' . random_int(100000, 999999);
            db()->prepare('UPDATE users SET password = ?, login_attempts = 0, locked_until = NULL, status = "Active" WHERE id = ?')
                ->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $id]);
            log_action((int) $actor['id'], 'Reset password for user account: ' . $target['username']);
            flash('success', 'Temporary password for ' . $target['username'] . ': ' . $temporaryPassword);
        }
    } elseif ($action === 'edit') {
        try {
            $id = (int) ($_POST['id'] ?? 0);
            $role = $_POST['role'] ?? 'OVPRE Staff';
            if (!array_key_exists($role, $rolePurposes)) {
                $role = 'OVPRE Staff';
            }

            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            [$gender, $birthday, $phone] = user_profile_fields($_POST);
            $username = trim((string) ($_POST['username'] ?? ''));
            $status = in_array($_POST['status'] ?? 'Active', ['Active', 'Inactive', 'Locked'], true) ? $_POST['status'] : 'Active';
            $password = (string) ($_POST['password'] ?? '');

            $existing = db()->prepare('SELECT id, username, email FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
            $existing->execute([$username, $email, $id]);
            $duplicate = $existing->fetch();

            if ($duplicate) {
                if (strcasecmp((string) $duplicate['username'], $username) === 0) {
                    flash('error', 'Username "' . $username . '" already exists. Please choose another username.');
                } else {
                    flash('error', 'Email "' . $email . '" already exists. Please use another email address.');
                }
            } else {
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        flash('error', 'New password must be at least 8 characters.');
                    } else {
                        db()->prepare('UPDATE users SET fullname = ?, gender = ?, birthday = ?, email = ?, phone = ?, username = ?, password = ?, role = ?, department_id = ?, status = ?, login_attempts = 0, locked_until = NULL WHERE id = ?')
                            ->execute([$fullname, $gender, $birthday, $email, $phone, $username, password_hash($password, PASSWORD_DEFAULT), $role, (int) $_POST['department_id'], $status, $id]);
                        log_action((int) $actor['id'], 'Edited user account and reset password: ' . $username);
                        flash('success', 'User account updated and password reset.');
                    }
                } else {
                    db()->prepare('UPDATE users SET fullname = ?, gender = ?, birthday = ?, email = ?, phone = ?, username = ?, role = ?, department_id = ?, status = ? WHERE id = ?')
                        ->execute([$fullname, $gender, $birthday, $email, $phone, $username, $role, (int) $_POST['department_id'], $status, $id]);
                    log_action((int) $actor['id'], 'Edited user account: ' . $username);
                    flash('success', 'User account updated.');
                }
            }
        } catch (InvalidArgumentException $error) {
            flash('error', $error->getMessage());
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                flash('error', 'That username or email is already registered. Please use unique account details.');
            } else {
                throw $error;
            }
        }
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
                db()->prepare('UPDATE users SET status = "Inactive", deleted_at = NOW() WHERE id = ?')->execute([$id]);
                log_action((int) $actor['id'], 'Deactivated user account: ' . $target['username']);
                flash('success', 'User account deactivated. Existing records remain intact.');
            }
        }
    }
    redirect_to('admin-users.php');
}

$users = db()->query('SELECT u.*, d.department_name, creator.fullname AS created_by_name FROM users u LEFT JOIN departments d ON d.id = u.department_id LEFT JOIN users creator ON creator.id = u.created_by WHERE u.deleted_at IS NULL ORDER BY u.created_at DESC')->fetchAll();
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
            <label class="field"><span>Gender</span><select name="gender"><option value="">Select gender</option><?php foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $genderOption): ?><option value="<?php echo h($genderOption); ?>"><?php echo h($genderOption); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Birthday</span><input name="birthday" type="date" max="<?php echo h(date('Y-m-d')); ?>"></label>
            <label class="field"><span>Phone</span><input name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="e.g. +63 912 345 6789"></label>
            <label class="field"><span>Username</span><input name="username" required></label>
            <label class="field"><span>Password</span><input name="password" type="password" minlength="8" required></label>
            <label class="field"><span>Role</span><select name="role" required><?php foreach ($rolePurposes as $role => $purpose): ?><option value="<?php echo h($role); ?>"><?php echo h($role); ?> - <?php echo h($purpose); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Department</span><select name="department_id"><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>"><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Status</span><select name="status"><option>Active</option><option>Inactive</option><option>Locked</option></select></label>
        </div>
        <button class="primary-btn post-btn add-user-btn" type="submit"><i class="fa-solid fa-user-plus"></i><span>Add User</span></button>
    </form>
</section>
<section class="tool-panel"><div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search users"></div></section>
<section class="panel">
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Gender</th><th>Birthday</th><th>Phone</th><th>Username</th><th>Department</th><th>Role</th><th>Last Login</th><th>Created By</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr data-table-row>
                    <td><?php echo (int) $user['id']; ?></td><td><?php echo h($user['fullname']); ?><br><small><?php echo h($rolePurposes[$user['role']] ?? 'Assigned User'); ?></small></td><td><?php echo h($user['email']); ?></td><td><?php echo h($user['gender'] ?? '—'); ?></td><td><?php echo !empty($user['birthday']) ? h(date('M d, Y', strtotime($user['birthday']))) : '—'; ?></td><td><?php echo h($user['phone'] ?? '—'); ?></td><td><?php echo h($user['username']); ?></td><td><?php echo h($user['department_name'] ?? 'Unassigned'); ?></td><td><?php echo h($user['role']); ?></td><td><?php echo $user['last_login_at'] ? h(date('M d, Y h:i A', strtotime($user['last_login_at']))) : 'Never'; ?></td><td><?php echo h($user['created_by_name'] ?? 'System'); ?></td><td><span class="live-status <?php echo $user['status'] === 'Active' ? 'is-active' : 'is-inactive'; ?>"><span></span><?php echo h($user['status']); ?></span></td>
                    <td>
                        <div class="user-action-panel">
                            <button class="ghost-btn action-btn" type="button" data-open-modal="edit-user-<?php echo (int) $user['id']; ?>"><i class="ri-edit-line"></i><span>Edit</span></button>
                            <form class="inline-delete-form" method="post" onsubmit="return confirm('Delete this user account? Existing records will remain intact.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="danger-btn action-btn" type="submit"><i class="ri-delete-bin-line"></i><span>Delete</span></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php foreach ($users as $user): ?>
    <div class="modal" id="edit-user-<?php echo (int) $user['id']; ?>" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <form class="modal-card" method="post">
            <button class="modal-close" type="button" data-close-modal aria-label="Close edit user form"><i class="ri-close-line"></i></button>
            <span class="category-badge">Edit User</span>
            <h2><?php echo h($user['fullname']); ?></h2>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
            <div class="form-grid">
                <label class="field"><span>Full Name</span><input name="fullname" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Email</span><input name="email" type="email" value="<?php echo h($user['email']); ?>" required></label>
                <label class="field"><span>Gender</span><select name="gender"><option value="">Not specified</option><?php foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $genderOption): ?><option value="<?php echo h($genderOption); ?>" <?php echo ($user['gender'] ?? '') === $genderOption ? 'selected' : ''; ?>><?php echo h($genderOption); ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Birthday</span><input name="birthday" type="date" max="<?php echo h(date('Y-m-d')); ?>" value="<?php echo h($user['birthday'] ?? ''); ?>"></label>
                <label class="field"><span>Phone</span><input name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?php echo h($user['phone'] ?? ''); ?>" placeholder="e.g. +63 912 345 6789"></label>
                <label class="field"><span>Username</span><input name="username" value="<?php echo h($user['username']); ?>" required></label>
                <label class="field"><span>New Password</span><input name="password" type="password" minlength="8" placeholder="Leave blank to keep current password"></label>
                <label class="field"><span>Role</span><select name="role" required><?php foreach ($rolePurposes as $role => $purpose): ?><option value="<?php echo h($role); ?>" <?php echo $user['role'] === $role ? 'selected' : ''; ?>><?php echo h($role); ?> - <?php echo h($purpose); ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Department</span><select name="department_id"><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>" <?php echo (int) ($user['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Status</span><select name="status"><?php foreach (['Active', 'Inactive', 'Locked'] as $status): ?><option <?php echo $user['status'] === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label>
            </div>
            <button class="primary-btn" type="submit"><i class="ri-save-line"></i> Save User</button>
        </form>
    </div>
<?php endforeach; ?>
<?php renderFooter(); ?>
