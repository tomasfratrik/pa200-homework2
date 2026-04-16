# Azure PaaS Todo App

Minimal PHP todo application prepared for Homework Assignment #2, Variant A.

The app uses:

- Azure App Service for PHP web hosting
- Azure Database for MySQL Flexible Server for relational storage
- Azure Blob Storage static website hosting for static assets
- GitHub Actions for automated deployment

## Features

- Create a todo item
- Mark a todo item as done or pending
- Delete a todo item
- Store data in a managed MySQL database
- Load CSS from Azure Blob Storage when `ASSET_BASE_URL` is configured

## Project structure

- `index.php` root entrypoint for Azure App Service
- `startup.sh` and `nginx-default.conf` Azure App Service nginx/PHP startup configuration
- `public/index.php` application page
- `public/styles.css` source CSS uploaded to Azure Blob Storage by the deployment pipeline
- `src/` PHP bootstrap and database wiring
- `sql/schema.sql` database schema
- `.github/workflows/deploy.yml` CI/CD deployment workflow template

Local deployment notes, screenshots, generated ZIP packages, and other submission artifacts are intentionally not part of the public repository.

## App Service configuration

Set these environment variables in Azure App Service:

```text
APP_NAME=Azure PaaS Todo App
DB_HOST=<mysql-server-name>.mysql.database.azure.com
DB_PORT=3306
DB_NAME=todo_app
DB_USER=<mysql-user>
DB_PASSWORD=<mysql-password>
DB_CHARSET=utf8mb4
DB_SSL=true
ASSET_BASE_URL=https://<storage-account>.zXX.web.core.windows.net
```

For local testing you can also copy `config.php.example` to `config.php`.

## Database schema

Import `sql/init_azure_mysql.sql` into the Azure Database for MySQL database.

## Deployment

The GitHub Actions workflow deploys the PHP application to Azure App Service and uploads `public/styles.css` to Azure Blob Storage.
