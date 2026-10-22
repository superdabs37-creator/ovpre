<?php
require_once __DIR__ . '/includes/bootstrap.php';

$notice = flash();
$errors = [];
$departmentsList = departments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $accountType = trim((string) ($_POST['account_type'] ?? 'personal'));
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $birthday = trim((string) ($_POST['birthday'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $departmentIdRaw = trim((string) ($_POST['department_id'] ?? ''));
    $departmentId = ($departmentIdRaw === '' || $departmentIdRaw === '0') ? null : (int) $departmentIdRaw;
    
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $recoveryEmail = strtolower(trim((string) ($_POST['recovery_email'] ?? '')));
    $recoveryPhone = trim((string) ($_POST['recovery_phone'] ?? ''));

    // Validation
    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($birthday === '' || !strtotime($birthday)) {
        $errors[] = 'Please provide a valid date of birth.';
    }
    if ($gender === '') {
        $errors[] = 'Please select your gender.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Username must be at least 3 characters and contain only letters, numbers, dots, or underscores.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    // Check email uniqueness
    $stmt = db()->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        $errors[] = 'An account with this email address already exists. Please sign in instead.';
    }

    // Check username uniqueness
    $stmt = db()->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        $errors[] = 'That username is already taken. Please choose a different one.';
    }

    if (empty($errors)) {
        // Map account type to role
        $role = match ($accountType) {
            'personal' => ROLE_RESEARCH_DIRECTOR,
            'child' => ROLE_RESEARCH_COORDINATOR,
            'work' => ROLE_RESEARCHER,
            default => ROLE_RESEARCHER,
        };

        $fullname = trim($firstName . ' ' . $lastName);
        if ($fullname === '') {
            $fullname = $username;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Direct insert with Active status - NO ADMIN APPROVAL REQUIRED!
        $insertStmt = db()->prepare("
            INSERT INTO users (
                fullname, gender, birthday, email, phone, username, password,
                role, department_id, status, approved_at, login_attempts, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, 'Active', NOW(), 0, NOW(), NOW()
            )
        ");
        $insertStmt->execute([
            $fullname,
            $gender ?: null,
            $birthday ?: null,
            $email,
            $phone ?: null,
            $username,
            $passwordHash,
            $role,
            $departmentId
        ]);

        $newUserId = (int) db()->lastInsertId();

        // Optional Supabase Auth sync (silent fallback)
        try {
            if (function_exists('supabase_auth_signup')) {
                supabase_auth_signup($email, $password, [
                    'full_name' => $fullname,
                    'username' => $username,
                    'role' => $role,
                    'department_id' => $departmentId
                ]);
            }
        } catch (Throwable $e) {}

        log_action($newUserId, 'Self-registered active account (' . $accountType . '): ' . $username);
        log_login_event($newUserId, $username, 'Registered & Auto-Activated');

        // Automatically establish session for seamless access
        $_SESSION['user_id'] = $newUserId;
        $dest = role_dashboard_path($role) ?? 'dashboard.php';
        redirect_to($dest);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account | <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg?v=20261012">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --reg-green: #15803d;
            --reg-green-dark: #0f3d24;
            --reg-green-light: #f0fdf4;
            --reg-border: #cbd5e1;
            --reg-border-focus: #16a34a;
        }

        body.register-body {
            background: linear-gradient(135deg, #f0fdf4 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1e293b;
        }

        .register-container {
            width: 100%;
            max-width: 680px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 45px rgba(15, 61, 36, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(22, 163, 74, 0.15);
            overflow: hidden;
        }

        .register-header {
            padding: 28px 32px 18px;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
            position: relative;
        }

        .register-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .register-brand img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #86efac;
        }

        .register-brand-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--reg-green-dark);
            letter-spacing: -0.02em;
        }

        .register-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin: 6px 0 4px;
        }

        .register-subtitle {
            font-size: 0.88rem;
            color: #64748b;
            margin: 0;
        }

        /* Stepper Progress Bar */
        .stepper-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 28px;
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            overflow-x: auto;
        }

        .step-node {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            position: relative;
            flex: 1;
        }

        .step-node::after {
            content: '';
            position: absolute;
            top: 14px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }

        .step-node:last-child::after {
            display: none;
        }

        .step-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            transition: all 0.25s ease;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .step-node.active .step-circle {
            background: var(--reg-green);
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.2);
        }

        .step-node.completed .step-circle {
            background: #16a34a;
            color: #ffffff;
        }

        .step-node.completed::after {
            background: #86efac;
        }

        .step-name {
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            text-align: center;
            white-space: nowrap;
        }

        .step-node.active .step-name {
            color: var(--reg-green-dark);
            font-weight: 700;
        }

        /* Step Contents */
        .register-body-content {
            padding: 28px 32px;
        }

        .step-pane {
            display: none;
            animation: fadeIn 0.28s ease;
        }

        .step-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-header {
            margin-bottom: 22px;
        }

        .step-header h3 {
            font-size: 1.22rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step-header p {
            font-size: 0.88rem;
            color: #64748b;
            margin: 0;
            line-height: 1.45;
        }

        /* Account Type Cards */
        .account-type-grid {
            display: grid;
            gap: 14px;
        }

        .account-type-card {
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s ease;
            position: relative;
            background: #ffffff;
        }

        .account-type-card:hover {
            border-color: #86efac;
            background: #fbfdfc;
            transform: translateY(-1px);
        }

        .account-type-card.selected {
            border-color: var(--reg-green);
            background: #f0fdf4;
            box-shadow: 0 4px 14px rgba(21, 128, 61, 0.12);
        }

        .account-type-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #f1f5f9;
            color: #475569;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .account-type-card.selected .account-type-icon {
            background: var(--reg-green);
            color: #ffffff;
        }

        .account-type-info h4 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 3px;
            color: #0f172a;
        }

        .account-type-info p {
            font-size: 0.82rem;
            color: #64748b;
            margin: 0;
            line-height: 1.4;
        }

        .account-type-radio {
            margin-left: auto;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .account-type-card.selected .account-type-radio {
            border-color: var(--reg-green);
            background: var(--reg-green);
        }

        .account-type-card.selected .account-type-radio::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ffffff;
        }

        /* Form Inputs */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 0.84rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-label .optional {
            font-weight: 400;
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--reg-border);
            border-radius: 10px;
            font-size: 0.92rem;
            color: #0f172a;
            background: #ffffff;
            transition: all 0.2s ease;
            box-sizing: border-box;
            outline: none;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--reg-border-focus);
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.14);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
        }

        .input-with-icon .form-input {
            padding-left: 42px;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 1.15rem;
            padding: 0;
            display: flex;
            align-items: center;
        }

        /* Suggestions Pills */
        .suggestions-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 16px;
        }

        .suggestions-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .suggestions-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .suggestion-pill {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #0f172a;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .suggestion-pill:hover {
            border-color: var(--reg-green);
            background: #f0fdf4;
            color: var(--reg-green);
        }

        /* Password Strength Meter */
        .strength-meter {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }

        .strength-checklist {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 10px;
            font-size: 0.78rem;
            color: #64748b;
        }

        .strength-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .strength-item.valid {
            color: #16a34a;
            font-weight: 600;
        }

        .strength-item.valid i {
            color: #16a34a;
        }

        /* Identity Verification Simulation */
        .verification-panel {
            background: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            border-radius: 14px;
            padding: 18px;
            margin-top: 10px;
            text-align: center;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 10px;
        }

        .otp-inputs {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 14px 0;
        }

        .otp-input {
            width: 44px;
            height: 48px;
            text-align: center;
            font-size: 1.25rem;
            font-weight: 700;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
        }

        .otp-input:focus {
            border-color: var(--reg-green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }

        /* Review Recap Card */
        .recap-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .recap-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #edf2f7;
            font-size: 0.88rem;
        }

        .recap-row:last-child {
            border-bottom: none;
        }

        .recap-label {
            color: #64748b;
            font-weight: 500;
        }

        .recap-val {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
        }

        /* Action Buttons */
        .register-footer {
            padding: 18px 32px 28px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
        }

        .btn-prev {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-prev:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-next, .btn-submit {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
            color: #ffffff;
            border: none;
            padding: 11px 24px;
            border-radius: 10px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 14px rgba(21, 128, 61, 0.25);
            transition: all 0.2s ease;
            margin-left: auto;
        }

        .btn-next:hover, .btn-submit:hover {
            box-shadow: 0 6px 18px rgba(21, 128, 61, 0.35);
            transform: translateY(-1px);
        }

        .instant-active-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #15803d;
            background: #dcfce7;
            padding: 4px 10px;
            border-radius: 14px;
            font-weight: 700;
        }
    </style>
</head>
<body class="register-body">

<div class="register-container">
    <!-- Header -->
    <header class="register-header">
        <div class="register-brand">
            <img src="assets/img/dlsp-seal.jpg" alt="DLSP Seal">
            <img src="assets/img/ovpre-logo.jpg" alt="OVPRE Logo">
            <span class="register-brand-title">DLSP OVPRE</span>
        </div>
        <h1 class="register-title">Create an Account</h1>
        <p class="register-subtitle">Instant registration &bull; No admin approval required &bull; Ready immediately</p>
    </header>

    <!-- Stepper Navigation -->
    <nav class="stepper-nav" aria-label="Registration steps">
        <div class="step-node active" data-step="1" onclick="jumpToStep(1)">
            <div class="step-circle">1</div>
            <span class="step-name">Account Type</span>
        </div>
        <div class="step-node" data-step="2" onclick="jumpToStep(2)">
            <div class="step-circle">2</div>
            <span class="step-name">Basic Info</span>
        </div>
        <div class="step-node" data-step="3" onclick="jumpToStep(3)">
            <div class="step-circle">3</div>
            <span class="step-name">Email</span>
        </div>
        <div class="step-node" data-step="4" onclick="jumpToStep(4)">
            <div class="step-circle">4</div>
            <span class="step-name">Password</span>
        </div>
        <div class="step-node" data-step="5" onclick="jumpToStep(5)">
            <div class="step-circle">5</div>
            <span class="step-name">Verification</span>
        </div>
        <div class="step-node" data-step="6" onclick="jumpToStep(6)">
            <div class="step-circle">6</div>
            <span class="step-name">Recovery &amp; Complete</span>
        </div>
    </nav>

    <!-- Error Alerts -->
    <?php if (!empty($errors)): ?>
        <div style="margin: 16px 32px 0; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; color: #b91c1c; font-size: 0.88rem;">
            <strong><i class="ri-error-warning-line"></i> Please correct the following:</strong>
            <ul style="margin: 6px 0 0; padding-left: 20px;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Main Registration Form -->
    <form id="registrationForm" method="post" action="register.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="account_type" id="accountTypeInput" value="personal">

        <div class="register-body-content">

            <!-- ==============================================
                 STEP 1: SELECT ACCOUNT TYPE
                 ============================================== -->
            <div class="step-pane active" id="pane-step-1">
                <div class="step-header">
                    <h3><i class="ri-user-settings-line" style="color:var(--reg-green);"></i> Select Account Type</h3>
                    <p>Choose whether the account is for Admin/Directors,Coordinators, or Researchers/Extentionists.</p>
                </div>

                <div class="account-type-grid">
                    <!-- Option 1: Personal use -->
                    <div class="account-type-card selected" data-type="personal" onclick="selectAccountType('personal', this)">
                        <div class="account-type-icon"><i class="ri-user-smile-line"></i></div>
                        <div class="account-type-info">
                            <h4>Admin/Directors</h4>
                            <p>For Admin, Directors of OVPRE.</p>
                        </div>
                        <div class="account-type-radio"></div>
                    </div>

                    <!-- Option 2: For my child -->
                    <div class="account-type-card" data-type="child" onclick="selectAccountType('child', this)">
                        <div class="account-type-icon"><i class="ri-parent-line"></i></div>
                        <div class="account-type-info">
                            <h4>Coordinator</h4>
                            <p>For Research and extension Coordinators of the institution.</p>
                        </div>
                        <div class="account-type-radio"></div>
                    </div>

                    <!-- Option 3: For work or my business -->
                    <div class="account-type-card" data-type="work" onclick="selectAccountType('work', this)">
                        <div class="account-type-icon"><i class="ri-briefcase-line"></i></div>
                        <div class="account-type-info"> 
                            <h4>Researchers/Extentionist</h4>
                            <p>For faculty Researchers and Extension practitioners.</p>
                        </div>
                        <div class="account-type-radio"></div>
                    </div>
                </div>
            </div>

            <!-- ==============================================
                 STEP 2: ENTER BASIC INFORMATION
                 ============================================== -->
            <div class="step-pane" id="pane-step-2">
                <div class="step-header">
                    <h3><i class="ri-id-card-line" style="color:var(--reg-green);"></i> Enter Basic Information</h3>
                    <p>Provide your first name, optional last name, date of birth, and gender.</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name *</label>
                        <input type="text" class="form-input" id="first_name" name="first_name" placeholder="Enter first name" required oninput="generateSuggestions()">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name <span class="optional">(optional)</span></label>
                        <input type="text" class="form-input" id="last_name" name="last_name" placeholder="Enter last name" oninput="generateSuggestions()">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="birthday">Date of Birth *</label>
                        <input type="date" class="form-input" id="birthday" name="birthday" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gender">Gender *</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="" disabled selected>Select gender</option>
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                            <option value="Non-binary">Non-binary</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="department_id">College Affiliation *</label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <option value="" disabled selected>Select your College</option>
                        <?php foreach ($departmentsList as $dept): ?>
                            <option value="<?php echo (int) $dept['id']; ?>"><?php echo h($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- ==============================================
                 STEP 3: CHOOSE YOUR EMAIL ADDRESS
                 ============================================== -->
            <div class="step-pane" id="pane-step-3">
                <div class="step-header">
                    <h3><i class="ri-mail-line" style="color:var(--reg-green);"></i> Choose Your Email Address</h3>
                    <p>Pick a suggested Gmail address, create a custom Gmail address, or use your existing third-party email.</p>
                </div>

                <!-- Suggestions container -->
                <div class="suggestions-box">
                    <div class="suggestions-title"><i class="ri-magic-line"></i> Suggested Addresses (click to use):</div>
                    <div class="suggestions-pills" id="emailPills">
                        <span class="suggestion-pill" onclick="setEmailValue(this.textContent)">user.dlsp@dlsp.edu.ph</span>
                        <span class="suggestion-pill" onclick="setEmailValue(this.textContent)">researcher.dlsp@gmail.com</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <div class="input-with-icon">
                        <i class="ri-at-line"></i>
                        <input type="email" class="form-input" id="email" name="email" placeholder="e.g. yourname@dlsp.edu.ph or custom@gmail.com" required oninput="syncUsernameWithEmail()">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">Username (for sign in) *</label>
                    <div class="input-with-icon">
                        <i class="ri-user-line"></i>
                        <input type="text" class="form-input" id="username" name="username" placeholder="e.g. juan_delacruz" required>
                    </div>
                    <small style="color:#64748b; font-size:0.78rem;">You can sign in using either your email address or username.</small>
                </div>
            </div>

            <!-- ==============================================
                 STEP 4: CREATE A PASSWORD
                 ============================================== -->
            <div class="step-pane" id="pane-step-4">
                <div class="step-header">
                    <h3><i class="ri-lock-password-line" style="color:var(--reg-green);"></i> Create a Password</h3>
                    <p>Enter and confirm a strong, unique password to secure your account.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password *</label>
                    <div class="input-with-icon">
                        <i class="ri-lock-line"></i>
                        <input type="password" class="form-input" id="password" name="password" placeholder="Create strong password" required oninput="checkPasswordStrength()">
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password', this)" aria-label="Toggle password">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>

                    <!-- Password strength bar -->
                    <div class="strength-meter">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>

                    <!-- Live checklist -->
                    <div class="strength-checklist">
                        <div class="strength-item" id="req-len"><i class="ri-circle-line"></i> 8+ characters</div>
                        <div class="strength-item" id="req-num"><i class="ri-circle-line"></i> Contains a number</div>
                        <div class="strength-item" id="req-cap"><i class="ri-circle-line"></i> Uppercase letter</div>
                        <div class="strength-item" id="req-sym"><i class="ri-circle-line"></i> Special character</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <div class="input-with-icon">
                        <i class="ri-shield-check-line"></i>
                        <input type="password" class="form-input" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required oninput="checkPasswordMatch()">
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)" aria-label="Toggle password">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    <small id="matchMessage" style="font-size:0.8rem; font-weight:600; margin-top:4px; display:block;"></small>
                </div>
            </div>

            <!-- ==============================================
                 STEP 5: VERIFY YOUR IDENTITY
                 ============================================== -->
            <div class="step-pane" id="pane-step-5">
                <div class="step-header">
                    <h3><i class="ri-smartphone-line" style="color:var(--reg-green);"></i> Verify Your Identity</h3>
                    <p>Enter a mobile phone number to receive an SMS verification code.</p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Mobile Phone Number *</label>
                    <div class="input-with-icon">
                        <i class="ri-phone-line"></i>
                        <input type="tel" class="form-input" id="phone" name="phone" placeholder="e.g. +63 917 123 4567 or 09171234567" required>
                    </div>
                </div>

                <div class="verification-panel" style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 14px 18px; border-radius: 12px; margin-top: 14px;">
                    <div style="display: flex; align-items: center; gap: 8px; color: #166534; font-weight: 700; font-size: 0.9rem;">
                        <i class="ri-checkbox-circle-fill" style="font-size: 1.2rem; color: #15803d;"></i>
                        <span>Contact Information Verified</span>
                    </div>
                    <p style="margin: 4px 0 0; font-size: 0.82rem; color: #4b5563;">
                        Your mobile number will be used for official proposal updates, coordinator feedback, and institutional notices.
                    </p>
                </div>
            </div>

            <!-- ==============================================
                 STEP 6: ADD RECOVERY INFO & COMPLETE
                 ============================================== -->
            <div class="step-pane" id="pane-step-6">
                <div class="step-header">
                    <h3><i class="ri-shield-keyhole-line" style="color:var(--reg-green);"></i> Add Recovery Info</h3>
                    <p>Provide a recovery email address or phone number to help protect and restore your account.</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="recovery_email">Recovery Email <span class="optional">(optional)</span></label>
                        <input type="email" class="form-input" id="recovery_email" name="recovery_email" placeholder="e.g. backup@gmail.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="recovery_phone">Recovery Phone <span class="optional">(optional)</span></label>
                        <input type="tel" class="form-input" id="recovery_phone" name="recovery_phone" placeholder="e.g. +63 9XX XXX XXXX">
                    </div>
                </div>

                <!-- Account Recap -->
                <div class="recap-card">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                        <strong style="color:#0f172a; font-size:0.95rem;">Profile Summary</strong>
                        <span class="instant-active-tag"><i class="ri-flashlight-line"></i> Instant Activation</span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Account Type</span>
                        <span class="recap-val" id="recapAccountType">Personal use</span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Full Name</span>
                        <span class="recap-val" id="recapFullName">-</span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">College</span>
                        <span class="recap-val" id="recapCollege">-</span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Email &amp; Username</span>
                        <span class="recap-val" id="recapEmailUser">-</span>
                    </div>
                    <div class="recap-row">
                        <span class="recap-label">Verified Mobile</span>
                        <span class="recap-val" id="recapPhone">-</span>
                    </div>
                </div>

                <div style="margin-top: 14px;">
                    <label style="display:flex; align-items:flex-start; gap:8px; font-size:0.84rem; color:#475569; cursor:pointer;">
                        <input type="checkbox" required checked style="margin-top:3px;">
                        <span>I accept the DLSP OVPRE Terms of Service, Ethics and Data Privacy Policy. My account will be active immediately.</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Footer Stepper Controls -->
        <footer class="register-footer">
            <button type="button" class="btn-prev" id="btnPrev" onclick="prevStep()" style="display:none;">
                <i class="ri-arrow-left-line"></i> Back
            </button>

            <button type="button" class="btn-next" id="btnNext" onclick="nextStep()">
                Next <i class="ri-arrow-right-line"></i>
            </button>

            <button type="submit" class="btn-submit" id="btnSubmit" style="display:none;">
                <i class="ri-check-double-line"></i> Create Account &amp; Sign In
            </button>
        </footer>
    </form>

    <div style="text-align: center; padding: 14px; background: #f8fafc; border-top: 1px solid #f1f5f9; font-size: 0.88rem; color: #64748b;">
        Already have an account? <a href="index.php" style="color: var(--reg-green); font-weight: 700; text-decoration: none;">Sign in here</a>
    </div>
</div>

<script>
    let currentStep = 1;
    const totalSteps = 6;

    function selectAccountType(type, card) {
        document.querySelectorAll('.account-type-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        document.getElementById('accountTypeInput').value = type;
        document.getElementById('recapAccountType').textContent = card.querySelector('h4').textContent;
    }

    function jumpToStep(step) {
        if (step < currentStep) {
            goToStep(step);
        } else if (validateStep(currentStep)) {
            goToStep(step);
        }
    }

    function nextStep() {
        if (!validateStep(currentStep)) return;
        if (currentStep < totalSteps) {
            goToStep(currentStep + 1);
        }
    }

    function prevStep() {
        if (currentStep > 1) {
            goToStep(currentStep - 1);
        }
    }

    function goToStep(step) {
        currentStep = step;

        // Update step panes
        document.querySelectorAll('.step-pane').forEach((pane, idx) => {
            pane.classList.toggle('active', idx + 1 === currentStep);
        });

        // Update stepper nav
        document.querySelectorAll('.step-node').forEach((node, idx) => {
            const stepNum = idx + 1;
            node.classList.toggle('active', stepNum === currentStep);
            node.classList.toggle('completed', stepNum < currentStep);
        });

        // Update buttons
        document.getElementById('btnPrev').style.display = currentStep > 1 ? 'inline-flex' : 'none';
        document.getElementById('btnNext').style.display = currentStep < totalSteps ? 'inline-flex' : 'none';
        document.getElementById('btnSubmit').style.display = currentStep === totalSteps ? 'inline-flex' : 'none';

        if (currentStep === totalSteps) {
            updateRecap();
        }
    }

    function validateStep(step) {
        if (step === 2) {
            const fName = document.getElementById('first_name').value.trim();
            const bday = document.getElementById('birthday').value;
            const gender = document.getElementById('gender').value;
            const dept = document.getElementById('department_id').value;
            if (!fName) {
                alert('Please enter your first name.');
                document.getElementById('first_name').focus();
                return false;
            }
            if (!bday) {
                alert('Please enter your date of birth.');
                document.getElementById('birthday').focus();
                return false;
            }
            if (!gender) {
                alert('Please select your gender.');
                document.getElementById('gender').focus();
                return false;
            }
            if (!dept) {
                alert('Please select your College affiliation.');
                document.getElementById('department_id').focus();
                return false;
            }
        } else if (step === 3) {
            const email = document.getElementById('email').value.trim();
            const username = document.getElementById('username').value.trim();
            if (!email || !email.includes('@')) {
                alert('Please enter a valid email address.');
                document.getElementById('email').focus();
                return false;
            }
            if (!username || username.length < 3) {
                alert('Please choose a username of at least 3 characters.');
                document.getElementById('username').focus();
                return false;
            }
        } else if (step === 4) {
            const pass = document.getElementById('password').value;
            const cpass = document.getElementById('confirm_password').value;
            if (pass.length < 8) {
                alert('Password must be at least 8 characters long.');
                document.getElementById('password').focus();
                return false;
            }
            if (pass !== cpass) {
                alert('Passwords do not match. Please re-enter.');
                document.getElementById('confirm_password').focus();
                return false;
            }
        } else if (step === 5) {
            const phone = document.getElementById('phone').value.trim();
            if (!phone || phone.length < 7) {
                alert('Please enter a valid mobile phone number.');
                document.getElementById('phone').focus();
                return false;
            }
        }
        return true;
    }

    function generateSuggestions() {
        const first = document.getElementById('first_name').value.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
        const last = document.getElementById('last_name').value.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
        const pillsContainer = document.getElementById('emailPills');
        if (!pillsContainer) return;

        pillsContainer.innerHTML = '';
        if (!first) {
            pillsContainer.innerHTML = '<span class="suggestion-pill" onclick="setEmailValue(this.textContent)">user.dlsp@dlsp.edu.ph</span><span class="suggestion-pill" onclick="setEmailValue(this.textContent)">researcher@gmail.com</span>';
            return;
        }

        const s1 = last ? `${first}.${last}@dlsp.edu.ph` : `${first}@dlsp.edu.ph`;
        const s2 = last ? `${first[0]}${last}@gmail.com` : `${first}.research@gmail.com`;
        const s3 = last ? `${first}${last}2026@gmail.com` : `${first}2026@dlsp.edu.ph`;

        [s1, s2, s3].forEach(s => {
            const span = document.createElement('span');
            span.className = 'suggestion-pill';
            span.textContent = s;
            span.onclick = () => setEmailValue(s);
            pillsContainer.appendChild(span);
        });

        // Also suggest username
        const uInput = document.getElementById('username');
        if (uInput && !uInput.value) {
            uInput.value = last ? `${first}_${last}` : first;
        }
    }

    function setEmailValue(val) {
        document.getElementById('email').value = val;
        syncUsernameWithEmail();
    }

    function syncUsernameWithEmail() {
        const email = document.getElementById('email').value.trim();
        const uInput = document.getElementById('username');
        if (email && uInput && !uInput.dataset.manuallyEdited) {
            const prefix = email.split('@')[0].replace(/[^a-zA-Z0-9_.-]/g, '');
            if (prefix) {
                uInput.value = prefix;
            }
        }
    }

    document.getElementById('username')?.addEventListener('input', () => {
        document.getElementById('username').dataset.manuallyEdited = 'true';
    });

    function togglePasswordVisibility(fieldId, btn) {
        const input = document.getElementById(fieldId);
        const icon = btn.querySelector('i');
        if (!input || !icon) return;
        const isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        icon.className = isPass ? 'ri-eye-off-line' : 'ri-eye-line';
        icon.style.color = isPass ? '#15803d' : '#64748b';
    }

    function checkPasswordStrength() {
        const pass = document.getElementById('password').value;
        const bar = document.getElementById('strengthBar');
        let score = 0;

        const hasLen = pass.length >= 8;
        const hasNum = /\d/.test(pass);
        const hasCap = /[A-Z]/.test(pass);
        const hasSym = /[^A-Za-z0-9]/.test(pass);

        setReqStatus('req-len', hasLen);
        setReqStatus('req-num', hasNum);
        setReqStatus('req-cap', hasCap);
        setReqStatus('req-sym', hasSym);

        if (hasLen) score += 25;
        if (hasNum) score += 25;
        if (hasCap) score += 25;
        if (hasSym) score += 25;

        bar.style.width = score + '%';
        if (score <= 25) {
            bar.style.background = '#ef4444';
        } else if (score <= 50) {
            bar.style.background = '#f59e0b';
        } else if (score <= 75) {
            bar.style.background = '#3b82f6';
        } else {
            bar.style.background = '#16a34a';
        }

        checkPasswordMatch();
    }

    function setReqStatus(id, valid) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('valid', valid);
        el.querySelector('i').className = valid ? 'ri-checkbox-circle-fill' : 'ri-circle-line';
    }

    function checkPasswordMatch() {
        const pass = document.getElementById('password').value;
        const cpass = document.getElementById('confirm_password').value;
        const msg = document.getElementById('matchMessage');
        if (!msg) return;

        if (!cpass) {
            msg.textContent = '';
            return;
        }

        if (pass === cpass) {
            msg.textContent = 'Passwords match';
            msg.style.color = '#16a34a';
        } else {
            msg.textContent = 'Passwords do not match';
            msg.style.color = '#ef4444';
        }
    }

    function updateRecap() {
        const fName = document.getElementById('first_name').value.trim();
        const lName = document.getElementById('last_name').value.trim();
        const deptSelect = document.getElementById('department_id');
        const deptText = deptSelect.options[deptSelect.selectedIndex]?.text || '-';
        const email = document.getElementById('email').value.trim();
        const username = document.getElementById('username').value.trim();
        const phone = document.getElementById('phone').value.trim();

        document.getElementById('recapFullName').textContent = `${fName} ${lName}`.trim() || '-';
        document.getElementById('recapCollege').textContent = deptText;
        document.getElementById('recapEmailUser').textContent = `${email} (${username})`;
        document.getElementById('recapPhone').textContent = phone || 'Verified';
    }
</script>

</body>
</html>
