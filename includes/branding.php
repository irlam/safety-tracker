<?php
declare(strict_types=1);

/**
 * Site-wide branding. No database migration or public credentials.
 * Runtime files are stored in uploads/branding, ignored by Git.
 * PDFs use the same JPEG logo as the responsive website.
 */
function safety_branding_directory(): string {
    return dirname(__DIR__) . '/uploads/branding';
}
function safety_branding_settings_path(): string {
    return safety_branding_directory() . '/settings.json';
}
function safety_branding_logo_path(): string {
    return safety_branding_directory() . '/company-logo.jpg';
}
function safety_branding(): array {
    $defaults = [
        'company_name' => 'Site Safety',
        'subtitle' => 'Safety Tours',
        'report_title' => 'Safety Tour Report',
    ];
    $path = safety_branding_settings_path();
    if (!is_file($path)) return $defaults;
    $data = json_decode((string) @file_get_contents($path), true);
    if (!is_array($data)) return $defaults;
    foreach (array_keys($defaults) as $field) {
        $value = trim((string) ($data[$field] ?? ''));
        if ($value !== '') $defaults[$field] = mb_substr($value, 0, 100);
    }
    return $defaults;
}
function safety_branding_logo_url(): string {
    $path = safety_branding_logo_path();
    if (is_file($path)) {
        return '/uploads/branding/company-logo.jpg?v=' . (int) filemtime($path);
    }
    return '/assets/img/safety-brand.svg';
}
function safety_branding_pdf_logo(): ?string {
    $path = safety_branding_logo_path();
    return is_file($path) ? $path : null;
}
function safety_branding_revision(): int {
    $times = [0];
    foreach ([safety_branding_settings_path(), safety_branding_logo_path()] as $p) {
        if (is_file($p)) $times[] = (int) filemtime($p);
    }
    return max($times);
}
function safety_branding_save(array $settings): void {
    $dir = safety_branding_directory();
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Branding directory is not writable.');
    }
    $old = safety_branding();
    $updated = [];
    foreach (['company_name', 'subtitle', 'report_title'] as $name) {
        $text = trim((string) ($settings[$name] ?? $old[$name]));
        if ($text === '' || mb_strlen($text) > 85
            || preg_match('/[\x00-\x1f\x7f<>]/', $text)) {
            throw new InvalidArgumentException('Please enter valid branding text (up to 85 characters).');
        }
        $updated[$name] = $text;
    }
    $file = safety_branding_settings_path();
    $tmp = tempnam($dir, '.settings-');
    if ($tmp === false) throw new RuntimeException('Cannot write branding settings.');
    try {
        if (file_put_contents($tmp, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException('Unable to write branding settings.');
        }
        if (!rename($tmp, $file)) throw new RuntimeException('Cannot activate branding settings.');
        @chmod($file, 0640);
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
}
function safety_branding_upload_logo(array $file): void {
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK || empty($file['tmp_name'])
        || !is_uploaded_file((string) $file['tmp_name'])) {
        throw new InvalidArgumentException('Select a valid logo file to upload.');
    }
    $max = 2 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > $max) {
        throw new InvalidArgumentException('Logo must be no larger than 2 MB.');
    }
    if (!extension_loaded('gd')) {
        throw new RuntimeException('PHP GD is needed to process uploaded logos.');
    }
    $meta = @getimagesize((string) $file['tmp_name']);
    if (!$meta || $meta[0] < 32 || $meta[1] < 32
        || $meta[0] > 4000 || $meta[1] > 4000) {
        throw new InvalidArgumentException('Logo must be a valid image, 32–4000 pixels wide/high.');
    }
    $type = (int) $meta[2];
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new InvalidArgumentException('Use JPG, PNG or WebP.');
    }
    $source = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg((string) $file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng((string) $file['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
            ? @imagecreatefromwebp((string) $file['tmp_name']) : false,
    };
    if (!$source) throw new InvalidArgumentException('Cannot read the image.');
    try {
        $size = min(1200, max($meta[0], $meta[1]));
        $factor = min(1, $size / max($meta[0], $meta[1]));
        $w = max(1, (int) round($meta[0] * $factor));
        $h = max(1, (int) round($meta[1] * $factor));
        $output = imagecreatetruecolor($w, $h);
        if (!$output) throw new RuntimeException('Cannot create logo.');
        try {
            // Flatten transparency onto white for compatibility with FPDF.
            $white = imagecolorallocate($output, 255, 255, 255);
            imagefill($output, 0, 0, $white);
            if (!imagecopyresampled($output, $source, 0, 0, 0, 0, $w, $h, $meta[0], $meta[1])) {
                throw new RuntimeException('Image conversion failed.');
            }
            $dir = safety_branding_directory();
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new RuntimeException('Branding directory is not writable.');
            }
            $tmp = tempnam($dir, '.logo-');
            if ($tmp === false) throw new RuntimeException('Cannot create logo file.');
            try {
                if (!imagejpeg($output, $tmp, 90) || !rename($tmp, safety_branding_logo_path())) {
                    throw new RuntimeException('Cannot save logo.');
                }
                @chmod(safety_branding_logo_path(), 0644);
            } finally {
                if (is_file($tmp)) @unlink($tmp);
            }
        } finally {
            imagedestroy($output);
        }
    } finally {
        imagedestroy($source);
    }
}
function safety_branding_remove_logo(): void {
    if (is_file(safety_branding_logo_path())) {
        if (!unlink(safety_branding_logo_path())) {
            throw new RuntimeException('Unable to remove current logo.');
        }
    }
}
