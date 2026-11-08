# Arowin

A React dashboard with a PHP/MySQL API for Sub Editors, Team Leaders, and Telecallers.

## Requirements

- XAMPP with PHP 8.1+ and `pdo_mysql` enabled
- MySQL
- Node.js

## Configure MySQL and create the first Sub Editor

Start **MySQL** from the XAMPP Control Panel first. Then run from the repository root in PowerShell (use XAMPP's PHP so the MySQL driver is loaded):

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "3306"
$env:DB_NAME = "arowin_db"
$env:DB_USER = "root"
$env:DB_PASSWORD = ""
$env:AROWIN_INITIAL_ADMIN_EMAIL = "admin@arowin.com"
$env:AROWIN_INITIAL_ADMIN_PASSWORD = "choose-a-password"
$env:AROWIN_INITIAL_ADMIN_NAME = "Sub Editor Admin"
C:\xampp\php\php.exe backend\api\setup.php
```

Set the same `DB_*` values in the terminal that runs the backend. The setup script creates the tables and seeds the first Sub Editor account; it also adds missing employee profile columns to an existing database without deleting its data.

## Run the application

Start the backend from the repository root:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t backend\api backend\api\index.php
```

In another terminal:

```powershell
npm --prefix frontend install
npm --prefix frontend run dev
```

The frontend connects to port `8000` on the same host by default. Set `VITE_API_URL` to override it.

Keep the PHP API terminal open while using the app. If sign-in says it cannot reach the API, verify MySQL is running in XAMPP and start the PHP command above.

## Employee account flow

Sign in as the provisioned Sub Editor and create Team Leader or Telecaller IDs in the dashboard. Account credentials and employee details are stored in MySQL, and passwords are hashed. The app emails a one-time credential-download link from `AROWIN <arowintradin@gmail.com>`; the password is not included in the email itself. Configure the Gmail App Password and encryption key in the backend environment before creating accounts. Recipients can then download their credentials and sign in to their role-specific dashboard.

Use **Target Setup → Team Assignment** to assign active Telecallers to Team Leaders, see each leader’s assigned-member count, and view their team. Use **Target Setup → Sales Target Setup** to save monthly USDT targets for each Team Leader and review the last 12 months of target history. Apply new database tables after pulling updates with the schema-only setup command in the backend README.

Team Leaders and Telecallers can apply for leave from **Leave** in their dashboard navigation and view request status there. Sub Editors can review all requests from **Leave Requests** in their dashboard navigation and approve or reject pending requests. Apply the additive schema update after pulling this feature.

Team Leaders and Telecallers can submit platform activity screenshots from **Daily Activities**. Each submission is timestamped automatically and may include multiple screenshots; Sub Editors can review the private uploads and verify each submission. Apply the additive schema update after pulling this feature.

Every account receives a unique, permanent six-digit Employee ID when created. Existing accounts receive an ID the next time the database setup script runs; the ID is shown in employee records and credential downloads.

Set each employee’s joining date when configuring their salary in **Budget → Salaries**.

Run the backend setup script with `--schema-only` after pulling schema updates to add new tables or fields to an existing database without reseeding or changing accounts.

See [backend setup, SMTP configuration, and API details](backend/README.md).
