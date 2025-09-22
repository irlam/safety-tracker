<?php
declare(strict_types=1);

// Base
const BASE_URL = 'https://safety.defecttracker.uk';
const TIMEZONE = 'Europe/London';

// DB
const DB_HOST = '10.35.233.124';
const DB_PORT = 3306;
const DB_NAME = 'k87747_safety';
const DB_USER = 'k87747_safety';
const DB_PASS = 'Subaru5554346';

// SMTP (Netcup)
const SMTP_HOST = 'mxe97d.netcup.net';
const SMTP_PORT = 465; // SSL/TLS
const SMTP_USER = 'safety@defecttracker.uk';
const SMTP_PASS = 'Subaru5554346';
const MAIL_FROM = 'safety@defecttracker.uk';
const MAIL_FROM_NAME = 'DefectTracker — Safety Tours';

// Auth
const AUTH_MODE = 'password'; // 'password' or 'open'
const AUTH_PASSWORD = 'site-safety'; // change this

// Files
const UPLOAD_DIR = __DIR__ . '/../uploads';
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);

// Bootstrap (no Composer)
require_once __DIR__ . '/bootstrap.php';
