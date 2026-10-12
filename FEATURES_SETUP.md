# New workflow features

## Database setup

New installs should use `database/schema.sql`.

## Database backups

Super Admins can download and restore database snapshots from **Settings → Backup and Recovery**. Recovery merges rows and updates matching IDs; it does not delete rows absent from the backup. The JSON snapshot does not include uploaded documents or profile photos, so back up the `uploads` directory separately.

## Email service

Set `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`, and `MAIL_FROM_NAME` in the PHP/Apache environment, then restart Apache. The repository no longer supplies SMTP credentials as defaults. Rotate any SMTP app password that was previously stored in the source configuration.
