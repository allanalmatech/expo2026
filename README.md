# Freshers Expo Stall Management Portal

Core PHP and MySQL web portal for managing Freshers Expo 2026 / Bazaar stall applications at Mbarara University of Science and Technology.

The system is designed for normal shared hosting and cPanel deployment. It uses plain PHP, MySQL, JavaScript, AJAX, HTML and CSS only. It does not use Laravel, React, Vue, Angular, Bootstrap, Tailwind or Composer packages.

## Main Capabilities

- Applicant account creation from existing Google Form responses.
- Email-first and phone-second matching against imported form responses.
- Uganda phone normalization, for example `0771234567`, `771234567`, `+256771234567` and `256771234567` all become `256771234567`.
- Applicant dashboard for application status, payment status, compliance status, messages and stall allocation.
- Admin dashboard with applicant, payment, approval and stall metrics.
- Manual CSV import from Google Forms / Google Sheets.
- Automatic Google Sheet sync using a public or published CSV feed.
- Token-protected cron endpoint for scheduled sheet sync.
- Payment proof uploads and admin verification.
- Direct messages and bulk announcements.
- Stall creation, allocation and release.
- Tent simulation for MUST Pitch planning, pricing, revenue and profit.
- U-shaped venue layout planning with stage frontage and visitor flow assumptions.
- CSV exports for applicants, payments and stalls.
- Secure sessions, CSRF protection, password hashing and PDO prepared statements.

## Requirements

- PHP 8.0 or newer.
- MySQL or MariaDB.
- PHP PDO MySQL extension enabled.
- PHP cURL enabled for Google Sheet sync, or `allow_url_fopen` enabled as a fallback.
- Apache, XAMPP, cPanel or similar shared hosting.
- Writable `uploads/` folder.

## Folder Structure

```text
assets/
  css/
  js/
admin/
ajax/
applicant/
config/
cron/
includes/
public/
sql/
uploads/
  compliance/
  imports/
  payments/
```

Important files:

- `config/database.php` contains database connection settings.
- `config/app.php` contains app-level constants such as `APP_URL`.
- `sql/database.sql` contains the database schema and demo seed data.
- `public/index.php` is the public landing page.
- `public/login.php` is the main login page.
- `admin/sync-google-sheet.php` manages automatic Google Sheet sync.
- `cron/sync-google-sheet.php` is the cron-safe sync endpoint.
- `admin/layout-designer.php` manages the drag-and-drop venue layout designer.
- `admin/pricing.php` manages stall pricing rules and individual discounts.
- `sql/migrations/add_layout_designer.sql` can be run separately to add only the layout designer tables.

Root shortcuts are also provided:

- `/index.php`
- `/login.php`
- `/create-account.php`
- `/logout.php`

## Installation On XAMPP

1. Place the project folder in `C:\xampp\htdocs\expo2026` or your preferred htdocs directory.
2. Start Apache and MySQL from XAMPP Control Panel.
3. Open phpMyAdmin.
4. Create a database with any name, for example `freshers_expo_2026`.
5. Select that database.
6. Import `sql/database.sql`.
7. Edit `config/database.php`.
8. Set `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASS`.
9. Visit `http://localhost/expo2026/login.php`.

Example local database config:

```php
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'freshers_expo_2026');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
```

## Installation On cPanel / Shared Hosting

1. Create a MySQL database in cPanel.
2. Create a MySQL user and assign it to the database with all required privileges.
3. Open phpMyAdmin from cPanel.
4. Select the new database.
5. Import `sql/database.sql`.
6. Upload all project files to `public_html`, a subfolder, or an addon domain folder.
7. Edit `config/database.php` with the cPanel database credentials.
8. Ensure `uploads/`, `uploads/payments/`, `uploads/compliance/` and `uploads/imports/` are writable.
9. Visit `/login.php` on your domain.

Example cPanel database config:

```php
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'cpaneluser_expo2026');
defined('DB_USER') || define('DB_USER', 'cpaneluser_expo');
defined('DB_PASS') || define('DB_PASS', 'your_password_here');
```

If the project is installed in a subfolder and links are not resolving correctly, set `APP_URL` in `config/app.php`:

```php
defined('APP_URL') || define('APP_URL', 'https://example.com/expo2026');
```

## Database Import Notes

`sql/database.sql` is importable into any selected database name.

It does not contain a hardcoded `CREATE DATABASE` statement.

It does not contain a hardcoded `USE freshers_expo_2026` statement.

Always select the target database first before importing the SQL file.

The SQL file creates all required tables and seeds demo data so the portal is not empty after installation.

## Default Logins

Change or delete these accounts immediately after installation.

Admin login:

```text
Email: admin@expo2026.test
Password: Admin@2026
```

Demo applicant login:

```text
Email: applicant@expo2026.test
Password: Applicant@2026
```

Login URL:

```text
/login.php
```

The SQL also seeds demo applicants, applications, payments, messages, compliance documents, stalls, fixed tent rules, U-shaped layout zones and pricing rules.

If you do not want demo data, remove the demo seed section at the bottom of `sql/database.sql` before importing, or delete the demo records after creating your own admin.

If you remove the seeded admin account, create the first admin at:

```text
/admin/create-first-admin.php
```

That page locks itself once an admin account exists.

## Public Pages

- `/index.php` or `/public/index.php` shows the landing page.
- `/create-account.php` or `/public/create-account.php` lets applicants create portal accounts after their Google Form response exists in the system.
- `/login.php` or `/public/login.php` logs in applicants and admins.
- `/logout.php` logs out the current user.

## Applicant Portal

Applicant pages are under `/applicant/`.

- `dashboard.php` shows application, payment, compliance, message and stall summary.
- `profile.php` shows imported Google Form details.
- `payment.php` allows payment proof upload or replacement.
- `messages.php` shows direct messages and announcements.
- `compliance.php` shows rules and compliance document actions.
- `stall.php` shows assigned stall number and location.

Applicant data imported from Google Forms is view-only by default. Corrections are handled by admins.

## Admin Portal

Admin pages are under `/admin/`.

- `dashboard.php` shows summary metrics.
- `applications.php` provides search, filters and status updates.
- `application-view.php` shows full applicant review, correction and stall assignment.
- `payments.php` verifies or rejects payment proof.
- `pricing.php` sets pricing by business nature and student status, plus individual discounts.
- `messages.php` sends direct messages and bulk announcements.
- `stalls.php` creates, edits, assigns and releases stalls.
- `layout-designer.php` visually edits the venue layout and syncs tent groups into stall records.
- `reports.php` exports applicant, payment and stall CSV reports, plus an XLS of paid customers with generated receipts.
- `settings.php` updates public portal settings.
- `import-responses.php` imports Google Form CSV files manually.
- `sync-google-sheet.php` connects and syncs a Google Sheet automatically.

## Applicant Account Creation Flow

1. Applicant fills the Google Form.
2. Admin imports or syncs Google Form responses into the portal.
3. Applicant opens `/create-account.php`.
4. Applicant enters the same email address or phone number used in the Google Form.
5. Applicant enters a password and confirmation.
6. The system searches `form_responses` by lowercase email first.
7. If no email match is found, the system normalizes the phone number and searches by normalized phone.
8. If one response is found, a portal user and linked application record are created.
9. If no response is found, the applicant is told to fill the Google Form or use the same contact details.
10. If more than one response matches the same phone, the applicant is told to contact the committee.
11. If an account already exists, the applicant is told to log in.

## Manual CSV Import

Use this when you have downloaded a Google Sheets CSV file manually.

1. Log in as admin.
2. Open `Admin > Import CSV` or `/admin/import-responses.php`.
3. Upload the CSV exported from Google Sheets.
4. Map CSV columns to portal fields.
5. Click `Import Responses`.

The importer automatically normalizes phone numbers and avoids duplicates using email or normalized phone.

Existing records are updated when the same email or phone already exists.

## Automatic Google Sheet Sync

The system can automatically read the response Sheet from Google Docs / Google Sheets without manually uploading CSV files.

This implementation uses Google Sheets CSV export links instead of the full Google API. That keeps the system lightweight and compatible with shared hosting.

### Prepare The Google Sheet

1. Open your Google Form.
2. Go to `Responses`.
3. Link responses to a Google Sheet.
4. Open the response Sheet.
5. Ensure the first row contains column headers.
6. Share the Sheet as `Anyone with the link can view`, or use `File > Share > Publish to web` and choose CSV.

### Connect The Sheet

1. Log in as admin.
2. Open `Admin > Sheet Sync` or `/admin/sync-google-sheet.php`.
3. Paste one of these values:

- A normal Google Sheet share URL.
- A published CSV URL.
- A Google Sheet ID.
- A published `/pubhtml` URL.

4. Enter the worksheet `gid` if it is not `0`.
5. Click `Sync Now`.

The system converts normal Google Sheet links into CSV export URLs automatically.

### Automatic Cron Sync

1. Open `Admin > Sheet Sync`.
2. Enable automatic cron sync.
3. Save settings.
4. Copy the generated cron URL.
5. Add it to cPanel Cron Jobs.

Example cron command:

```bash
curl -fsS "https://example.com/cron/sync-google-sheet.php?token=YOUR_TOKEN" >/dev/null
```

Recommended cron interval:

```text
Every 5 to 15 minutes
```

The cron endpoint is token-protected. Regenerating the token invalidates old cron URLs.

### Sheet Sync Requirements

- PHP cURL should be enabled.
- If cURL is not enabled, `allow_url_fopen` must be enabled.
- The Google Sheet must be viewable by the server.
- If Google returns an HTML page instead of CSV, publish the Sheet as CSV or share it as anyone-with-link viewable.

## CSV Column Matching

The importer tries to auto-map common Google Form column names.

Supported portal fields include:

- Submitted At
- Full Name
- Email
- Phone
- Student Status
- Institution
- Program
- Year of Study
- Business Name
- Business Nature
- Business Description
- Applicant Type
- Stall Type
- Number of Stalls
- Electricity Needed
- Equipment Needed
- Table and Chair Request
- Branding Space Needed
- Preferred Payment Method
- Proof of Payment URL
- Rules Agreement

The column names do not need to be exact. The system recognizes common aliases such as `Timestamp`, `Email Address`, `Phone Number`, `Business Name`, `Stall Size` and similar labels.

## Tent And Stall Planning Model

The portal uses the saved Layout Designer plan as the source for tent and stall allocation.

The exhibition layout assumes a large U shape:

- The entertainment stage is at the top center of the U.
- Visitors enter along one side of the U.
- Visitors pass across the stage frontage.
- Visitors continue down the opposite side.
- Visitors exit after seeing most exhibitors.

### 50-Seater Tent Rules

- Tent type: Single canopy.
- Minimum: 1 stall.
- Recommended average: 4 stalls.
- Absolute maximum: 5 stalls.
- Never allocate more than 5 stalls in one 50-seater tent.

Supported arrangements:

- Exclusive tent: 1 stall.
- Large stalls: 2 stalls.
- Standard stalls: 4 stalls.
- Small stalls: 5 stalls.

### 100-Seater Tent Rules

- Tent type: Double canopy.
- Minimum: 1 stall.
- Recommended average: 8 stalls.
- Absolute maximum: 10 stalls.
- Never allocate more than 10 stalls in one 100-seater tent.

Supported arrangements:

- Exclusive corporate pavilion: 1 stall.
- Mega premium stalls: 2 stalls.
- Large stalls: 4 stalls.
- Medium stalls: 6 stalls.
- Standard stalls: 8 stalls.
- Small stalls: 10 stalls.

### Simulation Categories

The simulator compares arrangements for:

- Student and startup tents.
- SME and retail tents.
- NGO and government tents.
- Corporate tents.
- Sponsor-exclusive tents.
- Food and beverage tents.

Each simulation calculates:

- Number of stalls.
- Approximate stall size.
- Internal walkways.
- Exhibitor category.
- Price per stall.
- Total revenue per tent.
- Tent hire and setup cost.
- Expected profit per tent.

### Pricing Model

The pricing model includes:

- Small stall price.
- Standard stall price.
- Large stall price.
- Premium stall price.
- Half-tent price.
- Exclusive 50-seater tent price.
- Exclusive 100-seater tent price.
- Sponsor pavilion price.

Prices are adjusted by:

- Number of exhibitors sharing the tent.
- Stall size.
- Tent location.
- Visitor traffic.
- Corner exposure.
- Electricity access.
- Branding space.
- Furniture provided.
- Proximity to main entrance.
- Proximity to stage.
- Exclusive pavilion use.

## Venue Layout Designer

Open:

```text
/admin/layout-designer.php
```

The layout designer lets admins visually place and edit venue elements on a grid-based pitch canvas.

Designer features:

- Drag palette items onto the canvas.
- Move elements with pointer dragging.
- Select elements and edit properties from the right panel.
- Rotate elements in 90-degree steps.
- Resize tents using constrained preset footprints.
- Toggle snap-to-grid.
- Toggle the U-shaped reference guide.
- Zoom in, zoom out and fit to screen.
- Save versioned layouts by name.
- Mark one layout as active.
- Export the visible layout as PNG.
- Export the synced stall list as CSV.

Supported elements:

- 50-seater tent.
- 100-seater tent.
- Stage.
- Registration Desk.
- Waste Collection Point.
- Mobile Toilet Male.
- Mobile Toilet Female.
- Walkway marker.
- Custom label.

When an active layout is saved, tent elements with a tent group code are synced into the `stalls` table. The system creates missing stall rows, updates tent and zone metadata, and blocks destructive changes if assigned stalls still depend on a deleted or reduced tent group.

The designer validates tent stall counts against `tent_arrangement_rules`, so a 50-seater cannot exceed its allowed arrangements and a 100-seater cannot exceed its allowed arrangements.

If the layout designer tables are missing, the module creates `venue_layouts` and `layout_elements` automatically at runtime and seeds the default U-shaped MUST Pitch layout. You can also run `sql/migrations/add_layout_designer.sql` manually if preferred.

## Stall Management

Open:

```text
/admin/stalls.php
```

Stalls can be assigned to tent groups using fields such as:

- Stall number.
- Location.
- Stall type.
- Tent group code, for example `TENT-A`.
- Tent type, for example `50` or `100`.
- Arrangement key.
- U-layout zone.
- Planned price per stall.

The system enforces arrangement capacity when creating or editing stalls in a tent group.

For example, a 50-seater tent with a 5-stall arrangement cannot receive a sixth stall in the same tent group.

## Payment Uploads

Applicants upload payment proof from:

```text
/applicant/payment.php
```

Admins verify payment proof from:

```text
/admin/payments.php
```

Allowed file extensions:

- PDF
- JPG
- JPEG
- PNG

Maximum upload size:

```text
5 MB
```

Uploaded files are renamed using random names and stored in `uploads/payments/`.

## Compliance Documents

Applicants confirm compliance or upload signed documents from:

```text
/applicant/compliance.php
```

Compliance uploads are stored in:

```text
uploads/compliance/
```

Final stall allocation remains subject to approval and compliance review.

## Messaging

Admins can send:

- Direct messages to one applicant.
- Bulk announcements to all applicants.
- Announcements to selected groups such as approved applicants, unpaid applicants, students, non-students, food vendors and pending applicants.

Applicants see direct messages and announcements in:

```text
/applicant/messages.php
```

## Reports

Open:

```text
/admin/reports.php
```

Available CSV exports:

- Applicants report.
- Payment report.
- Stall allocation report.

The stall CSV includes tent group, tent type, arrangement, U-layout zone and linked applicant details.

## Security Features

- Passwords are stored with `password_hash()`.
- Login uses `password_verify()`.
- Database queries use PDO prepared statements.
- Important forms use CSRF tokens.
- Sessions use HTTP-only cookies and SameSite=Lax.
- Admin pages require admin login.
- Applicant pages require applicant login.
- Upload types are restricted.
- Upload file size is limited.
- Uploaded files are renamed randomly.
- `uploads/.htaccess` blocks executable script files on Apache.
- Database errors are logged but not exposed directly to users.

## Upload Folder Permissions

The following folders must be writable by PHP:

```text
uploads/
uploads/payments/
uploads/compliance/
uploads/imports/
```

Recommended permissions on many shared hosts:

```text
755 for folders
644 for files
```

Some hosts may require `775` for upload folders depending on server ownership.

Avoid `777` unless your host specifically requires it and there is no safer option.

## Production Checklist

Before using the system live:

- Change the default admin password.
- Delete demo applicant accounts if not needed.
- Update `config/database.php` with production credentials.
- Set `APP_URL` in `config/app.php` if auto-detected links are wrong.
- Set the real Google Form link in `Admin > Settings`.
- Configure Google Sheet sync in `Admin > Sheet Sync`.
- Confirm the cron sync URL works.
- Confirm upload folders are writable.
- Confirm payment uploads work.
- Confirm applicant account creation works with one real form response.
- Confirm admin email and phone settings are correct.
- Review stall rules text in `Admin > Settings`.
- Remove or rotate default demo credentials.

## Common URLs

```text
/login.php
/create-account.php
/admin/dashboard.php
/admin/applications.php
/admin/import-responses.php
/admin/sync-google-sheet.php
/admin/layout-designer.php
/admin/pricing.php
/applicant/dashboard.php
```

## Troubleshooting

### Login Does Not Work

- Confirm the SQL file was imported.
- Confirm `config/database.php` has the correct database name, username and password.
- Confirm the default admin exists in the `users` table.
- Try the seeded admin login: `admin@expo2026.test` / `Admin@2026`.

### Pages Show Database Errors

- Start MySQL if using XAMPP.
- Confirm the database exists.
- Confirm the database user has privileges.
- Confirm `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASS` are correct.

### Google Sheet Sync Returns HTML Instead Of CSV

- Share the Sheet as `Anyone with the link can view`.
- Or use `File > Share > Publish to web` and choose CSV.
- Confirm the URL is from Google Sheets, not Google Forms.
- Confirm the `gid` is correct.

### Google Sheet Sync Cannot Fetch URL

- Enable PHP cURL.
- Or enable `allow_url_fopen`.
- Confirm your host allows outgoing HTTPS requests.
- Test with `Sync Now` before setting cron.

### Applicants Cannot Create Accounts

- Confirm their response exists in `form_responses`.
- Confirm they are using the same email or phone used in the Google Form.
- Confirm the phone number normalized correctly.
- Confirm an account does not already exist for that response.
- If a phone number matches multiple responses, correct duplicate records in admin.

### Uploaded Files Fail

- Confirm `uploads/` folders are writable.
- Confirm the file extension is PDF, JPG, JPEG or PNG.
- Confirm the file is under 5 MB.
- Confirm PHP upload limits allow the file size.

### CSS Or Layout Looks Wrong

- Hard refresh the browser using `Ctrl + F5`.
- Clear cached CSS in the browser.
- Confirm `assets/css/style.css` and `assets/css/responsive.css` are loading.
- Set `APP_URL` in `config/app.php` if the project is installed in a subfolder and asset paths are wrong.

### Cron Sync Does Not Run

- Confirm automatic sync is enabled in `Admin > Sheet Sync`.
- Confirm the cron URL token matches the current token.
- Confirm the cPanel cron command uses the full absolute URL.
- Check recent logs in `Admin > Sheet Sync`.

## Developer Notes

- Keep the project lightweight and framework-free.
- Use PDO prepared statements for database work.
- Use `csrf_field()` and `require_csrf()` for important forms.
- Escape output with `h()`.
- Do not place executable code in `uploads/`.
- Use `normalize_phone()` for Uganda phone matching.
- Use `import_form_responses_from_csv_path()` for CSV-style imports.
- Use `sync_google_sheet_responses()` for Google Sheet sync.
- Use the layout designer save endpoint to sync active visual tent groups into `stalls`.

## Key Database Tables

- `form_responses`
- `users`
- `applications`
- `payment_uploads`
- `messages`
- `announcement_recipients`
- `stalls`
- `compliance_documents`
- `portal_settings`
- `sheet_sync_logs`
- `tent_capacity_rules`
- `tent_arrangement_rules`
- `venue_layout_zones`
- `venue_layouts`
- `layout_elements`

## License And Ownership

This project was built for the Freshers Expo 2026 Stall Management Portal. Review and adapt the code, branding, pricing and operational rules before live deployment.
