# Automated Scholarship Records Management System

## Actors
- Applicant / Student
- Staff
- Admin
- SMS Service
- Email Service

## Applicant / Student Use Cases
- Register account
- Verify account via OTP
- Log in
- Recover account by email
- Reset password
- Manage account settings
- Apply for scholarship
- Fill out application form
- Upload initial requirements
- Capture or upload 2x2 photo
- Draw or upload e-signature
- Review and submit application
- View application status tracker
- View per-application timeline
- View submitted application record
- Resubmit rejected documents
- View interview schedule and result
- Upload or resubmit SOA
- View payout schedule
- View scholarship history
- Print application form

## Staff Use Cases
- Log in
- Manage account settings
- View dashboard
- View application board
- Open review workspace
- Verify or reject documents
- Add staff notes
- View version history
- Schedule interview batch
- Reschedule interview batch
- Record interview results
- View document center
- View master record or archive

## Admin Use Cases
- Log in
- Manage account settings
- View dashboard
- View application board
- Manage application period
- Manage internal users
- Perform applicant account recovery
- View or export reports
- View audit logs
- View exceptions monitoring
- Manage announcements
- Schedule or reschedule payout batch
- Print payroll or signature sheet
- Manage backups
- View master record or archive

## External Service Use Cases

### SMS Service
- Send OTP SMS
- Send interview schedule SMS
- Send interview result SMS
- Send payout schedule SMS

### Email Service
- Send email recovery code

## Include Relationships
- Register account includes Verify account via OTP
- Recover account by email includes Send email recovery code
- Verify account via OTP includes Send OTP SMS
- Apply for scholarship includes:
  - Fill out application form
  - Upload initial requirements
  - Capture or upload 2x2 photo
  - Draw or upload e-signature
  - Review and submit application
- Open review workspace includes:
  - View submitted application record
  - View per-application timeline
  - View version history
- Schedule interview batch includes Send interview schedule SMS
- Record interview results includes Send interview result SMS
- Schedule or reschedule payout batch includes Send payout schedule SMS

## System Boundary
- Automated Scholarship Records Management System
