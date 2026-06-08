<?php
require_once 'includes/student-layout.php';
$user = require_student();
verify_csrf();
$notice = flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'profile') {
            student_db()->prepare('UPDATE users SET fullname = ?, email = ?, username = ?, department_id = ? WHERE id = ?')
                ->execute([trim($_POST['fullname'] ?? ''), trim($_POST['email'] ?? ''), trim($_POST['username'] ?? ''), (int) $_POST['department_id'], (int) $user['id']]);
            student_log_document((int) $user['id'], null, 'Updated profile');
            flash('success', 'Profile information updated.');
        } elseif ($action === 'password') {
            if (strlen((string) ($_POST['new_password'] ?? '')) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }
            if (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
                throw new RuntimeException('Password confirmation does not match.');
            }
            if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $user['password'])) {
                throw new RuntimeException('Current password is incorrect.');
            }
            student_db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash((string) $_POST['new_password'], PASSWORD_DEFAULT), (int) $user['id']]);
            student_log_document((int) $user['id'], null, 'Changed password');
            flash('success', 'Password changed.');
        } elseif ($action === 'avatar') {
            if (($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Please choose an image.');
            }
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                throw new RuntimeException('Profile image must be JPG or PNG.');
            }
            $folder = __DIR__ . '/uploads/profile';
            if (!is_dir($folder)) {
                mkdir($folder, 0775, true);
            }
            $stored = 'profile-' . (int) $user['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $folder . '/' . $stored)) {
                throw new RuntimeException('Profile image upload failed.');
            }
            $_SESSION['student_profile_image'] = 'uploads/profile/' . $stored;
            if (student_column_exists('users', 'profile_image')) {
                student_db()->prepare('UPDATE users SET profile_image = ? WHERE id = ?')->execute(['student/uploads/profile/' . $stored, (int) $user['id']]);
            }
            flash('success', 'Profile image updated.');
        }
        redirect_to('profile.php');
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}

$user = student_render_header('Profile Management', 'profile');
?>
<section class="module-header"><div class="module-icon"><i class="ri-user-line"></i></div><div><p>Account Center</p><h2>Profile Management</h2><span>Update your identity, department, photo, and password.</span></div></section>
<?php student_notice($notice); ?>
<section class="profile-management">
    <article class="panel">
        <div class="panel-heading"><h2>Profile Information</h2></div>
        <form class="profile-form" method="post">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="profile">
            <div class="form-grid">
                <label class="field"><span>Full Name</span><input name="fullname" value="<?php echo h($user['fullname']); ?>" required></label>
                <label class="field"><span>Email</span><input type="email" name="email" value="<?php echo h($user['email']); ?>" required></label>
                <label class="field"><span>Username</span><input name="username" value="<?php echo h($user['username']); ?>" required></label>
                <label class="field"><span>Department</span><select name="department_id" required><?php foreach (departments() as $department): ?><option value="<?php echo (int) $department['id']; ?>" <?php echo (int) ($user['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : ''; ?>><?php echo h($department['department_name']); ?></option><?php endforeach; ?></select></label>
            </div>
            <button class="primary-btn" type="submit"><i class="ri-save-line"></i> Save Profile</button>
        </form>
    </article>
    <aside class="panel profile-side">
        <div class="profile-upload">
            <span class="avatar big-avatar"><?php echo h(student_initials($user['fullname'])); ?></span>
            <div><h3><?php echo h($user['fullname']); ?></h3><p><?php echo h($user['role']); ?></p></div>
        </div>
        <form class="profile-form" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="avatar">
            <label class="field"><span>Profile Image</span><input type="file" name="profile_image" accept=".jpg,.jpeg,.png" required></label>
            <button class="ghost-btn" type="submit"><i class="ri-image-edit-line"></i> Upload Image</button>
        </form>
        <form class="profile-form" method="post">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="password">
            <label class="field"><span>Current Password</span><input type="password" name="current_password" required></label>
            <label class="field"><span>New Password</span><input type="password" name="new_password" minlength="8" required></label>
            <label class="field"><span>Confirm Password</span><input type="password" name="confirm_password" minlength="8" required></label>
            <button class="danger-btn" type="submit"><i class="ri-lock-password-line"></i> Change Password</button>
        </form>
    </aside>
</section>
<?php student_render_footer(); ?>
