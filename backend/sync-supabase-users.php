<?php
/**
 * OVPRE I-Record - Supabase Auth User Synchronizer
 * ------------------------------------------------------------------
 * Synchronizes local user accounts to Supabase Auth so that Row Level
 * Security (RLS) policies and authentication tokens function seamlessly.
 *
 * Usage:
 *   php sync-supabase-users.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/supabase.php';

echo "========================================================\n";
echo " OVPRE I-Record: Supabase Auth User Synchronization\n";
echo "========================================================\n\n";

if (!defined('SUPABASE_URL') || SUPABASE_URL === '' || !defined('SUPABASE_SECRET_KEY') || SUPABASE_SECRET_KEY === '') {
    echo "ERROR: Supabase URL or SECRET KEY is not configured in config.local.php.\n";
    exit(1);
}

try {
    $pdo = db();
    $users = $pdo->query("SELECT id, username, fullname, email, role, status, auth_user_id FROM users WHERE deleted_at IS NULL ORDER BY id")->fetchAll();
    
    echo "Found " . count($users) . " active user records in database.\n\n";

    $synced = 0;
    $alreadyLinked = 0;
    $failed = 0;

    foreach ($users as $u) {
        $id = (int) $u['id'];
        $username = (string) $u['username'];
        $email = (string) $u['email'];
        $role = (string) $u['role'];
        $fullname = (string) ($u['fullname'] ?? $username);
        $authId = (string) ($u['auth_user_id'] ?? '');

        if ($authId !== '') {
            echo " [OK] User #{$id} ({$username}) already linked to Supabase Auth ({$authId})\n";
            $alreadyLinked++;
            continue;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo " [SKIP] User #{$id} ({$username}) has invalid or empty email: '{$email}'\n";
            $failed++;
            continue;
        }

        // 1. Check if user already exists in Supabase Auth
        $existing = supabase_auth_admin_find_user_by_email($email);
        if ($existing && !empty($existing['id'])) {
            $matchedAuthId = (string) $existing['id'];
            $pdo->prepare("UPDATE users SET auth_user_id = ?, updated_at = NOW() WHERE id = ?")->execute([$matchedAuthId, $id]);
            echo " [LINKED] User #{$id} ({$username}) matched existing Supabase Auth ({$matchedAuthId})\n";
            $synced++;
            continue;
        }

        // 2. Create user in Supabase Auth
        $initialPassword = 'ChangeMe@2026!';
        $created = supabase_auth_admin_create_user($email, $initialPassword, [
            'fullname' => $fullname,
            'username' => $username,
            'role' => $role
        ]);

        if ($created && !empty($created['id'])) {
            $newAuthId = (string) $created['id'];
            $pdo->prepare("UPDATE users SET auth_user_id = ?, updated_at = NOW() WHERE id = ?")->execute([$newAuthId, $id]);
            echo " [CREATED] User #{$id} ({$username}) created in Supabase Auth ({$newAuthId})\n";
            $synced++;
        } else {
            echo " [FAIL] Could not create user #{$id} ({$username} / {$email}) in Supabase Auth.\n";
            $failed++;
        }
    }

    echo "\n--------------------------------------------------------\n";
    echo "Summary: Already Linked: {$alreadyLinked} | Newly Synced: {$synced} | Failed/Skipped: {$failed}\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
