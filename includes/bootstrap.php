<?php
// Try vendor first (if you copy it over)
$vendor = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendor)) { require_once $vendor; }

// PHPMailer (self-hosted) optional
$phpMailerBase = __DIR__ . '/../lib/phpmailer/src';
if (file_exists($phpMailerBase . '/PHPMailer.php')) {
  require_once $phpMailerBase . '/Exception.php';
  require_once $phpMailerBase . '/PHPMailer.php';
  require_once $phpMailerBase . '/SMTP.php';
}
define('SAFETY_HAVE_MAILER', class_exists('PHPMailer\PHPMailer\PHPMailer'));

// FPDF (self-hosted)
$fpdfPath = __DIR__ . '/../lib/fpdf/fpdf.php';
if (file_exists($fpdfPath)) {
  require_once $fpdfPath;
}
define('SAFETY_HAVE_FPDF', class_exists('FPDF'));
