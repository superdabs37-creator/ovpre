<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if (!$user) {
    redirect_to('index.php');
}

// Support both POST (form submit) and GET (dropdown click with CSRF token)
$token = (string) ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_GET['token'] ?? '');
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(419);
    flash('error', 'Role switch session token expired. Please try again.');
    $fallback = role_dashboard_path((string) ($user['role'] ?? '')) ?? 'dashboard.php';
    redirect_to($fallback);
}

$targetRole = trim((string) ($_POST['role'] ?? $_GET['role'] ?? ''));

// Remember original user ID before the first switch
if (empty($_SESSION['original_user_id'])) {
    $_SESSION['original_user_id'] = (int) $user['id'];
}

$originalUserId = (int) $_SESSION['original_user_id'];

// If reset requested, switch back to the original logged in user account
if ($targetRole === 'reset') {
    $_SESSION['user_id'] = $originalUserId;
    unset($_SESSION['active_role']);
    unset($_SESSION['original_user_id']);
    
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$originalUserId]);
    $orig = $stmt->fetch();
    $origName = $orig['fullname'] ?? 'Original Account';
    $origRole = (string) ($orig['role'] ?? ROLE_SUPER_ADMIN);

    log_action($originalUserId, "Reset active account to {$origName} ({$origRole})");
    flash('success', "Switched back to {$origName} ({$origRole}).");
    $destination = role_dashboard_path($origRole) ?? 'dashboard.php';
    redirect_to($destination);
}

// Find designated account for the target role
$designated = find_designated_user_for_role($targetRole);

if ($designated) {
    // Switch active user session directly to the designated account
    $_SESSION['user_id'] = (int) $designated['id'];
    $_SESSION['active_role'] = (string) $designated['role'];

    log_action((int) $designated['id'], "Designated account switch: active account set to {$designated['fullname']} ({$targetRole}) by user #{$originalUserId}");
    flash('success', "Switched to {$designated['fullname']} ({$targetRole}).");

    $destination = role_dashboard_path($targetRole) ?? 'dashboard.php';
    redirect_to($destination);
}

// Fallback: If no designated account found in database, switch active_role string
$availableRoles = user_available_roles($user);
if (!in_array($targetRole, $availableRoles, true)) {
    flash('error', 'You are not assigned to the selected role: ' . $targetRole);
    $fallback = role_dashboard_path((string) ($user['role'] ?? '')) ?? 'dashboard.php';
    redirect_to($fallback);
}

$_SESSION['active_role'] = $targetRole;
log_action((int) $user['id'], "Switched active role to {$targetRole}");
flash('success', "Switched to {$targetRole} portal.");

$destination = role_dashboard_path($targetRole) ?? 'dashboard.php';
redirect_to($destination);
