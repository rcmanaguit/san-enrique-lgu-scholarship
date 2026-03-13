# San Enrique LGU Scholarship System

Plain PHP + MySQL + Bootstrap (no framework, no MVC) capstone project starter.

## New Core Features

- Multi-step application wizard (page-by-page)
- Dynamic requirements based on scholarship/applicant/school type
- Separate 2x2 photo page with upload/camera capture + crop
- Review/preview page before final submission
- OTP verification for registration, forgot password reset, and mobile number change
- Printable legal-size application form (`8.5 x 13`) with QR at upper-left
- Full QR page for applicant
- Multi-purpose QR scanner (verification/interview/documents/disbursement/lookup) with activity logs
- Textbee SMS integration hooks (auto notifications)
- Admin masterlist with PDF/DOCX/XLSX export
- Data analytics dashboard (application, disbursement, SMS, QR trends)
- Reporting hub with range filters and exportable datasets
- System logs (audit logs, SMS logs)
- Global live search for admin/staff across major records
- Live search, live filter, and client-side pagination
- Peach + Blue/LightBlue theme, modern fonts, and Font Awesome icons

## Stack

- PHP 8+ (XAMPP)
- MySQL / MariaDB
- Bootstrap 5
- Composer
- Font Awesome
- Cropper.js (photo cropping)
- dompdf (PDF export)
- phpoffice/phpword (DOCX export)
- phpoffice/phpspreadsheet (XLSX export)
- endroid/qr-code (server-side QR generation)

## Quick Setup (Fresh Install)

1. Place project in `c:\xampp\htdocs\san-enrique-lgu-scholarship`
2. Start Apache + MySQL in XAMPP
3. Run Composer install in project root:
   - `composer install`
4. Import:
   - [`app/database/schema.sql`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/database/schema.sql)
5. Point Apache `DocumentRoot` to:
   - `c:\xampp\htdocs\san-enrique-lgu-scholarship\public`
6. Open:
   - `http://san-enrique-lgu-scholarship.local/`

## Public Web Root

1. The project now includes a dedicated public folder:
   - [`public/`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/public)
2. To use it as your Apache web root, point your virtual host `DocumentRoot` to:
   - `c:\xampp\htdocs\san-enrique-lgu-scholarship\public`
3. `public/` is the only supported web root. The repository root no longer contains public page wrappers.
4. Sync the static assets into `public/assets` after frontend changes:
   - `php scripts/sync-public-assets.php`
5. Uploaded files are still stored in the project-level `uploads/` directory and served through:
   - [`public/files.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/public/files.php)

## PHPUnit Tests

1. Install/update dependencies:
   - `composer install`
2. Run tests:
   - `composer test`
3. Current coverage includes:
   - school/course/barangay normalization and validation
   - QR identifier parsing helper
   - application status list and approved-phase subset
   - status badge class mapping
   - period label formatting and age calculation helpers

## Flood Seed (Testing Data)

1. Import:
   - [`app/database/schema.sql`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/database/schema.sql)
2. Generate matching placeholder files for seeded `photo_path` and `application_documents.file_path`:
   - `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\seed\create-flood-assets.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship"`
   - Default flood volume is `120` applicants (override with `-SeedCount 150` if needed).
3. Import:
   - [`app/database/seed.sql`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/database/seed.sql)

## Default Admin Account

- Mobile: `09123456789`
- Password: `admin1234`

## Textbee SMS Setup

Edit:

- [`app/config/textbee.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/config/textbee.php)

Set:

- `'enabled' => true`
- `'api_key' => 'YOUR_TEXTBEE_API_KEY'`
- `'device_id' => 'YOUR_DEVICE_ID'`

SMS is used for:

- Application submission confirmation
- Status updates
- Interview schedule notices
- Disbursement schedule notices
- Optional announcement broadcast

## Main Pages

- Applicant wizard implementation: [`app/pages/applicant/apply.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/applicant/apply.php)
- Account security implementation: [`app/pages/applicant/account-security.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/applicant/account-security.php)
- Forgot/reset password implementation: [`app/pages/auth/forgot-password.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/auth/forgot-password.php)
- Applicant records implementation: [`app/pages/applicant/my-application.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/applicant/my-application.php)
- Printable form implementation: [`app/pages/applicant/print-application.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/applicant/print-application.php)
- Full QR page implementation: [`app/pages/applicant/my-qr.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/applicant/my-qr.php)
- Shared applications implementation: [`app/pages/shared/applications.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/shared/applications.php)
- Shared masterlist implementation: [`app/pages/shared/masterlist.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/shared/masterlist.php)
- Shared export endpoint implementation: [`app/pages/shared/export-masterlist.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/shared/export-masterlist.php)
- Reports export endpoint implementation: [`app/pages/admin/export-reports.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/admin/export-reports.php)
- Shared analytics dashboard implementation: [`app/pages/shared/analytics.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/shared/analytics.php)
- Shared global search implementation: [`app/pages/shared/global-search.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/shared/global-search.php)
- Admin logs implementation: [`app/pages/admin/logs.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/admin/logs.php)
- QR scanner and verification implementation: [`app/pages/shared/verify-qr.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/shared/verify-qr.php)
- Requirement templates implementation: [`app/pages/admin/requirements.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/admin/requirements.php)
- Application period management implementation: [`app/pages/admin/application-periods.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/app/pages/admin/application-periods.php)

## Role-Based Admin Files

- Shared (Admin + Staff): applications, masterlist, interviews, disbursements, QR scanner, scholars, masterlist export.
- Admin-only: announcements management, requirements templates, application periods, reports and report exports.
- Admin-only logs and audit trail are served at `/admin-only/logs.php`.
- Folder organization:
  - Page implementations are under `app/pages/shared/`
  - Auth page implementations are under `app/pages/auth/`
  - Applicant page implementations are under `app/pages/applicant/`
  - Admin-only page implementations are under `app/pages/admin/`
  - The public web root is `public/`

## Upload Folders

Make sure these are writable:

- `uploads/tmp/`
- `uploads/photos/`
- `uploads/documents/`

## Backup Automation (Windows + Linux)

- Scripts are in `scripts/backup/`
- Includes:
  - `backup-nightly.ps1` (DB + uploads backup)
  - `restore-latest.ps1` (restore drill / recovery)
  - `register-task.ps1` (Task Scheduler automation)
  - `backup-nightly.sh` (Linux DB + uploads backup)
  - `restore-latest.sh` (Linux restore drill / recovery)
  - `register-cron.sh` (Linux cron automation)

Run backup now:

- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\backup-nightly.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship" -KeepDays 30`

Register daily 1:00 AM backup:

- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\register-task.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship" -TaskName "SanEnriqueScholarshipNightlyBackup" -StartTime "01:00" -KeepDays 30`

Run restore drill (DB only):

- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\restore-latest.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship"`

Run restore drill (DB + uploads):

- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup\restore-latest.ps1 -ProjectRoot "C:\wamp64\www\san-enrique-lgu-scholarship" -RestoreUploads`

See `scripts/backup/README.md` for full details.

## Notes

- QR rendering is server-side via Composer (`endroid/qr-code`).
- Applicant login now uses mobile number + password.
- Registration and applying are only allowed during an open application period.
- Print page is optimized for legal-size form output.
- Use browser Print to save as PDF.
