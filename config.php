<?php
declare(strict_types=1);

define('APP_NAME', 'I-Record');
define('APP_ORG', 'Office of the Vice President for Research and Extension');

// Keep machine-specific mail credentials out of source control. Environment
// variables take precedence; config.local.php is a convenient local XAMPP option.
$localConfigPath = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) {
    $localConfig = [];
}
$localValue = static fn (string $key, string $default = ''): string =>
    (string) (getenv($key) !== false ? getenv($key) : ($localConfig[$key] ?? $default));

define('APP_BASE_URL', rtrim($localValue('APP_BASE_URL', 'http://localhost/ovpre'), '/'));
define('DB_DRIVER', $localValue('DB_DRIVER', 'mysql'));
define('DB_HOST', $localValue('DB_HOST', '127.0.0.1'));
define('DB_PORT', $localValue('DB_PORT', '3306'));
define('DB_NAME', $localValue('DB_NAME', 'ovpre'));
define('DB_USER', $localValue('DB_USER', 'root'));
define('DB_PASS', $localValue('DB_PASS', ''));

define('MAIL_FROM', $localValue('MAIL_FROM', 'no-reply@plsp.edu.ph'));
define('MAIL_FROM_NAME', $localValue('MAIL_FROM_NAME', APP_NAME . ' Security'));
// Set these in the PHP/Apache environment for outbound email (587 = STARTTLS, 465 = SSL).
define('SMTP_HOST', $localValue('SMTP_HOST'));
define('SMTP_USER', $localValue('SMTP_USER'));
define('SMTP_PASS', $localValue('SMTP_PASS'));
define('SMTP_PORT', (int) $localValue('SMTP_PORT', '587'));

// Supabase Auth and Storage secrets must be configured in the PHP/Apache server
// environment. Never expose SUPABASE_SECRET_KEY in browser code or templates.
define('SUPABASE_URL', rtrim($localValue('SUPABASE_URL'), '/'));
define('SUPABASE_ANON_KEY', $localValue('SUPABASE_ANON_KEY'));
define('SUPABASE_SECRET_KEY', $localValue('SUPABASE_SECRET_KEY'));
define('SUPABASE_STORAGE_BUCKET', $localValue('SUPABASE_STORAGE_BUCKET', 'irecord-records'));

define('UPLOAD_DIR', __DIR__ . '/uploads/records');
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024);
define('MAX_LOGIN_ATTEMPTS', 5);
