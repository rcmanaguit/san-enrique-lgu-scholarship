# DFD Level 1

## External Entities
- Applicant / Student
- Staff
- Admin
- SMS Service

## Subprocesses

### 1.0 Account and Authentication Management
- **1.1 Register / Verify Account**
- **1.2 Log In / Recover Account**

### 2.0 Scholarship Application Processing
- **2.1 Capture Application Details**
- **2.2 Submit Requirements and Media**

### 3.0 Document and Qualification Review
- **3.1 Review Initial Documents**
- **3.2 Manage Resubmission / Verification**

### 4.0 Interview and SOA Management
- **4.1 Schedule / Reschedule Interview**
- **4.2 Record Interview Result / Review SOA**

### 5.0 Payout and Records Management
- **5.1 Create Payout Schedule**
- **5.2 Maintain Archive / Master Record**

### 6.0 Notifications and Reporting
- **6.1 Generate Notifications**
- **6.2 Generate Reports / Logs**

### 7.0 Administrative Control
- **7.1 Manage Application Period**
- **7.2 Manage Users / Recovery**

## Data Stores
- **D1 User Accounts**
- **D2 Application Records**
- **D3 Documents / Versions**
- **D4 Schedules / Batches**
- **D5 Notifications / Logs / Reports**
- **D6 Settings / Period Rules**

## General Explanation
- Applicants create accounts, log in, and submit scholarship applications with documents and media.
- Staff review the submitted documents, handle corrections, schedule interviews, and review SOA submissions.
- Admin manages the application period, internal users, payout scheduling, records, and reporting.
- The system generates notifications and sends SMS alerts through the SMS service.
- All transactions are stored in the related data stores for records management, monitoring, and retrieval.
