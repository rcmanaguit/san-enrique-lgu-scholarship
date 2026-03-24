# LGU San Enrique Scholarship Management System
## User Manual

## 1. Introduction
The LGU San Enrique Scholarship Management System is a web-based application developed to manage scholarship applications, student records, document verification, interview scheduling, payout preparation, notifications, and audit logging. This manual provides guidance for students, staff, and administrators in using the system properly.

## 2. Purpose of the System
The system is designed to:
- automate the scholarship application process
- reduce manual paperwork and record handling
- improve monitoring of applicant requirements
- provide timely updates and notifications
- generate organized reports for scholarship management

## 3. User Roles
The system has three main user roles:

- `Student`
- `Staff`
- `Admin`

### 3.1 Student
A student can:
- register an account
- log in to the system
- complete a profile
- submit scholarship applications
- upload documentary requirements
- check application status
- receive notifications

### 3.2 Staff
A staff user can:
- review applications
- verify uploaded documents
- encode interview results
- add case notes
- assist in scholarship processing

### 3.3 Admin
An administrator can:
- manage staff and admin accounts
- configure application settings
- create interview and payout batches
- publish announcements
- approve applications
- archive records
- monitor reports and audit logs

## 4. Account Access Policy
The system follows this account setup:

- `Students` may register their own accounts
- `Staff` accounts are created by an admin
- `Admin` accounts are not publicly registered
- the initial admin account is seeded by the system
- additional admin accounts may only be created by an existing admin

## 5. Login Credentials
The system contains default accounts for initial access.

### 5.1 Default Admin Account
- Phone Number: `09123456789`
- Password: `admin123`

### 5.2 Default Staff Account
- Phone Number: `09987654321`
- Password: `staff123`

For security purposes, default passwords should be changed immediately after first login.

## 6. How to Access the System

### 6.1 Login Procedure
1. Open the system in a web browser.
2. Enter the registered phone number or email address.
3. Enter the password.
4. Click `Login`.

### 6.2 Logout Procedure
1. Click the account or profile menu.
2. Select `Logout`.
3. Wait until the system returns to the login page.

## 7. Student User Guide

### 7.1 Student Registration
1. Open the registration page.
2. Enter the required information:
   - first name
   - last name
   - phone number
   - email address, if applicable
   - password
3. Submit the registration form.
4. Enter the OTP if verification is required.
5. Log in after account verification.

### 7.2 Completing the Student Profile
1. Log in to the student account.
2. Open the `Profile` section.
3. Fill in the required personal details:
   - last name
   - first name
   - middle name
   - suffix, if applicable
   - date of birth
   - place of birth
   - sex
   - civil status
4. Enter address information:
   - address line
   - barangay
5. Enter family background:
   - mother's name
   - mother's age
   - mother's occupation
   - mother's monthly income
   - father's name
   - father's age
   - father's occupation
   - father's monthly income
6. Enter educational details:
   - school type
   - school name
   - course
   - year level
7. Upload the following if required:
   - e-signature
   - ID picture
8. Click `Save`.

### 7.3 Submitting a Scholarship Application
1. Open the `Application` page.
2. Select the following:
   - school year
   - semester
   - application type
3. Fill in additional information if required.
4. Confirm privacy consent.
5. Click `Submit`.

### 7.4 Uploading Required Documents
1. Open the submitted application.
2. Go to the `Documents` section.
3. Upload the required files:
   - Grades
   - Residency
   - SOA
4. Click `Upload`.
5. Wait for confirmation that the upload was successful.

### 7.5 Viewing Application Status
Students can monitor the progress of their applications using the status field. The possible statuses are:

- `Submitted`
- `Initial_Review`
- `Pending_Resubmission`
- `For_Interview`
- `Not_Eligible`
- `Eligible_Awaiting_SOA`
- `SOA_Under_Review`
- `SOA_Resubmission_Required`
- `Approved_Pending_Payroll`
- `Approved_Finished`
- `Forfeited`

### 7.6 Viewing Notifications
1. Open the `Notifications` page.
2. Read the latest updates from staff or admin.
3. Check notifications for:
   - document verification results
   - interview schedules
   - resubmission requests
   - approval or disqualification notices

## 8. Staff User Guide

### 8.1 Logging In as Staff
1. Enter the registered staff credentials.
2. Click `Login`.
3. Wait for the dashboard to load.

### 8.2 Reviewing Applications
1. Open the `Applications` section.
2. Search or filter records by:
   - student name
   - school year
   - semester
   - status
3. Select an application to open its details.

### 8.3 Verifying Documents
1. Open a specific application.
2. Go to `Documents`.
3. Review each uploaded document.
4. Assign a status to each document:
   - `Pending`
   - `Verified`
   - `Rejected`
5. If rejected, enter remarks explaining the reason.
6. Save the document review.

### 8.4 Recording Interview Results
1. Open the student application scheduled for interview.
2. Assign or confirm the interview batch.
3. Enter the interview result:
   - `Passed`
   - `Failed`
   - `Absent`
4. Save the update.

### 8.5 Adding Case Notes
1. Open the target application.
2. Go to `Case Notes`.
3. Enter remarks, findings, or follow-up notes.
4. Save the note.

## 9. Administrator User Guide

### 9.1 Managing User Accounts
The administrator is responsible for internal account management.

The admin may:
- create staff accounts
- activate or deactivate users
- manage existing admin accounts
- monitor user activity

### 9.2 Configuring Application Settings
1. Open `Application Settings`.
2. Enter or update the following:
   - school year
   - semester
   - application start date
   - application end date
   - SOA deadline mode
   - automatic archive setting
   - open or close application status
3. Save the settings.

### 9.3 Creating Interview and Payout Batches
1. Open the `Batches` section.
2. Click the option to create a new batch.
3. Select batch type:
   - `Interview`
   - `Payout`
4. Enter the following details:
   - batch name
   - scheduled date
   - venue
5. Save the batch.

### 9.4 Publishing Announcements
1. Open the `Announcements` section.
2. Enter the announcement title.
3. Write the announcement body.
4. Select the target audience:
   - `Public`
   - `Students`
   - `Both`
5. Set the publication status.
6. Save or publish the announcement.

### 9.5 Approving Applications
1. Review the student's application, documents, and interview result.
2. Confirm eligibility.
3. Update the application status.
4. Enter the final grant amount if needed.
5. Assign the student to a payout batch when approved.

### 9.6 Archiving Records
1. Open old or completed application records.
2. Mark the record as archived.
3. Enter archive notes if necessary.
4. Save the changes.

## 10. Reports Available in the System
The system can generate or display the following reports:

- student application report
- document verification report
- interview result report
- approved scholars list
- payout report
- notifications record
- audit log report

## 11. Audit Logs
The system records important user actions for accountability. These may include:
- login activity
- account updates
- document verification
- application approval
- batch assignment
- archive actions

Each log entry may include:
- user
- role
- action performed
- affected record
- description
- date and time

## 12. Security Guidelines
All users are advised to follow these security practices:

- keep passwords confidential
- change default passwords immediately
- do not share accounts
- log out after every session
- upload only valid and clear documents
- review all submitted information carefully
- allow only authorized admins to create staff or admin accounts

## 13. Troubleshooting Guide

### 13.1 Cannot Log In
Possible reasons:
- incorrect phone number or email
- wrong password
- inactive account
- unverified account

### 13.2 OTP Not Received
Possible reasons:
- wrong phone number entered
- delayed message delivery
- expired OTP

### 13.3 Document Rejected
If a document is rejected:
1. open the remarks section
2. read the rejection reason
3. prepare a clearer or corrected document
4. upload a replacement file

### 13.4 Application Cannot Be Edited
Possible reasons:
- the application is already under review
- the application period is closed
- the record has been locked by staff or admin

## 14. Best Practices for Users

### For Students
- complete your profile before applying
- upload readable and correct files
- monitor notifications regularly
- submit applications before the deadline

### For Staff
- review applications carefully
- provide clear remarks for rejected documents
- update interview results on time
- record important notes in case notes

### For Admin
- review settings before opening applications
- secure admin credentials
- manage staff accounts properly
- monitor reports and audit logs regularly

## 15. Conclusion
The LGU San Enrique Scholarship Management System helps improve the efficiency, transparency, and reliability of scholarship processing. By following the procedures in this manual, students, staff, and administrators can use the system effectively and maintain accurate scholarship records.
