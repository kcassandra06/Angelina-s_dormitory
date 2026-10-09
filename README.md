# Angelina’s Dormitory

A PHP 8.2 application built from `Angelinas_Dormitory_Complete_System_Flow_and_Database.docx`, now connected to **XAMPP MySQL/MariaDB** through PDO MySQL. The live database is **angelinas_dormitory**. All monetary amounts are stored as integer centavos.

## Open the system

Start **Apache** and **MySQL** in the XAMPP Control Panel, then open **http://localhost/dormitory/**. The live database has already been created and the earlier records migrated.

Alternatively, with XAMPP MySQL running, run `powershell -ExecutionPolicy Bypass -File .\start.ps1` in this folder and open **http://localhost:8080/**. Keep the terminal running while using the system. A preview server is also running at that address for this session.

Connection settings are in **config.database.php**: host `127.0.0.1`, port `3306`, database `angelinas_dormitory`, user `root`, empty password for this local XAMPP installation. Update the file if your MySQL credentials change. View tables through **http://localhost/phpmyadmin/** when Apache is running.

For a new installation, run `C:\xampp\php\php.exe database\setup.php`. It creates the database and tables without replacing existing records. `database/schema.sql` is the MySQL table structure and `database/angelinas_dormitory.sql` is an importable snapshot of the migrated database. Import a snapshot only into an empty database selected in phpMyAdmin.

Existing usernames, password hashes, and histories were preserved. The credentials below apply to fresh installations; if you already changed a password, continue using that password.

| Role | Username | Initial password |
| --- | --- | --- |
| Administrator | admin | Admin@12345 |
| Payment staff | staff | Staff@12345 |
| Tenant demonstration account | tenant | Tenant@12345 |

Each account must choose a new password on first sign-in. The initial data includes four rooms and one tenant clearly named **Maria Santos (Demo)**, with a sample bill and deposit. Real tenants receive their own accounts through **Tenants → Register a new tenant**. Move out the demo tenant to free its bed; its records remain as demonstration history.

## Included features

New tenants can create their own accounts from **Login → Create tenant account**. Enter name, email, contact number, username, password, and confirmation. The system creates a linked tenant account immediately and returns to sign-in with the Tenant role selected. Self-registration always creates the Tenant role and does not require a temporary-password change. Existing tenant emails and usernames cannot be reused to claim another tenant’s records.

Self-registration does not occupy a bed. The account shows **Not assigned yet** until Admin opens **Residents → Manage → Assign room & bed** and records move-in. The tenant can sign in immediately and can see only their own account. Admin-created accounts remain available under **User accounts → Create new tenant account** and use temporary passwords changed on first login.

For a resident already recorded without a login, Admin uses **User accounts → Create login for an existing tenant**. Existing accounts use **Reset password** or **Enable access** instead. Admin and Staff accounts cannot be created through public registration.

- One role-based login with individual password hashes, first-login password changes, login throttling, server-side access checks, and CSRF protection.
- Admin: tenant registration and editing, room and bed creation, room maintenance status, rent editing, bed transfers, assignment history, and move-out without deleting records.
- Monthly bills for rent, water, and electricity; cutoff and due dates; separate discounts and signed adjustments with reasons; printable statements and corrections.
- Previous outstanding balances carry into the newest bill. Older carried statements cannot receive additional payments. The overview counts outstanding balances only on current statements.
- Multiple partial payments, GCash references and personal payments, payer/date/receiver records, duplicate submission and reference protection, printable receipts, and paid/partial/unpaid/overdue statuses.
- Security deposits kept separate from rent, with move-out refunds and recorded deductions.
- Browser camera person counting and explicitly labeled manual verification. Scan records contain detected count, registered occupancy, and capacity snapshots.
- Queued billing notifications, payment receipts, overdue reminders, configurable encrypted SMTP delivery, retryable failures, and audit logs.
- Tenant accounts can access only their own statements, payments, receipts, and deposits. Staff manage payments and view the billing information needed to receive them.
- Batch monthly billing with room rent filled automatically, per-resident utility charges, selectable residents, estimated totals, duplicate-period skipping, and atomic transactions.
- A live calculator for individual statements, automatic room rent, and cutoff/due date defaults that cap dates at the end of short months.
- Financial reports with date filters, GCash and personal collection summaries, operating expenses, current balance aging, and protected CSV exports.
- A resident account ledger that excludes carried balances from new charges, avoiding duplicate receivables.
- Admin-only payment voiding with a reason, retained receipts, balance restoration, correction notification, and audit trail. Payments on carried statements require a correction on the current bill instead.
- New receipts store the balance at payment time, alongside the current statement balance. Migrated old receipts show the current balance because they did not originally store a snapshot.
- Tenant maintenance requests with saved room information, priority, progress, and admin responses.
- A shared announcement board with expiration and archival, plus notice previews on the dashboard.
- Dormitory name, address, contact, and default billing dates in Settings. Name and contact appear on receipts.
- A redesigned residence dashboard, consistent SVG navigation, searchable/paginated tables, statement-status filters, mobile navigation, and print layouts.

The collections-less-expenses figure is a cash summary for recorded rent/utility collections and operating costs, not a formal accounting profit calculation. Deposit receipts/refunds are tracked separately. Aging groups balances by the current bill’s due date, including carried balances. No automatic late fees are charged.

## Email configuration

1. Copy `config.example.php` to `config.local.php` in this folder.
2. Fill in the SMTP host, port, username, password, sender, and encryption (`tls` for STARTTLS or `ssl` for implicit TLS).
3. Enable `extension=openssl` in `C:\xampp\php\php.ini` and restart Apache. The provided XAMPP installation currently has this extension disabled.
4. From **Notifications**, choose **Send queued / retry failed**. Until settings are supplied, messages stay queued; they are never marked sent without SMTP acceptance.

For unattended delivery and overdue reminders, use Windows Task Scheduler to run `C:\xampp\php\php.exe` with argument `C:\xampp\htdocs\dormitory\app\jobs.php` daily. The worker sends up to 20 queued or failed messages per run. Run it more often if needed. Daily overdue reminders are deduplicated by bill and date. The application does not process or verify GCash transfers; staff record payments they have verified.

## Camera counting

Use localhost or HTTPS, allow browser camera permission, and keep internet access available for the model download. Counts use [TensorFlow’s COCO-SSD browser model](https://github.com/tensorflow/tfjs-models/tree/master/coco-ssd), filtering person detections at a minimum 55% confidence. Video stays in the browser; the database stores counts and notes, not faces or images. Counts are estimates and should be checked before saving. Manual counts are labeled separately. Camera/model loading needs to be verified with your physical webcam and network.

## Database and backup

The connected database is **angelinas_dormitory** in XAMPP MySQL. Its InnoDB table definitions are in `database/schema.sql`. Tables cover users, tenants, rooms, beds, assignments, bills, adjustments, utility charges, payments, deposits, occupancy, notifications, audit logs, settings, login attempts, expenses, maintenance requests, and announcements. Foreign keys, unique active bed/tenant columns, and an InnoDB transaction lock protect relationships, assignments, and financial changes. The conditional active-assignment indexes follow [MariaDB’s generated-column mechanism](https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/generated-columns).

Use phpMyAdmin → `angelinas_dormitory` → **Export**, or `mysqldump --single-transaction`, to make database backups. Store exports privately because they include personal records and account password hashes. The old `storage/dormitory.sqlite` file remains untouched as a migration backup; the app does not use it. Apache `.htaccess` rules and the development-server router block browser access to database exports, sessions, config, and internal code. Do not start the development server without `router.php`.

## Verification

With MySQL running, use `python tests\integration.py`. It creates a uniquely named `angelinas_test_*` MySQL database, starts a temporary server at localhost:8081, and removes the test database afterward. It never modifies the live database. Checks cover core flows plus batch billing, voided payments, immutable receipt balances, reports, CSV formula protection, maintenance, announcements, settings, ledger privacy, and MySQL constraints.

Use `node tests\visual.js` for native headless Edge checks. This also uses a disposable MySQL database. It verifies desktop/mobile rendering, the live bill calculator, batch selection, mobile navigation, and JavaScript errors. Screenshots are saved under `storage/previews/`. Ports 8081, 8082, and 9223 must be free during these tests.

Email delivery requires your SMTP credentials. Camera capture requires your browser permission and physical camera. These external integrations are implemented but cannot be confirmed end to end without those inputs.

## Tenant payment journey

Tenants register on the login page, then Admin assigns a room and creates their statement. My home shows each next step. **Make a payment** explains GCash and office payments. Under **Settings ? Tenant payment instructions**, Admin enters the real account number, office hours, and uploads the official GCash payment QR (validated JPG/PNG/WebP up to 2 MB). Tenants scan it using GCash, or save it and use GCash?s gallery option. The app does not initiate transfers or generate a fabricated receiving QR.

After transferring, tenants submit the amount, reference, date, payer, and optional private proof image. Pending submissions do not reduce balances. Admin/Staff checks the receiving account in **Payments ? Incoming tenant payments**, then approves to update the balance and issue a receipt, or declines with a reason. Duplicate references, excessive amounts, repeated approvals, and carrying bills with pending payments are blocked. Tenants can track submissions, cancel pending records, view receipts, read their inbox, update contact details, and send inquiries.

The MySQL schema now contains 20 tables, including payment_submissions and tenant_inquiries. Back up storage/payment-proofs alongside the database to retain payment proofs and the official QR. Integration and browser tests also cover the tenant payment journey and private proof access.

## Connected billing and payment flow

All roles use the same MySQL statements and payments. Admin assigns rooms and generates reviewed monthly statements. Tenants see their current balance and a prepared payable amount (less submissions already pending). Partial payments remain available. A submitted GCash reference enters the shared Admin/Staff verification queue; approval creates one confirmed payment and receipt, updates the statement balance, and queues a tenant confirmation. Office payments use the same payment records. Pending submissions remain separate from collections and charts.

Due-date notices appear in the tenant inbox at seven, three, and one day before payment is due, on the due date, and daily while overdue. Stages are deduplicated and fully paid/carried statements are excluded. Notices are prepared when signed-in pages are opened. For unattended generation and email delivery, schedule app/jobs.php daily in Windows Task Scheduler as described above; email requires SMTP configuration.

Admin financial reports now show monthly collections, payment methods, current statement statuses, and outstanding balances by age. Collections use the selected dates and exclude voided payments and deposits. Status/aging charts show live current statements, independent of that date filter. Every interface label uses English.

## Payment workspace, receipts, and notifications

The tenant guide is replaced by **Make a payment**, reached from the dashboard or Payments & receipts. It includes camera and QR-image scanning using the bundled [jsQR decoder](https://github.com/cozmo/jsQR), so decoding needs no CDN. Camera access needs localhost or HTTPS and browser permission; QR images also work. Scanning reads a QR and never executes its contents or initiates a transfer. The tenant completes the transfer in GCash and submits the reference for staff review.

When payment staff approves verified funds, the system generates the confirmed receipt once, updates every role?s shared balance, and sends a tenant inbox notification linking directly to the receipt. The latest receipt is also shown on the tenant dashboard. The receipt can be printed or saved as PDF through the browser. Email confirmations remain queued until SMTP is configured.

All three roles have a notification bell with unread counts refreshed every 30 seconds. Tenants receive their own billing/reminder/receipt/reply notifications; Admin and Staff receive relevant billing/payment activity. Staff inquiry notifications are restricted to billing/payment categories. Marking notifications as read applies only to that user and the displayed notification range.

Admin reports use a collections line chart, payment-method and statement-status doughnut charts, and balance-aging bars, with exact totals available. Redundant tenant ledger and payment-guide navigation and the unrelated occupancy sidebar entry are hidden; historical records and direct authorized routes remain preserved.

Tests additionally cover receipt delivery links, notification role privacy and read state, QR-image decoding, a simulated camera stream, permission-denial handling, and camera track cleanup. Physical camera quality and actual GCash transfers must be checked on the device and official receiving account.

## Resident assignment and professional documents

Admin **Residents** has a dedicated assignment form and profile pages for transfers, contact changes, and move-outs. **Rooms & beds** shows clickable beds: an available bed selects it in the assignment form; an occupied bed links to its resident. Maintenance rooms cannot receive assignments, and the database still prevents duplicate occupancy.

Creating an individual statement opens its **Statement of Account** immediately. A4 print/PDF layouts include the dormitory header, statement number, resident and historical room assignment, charges/corrections, payments applied, due date, and remaining balance. Receipts use the same document style and retain the payment-time balance. Click **Print / save PDF** and select the browser?s Save as PDF option. Set the real dormitory address/contact in Settings for the document header.

Operating expenses now include payee, invoice reference, payment method, duplicate-submission protection, date/category filters, and audited voiding. Voided entries remain visible but are excluded from reports and CSV exports. Run database/setup.php to apply the non-destructive expense-column migration on another installation. The supplied SQL export already contains the new columns. Financial reports separate confirmed collections, operating expenses, net operating cash flow, current receivables, and expense categories; they can be printed.

The QR scanner supports front/rear camera preference, default-camera fallback, cancellation, clear permission/busy-camera errors, payment QR recipient/amount fields, and larger QR-image decoding. A phone opened through an HTTP LAN address must use HTTPS for camera access or choose a QR image instead. Samples generated from the disposable test database are in storage/previews/sample-statement.pdf and sample-receipt.pdf.

## Simplified payment flow and tenant home

Tenant My Home now shows the current itemized bill, due date/countdown, one contextual next step, room rate, confirmed monthly payments, held deposit, latest receipt, recent notifications, and room support. The old account checklist and repeated general instructions are removed.

Make a payment starts with a choice: **GCash account number**, **QR payment**, or **Office cash payment**. Only QR payment displays the scanner. Office payments show office details and no digital submission form; staff records the payment after receiving it. Changing methods stops any active camera. The QR video has its own visible layout rather than the generic absolutely positioned occupancy-video style. Tests verify the preview has a real visible height.

Admin and Staff have one **Payments** page: incoming submissions at the top, confirmed payment history, and a collapsed office/verified-transfer form. Confirmation creates the receipt and updates the shared balance. The separate Payment verification navigation/page is removed; old bookmarks redirect to Payments. Successful tenant submissions open Payments & receipts to show their pending status.

## Tenant My Home dashboard

My Home follows a room-and-billing layout: welcome card with room, bed, capacity-based Single/Shared classification and move-in date; current itemized statement; contextual next step; room details; three recent confirmed transactions linked to receipts; and active unexpired announcements. All figures and notices come from the connected database. Example names, amounts and announcement dates from the design are not inserted as real records.

Paid bills show their original total and Paid status. Unpaid/partial bills show the remaining amount marked Outstanding, with Pay Now unless pending submissions already cover it. Pending confirmations stay separate from actual payments. Room details link to the tenant?s own profile. Recent transactions are sorted by payment date, with voided payments excluded. Each Paid label refers to the confirmed transaction; a partial payment can still leave a bill balance.


Admin workspace: the main sidebar contains twelve sections. Monthly billing is inside Billing Statements; Expenses and Account Ledger are tabs inside Financial Reports; the email delivery queue is under Settings. The top-right bell is the admin/staff notification entry point. Residents handles accommodation and resident registration, while User Accounts handles login access, password resets, and staff credentials. Overview separates financial and dormitory KPIs and links directly to tasks and alerts. Statement status is calculated from confirmed payments and due dates; pending submissions do not settle a bill. Notice Board supports editing, publishing, and archiving; maintenance updates preserve remarks in request history and the audit trail.


Public entry: the default signed-out page combines a dormitory landing page with sign-in, resident portal features, and tenant registration links. The interior SVG is an illustration rather than a photograph of the actual property. All visual assets are local. Contextual back links return detail/forms to their relevant section; public registration returns to home. Existing authentication and role permissions are preserved.


The signed-out default is now a dedicated commercial landing page (`?page=home`). Sign In and Get Started link to separate login and tenant registration screens. Dashboard figures and the October statement are explicitly labeled samples and never query public resident records. Public contact messages use CSRF validation, an IP-based rate limit, and a hidden spam field; accepted messages are saved as `public_contact` audit events and shown only in admin Help & Inquiries and notifications. No outgoing email is sent by this form. The supplied support email and Batangas location appear as requested; replace the phone placeholder through business contact settings before publishing and verify ownership of the support email. Privacy and Terms links open local pages describing current portal behavior.


Shared visual theme: assets/green-theme.css sets a consistent forest/sage palette and Inter typography for landing, authentication, admin, and tenant views. Icons respond to hover/focus on pointer devices; reduced-motion preferences disable the movement. assets/dorm-courtyard.png is an AI-generated architectural concept, not a photograph of the actual property, and is labeled on the landing hero. Built-in imagegen prompt: photorealistic modest Philippine two-story dormitory courtyard, pale white exterior, sage trim, tropical planting, natural morning daylight; no people, signage, text, logos, or resort styling. The image is used locally as the hero/authentication background and the About section visual.
