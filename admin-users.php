<?php
require_once __DIR__ . '/includes/layout.php';
$actor = require_login([ROLE_OVPRE_ADMIN, ROLE_SUPER_ADMIN]);
verify_csrf();

$rolePurposes = [
    ROLE_SUPER_ADMIN => 'Technical system administration and security control',
    ROLE_OVPRE_ADMIN => 'Institutional operations, user management, and academic records',
    ROLE_RESEARCH_DIRECTOR => 'Institutional research evaluation and RIUH (Research Director access)',
    ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR => 'Research and creative works oversight (Research Director access)',
    ROLE_EXTENSION_DIRECTOR => 'Institutional extension program evaluation and oversight (Extension Director access)',
    ROLE_RESEARCH_COORDINATOR => 'Research monitoring',
    ROLE_EXTENSION_COORDINATOR => 'Extension monitoring',
    ROLE_FACULTY_EXTENSIONIST => 'Extension submissions',
    ROLE_OVPRE_SECRETARIAT => 'OVPRE office operations (OVPRE Admin access)',
    ROLE_RESEARCHER => 'Faculty research submissions and institutional proposals',
];
$availableRolePurposes = $rolePurposes;
if ($actor['role'] !== ROLE_SUPER_ADMIN) {
    unset($availableRolePurposes[ROLE_SUPER_ADMIN]);
}

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
        return 'Supabase reports that this email already has an Auth account. Check Authentication -> Users before sending another invitation.';
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
            return 'Supabase may have sent the invitation, but the profile could not be linked. Apply the user/Auth database migration, then check Authentication -> Users before retrying.';
        }
        if ($sqlState === '23505') {
            return 'The profile could not be saved because its email or username already exists. Check the user list and Supabase Authentication -> Users.';
        }
        return 'The profile could not be saved to the database. Check the database connection and user/Auth migration; verify Authentication -> Users before retrying.';
    }
    return 'The user account change could not be completed. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($action === 'invite') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $role = (string) ($_POST['role'] ?? '');
            $departmentRaw = trim((string) ($_POST['department_id'] ?? ''));
            $isNoneDepartment = ($departmentRaw === 'none' || $departmentRaw === '0' || $departmentRaw === 'null');
            $departmentId = $isNoneDepartment ? null : (int) $departmentRaw;

            if ($departmentRaw === '' || (!$isNoneDepartment && ($departmentId === null || $departmentId <= 0))) {
                throw new InvalidArgumentException('Please choose a department or None.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)
                || !in_array($role, [ROLE_RESEARCH_COORDINATOR, ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR, ROLE_EXTENSION_COORDINATOR, ROLE_EXTENSION_DIRECTOR, ROLE_OVPRE_ADMIN, ROLE_OVPRE_SECRETARIAT, ROLE_FACULTY_EXTENSIONIST, ROLE_RESEARCHER], true)) {
                throw new InvalidArgumentException('Enter a valid email, department, and invited account type.');
            }
            $emailLocalPart = (string) strstr($email, '@', true);
            $fullname = trim(ucwords(str_replace(['.', '_', '-'], ' ', $emailLocalPart)));
            if ($fullname === '') {
                $fullname = 'Invited User';
            }
            $usernameBase = preg_replace('/[^A-Za-z0-9_.-]/', '', $emailLocalPart) ?? '';
            if (strlen($usernameBase) < 3) {
                $usernameBase = 'user_' . substr(hash('sha256', strtolower($email)), 0, 8);
            }
            $usernameBase = substr($usernameBase, 0, 80);
            $emailDuplicate = db()->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $emailDuplicate->execute([$email]);
            if ($emailDuplicate->fetchColumn()) {
                throw new InvalidArgumentException('That email already has a profile. Send its Auth invitation from the user list.');
            }
            $usernameCheck = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            for ($suffix = 1; ; $suffix++) {
                $suffixText = $suffix === 1 ? '' : '_' . $suffix;
                $username = substr($usernameBase, 0, 80 - strlen($suffixText)) . $suffixText;
                $usernameCheck->execute([$username]);
                if (!$usernameCheck->fetchColumn()) {
                    break;
                }
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
                    throw new InvalidArgumentException('An unlinked Supabase Auth account already uses this email, but its invitation details do not match. Check Authentication -> Users before continuing.');
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
            $departmentRaw = trim((string) ($_POST['department_id'] ?? ''));
            $departmentId = ($departmentRaw === 'none' || $departmentRaw === '0' || $departmentRaw === '') ? null : (int) $departmentRaw;
            $submittedRole = (string) ($_POST['role'] ?? '');

            if ($fullname === '' || strlen($fullname) > 160 || !preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
                throw new InvalidArgumentException('Enter a valid full name and a 3-80 character username.');
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
                throw new InvalidArgumentException('You cannot delete your own administrator account.');
            }
            $stmt = db()->prepare('SELECT username, role FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([(int) $id]);
            $target = $stmt->fetch();
            if (!$target || $target['role'] === ROLE_SUPER_ADMIN) {
                throw new InvalidArgumentException('That account cannot be deleted here.');
            }
            db()->prepare('UPDATE users SET status = "Inactive", deleted_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([(int) $id]);
            log_action((int) $actor['id'], 'Deleted user account: ' . $target['username']);
            flash('success', 'User account deleted. Existing records remain intact.');
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
$departmentFilter = trim((string) ($_GET['department'] ?? '0'));
$sql = 'SELECT u.*, d.department_name, creator.fullname AS created_by_name, approver.fullname AS approved_by_name, rejecter.fullname AS rejected_by_name FROM users u LEFT JOIN departments d ON d.id = u.department_id LEFT JOIN users creator ON creator.id = u.created_by LEFT JOIN users approver ON approver.id = u.approved_by LEFT JOIN users rejecter ON rejecter.id = u.rejected_by WHERE u.deleted_at IS NULL';
$params = [];
if ($statusFilter !== 'All') {
    $sql .= ' AND u.status = ?';
    $params[] = $statusFilter;
}
if ($departmentFilter === 'none') {
    $sql .= ' AND u.department_id IS NULL';
} elseif ((int) $departmentFilter > 0) {
    $sql .= ' AND u.department_id = ?';
    $params[] = (int) $departmentFilter;
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
<section class="panel modal-launcher-panel invite-account-launcher">
    <div class="panel-heading">
        <div><h2>Invite an account</h2><span>Users set their own password through the secure invitation email.</span></div>
        <button class="primary-btn" type="button" data-open-modal="inviteAccountModal"><i class="ri-user-add-line"></i> Invite an account</button>
    </div>
</section>
<div class="modal" id="inviteAccountModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <form class="modal-card editor-panel invite-account-modal" method="post" role="dialog" aria-modal="true" aria-labelledby="inviteAccountTitle">
        <button class="modal-close" type="button" data-close-modal aria-label="Close invitation form"><i class="ri-close-line"></i></button>
        <h2 id="inviteAccountTitle">Invite an account</h2>
        <p>Users set their own password through the secure invitation email.</p>
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="invite">
        <div class="form-grid"><label class="field"><span>Email</span><input name="email" type="email" maxlength="190" required></label><label class="field"><span>Account Type</span><select name="role" required><option value="" selected disabled>Choose account type</option><?php foreach ([ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR, ROLE_EXTENSION_DIRECTOR, ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR, ROLE_FACULTY_EXTENSIONIST, ROLE_OVPRE_ADMIN, ROLE_OVPRE_SECRETARIAT, ROLE_RESEARCHER] as $inviteRole): ?><option value="<?php echo h($inviteRole); ?>"><?php echo h($inviteRole === ROLE_RESEARCHER ? 'Faculty Researcher' : $inviteRole); ?></option><?php endforeach; ?></select></label><label class="field"><span>Department</span><select name="department_id" required><option value="" selected disabled>Choose department</option><option value="none">None</option><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>"><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label></div>
        <button class="primary-btn" type="submit"><i class="ri-mail-send-line"></i> Send Invitation</button>
    </form>
</div>
<section class="tool-panel">
    <div class="search-control"><i class="ri-search-line"></i><input data-table-search type="search" placeholder="Search users"></div>
    <form method="get" class="action-row" style="display:flex;flex-direction:row;align-items:flex-end;gap:12px;flex-wrap:wrap;"><label class="field" style="margin:0;"><span>Account Status</span><select class="filter-select" name="status" onchange="this.form.submit()"><?php foreach ($statusOptions as $status): ?><option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>><?php echo h($status); ?></option><?php endforeach; ?></select></label><label class="field" style="margin:0;"><span>Department</span><select class="filter-select" name="department" onchange="this.form.submit()"><option value="0" <?php echo ($departmentFilter === '0' || $departmentFilter === 0) ? 'selected' : ''; ?>>All Departments</option><option value="none" <?php echo $departmentFilter === 'none' ? 'selected' : ''; ?>>None</option><?php foreach ($departments as $department): ?><option value="<?php echo (int) $department['id']; ?>" <?php echo (string) $departmentFilter === (string) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label></form>
</section>
<section class="panel">
    <div class="table-scroll">
        <table class="data-table portal-table" data-filter-options="<?php echo h(json_encode(['Role' => [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN, ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR, ROLE_EXTENSION_DIRECTOR, ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR, ROLE_FACULTY_EXTENSIONIST, ROLE_OVPRE_SECRETARIAT, ROLE_RESEARCHER]])); ?>">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Username</th>
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
                        <td><?php echo h($user['role']); ?></td>
                        <td><?php echo h(date('M d, Y', strtotime($user['created_at']))); ?></td>
                        <td><?php if ($user['status'] === 'Rejected'): ?><?php echo h($user['rejection_reason'] ?? 'No reason recorded'); ?><br><small><?php echo h($user['rejected_by_name'] ?? 'Administrator'); ?> <?php echo $user['rejected_at'] ? h(date('M d, Y', strtotime($user['rejected_at']))) : ''; ?></small><?php elseif ($user['status'] === 'Active' && $user['approved_at']): ?>Approved by <?php echo h($user['approved_by_name'] ?? 'Administrator'); ?><br><small><?php echo h(date('M d, Y', strtotime($user['approved_at']))); ?></small><?php else: ?>- <?php endif; ?></td>
                        <td><?php echo $user['last_login_at'] ? h(date('M d, Y h:i A', strtotime($user['last_login_at']))) : 'Never'; ?></td>
                        <td><span class="status-pill status-<?php echo h(statusClass($user['status'])); ?>"><?php echo h($user['status']); ?></span></td>
                        <td>
                            <details class="user-actions-dropdown">
                                <summary class="user-actions-trigger"><i class="ri-more-2-fill"></i><span>Manage</span><i class="ri-arrow-down-s-line user-actions-chevron"></i></summary>
                                <div class="user-action-menu">
                                    <button class="ghost-btn action-btn" type="button" data-open-modal="edit-user-<?php echo (int) $user['id']; ?>"><i class="ri-edit-line"></i><span>Edit</span></button>
                                    <?php if ($user['role'] !== ROLE_SUPER_ADMIN && (int) $user['id'] !== (int) $actor['id']): ?>
                                        <form method="post" onsubmit="return confirm('Delete this account? It will be removed from User Management. Existing records remain intact.');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>"><button class="danger-btn action-btn" type="submit"><i class="ri-delete-bin-line"></i><span>Delete Account</span></button></form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?><tr>
                        <td colspan="10">No user accounts found for these filters.</td>
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
                <?php else: ?><label class="field"><span>Role</span><select name="role" required><?php foreach ($availableRolePurposes as $role => $purpose): ?><option value="<?php echo h($role); ?>" <?php echo $user['role'] === $role ? 'selected' : ''; ?>><?php echo h($role === ROLE_RESEARCHER ? 'Faculty Researcher' : $role); ?></option><?php endforeach; ?></select></label><?php endif; ?>
                <div class="field"><span>Account Status</span><strong><?php echo h($user['status']); ?></strong><small>Use the account action buttons to activate or deactivate.</small></div>
            </div>
            <button class="primary-btn" type="submit"><i class="ri-save-line"></i> Save User</button>
        </form>
    </div>
<?php endforeach; ?>
<?php renderFooter(); ?>
