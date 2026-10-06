<?php
declare(strict_types=1);

// Base
const BASE_URL = 'https://your-domain.com';
const TIMEZONE = 'Europe/London';

// DB
const DB_HOST = 'your-database-host';
const DB_PORT = 3306;
const DB_NAME = 'your-database-name';
const DB_USER = 'your-database-user';
const DB_PASS = 'your-database-password';

// SMTP
const SMTP_HOST = 'your-smtp-host';
const SMTP_PORT = 465; // SSL/TLS
const SMTP_USER = 'your-smtp-user';
const SMTP_PASS = 'your-smtp-password';
const MAIL_FROM = 'noreply@your-domain.com';
const MAIL_FROM_NAME = 'Safety Tours';

// Auth
const AUTH_MODE = 'password'; // 'password' or 'open'
const AUTH_PASSWORD = 'change-this-password'; // IMPORTANT: Use a strong, unique password (minimum 12+ characters)

// Construction Suite read-only integration. Use the same long random secret configured in the Suite Hub.
const CONSTRUCTION_SUITE_API_KEY = 'replace-with-a-long-random-secret';

// Files
const UPLOAD_DIR = SAFETY_APP_ROOT . '/uploads';
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);

// Bootstrap (no Composer)
require_once SAFETY_APP_ROOT . '/includes/bootstrap.php';
