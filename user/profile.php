<?php
require_once 'includes/user-layout.php';
$user = require_end_user();
verify_csrf();
$notice = flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'profile') {
            user_db()->prepare('UPDATE users SET fullname = ?, email = ? WHERE id = ?')->execute([trim($_POST['fullname'] ?? ''), trim($_POST['email'] ?? ''), (int) $user['id']]);
            flash('success', 'Profile saved.');
        } elseif (($_POST['action'] ?? '') === 'password') {
            if (strlen((string) ($_POST['new_password'] ?? '')) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }
            if (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
                throw new RuntimeException('Passwords do not match.');
            }
            user_db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash((string) $_POST['new_password'], PASSWORD_DEFAULT), (int) $user['id']]);
            flash('success', 'Password updated.');
        } elseif (($_POST['action'] ?? '') === 'image') {
            if (($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose a JPG or PNG image.');
            }
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                throw new RuntimeException('Profile image must be JPG or PNG.');
            }
            $folder = __DIR__ . '/uploads/profile';
            if (!is_dir($folder)) {
                mkdir($folder, 0775, true);
            }
            $name = 'profile-' . (int) $user['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], $folder . '/' . $name);
            $_SESSION['user_profile_image'] = 'uploads/profile/' . $name;
            flash('success', 'Profile image uploaded.');
        }
        redirect_to('profile.php');
    } catch (Throwable $error) {
        $notice = ['type' => 'error', 'message' => $error->getMessage()];
    }
}
$user = user_render_header('Profile', 'profile');
?>
<section class="simple-header"><div><h2>Profile</h2><p>Keep your basic account information updated.</p></div></section>
<?php user_notice($notice); ?>
<section class="profile-grid">
    <article class="panel center-card"><span class="avatar large"><?php echo h(user_initials($user['fullname'])); ?></span><h2><?php echo h($user['fullname']); ?></h2><p><?php echo h($user['email']); ?></p><form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="image"><label class="field"><span>Profile image</span><input type="file" name="profile_image" accept=".jpg,.jpeg,.png" required></label><button class="soft-btn">Upload image</button></form></article>
    <article class="panel"><h2>Basic Info</h2><form class="stack" method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="profile"><label class="field"><span>Name</span><input name="fullname" value="<?php echo h($user['fullname']); ?>" required></label><label class="field"><span>Email</span><input type="email" name="email" value="<?php echo h($user['email']); ?>" required></label><button class="primary-btn">Save changes</button></form></article>
    <article class="panel"><h2>Password</h2><form class="stack" method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="password"><label class="field"><span>New password</span><input type="password" name="new_password" minlength="8" required></label><label class="field"><span>Confirm password</span><input type="password" name="confirm_password" minlength="8" required></label><button class="danger-btn">Change password</button></form></article>
</section>
<?php user_render_footer(); ?>
