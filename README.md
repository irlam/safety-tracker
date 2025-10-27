# Safety Tours — Root, Self-hosted (FPDF)
- Uses **FPDF** for PDFs (drop `fpdf.php` into `lib/fpdf/`).
- Auth gate (password) enabled — change AUTH_PASSWORD in `includes/config.php`.
- Recipient chips for multi-recipient emailing.
- No Composer required; optional PHPMailer in `lib/phpmailer/src/` or copy `vendor/`.

Setup:
1) Upload all files to the docroot of safety.defecttracker.uk.
2) Run `safety_schema.sql` on DB `k87747_safety`.
3) Drop **FPDF** file into `lib/fpdf/fpdf.php` (and PHPMailer into `lib/phpmailer/src/` if needed).
4) Replace `assets/img/logo.png` with your logo.
5) Visit `/form.php` (password prompt defaults to `site-safety`). Submit a test; check DB, PDF, and email.

Generated: 2025-09-18T19:52:21.601132Z
