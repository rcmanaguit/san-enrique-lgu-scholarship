# San Enrique LGU Scholarship System Requirements

## Scope
This document defines the complete functional and non-functional requirements for the San Enrique LGU Scholarship Records Management System.

## Functional Requirements

### FR-01 User Registration and Authentication
1. The system shall allow applicants to create an account using required profile fields.
2. The system shall validate required registration fields before account creation.
3. The system shall require OTP verification before activating a newly registered applicant account.
4. The system shall allow users to log in using registered mobile number and password.
5. The system shall deny login for invalid credentials.
6. The system shall deny login for inactive accounts.
7. The system shall allow authenticated users to log out securely.
8. The system shall support forgot-password reset using OTP verification.
9. The system shall allow applicants to change mobile number using OTP verification.

### FR-02 Role-Based Access Control
1. The system shall enforce role-based access for Admin, Staff, and Applicant users.
2. The system shall allow Admin access to admin-only modules.
3. The system shall allow Staff access only to shared operational modules.
4. The system shall allow Applicant access only to applicant-facing modules.
5. The system shall block unauthorized page access and redirect safely.

### FR-03 Application Period Management
1. The system shall allow Admin to create application periods with academic year, semester, start date, and end date.
2. The system shall allow Admin to open and close application periods.
3. The system shall prevent applicant registration/application actions when no period is open.
4. The system shall allow Admin to extend period deadlines.
5. The system shall enforce one application per applicant per open period.

### FR-04 Applicant Application Workflow
1. The system shall provide a multi-step scholarship application wizard.
2. The system shall capture applicant profile, school, and course information.
3. The system shall capture family, socioeconomic, and educational background details.
4. The system shall support file upload for required documents.
5. The system shall support 2x2 photo upload/capture and cropping.
6. The system shall autosave draft progress while the applicant completes steps.
7. The system shall persist draft state per applicant account.
8. The system shall provide a review page before final submission.
9. The system shall validate required inputs and files before final submission.
10. The system shall generate application number and QR token upon successful submission.
11. The system shall save final application and document metadata into the database.

### FR-05 Applicant Record Viewing
1. The system shall allow applicants to view their submitted applications.
2. The system shall display current application status and status history context.
3. The system shall show review notes and deadlines when available.
4. The system shall provide printable application output.
5. The system shall provide applicant QR code view.

### FR-06 Requirements Template Management
1. The system shall allow Admin to create and maintain requirement templates.
2. The system shall support requirement filtering by applicant type and school type.
3. The system shall allow activation/deactivation of templates.
4. The system shall apply active templates in applicant document requirements.

### FR-07 Application Review and Status Processing
1. The system shall allow Admin/Staff to list and filter applications.
2. The system shall allow Admin/Staff to open detailed application records.
3. The system shall allow verification of submitted requirement documents.
4. The system shall allow status updates only through allowed workflow transitions.
5. The system shall support review notes per application.
6. The system shall support SOA submission deadline setting.
7. The system shall record audit trail events for review/status actions.

### FR-08 Scholarship Status Workflow
1. The system shall support statuses including under review, needs resubmission, for interview, interview passed, for SOA, SOA received, awaiting payout, disbursed, and rejected.
2. The system shall display status badges and human-readable labels in applicant/admin views.
3. The system shall prevent invalid status transitions.

### FR-09 Disbursement Management
1. The system shall allow Admin/Staff to create disbursement records for eligible applications.
2. The system shall store amount, date, time, reference number, location, and payout status.
3. The system shall link disbursement records to the corresponding application.
4. The system shall support disbursement updates (scheduled/released/cancelled).

### FR-10 Notifications
1. The system shall create in-app notifications for key events.
2. The system shall support notification read/unread states.
3. The system shall allow users to mark single or multiple notifications as read.

### FR-11 SMS Messaging
1. The system shall support SMS sending via configured SMS provider integration.
2. The system shall support single and bulk SMS sending for Admin/Staff.
3. The system shall support template-based SMS messages.
4. The system shall log outbound SMS message content and delivery result.
5. The system shall send automated SMS for key events such as submission and status updates.

### FR-12 Announcements
1. The system shall allow Admin to create, edit, activate, and deactivate announcements.
2. The system shall display announcements to users on public/app pages.

### FR-13 Search, Filtering, and Lists
1. The system shall provide live filtering and search in major administrative records pages.
2. The system shall support pagination for large data views.
3. The system shall provide global search for Admin/Staff across major entities.

### FR-14 Reporting and Exports
1. The system shall provide analytics and report views for operations.
2. The system shall support exporting report datasets in PDF, DOCX, and XLSX formats.
3. The system shall support date-range filtering for supported reports.

### FR-15 Audit and System Logs
1. The system shall record audit logs for critical actions.
2. The system shall record SMS logs and delivery status entries.
3. The system shall provide log viewing filters for Admin.

### FR-16 Data Seeding and Test Data
1. The system shall support clean schema installation.
2. The system shall support seed scripts for realistic sample data.
3. The system shall support flood seeding for high-volume testing.

## Non-Functional Requirements

### NFR-01 Performance
1. The system shall return standard page requests within acceptable response time under normal expected load.
2. The system shall support efficient filtering/search in records pages with large datasets.
3. The system shall use appropriate database indexes for common query patterns.
4. The system shall support at least 1,000+ application records without functional degradation.

### NFR-02 Availability and Reliability
1. The system shall handle invalid input and missing data gracefully without system crashes.
2. The system shall preserve data consistency for critical operations (submission, status updates, disbursements).
3. The system shall provide clear error handling paths for failed external SMS operations.
4. The system shall support operation in XAMPP/WAMP-like local environments and standard hosting setups.

### NFR-03 Security
1. The system shall enforce authenticated access for protected modules.
2. The system shall enforce role-based authorization checks on restricted pages/actions.
3. The system shall validate CSRF tokens on state-changing requests.
4. The system shall hash and store passwords securely.
5. The system shall validate and sanitize user input server-side.
6. The system shall validate uploaded file types and paths.
7. The system shall protect against SQL injection by using prepared statements or equivalent safe query handling.

### NFR-04 Data Integrity
1. The system shall enforce key uniqueness constraints such as email, application number, and QR token.
2. The system shall enforce relational consistency via foreign keys where applicable.
3. The system shall prevent duplicate application submissions for the same applicant and period.
4. The system shall maintain valid status values using controlled status sets.

### NFR-05 Usability
1. The system shall provide clear navigation and understandable labels for Applicant, Staff, and Admin users.
2. The system shall provide status messaging that is simple and formal for SMS and notifications.
3. The system shall provide responsive page layouts usable on desktop and mobile browsers.
4. The system shall provide user feedback for successful and failed operations.

### NFR-06 Maintainability
1. The system shall organize code and modules in a maintainable structure.
2. The system shall provide reusable helper functions for common validations and formatting.
3. The system shall include documentation for setup, seeding, and operations.
4. The system shall support automated test execution through PHPUnit configuration.

### NFR-07 Portability and Compatibility
1. The system shall run on PHP 8+ and MySQL/MariaDB environments.
2. The system shall remain compatible with common Windows-based local stacks (e.g., XAMPP/WAMP).
3. The system shall use web standards compatible with modern browsers.

### NFR-08 Scalability
1. The system shall support growth in records volume through indexed queries and server-side filtering.
2. The system shall allow extension of templates, statuses, and periods without architecture changes.

### NFR-09 Auditability and Traceability
1. The system shall maintain actionable logs for key system events and administrative actions.
2. The system shall provide enough context in logs for operational traceability.

### NFR-10 Testability
1. The system shall support unit testing for core helper and validation logic.
2. The system shall allow repeatable testing via seed scripts and deterministic test data.
3. The system shall support manual and automated verification of status workflow behavior.
