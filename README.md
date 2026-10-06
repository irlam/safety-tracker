# Safety Tours — Root, Self-hosted (FPDF)
- Uses **FPDF** for PDFs (drop `fpdf.php` into `lib/fpdf/`).
- Auth gate (password) enabled — change AUTH_PASSWORD in the private configuration.
- Recipient chips for multi-recipient emailing.
- No Composer required; optional PHPMailer in `lib/phpmailer/src/` or copy `vendor/`.

## Setup
1) Copy `includes/config.example.php` to an outside-root `../private/config.php` (or configure `SAFETY_CONFIG_FILE`) and update with your credentials.
2) Upload all files to your web server docroot.
3) Run `safety_schema.sql` on your database (if available).
4) Drop **FPDF** file into `lib/fpdf/fpdf.php` (and PHPMailer into `lib/phpmailer/src/` if needed).
5) Replace `assets/img/logo.png` with your logo.
6) Visit `/form.php` (password prompt defaults to `site-safety`). Submit a test; check DB, PDF, and email.

## Security Notes
- **IMPORTANT**: Never commit private configuration with real credentials to version control
- Update `AUTH_PASSWORD` in the private configuration before deployment
- Ensure `uploads/` directory has appropriate permissions (0775) but is not directly web-accessible for sensitive files
- Review and update database credentials, SMTP settings, and base URL in `config.php`

Generated: 2025-09-18T19:52:21.601132Z

## Existing deployment migration

Before pulling a configuration-loader release, deploy and run `bin/migrate-private-config.php` with PHP CLI to preserve the old live configuration outside httpdocs. It rewrites upload and bootstrap references around `SAFETY_APP_ROOT`, preserving their original application locations. Each existing installation must preserve its own values.

For hosts restricted to the document root, run `bin/install-runtime-config.php` after preservation. It creates an ignored owner-only `includes/runtime.private.php`, with a PHP guard rejecting direct web requests. The loader uses this file first, without widening the allowed filesystem boundary to other sites. Do not overwrite either private file or commit it. Keep the outside-root copy for recovery. Git history still contains older credentials; coordinate rotation separately.
