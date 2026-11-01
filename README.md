# GPB-Resolve

GPB-Resolve is a PHP-based complaint and resolution management app for colleges. It supports student registration, complaint submission, progress tracking, and admin dashboards.

## Default local setup

1. Copy the project into `C:/xampp/htdocs/GPB-Resolve`.
2. Start XAMPP Apache and MySQL.
3. Open `http://localhost/GPB-Resolve/`.
4. The application will automatically create the `gpb_resolve` database in MySQL if it is missing.
5. Create a student account from the registration page.
6. Create the first admin account directly in the database by inserting a row into `users` with `role = 'admin'` and a password hashed via `password_hash()`.

## Connection options

The application defaults to MySQL/MariaDB for XAMPP local development and automatically falls back to SQLite if MySQL is unavailable.

```bash
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_NAME=gpb_resolve
DB_USER=root
DB_PASS=
```

To force SQLite locally instead:

```bash
DB_DRIVER=sqlite
```

## Gmail OTP email

Copy `.env.example` to `C:/xampp/private/gpb-resolve.env` and fill in the Gmail
SMTP settings. This file is kept outside `htdocs` so it cannot be served by
Apache or PHP's development server. Use a Google App Password (not your normal
Gmail password), enable 2-Step Verification on the sending account, and set
`SMTP_FROM_EMAIL` to that account or a verified Gmail alias.

## File structure

- `frontend/` — static HTML pages, shared CSS, and JavaScript client
- `frontend/privacy-policy.html`, `frontend/terms.html` — public policy pages; both contain owner-review notes that must be resolved before production publication
- `frontend/404.html` — static not-found page rendered with an HTTP 404 status for unknown legacy routes
- `api/` — PHP JSON endpoints for authentication, complaints, and admin actions
- `index.php`, `login.php`, `register.php`, `student/`, `admin/` — legacy URLs that redirect to their static frontend pages
- `config/` — app and database config
- `database/` — schema and seed SQL files
- `storage/uploads/` — uploaded complaint image files; the database stores each image's filename, MIME type, size, and complaint link

All page markup is static HTML under `frontend/`. The JavaScript client calls the
PHP JSON API with the same-origin session cookie; database access and
authorization remain on the server.
Complaint images are stored as files rather than database BLOBs to keep the
database small. They are served through an authenticated endpoint, so only the
complaint owner or an admin can view them.

## Security reminder

- Use HTTPS in production.
- Never expose stack traces or credentials.
- Keep uploads outside public executable directories.
- Validate file types and sizes on the server.
- Session cookies are marked `Secure` when PHP detects HTTPS. Configure TLS and the web server or trusted TLS-terminating proxy before production; HTTPS redirects and HSTS are intentionally not enabled by this local-development setup.
- Login and registration endpoints have IP- and account-based request limits. Their counters are stored under `C:/xampp/private/gpb-resolve-rate-limits`, outside `htdocs`.
- Apache protections, compression, and asset caching in `.htaccess` require Apache with the corresponding modules enabled. The PHP development server does not read `.htaccess`.
- Update `RewriteBase` in `.htaccess` if deploying under a different URL path. `robots.txt` is written for the app when hosted at the web root.
- No production domain is configured, so canonical URLs, absolute social-preview URLs, sitemap entries, and HSTS are intentionally not published. No analytics or optional tracking cookies are configured; the app uses an essential session cookie.
- Before publication, the site owner must confirm the responsible institution, privacy contact, retention/deletion rules, email provider, sharing practices, and complaint-handling expectations shown in the policy pages.
