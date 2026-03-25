# Azure GitHub Actions Setup

This project already includes an Azure deployment workflow:

- [main_sanenriquelguscholarship.yml](C:/xampp/htdocs/san-enrique-lgu-scholarship/.github/workflows/main_sanenriquelguscholarship.yml)

It builds the PHP app, installs Composer dependencies, and deploys the app to Azure App Service when code is pushed to the `main` branch.

## What the workflow does

On every push to `main`, GitHub Actions:

1. Checks out the repository
2. Sets up PHP 8.2
3. Runs Composer install
4. Uploads the built app as an artifact
5. Signs in to Azure using GitHub secrets
6. Deploys the app to Azure Web App

## Current workflow file

Path:

- [main_sanenriquelguscholarship.yml](C:/xampp/htdocs/san-enrique-lgu-scholarship/.github/workflows/main_sanenriquelguscholarship.yml)

The workflow currently deploys to:

- App name: `sanenriquelguscholarship`
- Slot: `Production`

## Required GitHub Secrets

The workflow expects these repository secrets:

- `AZUREAPPSERVICE_CLIENTID_C28F880D73904C6D93D37A2A50F737E5`
- `AZUREAPPSERVICE_TENANTID_FC6BFC05F16047038614B3AE950732CA`
- `AZUREAPPSERVICE_SUBSCRIPTIONID_B6E91EBC0E9D454D93BC990444408414`

Add them in:

`GitHub Repository -> Settings -> Secrets and variables -> Actions`

## How to get the Azure values

These values usually come from an Azure App Service GitHub deployment setup using:

- Azure Portal Deployment Center, or
- Azure CLI with federated credentials / service principal setup

If Azure already generated this workflow for you, the secret names above should match what Azure expects.

## Recommended App Service configuration

The GitHub Actions workflow deploys code only. You still need to configure the app in Azure:

- Set application settings in Azure App Service
- Configure the MySQL database
- Make sure the app serves from `public`
- Set `APP_STORAGE_PATH=/home/site/wwwroot/storage`

See the full guide:

- [AZURE_DEPLOYMENT_GUIDE.md](C:/xampp/htdocs/san-enrique-lgu-scholarship/AZURE_DEPLOYMENT_GUIDE.md)

## How to use it

1. Push the repository to GitHub
2. Confirm the workflow file exists in the repo
3. Add the required Azure secrets in GitHub
4. Push to the `main` branch
5. Open `GitHub -> Actions` and watch the deployment run

## Notes

- The workflow now installs Composer dependencies with:
  `composer install --no-dev --prefer-dist --no-progress --optimize-autoloader`
- `.env` is ignored by Git, which is correct
- Production secrets should stay in Azure App Settings, not in the repository

## Troubleshooting

### Workflow fails during Azure login

Check:

- client ID
- tenant ID
- subscription ID
- whether the Azure identity still exists

### Workflow succeeds but the site fails

Check:

- Azure App Settings
- MySQL connectivity
- document root pointing to `public`
- writable storage path

### Workflow never runs

Check:

- the branch is `main`
- GitHub Actions is enabled for the repository
- the workflow file is committed to the repo
