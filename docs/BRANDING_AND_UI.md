# Safety Tours — branded web, login and PDF reports

## Administrator controls

Once the updated source is deployed, sign in as an **administrator** and open:

`https://sitesafety.site/admin_branding.php`

Or follow the **Admin → Branding & reports** menu. The company editor supports:

- Company name shown in website navigation, login and generated reports
- Website subtitle and PDF report title
- New JPG, PNG or WebP logo (maximum 2 MB, 32–4000 pixels)
- Live website and A4 PDF-style previews
- Restore the neutral Site Safety logo

The uploaded file is inspected by PHP GD and converted to a clean JPEG;
transparent backgrounds are flattened to white so standard FPDF can print
the logo. The report renderer fits very wide or tall logos inside the
header. When no custom logo is supplied, the generic Safety icon is used.

Changing the company logo affects newly created reports. Older
`/pdf.php?id=...` links regenerate their cached report if logo or
settings are newer than the PDF. Tour responses, scores and actions are
never edited by a branding change.

## User experience

- The sign-in screen mirrors Site Notices' two-column hero/form layout,
  stacking into a mobile-friendly layout on small screens.
- Shared navigation and admin links use the selected company identity.
- Navigation stays usable as a horizontal scroll strip on narrow phones.
- Actions register and user list have scrollable tables and larger
  touch targets; new/edit tour forms and action details use fluid inputs.
- PWA cache is restricted to public static shell assets. The worker
  **must not cache confidential PHP pages, uploaded records or PDFs**.
  The app's login/data features still require network access unless
  an individual workflow implements its own protected offline storage.

## Deployment and persistence

In Plesk for `sitesafety.site`:

1. Git → **Pull now → Deploy now** (repo `irlam/safety-tracker`).
2. Confirm PHP **GD** and **mbstring** extensions are enabled.
3. Confirm the PHP user may create/write `httpdocs/uploads/branding`.
   Runtime `settings.json` and `company-logo.jpg` are ignored by Git,
   **not source files**. Preserve `uploads/` across Git deployment,
   site copies, and backups. Never use deployment cleanup to delete it.
4. Hard refresh desktop and mobile browsers and, if previously installed
   as a PWA, allow the new service worker to activate.
5. Test saving a sample company name and PNG/JPG logo from the admin panel;
   confirm navbar, login and generated PDF all show that identity.
6. Create a nonproduction test tour, download a report, change the logo
   again and reopen the old `pdf.php?id=...` URL. It should refresh
   the branding. Use a staging project to test submissions and emails.

No new database table or database migration is required.

## Security

Only authenticated **admins** can update or delete logos. Form
submissions have CSRF validation. Raw images and untrusted HTML/SVG
uploads are rejected; staff passwords or API keys do not appear
in the company settings file.

The GitHub Actions Safety regression checks validate generated PDF
output, settings persistence, admin/CSRF hooks and service worker syntax.
They do not replace a real on-device upload and Plesk permission test.
