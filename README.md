# Safety Tours — Root, Self-hosted (FPDF)
- Uses **FPDF** for PDFs (drop `fpdf.php` into `lib/fpdf/`).
- Auth gate (password) enabled — change AUTH_PASSWORD in `includes/config.php`.
- Recipient chips for multi-recipient emailing.
- No Composer required; optional PHPMailer in `lib/phpmailer/src/` or copy `vendor/`.

## Setup
1) Copy `includes/config.example.php` to `includes/config.php` and update with your credentials.
2) Upload all files to your web server docroot.
3) Run `safety_schema.sql` on your database (if available).
4) Drop **FPDF** file into `lib/fpdf/fpdf.php` (and PHPMailer into `lib/phpmailer/src/` if needed).
5) Replace `assets/img/logo.png` with your logo.
6) Visit `/form.php` (password prompt defaults to `site-safety`). Submit a test; check DB, PDF, and email.

## Security Notes
- **IMPORTANT**: Never commit `includes/config.php` with real credentials to version control
- Update `AUTH_PASSWORD` in `includes/config.php` before deployment
- Ensure `uploads/` directory has appropriate permissions (0775) but is not directly web-accessible for sensitive files
- Review and update database credentials, SMTP settings, and base URL in `config.php`

Generated: 2025-09-18T19:52:21.601132Z
