I am planning on building a state of the art AI based CMS that will record and track a user's Ai Chatbot.

GARWIN AI
Complete Platform Design, Architecture & Development Master Prompt
Project: Garwin AI
Platform Type: AI-powered CMS, SaaS platform, cloud storage, productivity system, application marketplace, and developer ecosystem.
Production Domain: www.garwinai.com
Admin Email: support@garwinai.com

1. YOUR ROLE AND PRIMARY OBJECTIVE
Act as a senior software architect, SaaS product designer, UI/UX designer, PHP developer, database engineer, security engineer, and full-stack application developer.
Your assignment is to design and build Garwin AI as a complete, production-ready web application.
Do not build this as a WordPress website. Build an independent platform with its own:
User registration and authentication.
Membership and subscription system.
Cloud storage architecture.
AI assistant and AI-powered tools.
Application marketplace called AppZone.
Developer Program and Developer application.
Dashboard and widget system.
Productivity applications.
Super Admin dashboard.
Administrative settings and controls.
Billing and usage management.
Product installation and update system.
Permissions, security, and audit logging.
The platform must be modular so new applications, widgets, themes, services, and features can be added without rebuilding the core system.
Important development instructions:
Inspect the existing repository before making changes.
Preserve working code and existing functionality.
Do not overwrite existing files without understanding their purpose.
Build in clearly defined phases.
Create real working functionality, not static mockups.
Use database migrations and automated tests.
Document every major module.
Never use fake functionality, placeholder buttons, or simulated success messages.
Do not expose private configuration, credentials, storage directories, or API keys.
Complete and test each phase before moving to the next.
2. BRAND IDENTITY AND VISUAL DESIGN
Official logo
The logo must read:
GarwinAI
Use two distinct text treatments:
Garwin: black.
AI: blue.
The logo should be simple, clean, and professional.
Use a modern sans-serif typeface. Keep the logo readable at small sizes.
Create:
Full horizontal logo.
Compact logo.
Sidebar logo.
Login-page logo.
Favicon.
Browser application icon.
Do not use gradients or excessive decorative effects in the logo.
Color system
Use a grayscale interface with blue accents.
Suggested design tokens:
Element
Color
Main background
#F4F5F7
Main content background
#FFFFFF
Sidebar
#F0F1F3
Primary text
#17191D
Secondary text
#686D76
Muted text
#9298A2
Borders
#E1E4E8
Dark panels
#252A32
Primary blue
#246BCE
Blue hover
#1958B3
Light blue
#E8F1FF
Success
#218653
Warning
#D99A24
Error
#D74747

Blue must be the primary action color.
Use blue for:
Primary buttons.
Selected navigation items.
Active tabs.
Links.
Selected checkboxes.
Focus outlines.
Progress indicators.
Important active states.
Use grayscale for:
Navigation.
Cards.
Panels.
Tables.
Forms.
Borders.
Backgrounds.
Secondary buttons.
Avoid excessive colorful icons and gradients.
Interface design principles
The entire platform should follow these rules:
Consistent spacing.
Consistent button sizes.
Consistent typography.
Rounded corners.
Subtle shadows.
Thin borders.
Clear visual hierarchy.
Readable tables.
Responsive layouts.
Accessible color contrast.
Clear empty states.
Useful loading indicators.
Confirmation dialogs for destructive actions.
Create reusable CSS variables and design components instead of styling every page independently.
3. MAIN PLATFORM STRUCTURE
Create a shared application shell with:
Main navigation.
Left sidebar.
Top toolbar.
Main content workspace.
User profile menu.
Notifications.
Search.
Help and support.
AI Assistant access.
Responsive mobile navigation.
Top toolbar
Include:
GarwinAI logo.
Global search.
AI Assistant shortcut.
Notification bell.
Help button.
User avatar.
Account dropdown.
Settings shortcut.
Left sidebar
Organize navigation into sections.
WORKSPACE
Dashboard
AI Assistant
Projects
Calendar
Messages
Teams
PRODUCTIVITY
Wordbase
Spreadsheets
Codebase
Notes
Tasks
CLOUD
My Storage
Recent Files
Shared With Me
Favorites
Trash
MARKETPLACE
AppZone
My Installed Apps
Developer Program
ACCOUNT
Billing
Usage & Limits
Account Settings
Help Center
Only display modules that the user is permitted to access.
Allow users to collapse the sidebar.
Allow users to pin frequently used applications.
Save navigation preferences.
4. LOGIN, REGISTRATION AND USER ACCOUNTS
Create a complete authentication system.
Registration
Allow users to register using:
Email.
Password.
Confirm password.
First name.
Last name.
Terms acceptance.
Implement:
Email verification.
Password reset.
Secure sessions.
Login throttling.
Optional two-factor authentication.
Account recovery.
Logout from all devices.
User identification
Every account receives a random, unique, case-sensitive nine-character user ID.
Example:
aB7xK9mQ2
Requirements:
Uppercase letters.
Lowercase letters.
Numbers.
Cryptographically secure random generation.
Unique database constraint.
Collision checking.
Never use sequential public user IDs.
Keep the internal database ID separate.
Automatic user setup
Immediately after registration:
Create the user database record.
Assign FREE membership.
Generate the nine-character user ID.
Create the user's private storage directory.
Create the default folders.
Create the initial dashboard.
Initialize AI budget tracking.
Initialize account preferences.
Record registration activity.
Redirect to the user's dashboard.
5. MEMBERSHIP AND BILLING SYSTEM
Build a configurable membership system.
FREE membership
1GB cloud storage.
$3 monthly AI API spending budget.
Calendar.
Storage.
AI Assistant.
Free AppZone products.
Zero included team seats.
PRO membership
$49.99 monthly.
25GB cloud storage.
$8 monthly AI API spending budget.
Calendar.
Storage.
AI Assistant.
Wordbase.
Spreadsheets.
Codebase.
Three included team seats.
Access to eligible PRO marketplace products.
Paid AppZone products must still be purchased unless specifically included in a bundle or promotion.
Storage upgrades
$2 per additional GB per month.
Suggested packages:
5GB: $9.50/month.
10GB: $19/month.
25GB: $47.50/month.
50GB: $90/month.
Additional team seats
$9.99 per additional seat per month.
Suggested packages:
5 seats: $47.45/month.
10 seats: $94.91/month.
25 seats: $237.26/month.
50 seats: $449.55/month.
Team packages are available to PRO members unless changed by Super Admin.
All prices, billing periods, discounts, plan limits, and eligibility rules must be configurable through Super Admin settings.
Use a real payment provider integration with verified webhooks. Do not store raw card numbers.
6. DASHBOARD DESIGN
Create a personalized dashboard for each user.
The dashboard should be modular and widget-based.
Default dashboard widgets
Welcome message.
AI Assistant.
Storage usage.
AI budget usage.
Membership status.
Recent files.
Recent projects.
Calendar events.
Tasks.
Messages.
Installed applications.
AppZone recommendations.
Account activity.
Widget functionality
Every widget should support applicable actions:
Drag.
Reposition.
Resize.
Minimize.
Hide.
Configure.
Remove from dashboard.
Restore from widget library.
Users should be able to add widgets from a widget library.
Allow users to create multiple dashboard layouts if practical.
Save layouts to the user's private storage or database.
Persist:
Widget ID.
Widget type.
Position.
Row and column.
Width.
Height.
Visibility.
Settings.
Last modified date.
Restore the layout after login and across devices.
Create a dashboard reset option.
Super Admin must have separate control over administrator dashboard layouts.
7. CLOUD STORAGE SYSTEM
Build a secure private cloud-storage system.
Master storage directory
Use this application-level storage root:
ACSD-73h5ZXp1Q
The directory must exist outside the public web root.
User storage structure
Each user receives a separate directory.
Example:
ACSD-73h5ZXp1Q/
UCSD-aB7xK9mQ2-001/
Projects/
Media/
Apps/
Widgets/
Themes/
Documents/
System/
Trash/
Do not expose physical storage paths to users.
Storage interface
Design a modern file manager with:
Grid view.
List view.
Breadcrumb navigation.
Search.
Sorting.
Filtering.
Folder creation.
File upload.
Download.
Rename.
Move.
Copy.
Cut.
Paste.
Duplicate.
Delete.
Restore.
Permanent deletion.
Favorites.
File properties.
Version history where supported.
Universal right-click menu
Create a reusable context menu for all applicable manageable objects.
Include:
Open.
Cut.
Copy.
Paste.
Duplicate.
Move.
Rename.
Delete.
Properties.
Additional actions should appear depending on the object.
Support:
Desktop right-click.
Mobile long-press.
Keyboard shortcuts.
Accessible keyboard navigation.
The menu must check permissions before displaying or executing actions.
Storage calculations
Track:
Actual bytes stored.
User quota.
Used storage.
Available storage.
Reserved storage.
Trash storage.
Installed package storage.
Copying a file creates another physical copy and counts toward storage.
Moving a file must not count twice.
An installed application extracted from an AppZone ZIP is a separate stored copy. Both the original ZIP and installed copy count toward storage.
Provide configurable storage-accounting multipliers if needed, but keep physical storage usage separate from billing/accounting values.
8. APPZONE MARKETPLACE
Build AppZone as a major section of Garwin AI.
AppZone is the marketplace where users discover, purchase, download, install, and manage applications, widgets, and themes.
AppZone home page
Create a professional marketplace with:
Featured applications.
Popular applications.
New releases.
Recommended products.
Free products.
Paid products.
PRO products.
Categories.
Search.
Filters.
Developer profiles.
Product reviews.
Product categories
APPLICATIONS
Business.
Productivity.
Finance.
Project Management.
AI Tools.
Communication.
Marketing.
Development.
Utilities.
WIDGETS
Dashboard widgets.
Calendar widgets.
Storage widgets.
Analytics widgets.
AI widgets.
Productivity widgets.
THEMES
Dashboard themes.
Application themes.
Workspace themes.
Layout packages.
Product listing
Every product should display:
Product logo.
Product name.
Developer.
Description.
Screenshots.
Version.
Release date.
Compatibility.
Rating.
Review count.
Price.
Subscription information.
Free/PRO status.
Install or purchase button.
Create a detailed product page.
Marketplace product types
Support:
Free.
One-time purchase.
Monthly subscription.
Annual subscription.
PRO included.
Bundles.
Promotional discounts.
Product ownership
Users must be able to see:
Purchased products.
Free products.
Active subscriptions.
Available updates.
Installed products.
Previously installed products.
Prevent unauthorized downloading and installation.
9. DEVELOPER APP
Create a free application in AppZone named:
Garwin Developer
This application is the gateway to the Garwin Developer Program.
A user must install the Developer app before applying to become a marketplace developer.
The package contract and in-workspace guide are documented in [docs/developer-platform.md](./docs/developer-platform.md). The current Developer App is documentation-only; package uploads, review, sandbox execution, and publishing are not enabled.
Developer app dashboard
Include:
Developer application status.
Developer profile.
Submit a product.
My applications.
My published products.
Draft products.
Pending review.
Denied submissions.
Sales.
Downloads.
Revenue.
Product updates.
Support requests.
Developer settings.
Developer application fees
Configurable default fees:
Apps: $49.99.
Widgets: $34.99.
Themes: $26.99.
Super Admin can change fees or waive them.
Developer application form
Pre-fill existing account information when available.
Collect:
Developer/business name.
Username.
Email.
Mobile phone.
Full business or residential address.
Developer description.
Developer logo.
Website.
Individual or business classification.
Government ID or driver's license where required.
Relevant identification number.
SSN for an individual or EIN/Tax ID for a business, as applicable.
Tax verification status.
Sensitive personal and tax data must be encrypted and restricted to authorized personnel.
Do not expose identity information in public developer profiles, logs, or ordinary application screens.
Application workflow
User installs Developer app.
User selects Apply to Become a Developer.
User completes application.
System validates required fields.
User pays the applicable fee.
Application enters Pending Review.
Super Admin reviews application.
Super Admin approves or denies.
User receives notification.
Approved developers gain publishing permissions.
If denied, provide an explanation and configurable resubmission process.
Only approved developers can publish products.
10. DEVELOPER PRODUCT SUBMISSION
Build a complete product submission wizard.
Product information
Collect:
Product type.
Product name.
Product ID.
Description.
Developer.
Logo.
Screenshots.
Favicon.
Version.
Release date.
Changelog.
Category.
Minimum Garwin version.
Compatibility.
Dependencies.
Price.
Subscription options.
License.
Support information.
ZIP upload
Developers upload their product as a ZIP package.
Suggested package structure:
product-name.zip
product-name/
css/
js/
images/
videos/
assets/
pages/
index.php
app_name.txt
widget_description.txt
favicon.png
logo.png
copyright.txt
manifest.json
Additional files may be included.
The manifest must define:
Product ID.
Product type.
Name.
Description.
Developer.
Version.
Release date.
Minimum Garwin version.
Dependencies.
Configuration variables.
Permissions.
Entry point.
Package file list.
Validate every package before accepting it.
Prevent:
ZIP path traversal.
Malicious archive extraction.
Unauthorized file overwrites.
Executable code escaping the product sandbox.
Access to other users' files.
Access to core application files.
Do not execute untrusted developer PHP code inside the main Garwin application process.
Use an isolated execution environment or restricted runtime for products that require server-side execution.
Developer configuration variables
Allow developers to define variables that users configure during installation.
Supported field types:
Text.
Number.
Email.
Password.
Dropdown.
Multi-choice.
Checkbox.
Color.
File.
URL.
Date.
API configuration.
Custom field.
Each variable needs:
Variable name.
Label.
Description.
Field type.
Default value.
Required status.
Validation.
Allowed values.
Minimum and maximum.
Visibility.
Example:
company_name = Required text field.
primary_color = Optional color field, default #19293D.
Create the configuration form automatically from the manifest.
Store private configuration securely.
11. APP INSTALLATION AND UPDATES
Create a complete installation system.
When a user selects Install:
Check login.
Check product ownership or free eligibility.
Check membership requirements.
Check dependencies.
Check compatibility.
Check available storage.
Retrieve the authorized package.
Validate package integrity.
Save original ZIP to the user's UCSD.
Extract to the correct Apps, Widgets, or Themes folder.
Display configuration form.
Save configuration.
Register installed product.
Update storage accounting.
Record installation activity.
Display successful installation only after completion.
Support:
Install.
Update.
Disable.
Enable.
Uninstall.
Reinstall.
Version rollback where supported.
Uninstalling must not delete user-created data without explicit confirmation.
Developers must not be able to modify customer files outside their declared permissions.
12. AI ASSISTANT
Build a real AI Assistant interface.
Include:
Conversation history.
New conversation.
Rename conversation.
Delete conversation.
Search history.
File attachments.
Project context.
Model selection where authorized.
Usage tracking.
Cost tracking.
Create centralized AI billing and budget enforcement.
AI monthly budgets
FREE: $3.
PRO: $8.
Eligible recurring storage and team add-ons contribute 8% of their actual discounted monthly recurring charge to the AI budget.
Calculate:
Total AI budget = Membership AI budget + qualifying recurring add-on contribution.
Do not include taxes or one-time purchases.
AI spending enforcement
Track actual API costs, not promised token allowances.
Before every AI request:
Identify the user and billing period.
Estimate request cost.
Reserve budget atomically.
Check available budget.
Block if insufficient.
Submit authorized API request.
Retrieve actual provider usage.
Calculate actual cost.
Reconcile reservation.
Release unused reservation.
Record the transaction.
Protect against concurrent requests and overspending.
Centralize this logic across Assistant, Projects, Codebase, and all other AI modules.
Display warnings at:
75%.
90%.
100%.
At the limit display:
"You've reached your monthly AI Assistant usage limit.
Your available AI budget for this billing period has been used.
Your AI usage will reset on your next billing date.
You can review your usage and manage your membership or eligible monthly add-ons in Billing Settings."
Do not block ordinary account, storage, or non-AI functionality.
13. PRODUCTIVITY APPLICATIONS
Build the following applications as independent modules.
Wordbase
A document editor supporting:
Create documents.
Edit documents.
Save automatically.
Folders.
Templates.
Document search.
Sharing.
Export.
Import.
Version history.
Spreadsheets
Support:
Worksheets.
Rows and columns.
Cell editing.
Formulas.
Formatting.
Sorting.
Filtering.
Charts.
Import/export.
Auto-save.
Codebase
A code editor and project workspace supporting:
Project creation.
File browser.
Syntax highlighting.
Code editing.
AI code assistance.
Error detection.
Preview.
Testing.
Version history.
Download/export.
Calendar
Support:
Month view.
Week view.
Day view.
Events.
Tasks.
Recurring events.
Reminders.
Invitations.
Team calendars.
Notifications.
Projects
Support:
Project creation.
Tasks.
Milestones.
Deadlines.
Documents.
Team assignments.
Progress.
Comments.
AI project assistance.
Messages
Support:
Internal messaging.
Conversations.
Notifications.
Read status.
Attachments.
Search.
Teams
Support:
Invitations.
Team members.
Roles.
Permissions.
Shared projects.
Shared files.
Team management.
Respect included seat limits and paid additional seats.
14. SUPER ADMIN DASHBOARD
Create a separate, protected Super Admin interface.
This is not an ordinary user dashboard.
It must use the same grayscale and blue design system but have its own administrative navigation and controls.
Only authorized Super Admin accounts may access it.
Admin dashboard overview
Display:
Total users.
New registrations.
FREE members.
PRO members.
Active subscriptions.
Cancelled subscriptions.
Monthly recurring revenue.
AppZone sales.
Developer applications.
Pending product reviews.
Installed products.
Storage usage.
AI provider spending.
AI budget allocation.
Platform expenses.
Failed payments.
Security alerts.
Recent administrator activity.
Include charts for:
User growth.
Membership distribution.
Revenue.
Storage consumption.
AI usage.
AppZone sales.
Developer activity.
Admin navigation
OVERVIEW
Dashboard.
Analytics.
Reports.
USERS
All Users.
User Profiles.
Memberships.
Storage.
AI Usage.
Account Status.
Account Restrictions.
BILLING
Membership Plans.
Subscriptions.
Payments.
Refunds.
Storage Add-ons.
Team Add-ons.
Discounts.
Invoices.
Revenue Reports.
APPZONE
Marketplace.
Products.
Product Categories.
Featured Products.
Product Reviews.
Sales.
Commissions.
Bundles.
Promotions.
DEVELOPERS
Developer Applications.
Pending Review.
Approved Developers.
Denied Applications.
Developer Profiles.
Submission Fees.
Developer Payouts.
Developer Reports.
APPLICATIONS
Installed Applications.
Core Applications.
Widgets.
Themes.
Application Permissions.
Product Updates.
Dependencies.
AI MANAGEMENT
AI Providers.
AI Models.
API Configuration.
Provider Pricing.
User Budgets.
Actual API Spending.
Usage Reports.
Spending Warnings.
AI Restrictions.
STORAGE
Master Storage.
User Storage.
Storage Providers.
Storage Quotas.
Storage Pricing.
File Activity.
Trash Retention.
Backups.
Storage Reports.
PLATFORM
Global Settings.
Email Settings.
Notifications.
Security.
Authentication.
Logs.
Maintenance.
System Health.
Database.
Integrations.
ADMINISTRATION
Admin Accounts.
Admin Roles.
Permissions.
Audit Logs.
15. ADMIN SETTINGS
Create a comprehensive settings system.
Every setting must be saved persistently in the database and take effect throughout the application.
General settings
Platform name.
Logo.
Favicon.
Domain.
Support email.
Default timezone.
Default language.
Date format.
Time format.
Maintenance mode.
Branding settings
Primary blue.
Background colors.
Text colors.
Border colors.
Logo.
Login logo.
Favicon.
Custom CSS, restricted to authorized administrators.
Membership settings
FREE price.
PRO price.
Storage quotas.
Team seat allowances.
Included applications.
Feature access.
AI budgets.
Billing cycles.
Upgrade and downgrade rules.
Storage settings
Master storage location.
Storage provider.
Provider credentials.
Maximum upload size.
Allowed file extensions.
Default user quota.
Trash retention.
Backup frequency.
Storage accounting multiplier.
Storage package prices.
AI settings
Provider selection.
API keys.
Available models.
Model pricing.
Input/output pricing.
AI budget allocation.
Monthly budget resets.
Usage warnings.
Maximum request size.
Model access by membership.
API keys must be encrypted and never returned to normal users.
AppZone settings
Enable or disable marketplace.
Product categories.
Featured products.
Submission requirements.
Product review rules.
Developer fees.
Marketplace commission.
Refund rules.
Subscription rules.
Package size limits.
Developer settings
Enable Developer Program.
Application requirements.
Application fees.
Verification requirements.
Tax verification.
Approval workflow.
Resubmission rules.
Developer payout settings.
Security settings
Password policy.
Session duration.
Login attempt limits.
Two-factor authentication.
IP restrictions.
Admin access policies.
Audit retention.
API access.
File upload security.
16. USER ACCOUNT SETTINGS
Create a user settings area with:
Profile.
Profile photo.
Name.
Email.
Mobile number.
Password.
Security.
Two-factor authentication.
Active sessions.
Membership.
Billing.
Payment methods.
Invoices.
Storage.
AI usage.
Notifications.
Privacy.
Connected applications.
Team settings.
Users must be able to download their personal data and request account deletion subject to applicable retention and legal requirements.
17. NOTIFICATIONS AND EMAIL
Create an internal notification system.
Notifications should support:
Account registration.
Email verification.
Password changes.
Membership changes.
Payment success.
Payment failure.
Storage limits.
AI budget warnings.
AI budget exhaustion.
Developer approval.
Developer rejection.
Product updates.
Application installation.
Team invitations.
Calendar reminders.
Build configurable email templates.
Use a properly configured transactional email service.
Track delivery failures without logging private message contents unnecessarily.
18. DATABASE ARCHITECTURE
Use a relational database with normalized tables and appropriate indexes.
Create separate tables for:
users.
user_profiles.
user_sessions.
memberships.
membership_features.
subscriptions.
payments.
invoices.
storage_accounts.
storage_files.
storage_folders.
storage_events.
storage_quotas.
ai_budgets.
ai_transactions.
ai_reservations.
ai_providers.
ai_models.
dashboard_layouts.
widgets.
applications.
installed_applications.
appzone_products.
product_versions.
product_categories.
product_purchases.
product_reviews.
developer_profiles.
developer_applications.
developer_documents.
developer_payouts.
product_submissions.
product_dependencies.
product_permissions.
product_configurations.
projects.
tasks.
calendar_events.
messages.
teams.
team_members.
notifications.
admin_users.
admin_roles.
admin_permissions.
admin_audit_logs.
system_settings.
Use foreign keys, indexes, unique constraints, timestamps, and appropriate cascading rules.
Use database migrations rather than manually altering production tables.
19. TECHNICAL ARCHITECTURE
Use:
PHP 8.2 or newer.
MySQL or MariaDB.
Composer.
HTML5.
CSS3.
JavaScript.
Bootstrap 5.
Responsive design.
Server-side validation.
Secure REST API endpoints.
Database migrations.
Automated tests.
Use a modular MVC architecture.
Suggested structure:
garwinai/
app/
Controllers/
Models/
Services/
Middleware/
Policies/
Helpers/
config/
database/
migrations/
seeds/
public/
assets/
css/
js/
images/
index.php
resources/
views/
layouts/
components/
auth/
dashboard/
storage/
appzone/
developer/
admin/
settings/
routes/
storage/
logs/
cache/
sessions/
tests/
vendor/
.env
composer.json
README.md
Keep private user files outside the public web directory.
Use environment variables for secrets and database configuration.
Do not commit production credentials to GitHub.
20. SECURITY REQUIREMENTS
Security is a core platform requirement.
Implement:
Password hashing using Argon2id where supported.
CSRF protection.
SQL injection prevention.
Output escaping.
XSS prevention.
Secure cookies.
Session regeneration.
Rate limiting.
Login protection.
File upload validation.
MIME validation.
Path traversal protection.
Authorization middleware.
Ownership checks.
Role-based access control.
Encryption for sensitive information.
Secure API authentication.
Webhook signature verification.
Audit logging.
Backup and recovery procedures.
Users must never access another user's storage, projects, messages, private settings, or installed product configuration.
Developers must never access private customer files or administrator resources.
Ordinary administrators must not automatically receive unrestricted Super Admin permissions.
21. USER EXPERIENCE AND RESPONSIVENESS
The platform must work on:
Desktop.
Laptop.
Chromebook.
Tablet.
Android phones.
iPhone.
Use responsive sidebars, collapsible menus, mobile-friendly tables, accessible dialogs, and touch-friendly controls.
Create consistent application headers and navigation.
Use loading states, error messages, confirmation dialogs, and empty-state guidance.
Do not allow any interface element to appear functional when its backend operation is not implemented.
22. DEVELOPMENT AND IMPLEMENTATION PHASES
Build in the following sequence.
PHASE 1 — Foundation
Project structure.
Database connection.
Configuration.
MVC architecture.
Authentication.
Registration.
User ID generation.
Initial user setup.
Base UI.
Branding.
Responsive application shell.
PHASE 2 — Membership and Accounts
FREE and PRO plans.
Membership restrictions.
User settings.
Subscription foundation.
Billing integration.
Payment webhooks.
User account dashboard.
PHASE 3 — Cloud Storage
ACSD master storage.
UCSD user directories.
File manager.
Folder manager.
File operations.
Context menus.
Storage quotas.
Trash.
File permissions.
Storage reporting.
PHASE 4 — Dashboard and Widgets
Dashboard.
Widget library.
Drag and drop.
Resize.
Saved layouts.
Recent activity.
Personalized workspace.
PHASE 5 — AppZone
Marketplace.
Product categories.
Product details.
Product purchases.
Reviews.
Product downloads.
Installation system.
Updates.
Product ownership.
PHASE 6 — Developer App
Developer app listing.
Developer application.
Verification workflow.
Submission fees.
Product submission.
ZIP validation.
Manifest system.
Product configuration.
Developer dashboard.
Sales reporting.
PHASE 7 — AI System
AI Assistant.
Provider integration.
Model selection.
Usage tracking.
Budget calculations.
Reservations.
Cost reconciliation.
Monthly resets.
Warnings.
Admin AI management.
PHASE 8 — Productivity Applications
Wordbase.
Spreadsheets.
Codebase.
Calendar.
Projects.
Tasks.
Messages.
Teams.
PHASE 9 — Super Admin
Admin dashboard.
User management.
Membership management.
Billing.
AppZone management.
Developer approvals.
Storage administration.
AI administration.
Global settings.
Roles and permissions.
Audit logs.
PHASE 10 — Testing and Production
Functional testing.
Security testing.
Permission testing.
Storage isolation testing.
Payment testing.
AI budget testing.
AppZone installation testing.
Mobile testing.
Performance testing.
Backup testing.
Production deployment.
23. REQUIRED DEVELOPMENT WORKFLOW
Before coding:
Inspect all existing files.
Identify the current framework and database.
Identify completed modules.
Identify missing modules.
Check for broken functionality.
Produce a proposed implementation plan.
Identify any schema conflicts.
Preserve existing working code.
During development:
Work on one phase at a time.
Create the database migrations.
Implement the backend.
Implement the frontend.
Connect all controls to real operations.
Add authorization checks.
Write tests.
Run tests.
Fix errors.
Document completed functionality.
Do not generate the entire application in one giant code dump.
Create maintainable files and modules.
Do not leave TODO comments as substitutes for required features.
If a feature depends on a third-party service that is not configured, implement the integration interface and clearly identify the configuration required. Do not pretend the service is operational.
24. REQUIRED DELIVERABLES
At completion, provide:
1

## Phase 1 implementation: foundation

The repository now contains the first working foundation slice: a Composer-autoloaded PHP MVC app with a public landing page, registration, login/logout, an authenticated dashboard, PDO database configuration, and a migration runner. Registration creates a cryptographically random, case-sensitive nine-character GarwinAI ID, hashes the password with Argon2id where available, assigns the Free plan, initializes preferences/dashboard/AI-budget/storage records, creates the initial private storage folders, and records account activity. The shared UI includes the wordmark, responsive navigation, favicon, and web-app manifest.

### Local setup

Requirements: PHP 8.2+, Composer, Node/npm, PDO SQLite, cURL, and OpenSSL for the local setup. Copy `.env.example` to `.env`, set a generated `APP_KEY`, then run:

```sh
composer install
npm ci
npm run build
php bin/console migrate
composer test
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000`. Keep the web server document root at `public/`; private user files default to `var/private-storage/ACSD-73h5ZXp1Q`, outside that web root. Set `GARWIN_STORAGE_PATH` to an absolute private parent path for deployment; the application creates the required ACSD master directory inside it. Set `SESSION_SECURE_COOKIE=true` when TLS is terminated upstream and HTTPS is not visible to PHP.

### Super Admin, launch checklist, and demo users

The Super Admin is bootstrapped explicitly; there is no hardcoded admin password in source. Run `php bin/console key:generate`, put the resulting `APP_KEY` in the ignored `.env`, set `GARWIN_ADMIN_EMAIL` and `GARWIN_ADMIN_PASSWORD` there, then run:

```sh
php bin/console migrate
php bin/console admin:bootstrap
```

The bootstrap command creates or promotes the configured account only when the configured password matches any existing account. Remove `GARWIN_ADMIN_PASSWORD` from the local environment after bootstrap; the database stores only the password hash. Sign in through `/login` with the configured admin email to open the protected Super Admin dashboard.

On admin sign-in the launch checklist opens until dismissed or launch-ready. Each failed item includes an expandable “How to fix” guide. In addition to Stripe (secret, publishable, webhook signing keys), Resend (API key and verified sender email), and OpenAI (API key), it checks PHP 8.2 and required extensions, Composer/frontend assets, database connectivity and driver, pending migrations, the web server's `public/` document root, private storage permissions and account folders, `APP_URL=https://www.garwinai.com`, secure cookies, debug disabled, and a valid encryption key. Values are AES-256-GCM encrypted with `APP_KEY`; secret values are never rendered back. “Save & verify” makes read-only live requests to the providers; Resend sender-domain verification is checked, while Stripe's publishable/webhook values are format-checked but cannot be independently verified by the account API request.

Typical repairs include installing missing PHP extensions and restarting PHP-FPM, running `composer install`, `npm ci`, and `npm run build`, checking the `DB_*` environment values, backing up the database before `php bin/console migrate`, setting the document root to `public/`, and restoring/configuring private storage outside the web root with mode `0700`. Storage checks avoid displaying physical paths and flag missing account directories rather than silently creating empty replacements. The Launch button records readiness inside the application; it does not deploy code, configure DNS, or activate incomplete billing, email, or AI modules.

Admin Settings → **Demo Users** creates 1–100 tagged demo accounts per request, using reserved `.invalid` email addresses and random passwords. “Remove all demo users” requires both browser confirmation and a confirmation checkbox, and deletes only tagged accounts plus their private storage.

### Database and deployment

Local development defaults to SQLite. MySQL/MariaDB configuration is available through the `DB_*` environment variables in `.env.example`; apply schema updates explicitly with `php bin/console migrate`. The migration creates only the tables needed by Phase 1. The current development container has PDO SQLite but not PDO MySQL, so MySQL connectivity and migration execution must be verified in the target deployment environment.

### Scope and integrations

Email verification/password recovery, 2FA, paid subscriptions/payment webhooks, AI provider calls, and all later phase modules are not implemented or represented as operational. Registration currently creates a usable account immediately; configure a transactional email provider before requiring email verification. No payment or AI provider credentials are bundled. The registration checkbox records acknowledgement of the current foundation phase, not acceptance of a legally reviewed Terms of Service; approved legal and privacy documents must be supplied before production. The interface uses the locally bundled Bootstrap 5 stylesheet and responsive theme CSS; it does not rely on an external CDN. Run `npm ci && npm run build` after updating frontend dependencies. Use a TLS-enabled web server, set secure session cookies, and protect `.env`, `var/`, and other non-public files at deployment.

### Tests

`composer test` exercises migration idempotency, registration initialization, password hashing, ID format/collision checking, login throttling, encrypted integration configuration, launch readiness invalidation, admin bootstrapping, demo-user creation/removal, initial storage folder creation, and private directory permissions using an isolated in-memory SQLite database and temporary storage root.
