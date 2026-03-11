# Application and Updating Process

This document describes the full applicant-to-admin processing flow implemented in the current system.

## 1. Public Entry and Account Creation

1. The user opens [`index.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/index.php).
2. The landing page checks whether there is an open application period.
3. If there is no open period:
   - account creation for applicants is disabled from the public landing page
   - the user can still view announcements
4. If there is an open period:
   - the applicant can create an account through [`register.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/register.php)
   - OTP verification is completed through [`register-otp.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/register-otp.php)
5. After successful OTP verification, the applicant can log in through [`login.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/login.php).

## 2. Applicant Application Flow

### Entry rules

1. The applicant opens [`apply.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/apply.php).
2. The system allows access only when all of the following are true:
   - the user is logged in
   - the user role is `applicant`
   - the database is ready
   - an application period is currently open
   - the applicant has not yet submitted an application in the same period
3. If any condition fails, the applicant is redirected and shown a message.

### Step-by-step submission

1. Step 1 records the program/school details:
   - applicant type
   - semester and school year from the active period
   - school name
   - school type
   - course
2. Step 2 records personal details:
   - full name
   - age and birth date
   - sex and civil status
   - birthplace and address
   - barangay, town, province
   - contact number
3. Step 3 records family and academic background:
   - parent information
   - siblings
   - education history
   - honors
   - other grants
4. Step 4 uploads the required documents based on the active requirement templates for the open period.
5. Step 5 is handled in [`apply-photo.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/apply-photo.php):
   - the applicant uploads or captures a 2x2 photo
   - the image is cropped
   - the file is stored first in `uploads/tmp/`
6. Step 6 reviews the complete draft before final submission.

### Draft and autosave behavior

1. Steps 1 to 3 are autosaved through [`apply-autosave.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/apply-autosave.php).
2. Wizard state is stored in the session and also saved as a persistent draft in the database.
3. If the applicant leaves and returns, the draft is loaded back into the wizard.
4. The applicant can reset the draft, which clears both session state and the persistent draft.

### Final submission

1. On final submit, the system revalidates:
   - open application period still exists
   - no duplicate submission exists for the same applicant and period
   - required wizard fields are complete
   - required documents are present
   - the photo is present
2. If validation passes:
   - a row is inserted into `applications`
   - the initial application status is set to `under_review`
   - temporary files are moved from `uploads/tmp/` into final upload folders
   - document records are inserted into `application_documents`
   - an in-app notification is created
   - a submission SMS can be sent
   - the draft is cleared
3. The applicant is redirected to [`my-application.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/my-application.php).

## 3. Applicant Tracking Flow

1. The applicant opens [`my-application.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/my-application.php).
2. The page shows:
   - the latest application progress card
   - all application records for the active period, archived periods, or all periods
   - current status
   - review notes
   - SOA deadline, when applicable
   - SOA received timestamp, when applicable
   - uploaded document list
3. The applicant can also:
   - print the application form through [`print-application.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/print-application.php)
   - view the QR code through [`my-qr.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/my-qr.php)
   - preview uploaded documents through [`preview-document.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/preview-document.php)
4. The applicant cannot create another application in the same open period once one has already been submitted.

## 4. Admin and Staff Review Flow

### Queue access

1. Admin and staff open [`shared/applications.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/applications.php).
2. Applications are grouped into queue tabs:
   - `under_review`
   - `for_interview`
   - `for_soa`
   - `awaiting_payout`
   - `rejected`
   - `completed`
3. The page supports search, filtering, modal review, document preview, and selected bulk actions.

### Document review routing

1. While an application is in `under_review` or `needs_resubmission`, admin/staff can review uploaded documents.
2. Each document is marked as either:
   - `verified`
   - `rejected`
3. The system automatically routes the application after document review:
   - if all reviewed documents pass, status becomes `for_interview`
   - if any required document fails, status becomes `needs_resubmission`
4. Review notes are saved to the application.
5. The applicant receives:
   - an in-app notification
   - an SMS message, if SMS is enabled

## 5. Status Updating Process

The application status flow implemented in the queue is:

1. `under_review` -> `for_interview` or `rejected`
2. `needs_resubmission` -> `under_review` or `rejected`
3. `for_interview` -> `interview_passed` or `rejected`
4. `interview_passed` -> `for_soa`
5. `for_soa` -> `soa_received`
6. `soa_received` -> `awaiting_payout` or `disbursed`
7. `awaiting_payout` -> `disbursed`
8. `disbursed` -> final state
9. `rejected` -> final state

### Rules enforced during updates

1. Interview approval requires the application to already be in `for_interview`.
2. `interview_passed` is treated as the approved interview phase and leads into `for_soa`.
3. Moving to `for_soa` requires an SOA submission deadline.
4. Marking an application as `soa_received` requires a prior `for_soa` stage.
5. Marking an application as `disbursed` is only allowed from `soa_received` or `awaiting_payout`.
6. Archived-period applications are protected from updates unless admin explicitly allows archived updates.

### Meaning of each status

1. `under_review`: initial review is ongoing
2. `needs_resubmission`: applicant must replace or fix one or more documents
3. `for_interview`: documents are acceptable and the applicant moves to interview scheduling
4. `interview_passed`: interview stage was passed
5. `for_soa`: applicant must submit the statement of account before the deadline
6. `soa_received`: the statement of account was received and recorded
7. `awaiting_payout`: application is waiting for final payout approval or release processing
8. `disbursed`: payout was released
9. `rejected`: application was not approved

## 6. Interview, SOA, and Payout Updates

### Interview stage

1. Applications in the `for_interview` queue can receive interview scheduling details.
2. Once interview requirements are satisfied, admin/staff can mark the application as `interview_passed`.
3. Rejected applications can also be ended at this stage.

### SOA stage

1. After interview approval, the record moves to `for_soa`.
2. Admin must provide an SOA submission deadline.
3. The applicant is notified to submit the SOA.
4. Once the SOA is received and recorded, the status becomes `soa_received`.

### Payout stage

1. Records in `soa_received` can be moved to:
   - `awaiting_payout`
   - `disbursed`
2. Records in `awaiting_payout` can later be marked as `disbursed`.
3. Payout-related records are managed through [`shared/disbursements.php`](/c:/xampp/htdocs/san-enrique-lgu-scholarship/shared/disbursements.php).

## 7. Notifications, SMS, and Audit Trail

1. Status changes create in-app notifications linked back to applicant pages.
2. SMS templates are resolved per status and used for applicant updates when enabled.
3. Important actions are written to the audit log, including:
   - application submission
   - document review
   - status update
   - interview scheduling
   - SOA updates
   - payout updates

## 8. Archive and Reapplication Rules

1. The system keeps old applications as archived records instead of deleting them.
2. Archived applications remain visible in applicant history and admin review.
3. The applicant may submit a new application only when:
   - a new application period is open
   - no application has yet been submitted in that same period
4. This means one applicant can have multiple applications across different periods, but only one per open period.
