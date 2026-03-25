# Azure Quick Start

This is the short version of the Azure deployment process for the **San Enrique LGU Scholarship System**.

## What you need

- Azure account
- This project uploaded to GitHub or ready as a zip file
- MySQL database schema file: `database_schema.sql`
- SMTP credentials for email sending
- Optional TextBee credentials for SMS

## Fastest Deployment Path

### 1. Create Azure resources

Create these in Azure Portal:

- Resource Group
- Azure Database for MySQL Flexible Server
- App Service Plan
- Web App using `PHP 8.2` on `Linux`

### 2. Create the database

- Create a database named `lgu_san_enrique_scholarship`
- Import [`database_schema.sql`](C:/xampp/htdocs/san-enrique-lgu-scholarship/database_schema.sql)

### 3. Deploy the code

Choose one:

- **GitHub Actions**
  Use the existing workflow in [main_sanenriquelguscholarship.yml](C:/xampp/htdocs/san-enrique-lgu-scholarship/.github/workflows/main_sanenriquelguscholarship.yml)
- **Zip Deploy**
  Zip the project and upload it through Azure Portal

### 4. Set App Settings

In Azure Portal, open:

`Web App -> Configuration -> Application settings`

Add:

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

MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-username>
MAIL_PASSWORD=<smtp-password>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<from-email>
MAIL_FROM_NAME=San Enrique LGU Scholarship
```

### 5. Make sure Azure serves the `public` folder

This app uses [`public/index.php`](C:/xampp/htdocs/san-enrique-lgu-scholarship/public/index.php) as its entry point, so the site must serve the `public` folder.

### 6. Verify deployment

Test these after publish:

- Homepage loads
- Login works
- Registration works
- Database records save
- Uploads work
- Email recovery works
- PDF generation works

## If something breaks

- `404` or blank page:
  Azure is probably not serving the `public` folder
- database error:
  check MySQL hostname, firewall, username, password, and SSL
- upload error:
  check `APP_STORAGE_PATH`
- email error:
  check SMTP settings

## Full Guides

- Main deployment guide: [AZURE_DEPLOYMENT_GUIDE.md](C:/xampp/htdocs/san-enrique-lgu-scholarship/AZURE_DEPLOYMENT_GUIDE.md)
- GitHub Actions setup: [AZURE_GITHUB_ACTIONS_SETUP.md](C:/xampp/htdocs/san-enrique-lgu-scholarship/AZURE_GITHUB_ACTIONS_SETUP.md)
