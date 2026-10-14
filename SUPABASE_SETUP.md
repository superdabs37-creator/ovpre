# Supabase Auth and private files

## Server configuration

Set these variables in the PHP/Apache process environment or in the ignored, server-only `config.local.php` file. Environment variables take precedence. Never put the secret key in HTML, JavaScript, or any browser-visible file. `config.local.php` is excluded from Git.

- `SUPABASE_URL`: project URL, for example `https://your-project.supabase.co`
- `SUPABASE_ANON_KEY`: publishable/anon key used for password sign-in
- `SUPABASE_SECRET_KEY`: server-only secret key used for invitations and Storage
- `SUPABASE_STORAGE_BUCKET`: private bucket name; defaults to `irecord-records`
- `APP_BASE_URL`: browser-accessible base URL for invitation and recovery callbacks

On XAMPP, you can either configure environment variables for Apache/PHP (for example with Apache `SetEnv` in a server-only configuration) and restart Apache, or add the keys to the returned array in `config.local.php`:

```php
return [
    // Keep any existing local settings in this array.
    'SUPABASE_URL' => 'https://your-project.supabase.co',
    'SUPABASE_ANON_KEY' => 'your-publishable-or-anon-key',
    'SUPABASE_SECRET_KEY' => 'your-server-only-secret-or-service-role-key',
];
```

Get the project URL and keys from the Supabase project settings. The PHP `SMTP_*` settings send I-Record's email OTP and local password reset messages. Supabase Auth invitation emails use Supabase's own Auth SMTP settings; configure custom SMTP in the Supabase Dashboard under Authentication settings. Supabase's default Auth mailer only sends to project team addresses, so it is not suitable for inviting general users. See [Supabase SMTP setup](https://supabase.com/docs/guides/auth/auth-smtp).

Add the invite callback URL (by default, `http://localhost/ovpre/complete-invitation.php`) to the allowed Redirect URLs in Supabase Authentication URL Configuration. If `APP_BASE_URL` is different, allow the corresponding `/complete-invitation.php` URL instead. See [Supabase redirect URL configuration](https://supabase.com/docs/guides/auth/redirect-urls).

## One-time database cutover

1. Review the read-only report at `database/audits/20261015_user_role_status_audit.postgresql.sql`.
2. Apply `database/migrations/20261015_user_roles_auth_rls.postgresql.sql` once in the Supabase SQL Editor. It preserves profile IDs and existing records, adds the Auth link, normalizes roles/statuses, and installs RLS policies plus a private Storage bucket.
3. Keep the current Super Admin browser session open. In User Management, send an Auth invitation for each existing profile that has no Auth link, including the Super Admin profile. Existing Active/Inactive status is retained; each person must set a new password. New invitations move to Pending Approval after password setup.
4. The System Administrator approves Pending Approval profiles. Existing active Super Admin can then sign in through Supabase Auth with the new password and email OTP.

The supplied live database audit found eight profiles and no Supabase Auth users, so existing accounts cannot use Supabase password sign-in until invitations are accepted. Do not log out of the existing admin session until the Super Admin invitation has been accepted and a fresh login succeeds.

New documents are uploaded to the private Supabase Storage bucket. Older files remain on disk and are served through `download-document.php`, which checks the signed-in profile’s ownership or department scope. Direct web access to `uploads/records` is denied.
