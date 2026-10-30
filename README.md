# Online Donation

Laravel campaign management application for charity drives and manual donation tracking. It is a software demo, not a regulated fundraising platform. No money is collected, transferred, or settled here; no payment provider or webhook integration is included.

## Features

- User-submitted campaigns with descriptions, goals, deadlines, and validated image uploads; admin review before public listing.
- Public campaign pages with confirmed-only totals, goal progress, and privacy-aware donor lists.
- Authenticated donation records, idempotent form submission, personal donation history, and clearly labeled pending receipts.
- Admin-only campaign moderation, donation confirmation/rejection, audit events, campaign reports, and confirmed-record CSV export.
- Campaign closure/completion transitions; exact integer-minor-unit amounts; pagination; role-protected controls.
- No seed campaigns, donor identities, or fabricated financial records.

## Local setup (Windows)

```powershell
Copy-Item .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

The example configuration uses SQLite at `database/database.sqlite`; set `DB_CONNECTION` and credentials for another database through local `.env` as required. `storage:link` serves uploaded campaign images from the public disk. Configure web-server upload limits as well as the application’s 4 MB image limit before deployment.

Register a normal user through the app. A trusted operator must grant an administrator role directly in the database, for example using Tinker after replacing the email with the trusted account:

```powershell
php artisan tinker --execute="\App\Models\User::where('email', 'operator@example.test')->update(['is_admin' => true]);"
```

Never grant admin by accepting a public registration field. `DatabaseSeeder` intentionally adds no demo users, donations, or campaigns.

## Donation and moderation rules

- Amounts accept decimal IDR text, validate on the server, and are stored as integer hundredths (`100` minor units = Rp 1.00); no floating-point arithmetic.
- A submitted donation is `pending`. Admin review changes it once to `confirmed` or `failed`; subsequent transitions are rejected. Admin decisions are recorded in `donation_events`.
- Campaign progress, reports, and CSV include **confirmed** donations only. Pending/failed records are not represented as collected funds.
- A receipt is a record of an offline/manual submission, not proof of payment. Donors must verify payment directly with the organizer.
- Donors control public-name and amount display. Account email/name, internal references, and pending records never appear in public donor lists. Private details are excluded from CSV exports.
- Campaigns begin as `pending_review`; only admins approve/reject. Owners may edit pending/rejected campaigns and edits are resubmitted for review. Admins may close or complete active campaigns; closed/completed campaigns accept no new donations.
- Donation form keys prevent accidental duplicate submissions on retry; mismatched reuse returns a conflict. Database uniqueness provides a second guard.
- Uploaded files are image-validated, limited to JPEG/PNG/WebP and 4 MB, and stored using generated paths on Laravel’s public disk.

## Deployment checklist

1. Provision PHP 8.3+, supported database, HTTPS web server, and persistent private application storage; point the document root only at `public/`.
2. Configure production environment variables outside Git; set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to HTTPS, strong `APP_KEY`, database credentials, session/cookie security, logs, and backup/retention controls.
3. Build assets and dependencies in the release environment (`composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`); deploy code and migrations, run `php artisan migrate --force`, create `storage:link`, ensure writable `storage/` and `bootstrap/cache/`, then run `php artisan optimize`.
4. Create the first administrator through an audited, trusted operator path; keep any admin-creation tooling out of public registration. Verify uploads, permissions, backups, and logs before public traffic.
5. Do not operate this demo as a real fundraising/payment service without legal and operational authorization. It provides no payment provider, webhook, reconciliation, refunds, or settlement.

## Validation

```powershell
php artisan migrate:fresh --seed --no-interaction
php artisan route:list --except-vendor
php artisan view:clear
php artisan view:cache
php artisan test --compact
npm run build
composer validate --no-check-publish
composer audit
npm audit --omit=dev
```

Before real-world fundraising use, obtain legal/compliance review and define organizer verification, payment settlement, refunds/disputes, data-retention, and donor-support operations. A real payment provider requires explicit authorization and secure credentials, verified webhook signatures, idempotent webhook handling, and reconciliation; none is implemented here.

Keep `.env`, local databases, dependencies, uploads containing personal data, and build artifacts out of Git.
