# AI-Powered Inventory Management System

yeah another inventory system. started simple, got carried away with features lol. now it has roles, notifications, reservations, a chatbot, and too many things to list.

## Quick Start

```bash
# 1. drop this in htdocs (folder name: AI-POWERED-INVENTORY)
# 2. import database/inventory_db.sql
# 3. go to http://localhost/AI-POWERED-INVENTORY/
# 4. login: admin / admin123
```

if it breaks check `config/database.php` and make sure mysql is running.

## What's Inside

- products with profit tracking and category management
- invoices (in/out stock) with receipt printing
- QR codes (scan with camera or upload)
- AI forecasting and chatbot (natural language)
- reports and analytics
- real-time notifications (low stock alerts, system events)
- stock reservations with chatbot integration
- role-based access control (admin / manager / cashier / viewer)
- user management with activity logging
- account settings (profile + password)
- CSRF protection and rate limiting
- mobile responsive UI
- monochrome design (white/grey/black)

## Features

**Invoice System** — create invoices, auto stock deduction, print layout (receipt + full invoice), payment tracking, customer management

**QR Codes** — generate codes, scan with camera, quick stock updates, bulk printing

**Profit Tracking** — cost vs selling price, auto calculations, color-coded indicators, margin analysis

**AI Stuff** — stock forecasting, trend analysis, chatbot with natural language queries. chatbot now runs exclusively on `invoices` + `invoice_items` architecture for accurate data

**Reservations** — reserve stock via chatbot or UI, track active/completed/cancelled, auto stock deduction and return, CSV export

**Notifications** — real-time low stock alerts, system events, priority levels (low / medium / high / critical), role-targeted delivery, mark as read, auto-refresh every 30s

**Role-Based Access Control (RBAC)** — four roles with different permissions. sidebar and pages adapt based on role. admin-only: categories, inventory management, user management

**User Management** — admin-only. create/edit/suspend users, assign roles, reset passwords, view activity log, sticky notes panel

**Account Settings** — any logged-in user can update their profile (name, email) and change their password

**Security** — CSRF tokens on all forms, rate limiting on sensitive actions, input validation, activity logging, account lockout on failed logins

**UI** — button hierarchy, icon-only table actions, loading states, animations, tooltips, mobile responsive tables, accessibility (WCAG 2.1)

## User Roles

| Role | Access |
|------|--------|
| `admin` | everything — users, categories, inventory, reports, settings |
| `manager` | inventory, products, reports (no user management or categories) |
| `cashier` | inventory, products (no reports or category management) |
| `viewer` | read-only — products and reports |

## Chatbot Commands

try these in the chatbot:
- `check stock laptop`
- `reserve 5 units of product 123`
- `show low stock products`
- `forecast`
- `help` (shows all commands)

---

## SCREENSHOT
- some parts of the system in action:

## Dashboard
<img width="1919" height="959" alt="dashboard" src="https://github.com/user-attachments/assets/7875dac3-57dd-4862-aeb3-6ba2b239d59b" />

## Product Management
<img width="1919" height="966" alt="products" src="https://github.com/user-attachments/assets/ad99981c-2993-43b4-875f-bd843330cff5" />

## Inventory
<img width="1918" height="958" alt="inventory" src="https://github.com/user-attachments/assets/8d7ea4a3-c706-46fa-9430-1ec54c3e2c04" />

## Invoice System
<img width="1918" height="959" alt="invoices" src="https://github.com/user-attachments/assets/451127d3-54d3-443e-ba72-c4004e858700" />

## Reports & Analytics
<img width="1919" height="956" alt="reports" src="https://github.com/user-attachments/assets/704b38af-4f49-466f-a684-6935cb2fd8c1" />
<img width="1917" height="957" alt="aiforecast" src="https://github.com/user-attachments/assets/cd99bf2a-3e3a-405d-a7f5-cc784b42fa82" />

## Adminpage
<img width="1917" height="960" alt="adminpage" src="https://github.com/user-attachments/assets/0016fbec-2b47-433f-8378-82b3f56e0c23" />

---

## Requirements

- PHP 7.4+
- MySQL/MariaDB
- Apache (or whatever)
- XAMPP if local
- modern browser

## Documentation

all the detailed stuff is in the `docs/` folder:

- `docs/README.md` — full feature list and setup guide
- `docs/CHANGELOG.md` — what changed and when

## Recent Updates (April 14, 2026)

- **RBAC hardening** — strict role gating on categories, inventory, and user management pages
- **Sidebar now role-aware** — links hidden based on user role (no more seeing pages you can't access)
- **Chatbot data pipeline** — fully migrated to `invoices` + `invoice_items` tables for all analytics
- **Notifications system** — real-time low stock alerts, system events, priority levels, role-targeted delivery
- **User management** — create/edit/suspend users, role assignment, activity log, sticky notes panel
- **Reservations page** — track active/completed/cancelled stock holds, CSV export
- **Account settings** — profile editing and password change for all users
- **Security layer** — CSRF protection, rate limiter, input validation, account lockout
- **AI chatbot UI overhaul** — improved chat interface with better UX

## Previous Updates (March 25, 2026)

- complete UI overhaul with button hierarchy
- icon-only table actions (horizontal layout)
- loading states, animations, tooltips
- mobile responsive tables
- accessibility features (WCAG 2.1)

---

## File Structure

```
AI-POWERED-INVENTORY/
├── config/
│   ├── database.php        - PDO connection
│   ├── database.example.php - config template (rename to use)
│   ├── session.php         - auth, RBAC helpers, activity logging, notifications
│   ├── error_handler.php   - input validation and error handling
│   ├── rate_limiter.php    - brute force / abuse protection
│   └── file_handler.php    - upload handling
├── includes/
│   ├── navbar.php          - top navigation
│   ├── sidebar.php         - role-aware sidebar
│   ├── chatbot.php         - chatbot widget
│   └── head.php            - shared HTML head
├── ai/
│   ├── chatbot_engine.php  - NLP command processor
│   └── forecasting.php     - stock forecasting logic
├── api/
│   ├── notifications.php   - notification read/mark/check endpoints
│   ├── inventory_summary.php
│   ├── search_products.php
│   ├── get_product.php
│   ├── get_product_by_barcode.php
│   ├── get_customers.php
│   ├── save_customer.php
│   ├── quick_stock_update.php
│   ├── create_quick_invoice.php
│   └── csrf_token.php
├── css/js/                 - styles and scripts
├── database/
│   ├── inventory_db.sql    - main DB schema + seed data
│   ├── invoices.sql        - invoice tables (run if missing)
│   └── add_qr_columns.sql  - QR column migration
├── logs/                   - error logs
├── uploads/                - product images
│
├── dashboard.php           - main dashboard
├── products.php            - product management
├── categories.php          - category management (admin only)
├── inventory.php           - inventory log (admin/manager only)
├── invoices.php            - invoice list
├── create_invoice.php      - invoice builder
├── view_invoice.php        - invoice viewer
├── print_invoice.php       - print layout
├── print_receipt.php       - receipt print layout
├── reservations.php        - stock reservations
├── qr_codes.php            - QR code generator/scanner
├── forecast.php            - AI forecast page
├── reports.php             - reports and analytics
├── notifications.php       - notification center
├── user_management.php     - user CRUD (admin only)
├── settings.php            - account settings
├── product_detail.php      - single product view
├── login.php               - login page
└── logout.php              - session destroy
```

## Troubleshooting

**invoice tables missing?** — run `database/invoices.sql` or just create an invoice (auto-creates)

**QR codes not working?** — enable camera permissions or use file upload

**profit calculations wrong?** — check `cost_price` column has values in your products

**user management broken?** — check that your `users` table has `email`, `first_name`, `last_name`, `status`, `created_by` columns. the page will tell you if columns are missing

**CSRF errors?** — session probably expired. just refresh and try again

**general issues?** — check browser console, verify database connection in `config/database.php`

## Code Notes

code isn't perfect — started as basic CRUD and kept adding stuff. works fine though. some debug `error_log()` calls still in user_management.php, feel free to clean those up.

## What I Learned

- PHP is actually useful
- invoicing easier than expected
- QR codes easier than expected
- RBAC is surprisingly not that hard once you have a session helper
- CSRF tokens everywhere > CSRF tokens nowhere
- UI refinement > redesign
- button hierarchy matters
- real-time notifications feel way more professional than they are to implement
- documentation helps (even casual)

## License

MIT or whatever. use it however you want.

---

built with PHP, MySQL, and too much coffee ☕

