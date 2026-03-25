# Inventory Management System

yeah another inventory system. started simple, got carried away with features lol.

## Quick Start

```bash
# 1. drop this in htdocs
# 2. import database/inventory_db.sql
# 3. go to http://localhost/INVENTORY/
# 4. login: admin / admin123
```

if it breaks check `config/database.php` and make sure mysql is running.

## What's Inside

- products with profit tracking
- invoices (in-stock and out-of-stock)
- QR codes (scan with camera or upload)
- AI forecasting and chatbot
- reports and analytics
- mobile responsive UI
- monochrome design (white/grey/black)

## Features

**Invoice System** - create invoices, auto stock deduction, print layout, payment tracking

**QR Codes** - generate codes, scan with camera, quick stock updates, bulk printing

**Profit Tracking** - cost vs selling price, auto calculations, color-coded indicators

**AI Stuff** - stock forecasting, trend analysis, chatbot with natural language

**UI** - button hierarchy, icon-only table actions, loading states, animations, accessibility (WCAG 2.1)

## Chatbot Commands

try these in the chatbot:
- `check stock laptop`
- `reserve 5 units of product 123`
- `show low stock products`
- `forecast`
- `help` (shows all commands)

## Requirements

- PHP 7.4+
- MySQL/MariaDB
- Apache or whatever
- XAMPP if local
- modern browser

## Documentation

all the detailed stuff is in the `docs/` folder:

- `docs/README.md` - full feature list and setup guide
- `docs/CHANGELOG.md` - what changed and when
- `docs/ISSUES.md` - known issues and fixes
- `docs/PROJECT_STRUCTURE.md` - code organization
- `docs/QUICK_REFERENCE.md` - common tasks
- `docs/UI_IMPLEMENTATION_GUIDE.md` - UI patterns
- `docs/INDEX.md` - navigation for all docs

## Recent Updates (March 25, 2026)

- complete UI overhaul with button hierarchy
- icon-only table actions (horizontal layout)
- loading states, animations, tooltips
- mobile responsive tables
- accessibility features
- ~700 lines of CSS added
- 25+ features implemented
- production ready ✅

## File Structure

```
INVENTORY/
├── config/          - database, session, error handling
├── includes/        - navbar, sidebar, chatbot
├── ai/              - chatbot engine, forecasting
├── api/             - REST endpoints
├── css/js/          - styles and scripts
├── database/        - SQL files
├── docs/            - all documentation
├── uploads/         - product images
└── *.php            - main pages
```

## Troubleshooting

**invoice tables missing?** - run `database/invoices.sql` or just create an invoice

**QR codes not working?** - enable camera permissions or use file upload

**profit calculations wrong?** - check cost_price column has values

**general issues?** - check browser console, verify database connection

## Code Notes

code isn't perfect - started as basic CRUD and kept adding stuff. works fine though.

## What I Learned

- PHP is actually useful
- invoicing easier than expected
- QR codes easier than expected
- UI refinement > redesign
- button hierarchy matters
- sometimes simple is better
- documentation helps (even casual)

## License

MIT or whatever. use it however you want.

---

built with PHP, MySQL, and too much coffee ☕

*check docs/CHANGELOG.md for detailed updates*
