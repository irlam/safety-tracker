<?php
declare(strict_types=1);

// Never run a fixture that writes branding data on a production server.
if (getenv('CI') !== 'true') {
    fwrite(STDERR, "This branding fixture may only run in isolated CI.\n");
    exit(2);
}
require_once dirname(__DIR__) . '/includes/branding.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function brandCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('FAIL: ' . $message);
}
$root = dirname(__DIR__);
$dir = safety_branding_directory();
brandCheck(!is_dir($dir), 'CI fixture must not overwrite existing branding');
try {
    $default = safety_branding();
    brandCheck($default['company_name'] === 'Site Safety', 'Generic default company identity');
    brandCheck(
        safety_branding_logo_url() === '/assets/img/safety-brand.svg',
        'Generic default icon'
    );
    safety_branding_save([
        'company_name' => 'Example Site Ltd',
        'subtitle' => 'Workplace Safety',
        'report_title' => 'Inspection Test Report'
    ]);
    $updated = safety_branding();
    brandCheck($updated['company_name'] === 'Example Site Ltd', 'Persisted company name');
    brandCheck($updated['subtitle'] === 'Workplace Safety', 'Persisted subtitle');
    brandCheck($updated['report_title'] === 'Inspection Test Report', 'Persisted PDF title');
    brandCheck(safety_branding_revision() > 0, 'Branding revision for PDF regeneration');
    $blocked = false;
    try {
        safety_branding_save(['company_name' => '<script>', 'subtitle' => 'Test', 'report_title' => 'Test']);
    } catch (InvalidArgumentException $e) {
        $blocked = true;
    }
    brandCheck($blocked, 'Invalid HTML company names must be rejected');
    brandCheck(safety_branding()['company_name'] === 'Example Site Ltd', 'Invalid writes must not replace branding');

    // A real FPDF report is created with disposable values, not actual tour data.
    $path = tempnam(sys_get_temp_dir(), 'safety-brand-');
    if ($path === false) throw new RuntimeException('Temp PDF unavailable');
    try {
        render_pdf([
            'tour_date' => '2026-10-06 09:00:00',
            'site' => 'Example Test Site',
            'area' => 'Block A',
            'lead_name' => 'Example Tester',
            'status' => 'Open',
            'responses' => '[]',
            'photos' => '[]'
        ], $path);
        $head = file_get_contents($path, false, null, 0, 8);
        brandCheck(str_starts_with((string) $head, '%PDF-'), 'PDF renderer must produce a real PDF');
        brandCheck(filesize($path) > 700, 'PDF output must contain content');
    } finally {
        @unlink($path);
    }
    $nav = (string) file_get_contents($root . '/includes/nav.php');
    $login = (string) file_get_contents($root . '/login_admin.php');
    $pdf = (string) file_get_contents($root . '/pdf.php');
    $admin = (string) file_get_contents($root . '/admin_branding.php');
    $worker = (string) file_get_contents($root . '/service-worker.js');
    brandCheck(str_contains($nav, 'safety_branding_logo_url()'), 'Navbar branding source');
    brandCheck(str_contains($login, 'safety_branding_logo_url()'), 'Login branding source');
    brandCheck(str_contains($admin, 'auth_check(true)'), 'Brand editor needs admin permission');
    brandCheck(str_contains($admin, "hash_equals"), 'Brand editor needs CSRF protection');
    brandCheck(str_contains($pdf, 'safety_branding_revision()'), 'PDF branding cache invalidation');
    brandCheck(str_contains($worker, "url.pathname.startsWith('/uploads/')"), 'PWA must not cache changing/private evidence');

    echo "PASS: persistent company branding, PDF output, access/CSRF checks and privacy-safe PWA.\n";
} finally {
    @unlink(safety_branding_settings_path());
    @rmdir($dir);
}
