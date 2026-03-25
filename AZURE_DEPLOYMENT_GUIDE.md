# Azure Deployment Guide

This guide explains how to deploy the **San Enrique LGU Scholarship System** to **Microsoft Azure App Service** using **PHP** and **Azure Database for MySQL**.

## Recommended Azure Architecture

- **Azure App Service (Linux)** for the PHP web app
- **Azure Database for MySQL Flexible Server** for the database
- **App Service App Settings** instead of committing secrets in `.env`
- **Azure Storage is optional**
  This app already supports a local writable storage path, so `/home/site/wwwroot/storage` is enough for a basic deployment.

## 1. Prerequisites

Before deploying, make sure you have:

- An Azure subscription
- The full project source code
- The database schema file: `database_schema.sql`
- A valid SMTP account for email recovery
- Optional TextBee credentials for SMS OTP

This project requires:

- PHP 8.0 or newer
- Composer
- MySQL

## 2. Create Azure Resources

Create these resources in Azure Portal:

1. **Resource Group**
2. **Azure Database for MySQL Flexible Server**
3. **App Service Plan**
4. **Web App**

### Suggested Web App settings

- Runtime stack: `PHP 8.2` or newer
- Operating System: `Linux`
- Region: same region as the MySQL server

## 3. Create the MySQL Database

Inside Azure Database for MySQL:

1. Create a database named `lgu_san_enrique_scholarship`
2. Allow your client IP temporarily so you can import the schema
3. Import [`database_schema.sql`](C:/xampp/htdocs/san-enrique-lgu-scholarship/database_schema.sql)

You can import with MySQL Workbench, Azure Cloud Shell, or the MySQL CLI.

Example:

```bash
mysql -h <mysql-server>.mysql.database.azure.com -u <admin-user> -p --ssl-mode=REQUIRED lgu_san_enrique_scholarship < database_schema.sql
```

## 4. Prepare the App for Azure

This project uses:

- [`public/index.php`](C:/xampp/htdocs/san-enrique-lgu-scholarship/public/index.php) as the front controller
- [`app/Support/helpers.php`](C:/xampp/htdocs/san-enrique-lgu-scholarship/app/Support/helpers.php) for `APP_BASE_PATH` and `APP_STORAGE_PATH`
- [`app/Config/Database.php`](C:/xampp/htdocs/san-enrique-lgu-scholarship/app/Config/Database.php) for MySQL connection settings

On Azure App Service, the important parts are:

- The app must serve from the `public` folder
- The `storage` folder must be writable
- Environment variables must be defined in App Settings

## 5. Deploy the Code to App Service

You can deploy with any of these methods:

- Local Git
- GitHub Actions
- Zip Deploy
- VS Code Azure extension

The simplest option is **Zip Deploy**.

### Zip Deploy steps

1. Make sure `vendor/` is included if you are deploying a ready-to-run package
2. Zip the project contents
3. Upload the zip through:
   Azure Portal -> Web App -> Deployment Center -> Zip Deploy

If you prefer building in Azure, make sure Composer runs during deployment.

### GitHub Actions option

This repository already includes an Azure deployment workflow:

- [main_sanenriquelguscholarship.yml](C:/xampp/htdocs/san-enrique-lgu-scholarship/.github/workflows/main_sanenriquelguscholarship.yml)

Setup details are documented here:

- [AZURE_GITHUB_ACTIONS_SETUP.md](C:/xampp/htdocs/san-enrique-lgu-scholarship/AZURE_GITHUB_ACTIONS_SETUP.md)

### Portal-only option

If you do not want to use GitHub Actions or Azure CLI, you can deploy entirely from Azure Portal:

1. Open your Web App in Azure Portal
2. Go to `Deployment Center`
3. Choose one of these sources:
   - `Local Git`
   - `External GitHub repository`
   - `Zip Deploy`
4. If using Zip Deploy, upload a zip of the project
5. After deployment, open `Configuration`
6. Add all required app settings
7. Restart the Web App

For most manual deployments, `Zip Deploy` is the simplest Portal-only path.

## 6. Set the Startup / Document Root

This application must use the `public` folder as the web root.

In **App Service -> Configuration -> Path mappings / Startup settings**, configure the app so the site serves from:

```text
/home/site/wwwroot/public
```

If your App Service configuration does not expose a direct document-root setting, use a startup command or custom container approach. For a normal PHP App Service deployment, the most reliable approach is to deploy the repository so that the runtime serves the `public` folder.

If you deploy the repository root directly and Azure serves from `/home/site/wwwroot`, requests may not hit the correct front controller.

### Portal-only checklist for document root

If you are deploying only through Azure Portal, confirm these items after publish:

1. The application files exist under `/home/site/wwwroot`
2. The `public` folder contains `index.php`
3. The app is configured so requests are handled through `public`

If Azure serves the repository root directly without pointing to `public`, the site may return `404` or load incorrectly.

## 7. Configure App Settings

Do not rely on a production `.env` file in Azure. Add the values in:

**App Service -> Configuration -> Application settings**

Use these keys based on [`.env.example`](C:/xampp/htdocs/san-enrique-lgu-scholarship/.env.example):

```text
APP_NAME=San Enrique LGU Scholarship
APP_ENV=production
APP_URL=https://<your-app-name>.azurewebsites.net
APP_DEBUG=false
APP_BASE_PATH=
APP_STORAGE_PATH=/home/site/wwwroot/storage

DB_HOST=<mysql-server>.mysql.database.azure.com
DB_PORT=3306
DB_DATABASE=lgu_san_enrique_scholarship
DB_USERNAME=<mysql-admin-user>
DB_PASSWORD=<mysql-password>
DB_SSL_CA=
DB_SSL_VERIFY_SERVER_CERT=true

TEXTBEE_API_KEY=
TEXTBEE_DEVICE_ID=

MAIL_HOST=<your-smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<your-smtp-username>
MAIL_PASSWORD=<your-smtp-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<your-from-email>
MAIL_FROM_NAME=San Enrique LGU Scholarship
```

### Notes

- `APP_BASE_PATH` should usually be empty if the app is deployed at the site root
- Set `APP_BASE_PATH` only if the app is hosted under a subpath
- `APP_STORAGE_PATH=/home/site/wwwroot/storage` matches how this app resolves uploads and generated files
- For Azure Database for MySQL, SSL is normally required

## 8. Make Storage Writable

This app writes uploaded files into the storage area. Ensure the following directory exists after deployment:

```text
/home/site/wwwroot/storage
```

If needed, create subfolders used by uploads and generated documents after the first deploy.

The app already supports custom storage roots through `APP_STORAGE_PATH`, so no code change is required for Azure.

## 9. Composer Dependencies

If you deploy source code without `vendor/`, install dependencies during deployment:

```bash
composer install --no-dev --optimize-autoloader
```

This project depends on:

- `bramus/router`
- `vlucas/phpdotenv`
- `guzzlehttp/guzzle`
- `phpmailer/phpmailer`
- `dompdf/dompdf`

## 10. Post-Deployment Checks

After deployment:

1. Open the site homepage
2. Test login
3. Test registration
4. Test file upload
5. Test password recovery email
6. Test PDF generation
7. Confirm database reads and writes work correctly

If uploads fail, check:

- `APP_STORAGE_PATH`
- folder permissions
- PHP upload limits in App Service

If database connection fails, check:

- MySQL firewall rules
- host, username, password, and database name
- SSL requirements

## 11. Common Azure Issues

### 404 or blank page on first load

Most likely cause:

- Azure is not serving the `public` folder as the document root

### Database connection failed

Most likely causes:

- Wrong MySQL hostname
- Firewall not allowing App Service outbound access
- SSL requirements not matched

### Uploaded files disappear

Most likely causes:

- Files were written to the wrong path
- The storage path was not explicitly configured

Use:

```text
APP_STORAGE_PATH=/home/site/wwwroot/storage
```

### Email recovery does not send

Most likely causes:

- Invalid SMTP host or credentials
- SMTP port or encryption mismatch

## 12. Recommended Production Hardening

- Keep `APP_DEBUG=false`
- Restrict MySQL firewall access
- Use a custom domain and TLS certificate
- Store secrets only in Azure App Settings
- Back up the MySQL database
- Monitor App Service logs and failed requests

## 13. Optional Improvement

For a cleaner Azure deployment, add:

- A GitHub Actions workflow for automatic deploys
- A startup script if you want to automate folder creation
- Azure Blob Storage later if you want external file storage instead of local App Service storage

For a simpler handoff to non-technical users, use:

- [AZURE_QUICKSTART.md](C:/xampp/htdocs/san-enrique-lgu-scholarship/AZURE_QUICKSTART.md)

## 14. Summary

For this project, the main Azure deployment requirements are:

1. Deploy the PHP application to Azure App Service
2. Use Azure Database for MySQL
3. Serve the app from the `public` folder
4. Set production values through App Settings
5. Set `APP_STORAGE_PATH=/home/site/wwwroot/storage`
6. Import [`database_schema.sql`](C:/xampp/htdocs/san-enrique-lgu-scholarship/database_schema.sql)

Once those are in place, the application should run correctly on Azure.
