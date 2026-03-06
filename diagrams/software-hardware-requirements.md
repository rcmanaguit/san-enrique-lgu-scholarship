# Software and Hardware Requirements

## 1. Software Requirements

### 1.1 Operating System
1. Windows 10/11 (recommended for XAMPP/WAMP setup)
2. Linux distributions (Ubuntu/Debian/CentOS/RHEL) for production hosting
3. macOS (supported for development if PHP/MySQL stack is configured)

### 1.2 Web Server
1. Apache HTTP Server 2.4+
2. `mod_rewrite` enabled (if URL rewriting rules are used)
3. Directory/file permissions configured for upload folders

### 1.3 PHP Runtime
1. PHP 8.0+ (PHP 8.2/8.3 recommended)
2. Required PHP extensions:
   - `mysqli`
   - `mbstring`
   - `json`
   - `openssl`
   - `curl`
   - `gd` (image handling/cropping support)
   - `fileinfo` (upload MIME detection)
   - `zip` (export libraries)
   - `xml` (document/export libraries)
3. Recommended PHP settings:
   - `upload_max_filesize` >= 10M
   - `post_max_size` >= 20M
   - `max_execution_time` >= 60
   - `memory_limit` >= 256M

### 1.4 Database
1. MySQL 8.0+ or MariaDB 10.5+
2. UTF-8 support (`utf8mb4`) enabled
3. SQL user with rights to:
   - `CREATE`, `ALTER`, `INDEX`, `INSERT`, `UPDATE`, `DELETE`, `SELECT`

### 1.5 Dependency Management
1. Composer 2.x
2. Required PHP packages (from `composer.json`):
   - `dompdf/dompdf`
   - `phpoffice/phpword`
   - `phpoffice/phpspreadsheet`
   - `endroid/qr-code`
3. Optional dev dependency:
   - `phpunit/phpunit` (for automated tests)

### 1.6 Client/Browser
1. Google Chrome (latest stable)
2. Microsoft Edge (latest stable)
3. Mozilla Firefox (latest stable)
4. JavaScript enabled
5. Camera permission enabled for QR/photo capture features

### 1.7 External Integrations
1. SMS provider account/API credentials (if SMS is enabled)
2. Internet connectivity required for outbound SMS API calls

### 1.8 Optional Development Tools
1. Git for version control
2. VS Code or equivalent IDE
3. Postman/Insomnia for endpoint testing (optional)

## 2. Hardware Requirements

### 2.1 Development Machine (Minimum)
1. CPU: Dual-core 64-bit processor
2. RAM: 8 GB
3. Storage: 20 GB free disk space (project, dependencies, DB, uploads)
4. Network: Stable internet (for Composer/API access)

### 2.2 Development Machine (Recommended)
1. CPU: Quad-core 64-bit processor (Intel i5/Ryzen 5 or better)
2. RAM: 16 GB
3. Storage: SSD with 50+ GB free space
4. Network: Broadband internet

### 2.3 Production Server (Minimum)
1. CPU: 2 vCPU
2. RAM: 4 GB
3. Storage: 80 GB SSD
4. Network: 1 static public IP or managed hosting endpoint
5. Backup storage: additional space for DB and uploads retention

### 2.4 Production Server (Recommended)
1. CPU: 4 vCPU or higher
2. RAM: 8-16 GB
3. Storage: 160+ GB SSD/NVMe
4. Network: Reliable low-latency connection with firewall controls
5. Separate backup target (separate disk, NAS, or cloud object storage)

### 2.5 Client Device Requirements (End Users)
1. Any desktop/laptop capable of modern browser execution
2. Minimum 4 GB RAM
3. Camera-capable device for QR/photo features when needed
4. Stable mobile or broadband connection

## 3. Environment-Specific Notes

### 3.1 Local Setup (XAMPP/WAMP)
1. Place project under web root (e.g., `c:\wamp64\www\...` or `c:\xampp\htdocs\...`)
2. Start Apache and MySQL services
3. Run `composer install`
4. Import `database/schema.sql`
5. Import `database/seed.sql` if test data is needed

### 3.2 Shared Hosting / VPS
1. Confirm PHP version and required extensions are available
2. Confirm MySQL/MariaDB version compatibility
3. Set writable permissions for:
   - `uploads/tmp`
   - `uploads/photos`
   - `uploads/documents`
4. Configure secure environment variables and DB credentials
5. Enable HTTPS (SSL/TLS) for production use

## 4. Capacity Planning Baseline
1. Designed to operate with 1,000+ application records.
2. For higher record volume and concurrent users, scale:
   - CPU and RAM
   - DB tuning/indexing
   - storage IOPS and capacity
3. Use realistic flood seed data and load testing before production launch.
