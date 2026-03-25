# LGU San Enrique Scholarship Management System
## User Guide With Screenshot Guide

## 1. Introduction
This guide follows the actual workflow implemented in the LGU San Enrique Scholarship Management System. Use it when preparing the user manual, capstone documentation, or presentation screenshots.

## 2. Actual System Workflow
The application does not use a separate student profile module and a separate general document-upload module for the first submission. The real workflow is:

1. Student registers and logs in.
2. Student submits one complete application form for the active school year and semester.
3. Staff reviews the uploaded initial requirements.
4. If requirements are rejected, the student uses the document resubmission screen.
5. If requirements are verified, the application moves to `For_Interview`.
6. Staff schedules the interview and records the result.
7. If the student passes, the student uploads the Statement of Account (SOA).
8. Staff reviews the SOA.
9. Approved records move to payout scheduling.
10. Completed, not eligible, or forfeited records appear in the closed/completed records view.

## 3. Screenshot Guidelines
When taking screenshots, make sure that:

- the page title is visible
- the current status, queue, or action is visible
- the main form fields, tabs, or tables are readable
- sensitive data such as passwords are hidden
- the screenshot matches the actual page name used by the system

## 4. Student User Guide

### 4.1 Student Registration
**Procedure**
1. Open the `Register` page.
2. Enter the required account details.
3. Submit the registration form.
4. Enter the OTP on the verification screen.

**Where to Screenshot**
- registration page
- OTP verification page

**What to Screenshot**
- registration form
- `Register` button
- OTP input form

### 4.2 Student Login
**Procedure**
1. Open the `Login` page.
2. Enter phone number or email.
3. Enter password.
4. Click `Login`.

**Where to Screenshot**
- login page

**What to Screenshot**
- login form
- `Login` button
- system title or branding

### 4.3 Student Dashboard
**Procedure**
1. Log in as a student.
2. Open the dashboard.
3. Review the current application card, status badge, next action, and full timeline.

**Where to Screenshot**
- student dashboard

**What to Screenshot**
- welcome header
- current application status
- next action button such as `Apply Now`, `Fix Documents`, or `Upload SOA`
- full application timeline section
- notification bell in the header if visible

### 4.4 Student Application Form
**Procedure**
1. Open `Apply`.
2. Complete the application form for the current school year and semester.
3. Fill in personal, family, and school details.
4. Upload the 2x2 ID picture, e-signature, grades, and Barangay Residency file.
5. Confirm the Data Privacy Notice.
6. Click `Submit Application`.

**Where to Screenshot**
- student application form

**What to Screenshot**
- school year and semester display
- application type
- personal information section
- family background section
- educational information section
- upload fields for ID picture, e-signature, grades, and Barangay Residency
- privacy consent
- `Submit Application` button

**Important Note**
- Do not use a separate "Student Profile Completion" screenshot for the guide. The current system uses one application form instead of a separate profile-completion workflow.

### 4.5 Submitted Application Record
**Procedure**
1. Open `My Applications`.
2. Select one submitted application.
3. Review the saved record.

**Where to Screenshot**
- `My Applications` page
- submitted application record page

**What to Screenshot**
- applications list with status badge
- `Submitted Application Record` page title
- `Info`, `Files / Documents`, and `Timeline` tabs
- application ID, date submitted, and status

### 4.6 Document Resubmission
**Procedure**
1. Wait for staff review.
2. If the application status becomes `Pending_Resubmission`, open the resubmission screen from the dashboard.
3. Read the staff remarks.
4. Upload only the rejected replacement files.
5. Click `Submit Corrected Documents`.

**Where to Screenshot**
- document resubmission page

**What to Screenshot**
- `Document Resubmission` page title
- rejected document card
- staff remarks
- replacement upload field
- `Submit Corrected Documents` button

### 4.7 Interview Status Tracking
**Procedure**
1. Open the dashboard or application record.
2. Check when the application reaches `For_Interview`.
3. Review the interview schedule once assigned.

**Where to Screenshot**
- dashboard current application card
- application timeline

**What to Screenshot**
- `For_Interview` status badge
- interview schedule details if already assigned
- timeline entry showing interview progress

### 4.8 Statement of Account (SOA) Submission
**Procedure**
1. After passing the interview, open `Apply` again.
2. The system switches to the `Statement of Account Submission` screen.
3. Review the SOA deadline and policy.
4. Upload the SOA file.
5. Click `Submit SOA`.

**Where to Screenshot**
- SOA upload page

**What to Screenshot**
- `Statement of Account Submission` title
- application period
- SOA deadline policy
- SOA deadline
- SOA file upload field
- `Submit SOA` button

### 4.9 Student Notifications
**Procedure**
1. Use the notification bell in the header.
2. Review unread updates for document review, interview scheduling, SOA review, or payout schedule.

**Where to Screenshot**
- notification dropdown in the header

**What to Screenshot**
- notification bell
- unread badge
- notification list
- `Mark all as read` action

**Important Note**
- The current system uses a header notification dropdown, not a dedicated `Notifications` page.

## 5. Staff User Guide

### 5.1 Staff Login
**Procedure**
1. Open the login page.
2. Enter staff credentials.
3. Click `Login`.

**Where to Screenshot**
- login page

**What to Screenshot**
- login form
- system title

### 5.2 Staff Dashboard
**Procedure**
1. Log in as staff.
2. Review the dashboard summary cards and workload snapshot.

**Where to Screenshot**
- staff dashboard

**What to Screenshot**
- dashboard title
- cards for review, interview, SOA, payout, returned, or closed records

### 5.3 Applications Board
**Procedure**
1. Open `Applications`.
2. Review the queue chips and records list.
3. Filter or search as needed.
4. Open an application for detailed review.

**Where to Screenshot**
- applications page

**What to Screenshot**
- `Applications` page title
- queue chips such as `Under Review`, `Needs Correction`, `For Interview`, `SOA Phase`, `Ready for Payout`, and `Completed`
- applications table or cards
- search and filter controls

### 5.4 Document Verification Workspace
**Procedure**
1. Open one application from the applications board.
2. Review the applicant workspace.
3. Check the submitted files.
4. Mark a document as `Verified` or `Rejected`.
5. Enter remarks when rejecting a file.

**Where to Screenshot**
- document verification page

**What to Screenshot**
- applicant page title
- tabs: `Documents`, `Timeline`, `File History`, `Staff Notes`
- document preview or document list
- status action area
- remarks field

**Workflow Note**
- Rejecting an initial document returns the application to `Pending_Resubmission`.
- Verifying all initial required files moves the application to `For_Interview`.

### 5.5 Interview Scheduling
**Procedure**
1. Open `Interviews` or `Interview Schedules`.
2. Review applicants who are ready for scheduling.
3. Create an interview batch with batch name, date, and venue.
4. Save the schedule.

**Where to Screenshot**
- `Interview Batches` page
- create batch form

**What to Screenshot**
- `Interview Batches` title
- ready-for-interview list
- batch creation form
- scheduled interview batch table

### 5.6 Interview Result Recording
**Procedure**
1. Open an interview batch.
2. Record the result for each applicant.
3. Save the interview result.

**Where to Screenshot**
- interview batch details or results table

**What to Screenshot**
- applicant list
- interview result field with `Passed`, `Failed`, and `Absent`
- schedule and venue details
- saved result row

**Workflow Note**
- `Passed` moves the student to the SOA stage.
- `Failed` or `Absent` closes the application as `Not_Eligible`.

### 5.7 Staff Notes and Record Review
**Procedure**
1. Open an application workspace.
2. Go to `Staff Notes`.
3. Add a case note or internal remark.

**Where to Screenshot**
- staff notes tab

**What to Screenshot**
- note input field
- saved notes list
- submit button

### 5.8 Master Record and Closed Records
**Procedure**
1. Open `Master Record`.
2. Review active or closed scholarship records.
3. Print interview or disqualification notices when needed.
4. Archive eligible closed records if required.

**Where to Screenshot**
- master record page

**What to Screenshot**
- record list
- status badges such as `Approved_Finished`, `Not_Eligible`, or `Forfeited`
- print notice actions if visible
- archive action if visible

## 6. Administrator User Guide

### 6.1 Admin Dashboard
**Procedure**
1. Log in as admin.
2. Review the dashboard summary and management shortcuts.

**Where to Screenshot**
- admin dashboard

**What to Screenshot**
- dashboard title
- summary cards
- charts or record summaries if visible

### 6.2 Application Period Management
**Procedure**
1. Open `Application Period`.
2. Review the current application period.
3. Open a new period or update the existing one.
4. Set school year, semester, application dates, and SOA deadline policy.
5. Save the settings.

**Where to Screenshot**
- application period management page

**What to Screenshot**
- `Application Period Management` page title
- `Current Application Period` section
- `Open New Application Period` section
- school year and semester fields
- application start and end dates
- SOA deadline policy and global deadline fields

### 6.3 Interview Schedules
**Procedure**
1. Open `Interview Schedules`.
2. Review created interview batches and reschedule if necessary.

**Where to Screenshot**
- `Interview Batches` page under admin access

**What to Screenshot**
- batch list
- schedule and venue
- reschedule action

### 6.4 Payout Batches
**Procedure**
1. Open `Payouts`.
2. Review scholars who are ready for payout scheduling.
3. Create a payout batch using the available filters.
4. Enter batch name, schedule, venue, and amount per scholar.
5. Save the payout batch.

**Where to Screenshot**
- payout page
- create payout batch form

**What to Screenshot**
- page title `Payout Batches`
- payout snapshot cards
- approved scholars ready for scheduling
- payout batch form
- scheduled payout table

**Important Note**
- The implemented admin workflow uses `Payout Batches`, not a separate generic per-record `Application Approval` page in the current UI.

### 6.5 Reports
**Procedure**
1. Open `Reports`.
2. Select the report type and filters.
3. Review the generated summaries and tables.

**Where to Screenshot**
- reports page

**What to Screenshot**
- report title
- filter controls
- report table or chart output

### 6.6 Announcements
**Procedure**
1. Open `Announcements`.
2. Create or update announcement content.
3. Toggle publish status when ready.

**Where to Screenshot**
- announcements page

**What to Screenshot**
- announcement form
- announcements list
- publish toggle

### 6.7 User Management
**Procedure**
1. Open `Users`.
2. Review the user list.
3. Create a staff account or update account status.
4. Reset a password when needed.

**Where to Screenshot**
- users page

**What to Screenshot**
- users table
- role column
- create user form
- active or inactive status controls

### 6.8 Exceptions and Recovery
**Procedure**
1. Open `Exceptions` to review overdue SOA, rejected documents, or pending interview results.
2. Open `Recovery` when helping recover a student account.

**Where to Screenshot**
- exceptions page
- recovery page

**What to Screenshot**
- exception summary cards
- overdue or returned-record tables
- recovery lookup form

### 6.9 Audit Logs
**Procedure**
1. Open `Audit Logs`.
2. Review recorded actions for users and workflow changes.

**Where to Screenshot**
- audit logs page

**What to Screenshot**
- audit table
- action name
- actor
- timestamp

## 7. Recommended Screenshot Sequence for Documentation
If you need a clean system demo flow, capture screenshots in this order:

1. Register
2. Login
3. Student Dashboard
4. Student Application Form
5. Submitted Application Record
6. Staff Applications Board
7. Staff Document Verification
8. Interview Batches
9. SOA Submission
10. Payout Batches
11. Master Record or Completed Record
