# Security Policy

## Supported Versions

This is a self-hosted safety tracking application. Security updates are provided as they become available.

## Reporting a Vulnerability

If you discover a security vulnerability, please report it by creating a GitHub issue or contacting the repository owner directly.

## Security Best Practices

### Configuration
- **Never commit `includes/config.php` with real credentials to version control**
- Always use `includes/config.example.php` as a template and create your own `config.php` locally
- Update all default passwords before deployment:
  - `AUTH_PASSWORD` in `config.php`
  - Database credentials
  - SMTP credentials

### File Uploads
- The `uploads/` directory should have appropriate permissions (0775)
- Consider implementing file type validation and size limits
- Ensure uploaded files are not executable
- Consider storing uploads outside the web root for additional security

### Database
- Use strong database passwords
- Restrict database user permissions to only what's necessary
- Enable MySQL SSL/TLS if possible
- Keep regular backups

### Web Server
- Use HTTPS in production (configure SSL/TLS certificates)
- Update `BASE_URL` in `config.php` to use `https://`
- Configure appropriate `.htaccess` rules to prevent directory listing
- Keep PHP and web server software up to date

### Dependencies
- FPDF library is included in `lib/fpdf/`
- PHPMailer can be added to `lib/phpmailer/src/`
- Keep all dependencies up to date

### Authentication
- The application uses a simple password-based authentication
- Consider implementing user management with proper password hashing for production use
- The `admin_users.php` provides user management functionality

## Known Security Considerations

1. **Configuration File**: The `includes/config.php` file contains sensitive credentials. Ensure this file is not accessible via web browser.
2. **Upload Directory**: Files uploaded to the `uploads/` directory should be validated and sanitized.
3. **Session Security**: Ensure PHP session configuration is secure in production.

## Deployment Checklist

- [ ] Copy `includes/config.example.php` to `includes/config.php`
- [ ] Update all credentials and passwords in `config.php`
- [ ] Ensure `includes/config.php` is in `.gitignore`
- [ ] Configure HTTPS on your web server
- [ ] Set appropriate file permissions (0644 for files, 0755 for directories)
- [ ] Ensure `uploads/` directory is not directly browsable
- [ ] Review and configure `.htaccess` for your environment
- [ ] Test file upload functionality and size limits
- [ ] Enable error logging in production (not error display)
- [ ] Configure regular database backups
