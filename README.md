# Rentivo

A multi-agency car rental marketplace and management platform for Bahrain.

Independent rental agencies each run their own organization inside one shared
platform — their own branding, fleet, locations, customers, staff, permissions
and operational records — while visitors browse and book across every agency
from a single catalogue.

Built as plain PHP with no application framework: a small router, explicit
repositories and services, and server-rendered views composed from reusable
components.

---

## Contents

- [Stack](#stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Google OAuth setup](#google-oauth-setup)
- [SMTP setup](#smtp-setup)
- [Running the application](#running-the-application)
- [Creating your first organization](#creating-your-first-organization)
- [The scheduler](#the-scheduler)
- [Storage](#storage)
- [Tests](#tests)
- [Project structure](#project-structure)
- [How it works](#how-it-works)
- [Troubleshooting](#troubleshooting)
- [Production notes](#production-notes)

---

## Stack

| Layer          | Technology                                    |
| -------------- | --------------------------------------------- |
| Backend        | Plain PHP 8.3+ with Composer autoloading      |
| Database       | MySQL 8+ via PDO with prepared statements     |
| Frontend       | Server-rendered PHP, semantic HTML, CSS, vanilla JS (ES modules) |
| Authentication | Google OAuth 2.0 only — no passwords anywhere |
| Email          | PHPMailer over SMTP (optional)                |
| File storage   | Local filesystem                              |
| Tests          | PHPUnit                                       |

There is no framework, no SPA, no build step, and no JavaScript bundler. Open a
`.php` file and what runs is what you read.

---

## Prerequisites

- **PHP 8.3 or newer** with these extensions: `pdo_mysql`, `gd`, `fileinfo`,
  `mbstring`, `openssl`, `curl`, `json`
- **MySQL 8.0 or newer**
- **Composer**

Check your PHP build:

```bash
php -v
php -m | grep -E 'pdo_mysql|gd|fileinfo|mbstring|openssl|curl'
```

`gd` is required for image processing and `fileinfo` for upload validation —
the application refuses uploads it cannot verify, so neither is optional.

---

## Installation

### 1. Install dependencies

```bash
composer install
```

### 2. Create your environment file

```bash
cp .env.example .env
```

Then edit `.env` and set at minimum your database credentials:

```ini
APP_NAME=Rentivo
APP_ENV=local
APP_URL=http://localhost:8000
APP_DEBUG=true
APP_TIMEZONE=Asia/Bahrain

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rentivo
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

`.env` is git-ignored and must never be committed.

### 3. Create the database

The migration command creates the schema for you if it does not exist:

```bash
php scripts/migrate.php
```

If you prefer to create it yourself:

```sql
CREATE DATABASE rentivo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Useful migration flags:

```bash
php scripts/migrate.php            # run pending migrations
php scripts/migrate.php --status   # show applied and pending migrations
php scripts/migrate.php --fresh    # drop every table and rebuild (local only)
```

Migrations are ordered, recorded in a `migrations` table, and each runs exactly
once, so the command is always safe to re-run.

### 4. Seed the permission catalogue

```bash
php scripts/seed.php
```

This is **required**: it populates the 19 employee permission keys the
authorization system resolves against. It is idempotent, so run it after every
deployment.

Optionally, add development demo content:

```bash
php scripts/seed.php --demo
```

That creates two independent agencies, staff with contrasting permission sets,
customers, fleets and bookings in several states — useful for exercising tenant
isolation and the management screens by hand. It refuses to run when
`APP_ENV=production`.

---

## Google OAuth setup

Rentivo has no password authentication. Until Google credentials are configured,
the sign-in page explains what is missing and public browsing continues to work
normally.

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and create
   (or select) a project.
2. Configure the **OAuth consent screen**. For local development, "External" in
   testing mode is fine; add your own Google account as a test user.
3. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**
   and choose **Web application**.
4. Add the following:

   **Authorised JavaScript origin**

   ```
   http://localhost:8000
   ```

   **Authorised redirect URI**

   ```
   http://localhost:8000/auth/google/callback
   ```

   The redirect URI must match `GOOGLE_REDIRECT_URI` exactly — including the
   scheme, port and path.

5. Copy the client ID and secret into `.env`:

   ```ini
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=your-client-secret
   GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
   ```

In production, use your real domain over HTTPS and register that redirect URI
alongside (or instead of) the local one.

Only a **verified** Google email can sign in. The application requests just
`openid`, `email` and `profile`, and deliberately does not persist Google access
or refresh tokens — V1 makes no further calls on the user's behalf.

---

## SMTP setup

Email is entirely optional. With no SMTP configured, notifications still appear
in the in-app notification centre, and mail is logged and skipped rather than
failing a workflow.

```ini
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USERNAME=your-smtp-user
SMTP_PASSWORD=your-smtp-password
SMTP_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@yourdomain.com
MAIL_FROM_NAME=Rentivo
```

When SMTP is absent in a local environment, employee invitation links are shown
directly to the inviting admin so the flow remains testable.

---

## Running the application

```bash
php -S localhost:8000 -t public
```

Then open <http://localhost:8000>.

The document root must be `public/`. Everything else — including
`storage/private/`, `.env` and `vendor/` — then sits outside the web root and
cannot be requested over HTTP.

For Apache, `public/.htaccess` already routes requests through the front
controller. For nginx:

```nginx
server {
    listen 80;
    server_name rentivo.local;
    root /path/to/rentivo/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Uploaded files are static assets and must never execute.
    location ^~ /uploads/ {
        location ~ \.php$ { return 403; }
    }
}
```

### Development component gallery

With `APP_ENV=local`, every reusable UI component is rendered with sample data at:

```
http://localhost:8000/dev/components
```

The route returns 404 in any other environment.

---

## Creating your first organization

1. Start the server and open <http://localhost:8000>.
2. Click **Sign in** and continue with Google.
3. Open the account menu and choose **Create an organization**.
4. Fill in the name, contact email and phone. You become its **administrator**
   with full authority.
5. Inside the management console:
   - Add a **location** — customers need somewhere to collect the car.
   - Check your **categories** (six starters are created automatically).
   - Add a **car**, then upload photographs on the edit screen.

A car appears on the public marketplace once it is not archived, its status is
not `inactive`, and its organization is active. Photographs are not required for
listing, but a car without one shows a placeholder.

### Inviting staff

**Employees → Invite an employee.** Enter the person's Google address and tick
the permissions they need. They receive a link (or you are shown it directly when
SMTP is not configured); only that exact verified Google account can accept it,
and the link expires after seven days.

Administrators hold every permission implicitly. Employees hold only what you
grant, and every permission is enforced on the server — hiding a button is never
the only defence.

---

## The scheduler

```bash
php scripts/scheduler.php
```

Run it hourly from cron:

```cron
0 * * * * cd /path/to/rentivo && php scripts/scheduler.php --quiet >> storage/logs/scheduler.log 2>&1
```

It sends pickup reminders, return reminders and overdue-rental notifications,
expires lapsed document verifications, and prunes spent rate-limit windows.

The run is **idempotent**: every reminder carries a unique dedupe key, so running
it twice in the same hour — or catching up after downtime — never produces a
duplicate notification.

---

## Storage

```
public/uploads/          Publicly served marketing imagery
├── organizations/{id}/  Agency logos
├── cars/{org}/{car}/    Vehicle photographs
└── profiles/{user}/     Customer profile pictures

storage/private/         Never web-accessible
├── documents/{user}/    Driving licences and national IDs
└── inspections/{org}/   Pickup and return condition photographs

storage/logs/            Application and scheduler logs
```

Both directories need to be writable by the web server user:

```bash
chmod -R 775 storage public/uploads
```

Private files are read only through authorizing PHP routes
(`/documents/{id}/file` and `/manage/{org}/inspections/{id}/file`), which check
the viewer before streaming a single byte.

Uploads are validated in layers: PHP's upload status, a size limit, the MIME type
as reported by `finfo` (never the browser's claim), a real `getimagesize()`
decode, and finally re-encoding through GD. Re-encoding is what strips any
payload appended to an otherwise-valid image. Stored filenames are always
server-generated random hex; the client's filename never becomes a path.

---

## Tests

```bash
vendor/bin/phpunit                      # everything
vendor/bin/phpunit --testsuite Unit     # pure logic, no database
vendor/bin/phpunit --testsuite Feature  # integration, needs MySQL
```

The integration suite uses a **separate `rentivo_test` schema**, which it drops
and rebuilds from the migrations on every run. It never touches your development
data. Point it elsewhere with `DB_DATABASE` in `phpunit.xml` if you prefer.

If MySQL is unavailable the unit suite still runs and the integration tests skip
with a clear reason rather than failing.

The suite covers every critical acceptance scenario in SRS §100: public browsing
without an account, authentication only at booking with the selection preserved,
tenant isolation, employee permission enforcement, invitation email matching and
replay, concurrent booking conflicts, historical price snapshots, private
document authorization, the booking state machine, the pickup/return lifecycle,
integer-only money, and CSRF rejection.

---

## Project structure

```
app/
├── Auth/            Google OAuth and the session identity
├── Components/      The view renderer
├── Database/        PDO connection, transactions, migration runner
├── Http/            Request, Response, Router, Kernel, controllers
├── Repositories/    Every SQL statement in the application
├── Security/        CSRF, authorization, permissions, rate limiting
├── Services/        Business rules and orchestration
├── Support/         Currency, dates, pagination, slugs, flash, logging
└── Validation/      The rule-based validator

config/app.php       All configuration, read from the environment
database/
├── migrations/      Ordered, run-once schema changes
└── seeds/           Optional development demo content
public/              Document root: front controller, assets, uploads
routes/web.php       Every HTTP entry point
scripts/             migrate.php, seed.php, scheduler.php
storage/             Private files and logs (never web-accessible)
tests/               Unit and Feature suites
views/
├── components/      Reusable view components
├── layouts/         Public, account and management shells
├── public/          Marketplace pages
├── account/         Customer account pages
├── manage/          Management console pages
└── dev/             Component gallery (development only)
```

---

## How it works

### Request flow

```
public/index.php → Kernel → Router → Controller → Service → Repository → MySQL
                                          ↓
                                   View + components
```

The kernel owns what must never be re-implemented per page: session startup,
CSRF validation for every state-changing request, shared view state, and error
rendering that keeps stack traces out of production responses.

### Tenant isolation

Organization-owned data is always queried with the organization as part of the
predicate, never checked afterwards:

```php
// Every management car lookup
$cars->findInOrganization($carId, $context->organizationId());
//   ... WHERE `id` = ? AND `organization_id` = ?
```

`Authorization::organizationContext()` resolves the current user's membership
before a controller runs, and a non-member receives 404 rather than 403 so
another agency's existence is not confirmed to a probing user.

### Money

Every monetary value is an integer number of fils (1 BHD = 1000 fils), stored in
unsigned integer columns and formatted only at the edge:

```php
Currency::format(25500);  // "BHD 25.500"
Currency::toFils('25.5'); // 25500
```

No float or decimal column exists anywhere in the schema, and a test asserts it
stays that way.

### Booking availability

A car is bookable for a window when both conditions hold:

1. It is operationally rentable — not archived, not in maintenance, not inactive.
2. No booking in a blocking status (`confirmed`, `ready_for_pickup`, `active`)
   overlaps the window. Pending bookings never block.

Overlap is `existing.pickup_at < requested.return_at AND existing.return_at >
requested.pickup_at`, so windows that merely touch do not conflict. A car that is
currently `rented` remains bookable for a later free period.

Confirmation is the authoritative check: it opens a transaction, locks the car
row, re-queries conflicting bookings `FOR UPDATE`, and only then writes — so two
staff confirming overlapping requests at the same moment cannot both succeed.

---

## Troubleshooting

**`Unable to connect to the database "rentivo"`**
MySQL is not running, or the credentials in `.env` are wrong. Verify with
`mysql -u your_user -p -e 'SELECT 1'`.

**`The database has not been migrated yet`**
Run `php scripts/migrate.php` before `php scripts/seed.php`.

**Permission checks behave unexpectedly**
Run `php scripts/seed.php`. Without the permission catalogue, employee
permissions cannot resolve.

**Google sign-in returns "The sign-in request could not be verified"**
The OAuth state did not match, usually because cookies were blocked or the
session was lost. Confirm `APP_URL` matches the address you are browsing, and
that `GOOGLE_REDIRECT_URI` is registered in Google Cloud exactly as written.

**Uploads are rejected**
Confirm `gd` and `fileinfo` are loaded. Files that are not genuinely decodable
images are refused by design — including PHP renamed to `.jpg`.

**Images upload but do not appear**
Check that `public/uploads` is writable, and that your web server serves
`/uploads/` as static files.

**A car is not on the marketplace**
It must be non-archived, not `inactive`, and belong to an active organization.
Check the car's status on its edit screen and the organization's visibility
toggle in settings.

**Everything 404s except the homepage**
The document root is wrong, or URL rewriting is not active. The root must be
`public/`, and non-file requests must reach `index.php`.

---

## Production notes

Set these before going live:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

With `APP_DEBUG=false`, technical detail never reaches the browser: errors show a
clean message and the stack trace goes to `storage/logs/`.

Checklist:

- **Serve over HTTPS.** Session cookies are automatically marked `Secure` under
  HTTPS or in production; they are always `HttpOnly` and `SameSite=Lax`.
- **Point the document root at `public/`** so `storage/`, `.env` and `vendor/`
  are unreachable.
- **Prevent execution in `public/uploads/`** — `.htaccess` does this for Apache;
  the nginx snippet above does the equivalent.
- **Register your production redirect URI** in the Google Cloud console.
- **Run migrations and the permission seed** on every deploy:
  `php scripts/migrate.php && php scripts/seed.php`
- **Install the hourly scheduler cron entry.**
- **Back up all three** of MySQL, `public/uploads/` and `storage/private/`. A
  database dump alone loses every document and photograph.
- **Rotate `storage/logs/`** — the application appends one file per day.

### Not included in V1

Deliberately out of scope, and structured so they can be added later: online
payments, organization subdomains, a public API, Arabic localisation, map-based
browsing, customer reviews, and coupon or loyalty schemes. Payment status is
tracked (`unpaid`, `paid`, `refunded`) with payment taken at pickup.
