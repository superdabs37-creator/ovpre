<?php
require_once __DIR__ . '/includes/layout.php';
$actor = require_super_admin();
verify_csrf();

$rolePurposes = [
    ROLE_OVPRE_ADMIN => 'Records management and institutional operations',
    ROLE_RESEARCH_COORDINATOR => 'Research monitoring',
    ROLE_EXTENSION_COORDINATOR => 'Extension monitoring',
    ROLE_FACULTY_EXTENSIONIST => 'Extension submissions',
    ROLE_RESEARCHER => 'Research submissions',
];
$availableRolePurposes = $rolePurposes;

function user_profile_fields(array $input): array
{
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

function require_supabase_invitation_config(): void
{
    if (SUPABASE_URL === '' || SUPABASE_SECRET_KEY === '') {
        throw new InvalidArgumentException('Supabase invitations are not configured. Add SUPABASE_URL and SUPABASE_SECRET_KEY to config.local.php or the Apache environment.');
    }
}

function require_invitation_schema(?string $role): void
{
    if (DB_DRIVER !== 'pgsql') {
        return;
    }

    $columns = db()->prepare("SELECT column_name, data_type, is_nullable, udt_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'users' AND column_name IN ('auth_user_id', 'password', 'role', 'status')");
    $columns->execute();
    $byName = [];
    foreach ($columns->fetchAll() as $column) {
        $byName[(string) $column['column_name']] = $column;
    }

    if (!isset($byName['auth_user_id'])) {
        throw new InvalidArgumentException('Invitations are not ready: the users.auth_user_id column is missing. Apply the Auth/RLS database migration before sending invitations.');
    }
    if (($byName['password']['is_nullable'] ?? 'NO') !== 'YES') {
        throw new InvalidArgumentException('Invitations are not ready: users.password must allow NULL. Apply the Auth/RLS database migration before sending invitations.');
    }

    $requiredEnumValues = [['status', 'Pending Invitation']];
    if ($role !== null) {
        $requiredEnumValues[] = ['role', $role];
    }
    foreach ($requiredEnumValues as [$columnName, $value]) {
        $column = $byName[$columnName] ?? null;
        if (!$column) {
            throw new InvalidArgumentException('Invitations are not ready: the users table is missing a required role or status column.');
        }
        if ($column['data_type'] === 'USER-DEFINED') {
            $enumCheck = db()->prepare('SELECT EXISTS (SELECT 1 FROM pg_type t JOIN pg_enum e ON e.enumtypid = t.oid WHERE t.typname = ? AND e.enumlabel = ?)');
            $enumCheck->execute([(string) $column['udt_name'], $value]);
            if (!$enumCheck->fetchColumn()) {
                throw new InvalidArgumentException('Invitations are not ready: the database role/status enum does not allow "' . $value . '". Apply the Auth/RLS database migration before sending invitations.');
            }
        }
    }
}

function user_management_failure_message(Throwable $exception, string $action): string
{
    $details = strtolower($exception->getMessage());
    if (str_contains($details, 'server credentials are not configured')) {
        return 'Supabase invitations are not configured. Check SUPABASE_URL and SUPABASE_SECRET_KEY.';
    }
    if (str_contains($details, 'already registered') || str_contains($details, 'already exists')) {
        return 'Supabase reports that this email already has an Auth account. Check Authentication → Users before sending another invitation.';
    }
    if (str_contains($details, 'rate limit') || str_contains($details, 'too many')) {
        return 'Supabase temporarily limited invitation emails. Wait before trying again, and check Supabase Auth email settings.';
    }
    if (str_contains($details, 'curl error') || str_contains($details, 'request failed:')) {
        return 'The app could not reach Supabase. Check the Apache server connection and Supabase URL.';
    }
    if (str_contains($details, 'invalid api key') || str_contains($details, 'invalid jwt') || str_contains($details, 'unauthorized')) {
        return 'Supabase rejected the server key. Check SUPABASE_URL and SUPABASE_SECRET_KEY in config.local.php.';
    }
    if ($exception instanceof PDOException && $action === 'invite') {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if ($sqlState === '42703' || str_contains($details, 'auth_user_id')) {
            return 'Supabase may have sent the invitation, but the profile could not be linked. Apply the user/Auth database migration, then check Authentication → Users before retrying.';
        }
        if ($sqlState === '23505') {
            return 'The profile could not be saved because its email or username already exists. Check the user list and Supabase Authentication → Users.';
        }
        return 'The profile could not be saved to the database. Check the database connection and user/Auth migration; verify Authentication → Users before retrying.';
    }
    return 'The user account change could not be completed. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($action === 'invite_existing') {
            if (!$id || $id <= 0) {
                throw new InvalidArgumentException('Choose a valid profile to invite.');
            }
            require_invitation_schema(null);
            $stmt = db()->prepare('SELECT id, fullname, email, username, role, department_id, auth_user_id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([(int) $id]);
            $target = $stmt->fetch();
            if (!$target || !in_array($target['role'], [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN, ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR, ROLE_FACULTY_EXTENSIONIST, ROLE_RESEARCHER], true)) {
                throw new InvalidArgumentException('That profile cannot be invited.');
            }
            if (!empty($target['auth_user_id'])) {
                throw new InvalidArgumentException('This profile is already linked to Supabase Auth.');
            }
            require_supabase_invitation_config();
            require_invitation_schema((string) $target['role']);
            $invited = supabase_auth_invite((string) $target['email'], ['full_name' => $target['fullname'], 'username' => $target['username'], 'role' => $target['role'], 'department_id' => $target['department_id'] ?? null]);
            $authId = (string) ($invited['id'] ?? '');
            if ($authId === '') {
                throw new RuntimeException('Supabase did not return an invited user ID.');
            }
            db()->prepare('UPDATE users SET auth_user_id = ?, updated_at = NOW() WHERE id = ? AND auth_user_id IS NULL')->execute([$authId, (int) $id]);
            log_action((int) $actor['id'], 'Sent Supabase invitation for existing profile: ' . $target['username']);
            flash('success', 'Invitation sent to ' . $target['email'] . '.');
        } elseif ($action === 'invite') {
            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $role = (string) ($_POST['role'] ?? '');
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username) || $departmentId <= 0
                || !in_array($role, [ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR, ROLE_FACULTY_EXTENSIONIST, ROLE_RESEARCHER], true)) {
            throw new InvalidArgumentException('Enter a valid name, email, username, department, and invited account type.');
            }
            $duplicate = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
            $duplicate->execute([$email, $username]);
            if ($duplicate->fetchColumn()) {
                throw new InvalidArgumentException('That email or username already has a profile. Send its Auth invitation from the user list.');
            }
            require_supabase_invitation_config();
            require_invitation_schema($role);
            $orphanStmt = db()->prepare('SELECT a.id, a.email_confirmed_at, a.raw_user_meta_data::text AS metadata_json FROM auth.users a LEFT JOIN public.users linked ON linked.auth_user_id = a.id WHERE LOWER(a.email) = LOWER(?) AND linked.id IS NULL LIMIT 1');
            $orphanStmt->execute([$email]);
            $orphanAuth = $orphanStmt->fetch();
            $linkedExistingInvite = false;
            if ($orphanAuth) {
                $metadata = json_decode((string) ($orphanAuth['metadata_json'] ?? ''), true);
                if (!is_array($metadata)
                    || strcasecmp((string) ($metadata['username'] ?? ''), $username) !== 0
                    || (string) ($metadata['role'] ?? '') !== $role) {
                    throw new InvalidArgumentException('An unlinked Supabase Auth account already uses this email, but its invitation details do not match. Check Authentication → Users before continuing.');
                }
                $authId = (string) $orphanAuth['id'];
                $newStatus = !empty($orphanAuth['email_confirmed_at']) ? 'Pending Approval' : 'Pending Invitation';
                $linkedExistingInvite = true;
            } else {
                $invited = supabase_auth_invite($email, ['full_name' => $fullname, 'username' => $username, 'role' => $role, 'department_id' => $departmentId]);
                $authId = (string) ($invited['id'] ?? '');
                if ($authId === '') {
                    throw new RuntimeException('Supabase did not return an invited user ID.');
                }
                $newStatus = 'Pending Invitation';
            }
            db()->prepare('INSERT INTO users (fullname, email, username, auth_user_id, role, status, department_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$fullname, $email, $username, $authId, $role, $newStatus, $departmentId, (int) $actor['id']]);
            log_action((int) $actor['id'], ($linkedExistingInvite ? 'Linked prior Supabase invitation: ' : 'Invited ' . $role . ': ') . $username);
            if ($linkedExistingInvite && $newStatus === 'Pending Approval') {
                flash('success', 'The existing Supabase account is now linked. The profile is Pending Approval; approve it from Pending Accounts. No duplicate invitation was sent.');
            } elseif ($linkedExistingInvite) {
                flash('success', 'The existing Supabase invitation is now linked to this profile. The user can continue from the original invitation email; do not send a duplicate invite.');
            } else {
                flash('success', 'Invitation sent to ' . $email . '.');
            }
        } elseif (in_array($action, ['suspend', 'reactivate'], true)) {
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
                db()->prepare('UPDATE users SET status = "Inactive", updated_at = NOW() WHERE id = ? AND status = "Active"')->execute([(int) $id]);
                log_action((int) $actor['id'], 'Set account inactive: ' . $target['username']);
                flash('success', 'User account set to Inactive.');
            } else {
                if ($target['status'] !== 'Inactive') {
                    throw new InvalidArgumentException('This account cannot be reactivated from its current status.');
                }
                db()->prepare('UPDATE users SET status = "Active", login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?')->execute([(int) $id]);
                log_action((int) $actor['id'], 'Reactivated user account: ' . $target['username']);
                flash('success', 'User account reactivated.');
            }
        } elseif ($action === 'edit') {
            if (!$id || $id <= 0) {
                throw new InvalidArgumentException('Choose a valid user account.');
            }
            $targetStmt = db()->prepare('SELECT id, username, email, auth_user_id, role, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $targetStmt->execute([(int) $id]);
            $target = $targetStmt->fetch();
            if (!$target) {
                throw new InvalidArgumentException('User account was not found.');
            }

            $fullname = trim((string) ($_POST['fullname'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            [$gender, $birthday, $phone] = user_profile_fields($_POST);
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $submittedRole = (string) ($_POST['role'] ?? '');

            if ($fullname === '' || strlen($fullname) > 160 || !preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
                throw new InvalidArgumentException('Enter a valid full name and a 3–80 character username.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            if (!empty($target['auth_user_id']) && strcasecmp($email, (string) $target['email']) !== 0) {
                throw new InvalidArgumentException('This email is linked to Supabase Auth and cannot be changed from profile management.');
            }
            $duplicate = db()->prepare('SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
            $duplicate->execute([$username, $email, (int) $id]);
            $existing = $duplicate->fetch();
            if ($existing) {
                throw new InvalidArgumentException(strcasecmp((string) $existing['username'], $username) === 0 ? 'That username is already in use.' : 'That email address is already registered.');
            }

            $role = $target['role'];
            if ($target['role'] !== ROLE_SUPER_ADMIN && (int) $target['id'] !== (int) $actor['id']) {
                if ($submittedRole !== $target['role'] && !array_key_exists($submittedRole, $availableRolePurposes)) {
                    throw new InvalidArgumentException('Choose a valid user role.');
                }
                $role = $submittedRole;
            }
            db()->prepare('UPDATE users SET fullname = ?, gender = ?, birthday = ?, email = ?, phone = ?, username = ?, role = ?, department_id = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$fullname, $gender, $birthday, $email, $phone, $username, $role, $departmentId > 0 ? $departmentId : null, (int) $id]);
            if ($role !== $target['role']) {
                log_action((int) $actor['id'], 'Changed role for ' . $target['username'] . ' from ' . $target['role'] . ' to ' . $role);
            }
            log_action((int) $actor['id'], 'Edited user account: ' . $username);
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
            flash('error', user_management_failure_message($exception, $action));
        }
    } catch (Throwable $exception) {
        error_log('User management action failed: ' . $exception->getMessage());
        flash('error', user_management_failure_message($exception, $action));
    }
    redirect_to('admin-users.php');
}

$statusOptions = ['All', 'Pending Invitation', 'Pending Approval', 'Active', 'Inactive'];
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
<section class="module-header">
    <div class="module-icon"><i class="ri-team-line"></i></div>
    <div>
        <p>Access Control</p>
        <h2>User Management</h2><span>Manage roles and access. Registration approval is handled in Pending Account Requests.</span>
    </div>
</section>
<?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-information-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
<section class="panel"><div class="panel-heading"><h2>Invite an account</h2><span>Users set their own password through the secure invitation email.</span></div><form class="editor-panel" method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="invite"><div class="form-grid"><label class="field"><span>Full Name</span><input name="fullname" maxlength="160" required></label><label class="field"><span>Email</span><input name="email" type="email" maxlength="190" required></label><label class="field"><span>Username</span><input name="username" minlength="3" maxlength="80" pattern="[A-Za-z0-9_.-]+" required></label><label class="field"><span>Account Type</span><select name="role" required><?php foreach ([ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR, ROLE_FACULTY_EXTENSIONIST, ROLE_RESEARCHER] as $inviteRole): ?><option value="<?php echo h($inviteRole); ?>"><?php echo h($inviteRole === ROLE_RESEARCHER ? 'Other Authorized User (Researcher)' : $inviteRole); ?></option><?php endforeach; ?></select></label><label class="field"><span>Department</span><select name="department_id" required><option value="0" selected disabled>Choose department</option><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>"><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label></div><button class="primary-btn" type="submit"><i class="ri-mail-send-line"></i> Send Invitation</button></form></section>
<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search users"></div>
    <form method="get" class="action-row"><label class="field"><span>Account Status</span><select class="filter-select" name="status" onchange="this.form.submit()"><?php foreach ($statusOptions as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label></form>
</section>
<section class="panel">
    <div class="table-scroll">
        <table class="data-table portal-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Registered</th>
                    <th>Review</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th>Manage</th>
                </tr>
            </thead>
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
                        <td>
                            <details class="user-actions-dropdown">
                                <summary class="user-actions-trigger"><i class="ri-more-2-fill"></i><span>Manage</span><i class="ri-arrow-down-s-line user-actions-chevron"></i></summary>
                                <div class="user-action-menu">
                                    <button class="ghost-btn action-btn" type="button" data-open-modal="edit-user-<?php echo (int) $user['id']; ?>"><i class="ri-edit-line"></i><span>Edit</span></button>
                                    <?php if (empty($user['auth_user_id'])): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="invite_existing"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="ghost-btn action-btn" type="submit"><i class="ri-mail-send-line"></i><span>Send Auth Invitation</span></button></form><?php endif; ?>
                                    <?php if ($user['role'] !== ROLE_SUPER_ADMIN && (int) $user['id'] !== (int) $actor['id']): ?>
                                        <?php if ($user['status'] === 'Active'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="danger-btn action-btn" type="submit"><i class="ri-forbid-line"></i><span>Set Inactive</span></button></form>
                                        <?php elseif ($user['status'] === 'Inactive'): ?><form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="reactivate"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="primary-btn action-btn" type="submit"><i class="ri-user-follow-line"></i><span>Activate</span></button></form><?php endif; ?>
                                        <form method="post" onsubmit="return confirm('Deactivate this account? Existing records remain intact.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="danger-btn action-btn" type="submit"><i class="ri-delete-bin-line"></i><span>Deactivate</span></button></form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?><tr>
                        <td colspan="11">No user accounts found for this status.</td>
                    </tr><?php endif; ?>
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
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
            <div class="form-grid">
                <label class="field"><span>Full Name</span><input name="fullname" maxlength="160" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Email</span><input name="email" type="email" maxlength="190" value="<?php echo h($user['email']); ?>" required></label>
                <label class="field"><span>Gender</span><select name="gender">
                        <option value="">Not specified</option><?php foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $genderOption): ?><option value="<?php echo h($genderOption); ?>" <?php echo ($user['gender'] ?? '') === $genderOption ? 'selected' : ''; ?>><?php echo h($genderOption); ?></option><?php endforeach; ?>
                    </select></label>
                <label class="field"><span>Birthday</span><input name="birthday" type="date" max="<?php echo h(date('Y-m-d')); ?>" value="<?php echo h($user['birthday'] ?? ''); ?>"></label>
                <label class="field"><span>Phone</span><input name="phone" type="tel" maxlength="30" value="<?php echo h($user['phone'] ?? ''); ?>"></label>
                <label class="field"><span>Username</span><input name="username" maxlength="80" value="<?php echo h($user['username']); ?>" required></label>
                <label class="field"><span>Department</span><select name="department_id">
                        <option value="0">Unassigned</option><?php foreach ($departments as $department): ?><option value="<?php echo (int) $department['id']; ?>" <?php echo (int) ($user['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?>
                    </select></label>
                <?php if ($user['role'] === ROLE_SUPER_ADMIN || (int) $user['id'] === (int) $actor['id']): ?><div class="field"><span>Role</span><strong><?php echo h($user['role']); ?></strong><small>Role changes are disabled for this account.</small></div>
                <?php elseif (!array_key_exists($user['role'], $availableRolePurposes)): ?><label class="field"><span>Role</span><strong><?php echo h($user['role']); ?></strong><small>This legacy role is no longer available for assignment.</small><input type="hidden" name="role" value="<?php echo h($user['role']); ?>"></label>
                <?php else: ?><label class="field"><span>Role</span><select name="role" required><?php foreach ($availableRolePurposes as $role => $purpose): ?><option value="<?php echo h($role); ?>" <?php echo $user['role'] === $role ? 'selected' : ''; ?>><?php echo h($role); ?> — <?php echo h($purpose); ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <div class="field"><span>Account Status</span><strong><?php echo h($user['status']); ?></strong><small>Use the account action buttons to activate or deactivate.</small></div>
            </div>
            <button class="primary-btn" type="submit"><i class="ri-save-line"></i> Save User</button>
        </form>
    </div>
<?php endforeach; ?>
<?php renderFooter(); ?>
