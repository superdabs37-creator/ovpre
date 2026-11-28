<?php
/**
 * OVPRE Weekly Dean & Director Digest Cron Job
 * 
 * Can be run via CLI:
 *   php backend/cron-weekly-digest.php
 * Or via HTTP request with secret token or authenticated administrator session:
 *   GET backend/cron-weekly-digest.php?key=ovpre_weekly_digest_secret
 */
require_once __DIR__ . '/includes/bootstrap.php';

$isCli = (php_sapi_name() === 'cli' || defined('STDIN'));
$cronSecret = 'ovpre_weekly_digest_secret';

if (!$isCli) {
    $providedKey = (string) ($_GET['key'] ?? '');
    $user = current_user();
    $isSuperAdmin = ($user && in_array(role_access_group($user['role'] ?? ''), [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN, ROLE_RESEARCH_DIRECTOR], true));

    if (!hash_equals($cronSecret, $providedKey) && !$isSuperAdmin) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized access. Provide a valid secret key or login as administrator.']);
        exit;
    }
}

$result = send_weekly_dean_director_digest();

if ($isCli) {
    echo "==========================================\n";
    echo "OVPRE Weekly Dean & Director Digest\n";
    echo "==========================================\n";
    echo "Status: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Message: " . $result['message'] . "\n";
    echo "Delivered Count: " . $result['count'] . "\n";
    if (!empty($result['recipients'])) {
        echo "Recipients: " . implode(', ', $result['recipients']) . "\n";
    }
    echo "==========================================\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
}
