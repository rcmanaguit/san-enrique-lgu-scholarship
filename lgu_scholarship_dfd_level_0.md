# DFD Level 0

## System
Automated Scholarship Records Management System

## External Entities
- Applicant / Student
- Staff
- Admin
- SMS Service

## Main Processes
- **1.0 Account & Authentication Management**
  - Handles registration, login, OTP verification, password reset, and account updates.
- **2.0 Scholarship Application Processing**
  - Handles application form submission, initial requirements, 2x2 photo, and e-signature.
- **3.0 Document & Qualification Review**
  - Handles document verification, rejection remarks, resubmission review, and qualification checks.
- **4.0 Interview & SOA Management**
  - Handles interview scheduling, interview results, SOA upload, and SOA review.
- **5.0 Payout & Records Management**
  - Handles payout scheduling, scholar release processing, and archival record updates.
- **6.0 Notifications & Reporting**
  - Handles applicant/staff/admin notifications, audit trail output, monitoring, and reports.

## Data Stores
- **D1 User Accounts**
- **D2 Applicant / Scholar Records**
- **D3 Application Files & Documents**
- **D4 Schedules & Batches**
- **D5 Notifications, Logs, Reports & Archive**

## General Data Flow Summary
- The **Applicant / Student** submits account details, scholarship applications, requirements, SOA, and receives status updates.
- **Staff** reviews applications and documents, schedules interviews, and records evaluation results.
- **Admin** manages periods, reports, users, payouts, and records.
- The **SMS Service** sends system-generated notifications and returns delivery status.
- The processes read from and write to the core data stores for accounts, records, documents, schedules, and system outputs.
