<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
ensure_profile_photo_column();
$error = null;
$passwordOnly = ($_GET['section'] ?? '') === 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($passwordOnly) {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 12 || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $error = 'New password must be at least 12 characters and include uppercase, lowercase, a number, and a symbol.';
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            $error = 'New password and confirmation do not match.';
        } else {
            try {
                $auth = supabase_auth_password_login((string) $user['email'], $currentPassword);
                $authUser = supabase_auth_user((string) ($auth['access_token'] ?? ''));
                if (empty($user['auth_user_id']) || strcasecmp((string) ($authUser['id'] ?? ''), (string) $user['auth_user_id']) !== 0) {
                    throw new RuntimeException('The profile is not linked to the signed-in Auth account.');
                }
                supabase_auth_set_password((string) $auth['access_token'], $newPassword);
                log_action((int) $user['id'], 'Changed own Supabase Auth password');
                flash('success', 'Your password has been changed.');
                redirect_to('profile.php?section=password');
            } catch (Throwable $exception) {
                $error = 'Your current password is incorrect, or the Auth service is unavailable.';
            }
        }
    } else {
    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $birthday = trim((string) ($_POST['birthday'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if ($fullname === '' || strlen($fullname) > 160) {
        $error = 'Enter your name (up to 160 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Enter a valid email address.';
    } elseif (!empty($user['auth_user_id']) && strcasecmp($email, (string) $user['email']) !== 0) {
        $error = 'Ask the System Administrator to change the email linked to your Auth account.';
    } elseif ($gender !== '' && !in_array($gender, ['Male', 'Female', 'Other', 'Prefer not to say'], true)) {
        $error = 'Choose a valid gender option.';
    } elseif ($birthday !== '' && (!($date = DateTime::createFromFormat('!Y-m-d', $birthday)) || $date->format('Y-m-d') !== $birthday || $date > new DateTime('today'))) {
        $error = 'Enter a valid birthday that is not in the future.';
    } elseif ($phone !== '' && !preg_match('/^[0-9+().\s-]{7,30}$/', $phone)) {
        $error = 'Enter a valid phone number.';
    }

    $photoExtension = null;
    $photoUpload = $_FILES['profile_photo'] ?? null;
    if ($error === null && $photoUpload && ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'The profile photo could not be uploaded. Please try again.';
        } elseif (($photoUpload['size'] ?? 0) > 5 * 1024 * 1024) {
            $error = 'Choose a profile photo smaller than 5 MB.';
        } else {
            $imageInfo = @getimagesize($photoUpload['tmp_name']);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photoUpload['tmp_name']);
            $photoExtension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => null,
            };
            if (!$imageInfo || !$photoExtension) {
                $error = 'Use a valid JPG, PNG, or WebP image for your profile photo.';
            }
        }
    }

    if ($error === null) {
        $duplicate = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $duplicate->execute([$email, (int) $user['id']]);
        if ($duplicate->fetch()) {
            $error = 'That email address is already used by another account.';
        } else {
            $profilePhoto = $user['profile_photo'] ?? null;
            if ($photoExtension !== null) {
                $photoDirectory = __DIR__ . '/uploads/profile-photos';
                if (!is_dir($photoDirectory) && !mkdir($photoDirectory, 0755, true) && !is_dir($photoDirectory)) {
                    $error = 'The profile photo storage folder could not be created.';
                } else {
                    $photoFilename = 'user-' . (int) $user['id'] . '-' . bin2hex(random_bytes(12)) . '.' . $photoExtension;
                    if (!move_uploaded_file($photoUpload['tmp_name'], $photoDirectory . '/' . $photoFilename)) {
                        $error = 'The profile photo could not be saved. Please try again.';
                    } else {
                        $profilePhoto = 'uploads/profile-photos/' . $photoFilename;
                    }
                }
            }

            if ($error === null) {
                db()->prepare('UPDATE users SET fullname = ?, email = ?, gender = ?, birthday = ?, phone = ?, profile_photo = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$fullname, $email, $gender ?: null, $birthday ?: null, $phone ?: null, $profilePhoto, (int) $user['id']]);

                $oldPhoto = (string) ($user['profile_photo'] ?? '');
                if ($photoExtension !== null && str_starts_with($oldPhoto, 'uploads/profile-photos/')) {
                    $oldPhotoPath = __DIR__ . '/uploads/profile-photos/' . basename($oldPhoto);
                    if (is_file($oldPhotoPath)) {
                        @unlink($oldPhotoPath);
                    }
                }
                log_action((int) $user['id'], 'Updated own profile');
                flash('success', 'Your profile has been updated.');
                redirect_to('profile.php');
            }
        }
    }
    }
}

$form = [
    'fullname' => $fullname ?? $user['fullname'],
    'email' => $email ?? $user['email'],
    'gender' => $gender ?? ($user['gender'] ?? ''),
    'birthday' => $birthday ?? ($user['birthday'] ?? ''),
    'phone' => $phone ?? ($user['phone'] ?? ''),
];
$notice = flash();
$profileReturnUrl = role_dashboard_path((string) ($user['role'] ?? '')) ?: 'dashboard.php';
$savedProfilePhoto = (string) ($user['profile_photo'] ?? '');
$profilePhotoUrl = preg_match('#^uploads/profile-photos/[A-Za-z0-9._-]+\.(jpg|png|webp)$#', $savedProfilePhoto) ? $savedProfilePhoto : '';
renderHeader($passwordOnly ? 'Change Password' : 'Edit Profile', 'profile');
?>
<div class="modal open" id="profileEditorModal" data-return-url="<?php echo h($profileReturnUrl); ?>" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle" aria-hidden="false">
    <a class="modal-backdrop" href="<?php echo h($profileReturnUrl); ?>" aria-label="Close profile editor"></a>
    <article class="modal-card profile-modal-card">
        <a class="modal-close" href="<?php echo h($profileReturnUrl); ?>" aria-label="Close profile editor"><i class="ri-close-line"></i></a>
        <div class="activity-modal-head">
            <span class="category-badge">Account</span>
            <h2 id="profileModalTitle"><?php echo $passwordOnly ? 'Change Password' : 'Edit Profile'; ?></h2>
            <p><?php echo $passwordOnly ? 'Choose a new password for your account.' : 'Update your personal details. Your role and account status are managed by the administrator.'; ?></p>
        </div>
        <?php if ($notice): ?><div class="notice <?php echo h($notice['type']); ?>"><i class="ri-information-line"></i><?php echo h($notice['message']); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice error"><i class="ri-error-warning-line"></i><?php echo h($error); ?></div><?php endif; ?>
        <form method="post" class="portal-form" enctype="multipart/form-data"><?php echo csrf_field(); ?>
            <?php if (!$passwordOnly): ?><div class="profile-photo-editor">
                <span class="profile-photo-preview" id="profilePhotoPreview"><?php if ($profilePhotoUrl): ?><img src="<?php echo h($profilePhotoUrl); ?>" alt="Current profile photo"><?php else: ?><i class="ri-user-3-fill"></i><?php endif; ?></span>
                <label class="field profile-photo-field"><span>Upload Photo</span><input type="file" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, or WebP. Maximum 5 MB.</small></label>
            </div>
            <div class="form-grid">
                <label class="field"><span>Full Name</span><input name="fullname" maxlength="160" value="<?php echo h($form['fullname']); ?>" required></label>
                <label class="field"><span>Email</span><input type="email" name="email" maxlength="190" value="<?php echo h($form['email']); ?>" required></label>
                <label class="field"><span>Username</span><input value="<?php echo h($user['username']); ?>" readonly></label>
                <label class="field"><span>Role</span><input value="<?php echo h($user['role']); ?>" readonly></label>
                <label class="field"><span>Gender</span><select name="gender"><option value="">Not specified</option><?php foreach (['Male', 'Female', 'Other', 'Prefer not to say'] as $option): ?><option value="<?php echo h($option); ?>" <?php echo $form['gender'] === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Birthday</span><input type="date" name="birthday" max="<?php echo h(date('Y-m-d')); ?>" value="<?php echo h($form['birthday']); ?>"></label>
                <label class="field"><span>Phone</span><input type="tel" name="phone" maxlength="30" value="<?php echo h($form['phone']); ?>"></label>
            </div>
            <?php else: ?>
            <h3>Change Password</h3>
            <div class="form-grid">
                <div class="field">
                    <label for="current_password"><span>Current Password</span></label>
                    <div class="password-input-wrap">
                        <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" aria-label="Show password" data-target="current_password">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label for="new_password"><span>New Password</span></label>
                    <div class="password-input-wrap">
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="12">
                        <button type="button" class="password-toggle-btn" aria-label="Show password" data-target="new_password">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label for="confirm_password"><span>Confirm New Password</span></label>
                    <div class="password-input-wrap">
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="12">
                        <button type="button" class="password-toggle-btn" aria-label="Show password" data-target="confirm_password">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                </div>
            </div><?php endif; ?>
            <div class="action-row modal-actions">
                <button class="primary-btn" type="submit"><i class="ri-save-line"></i> <?php echo $passwordOnly ? 'Save Password' : 'Save Profile'; ?></button>
                <a class="ghost-btn" href="<?php echo h($profileReturnUrl); ?>">Cancel</a>
            </div>
        </form>
    </article>
</div>
<script>
    document.body.style.overflow = 'hidden';
    const profilePhotoInput = document.getElementById('profilePhotoInput');
    const profilePhotoPreview = document.getElementById('profilePhotoPreview');
    profilePhotoInput?.addEventListener('change', () => {
        const photo = profilePhotoInput.files?.[0];
        if (!photo || !photo.type.startsWith('image/')) return;
        const image = document.createElement('img');
        image.alt = 'Selected profile photo preview';
        image.src = URL.createObjectURL(photo);
        profilePhotoPreview.replaceChildren(image);
    });

    document.querySelectorAll('.password-toggle-btn[data-target]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);
            const icon = btn.querySelector('i');
            if (input && icon) {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.className = isPassword ? 'ri-eye-off-line' : 'ri-eye-line';
                btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                icon.style.color = isPassword ? '#15803d' : '#6f7b73';
            }
        });
    });
</script>
<?php renderFooter(); ?>
