<?php
declare(strict_types=1);

define('APP_NAME', 'I-Record');
define('APP_ORG', 'Office of the Vice President for Research and Extension');
define('APP_BASE_URL', getenv('APP_BASE_URL') ?: 'http://localhost/ovpre');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'ovpre');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('MAIL_FROM', getenv('MAIL_FROM') ?: 'no-reply@plsp.edu.ph');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME . ' Security');
// Set these in the PHP/Apache environment for outbound email (587 = STARTTLS, 465 = SSL).
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_USER', getenv('SMTP_USER') ?: 'superdabs37@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'enzk fwsu wjrm stjt');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));

define('UPLOAD_DIR', __DIR__ . '/uploads/records');
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024);
define('MAX_LOGIN_ATTEMPTS', 5);
