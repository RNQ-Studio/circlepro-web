# Security Policy

This document outlines the security policy, vulnerability reporting guidelines, and security considerations for the **Laravel Starter** project. We take security seriously and want to ensure a secure environment for all developers and users.

---

## Supported Versions

Only the latest major version of Laravel Starter is actively supported with security updates. 

| Version | Supported PHP | Supported Laravel | Status |
|---------|---------------|-------------------|--------|
| v1.x    | >= 8.2        | ^11.0             | 🛡️ Active Security Support |
| < v1.0  | -             | -                 | ❌ End of Life (EOL) |

---

## Reporting a Vulnerability

If you discover a security vulnerability within this project, please report it immediately and privately. **Do NOT open a public GitHub issue** for security-related bugs.

### Submission Process
1. Email your findings to **security@example.com** (replace with your organization's actual security email during staging/deployment).
2. In your report, please include:
   - A detailed description of the vulnerability.
   - Step-by-step instructions (proof-of-concept) to reproduce the issue.
   - Any potential impact or exploits.
   - System environment details (PHP version, OS, etc.).

### Our Commitment
- **Acknowledgement**: We will acknowledge receipt of your report within **48 hours**.
- **Assessment**: Our team will assess the issue and keep you updated on our progress toward a fix.
- **Remediation**: We strive to release security patches within **7 days** of verifying critical/high-severity issues.
- **Credit**: If requested, we will gladly credit you for your responsible disclosure in the release notes.

---

## Security Best Practices & Design Decisions

This starter project incorporates several built-in security mechanisms to protect application integrity and data privacy. Developers should adhere to these practices during extension:

### 1. Production HTTPS Enforcement
In `app/Providers/AppServiceProvider.php`, the framework is configured to automatically force all routes, redirects, and generated asset URLs to use the `https://` scheme when running in a production environment (`APP_ENV=production`).
```php
if ($this->app->environment('production')) {
    URL::forceScheme('https');
}
```
*Make sure your SSL certificates are correctly installed and terminated on your load balancer or web server (e.g., Nginx, Caddy).*

### 2. Authentication & Session Security (Laravel Passport)
The application utilizes **Laravel Passport** for API authentication, issuing OAuth2 JSON Web Tokens (JWTs).
- **Encryption Keys**: The encryption keys (`oauth-private.key` and `oauth-public.key`) are generated in the `storage/` directory. **Never commit these keys to version control.** They are ignored by default via `.gitignore`.
- **Client Secrets**: Passport Client IDs and Secrets (defined in `.env`) must be kept confidential and never exposed to the client-side code directly.
- **Token Invalidation**: Features like "Logout All Devices" (`POST /api/v1/auth/logout-all`) revoke all active Access Tokens and Refresh Tokens associated with a user in the database.

### 3. API Rate Limiting (Throttling)
To prevent brute-force attacks and Denial of Service (DoS) conditions, sensitive endpoints are protected by rate-limiting middleware:
- **Authentication Endpoints** (e.g., Register, Login, OTP Send): Protected by a tight throttle (`throttle:6,1`, allowing 6 requests per minute).
- **General API Routes**: Configured with standard API rate limiters defined in `bootstrap/app.php`.

### 4. Input Validation & Mass Assignment Protection
- **Form Requests**: All incoming request payloads must be validated using custom Form Request classes (e.g., `LoginRequest`, `RegisterRequest`) with strict rules.
- **Eloquent Attribute Casting**: Sensitive attributes (e.g., `password` inside the `User` model) are automatically cast to `hashed` and hidden from JSON serialization via the `#[Hidden]` attribute or `$hidden` array.
- **Fillable Attributes**: Models explicitly define fillable attributes using the `#[Fillable]` attribute or `$fillable` array to prevent Mass Assignment vulnerabilities.

### 5. Role-Based Access Control (RBAC)
Back-office panel access is restricted using **Spatie Laravel-Permission** and Filament policy hooks.
- Back-office resources (e.g., Users, Roles, Configurations) have explicit RBAC checks.
- Default roles (`super-admin`, `admin`, `staff`) have granular permissions that restrict unauthorised modifications.
