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
- System logs (audit logs, SMS logs, QR scan logs)
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
   - [`database/schema.sql`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/database/schema.sql)
5. Open:
   - `http://localhost/san-enrique-lgu-scholarship/`

## Default Admin Account
- Mobile: `09123456789`
- Password: `admin1234`

## Textbee SMS Setup
Edit:
- [`config/textbee.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/config/textbee.php)

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
- Applicant wizard: [`apply.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/apply.php)
- Account security (change mobile with OTP): [`account-security.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/account-security.php)
- Forgot/reset password via OTP: [`forgot-password.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/forgot-password.php)
- Applicant records: [`my-application.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/my-application.php)
- Printable form: [`print-application.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/print-application.php)
- Full QR page: [`my-qr.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/my-qr.php)
- Shared applications: [`shared/applications.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/applications.php)
- Shared masterlist: [`shared/masterlist.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/masterlist.php)
- Shared export endpoint (PDF/DOCX/XLSX): [`shared/export-masterlist.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/export-masterlist.php)
- Reports export endpoint (PDF/DOCX/XLSX): [`admin-only/export-reports.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/admin-only/export-reports.php)
- Shared analytics dashboard: [`shared/analytics.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/analytics.php)
- Shared global search: [`shared/global-search.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/global-search.php)
- Admin logs page: [`admin-only/logs.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/admin-only/logs.php)
- QR scanner and verification page: [`shared/verify-qr.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/verify-qr.php)
- Requirement templates: [`admin-only/requirements.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/admin-only/requirements.php)
- Application period management: [`admin-only/application-periods.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/admin-only/application-periods.php)

## Role-Based Admin Files
- Shared (Admin + Staff): applications, masterlist, interviews, disbursements, QR scanner, scholars, masterlist export.
- Admin-only: announcements management, requirements templates, application periods, reports and report exports.
- Admin-only logs and audit trail are under `/admin-only/logs.php`.
- Folder organization:
  - Shared files are under `/shared/`
  - Admin-only files are under `/admin-only/`
  - Staff-only files can be placed under `/staff-only/`

## Upload Folders
Make sure these are writable:
- `uploads/tmp/`
- `uploads/photos/`
- `uploads/documents/`

## Notes
- QR rendering is server-side via Composer (`endroid/qr-code`).
- Applicant login now uses mobile number + password.
- Registration and applying are only allowed during an open application period.
- Print page is optimized for legal-size form output.
- Use browser Print to save as PDF.
