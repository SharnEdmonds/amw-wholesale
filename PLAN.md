# AMW Wholesale Plugin — Implementation Plan

## Context

AMW currently relies on WholesaleX (v2.3.6) — a third-party vendor plugin (~39K LOC, 298 WooCommerce hook calls, options-table-only data model, no automated tests, confusing UX for both owners and buyers). It bolts every feature onto WooCommerce's retail flow, which mixes B2B quote workflows awkwardly with B2C checkout.

We're building a **full replacement** — a single purpose-built plugin, AMW-internal only (no license server, no commercial stack), with a **quote-first** B2B flow:

> Wholesale customer browses a dedicated `/wholesale` catalog → builds a line-item list → submits a **quote request** → AMW reviews/adjusts in admin → approves → customer confirms → system generates a **PDF invoice**, emails customer, creates a WooCommerce order marked `awaiting-payment`, payment collected on terms (bank transfer, marked paid manually).

Version/compatibility alerts use a simple **WP-Cron poll** (every 12h) against a static JSON file we host (e.g. on the Vantura Digital site). No webhooks, no HMAC, no license keys. If a WP/WC update breaks the plugin we update the JSON file; affected sites see an admin notice within 12h.

Success criteria: the new plugin ships **fewer features than WholesaleX but does them all well** — focused on AMW's real workflow, not the vendor's "60K+ businesses" feature catalog.

---

## Step 0 — Scaffold the plugin repo (prerequisite for Ultraplan handoff)

Ultraplan requires a git repo. The AMW workspace root isn't one and we don't want to `git init` it (the sibling plugins each have their own `.git`). Instead, create the new plugin's directory as its own repo from day one — matching the existing sibling plugin convention.

1. **Create** `C:\Users\sharn\Desktop\AMW-LIVE_SOFTWARE\Plugins\amw-wholesale\` (parent `Plugins\` exists — memory confirms siblings live there).
2. **`git init`** inside it; set default branch to `main` via `git symbolic-ref HEAD refs/heads/main`.
3. **Write `.gitignore`** — covers WordPress/PHP noise (`/vendor/`, `/node_modules/`, `*.log`, `.DS_Store`, `Thumbs.db`, `.idea/`, `.vscode/`, `/assets/js/*.min.js`, `/assets/css/*.min.css`).
4. **Write stub `README.md`** — one paragraph: "AMW-internal B2B wholesale replacement for WholesaleX. Quote-first flow, PDF invoices, WooCommerce-native. See PLAN.md for full implementation plan."
5. **Copy this plan** to `Plugins\amw-wholesale\PLAN.md` so Ultraplan sees it as the artifact to refine.
6. **Initial commit** — `git add .` then `git commit -m "Scaffold amw-wholesale plugin repo with initial plan"`. No remote pushed.
7. **Re-invoke `/ultraplan`** from inside the new directory.

Verification: `git status` clean on `main`, one commit in `git log`, `/ultraplan` proceeds past the git check.

---

## Architectural foundations (from exploration)

**Follow APA's namespace + PSR-4 pattern** (sibling plugin `advanced-product-addons`, `namespace APA;`). Use **VRS's custom-tables + `dbDelta` pattern** (`amw-rental-products/includes/core/class-vrs-database.php`) for quotes/invoices — options-table-only (WholesaleX's mistake) won't scale queries like "all open quotes for customer X."

**Security baseline from research brief** — non-negotiable:
- `current_user_can('manage_woocommerce')` for admin REST routes, not `manage_options`
- `$wpdb->prepare()` on every query, `hash_equals()` for token comparison
- `sanitize_email()` / `sanitize_text_field()` / `absint()` on input; `esc_html()` / `esc_attr()` on output
- Nonce + cap check paired on every mutation
- File uploads (quote attachments) via `wp_handle_upload()` with MIME allowlist + magic-byte check, never trust extension
- No `unserialize()` on user input anywhere — JSON only
- WooCommerce HPOS declared at load time (pattern from both VRS line 51–55 and APA line 51–55)

**Fix WholesaleX's specific gaps** (found during exploration):
- Email read without sanitization (`registration.php:1185`) — sanitize immediately on `$_POST` read
- `$_REQUEST` access without nonce (`initialization.php:242–245`) — nonce-gate all mutating endpoints
- `wp_kses_post()` used on email subjects — use `sanitize_text_field()`
- Implicit rule `RuleInterface` — we define a formal `RuleInterface` PHP interface so rule handlers are contract-bound

---

## Plugin layout

```
amw-wholesale/
├── amw-wholesale.php                         (bootstrap, ~200 LOC)
├── uninstall.php                             (drop tables, clear options)
├── readme.txt                                (WP header with Tested up to, Requires PHP)
├── includes/
│   ├── class-amw-wholesale-plugin.php        (singleton, init order)
│   ├── class-amw-wholesale-activator.php     (dbDelta, default role, seed options)
│   ├── class-amw-wholesale-deactivator.php   (clear cron schedules only)
│   ├── class-amw-wholesale-database.php      (custom table schema + migrations)
│   ├── class-amw-wholesale-scripts.php       (enqueue, versioned cache-bust)
│   ├── rest/
│   │   ├── class-rest-base.php               (shared permission + sanitization helpers)
│   │   ├── class-rest-quotes.php             (CRUD quotes)
│   │   ├── class-rest-invoices.php           (generate PDF, resend)
│   │   ├── class-rest-pricing.php            (tier CRUD, role CRUD)
│   │   └── class-rest-customers.php          (approve/reject, list)
│   ├── quotes/
│   │   ├── class-quote-repository.php        (data access, prepared SQL)
│   │   ├── class-quote-service.php           (business logic: submit, approve, convert)
│   │   ├── class-quote-state-machine.php     (draft → submitted → reviewing → approved → invoiced → paid | rejected)
│   │   └── class-quote-notifier.php          (email triggers)
│   ├── invoices/
│   │   ├── class-invoice-service.php         (generate, number, store PDF)
│   │   ├── class-invoice-pdf.php             (wraps Dompdf)
│   │   └── templates/invoice.php             (HTML template)
│   ├── pricing/
│   │   ├── class-pricing-engine.php          (resolve price for user × product × qty)
│   │   ├── class-price-rule-interface.php    (formal contract)
│   │   ├── rules/
│   │   │   ├── class-rule-role-tier.php      (role × product base/sale)
│   │   │   ├── class-rule-quantity-break.php (tier pricing)
│   │   │   └── class-rule-category-discount.php
│   │   └── class-pricing-cache.php           (transient layer, explicit TTL)
│   ├── catalog/
│   │   ├── class-wholesale-catalog.php       (the /wholesale page controller)
│   │   └── templates/catalog-table.php       (SKU, qty input, price, subtotal, add-to-quote)
│   ├── customers/
│   │   ├── class-customer-registration.php   (B2B apply form)
│   │   ├── class-customer-roles.php          (WP role CRUD, scoped caps)
│   │   └── class-customer-account-pages.php  (My Account → Quotes, Invoices tabs)
│   ├── admin/
│   │   ├── class-admin-menu.php              (top-level menu)
│   │   ├── class-admin-quotes-list.php       (WP_List_Table subclass)
│   │   ├── class-admin-quote-editor.php      (metabox-style edit screen)
│   │   ├── class-admin-pricing-rules.php
│   │   └── class-admin-customers.php
│   ├── emails/
│   │   ├── class-email-quote-received.php            (to customer + admin on submit)
│   │   ├── class-email-quote-approved.php            (to customer with accept link)
│   │   ├── class-email-quote-rejected.php
│   │   ├── class-email-invoice-issued.php            (PDF attachment)
│   │   └── class-email-customer-registered.php
│   ├── compat/
│   │   └── class-compat-checker.php          (WP/WC version check, admin notice dispatcher)
│   └── helpers/
│       ├── class-sanitizer.php               (type-specific sanitize helpers)
│       └── class-nonce.php                   (named nonce helpers)
├── assets/
│   ├── css/
│   │   ├── wholesale-catalog.css
│   │   └── admin.css
│   └── js/
│       ├── wholesale-catalog.js              (vanilla JS quote builder, no React)
│       └── admin-quote-editor.js
├── templates/
│   └── emails/                               (overridable by theme: amw-wholesale/emails/...)
└── vendor/
    └── dompdf/                               (PDF generation — single dependency, bundled)
```

**Scale target:** ~8-12K LOC PHP (WholesaleX is 39K doing more, with worse separation). JS is vanilla — VRS/APA/GAW all avoid frameworks; we match that.

---

## Custom database tables

All created via `dbDelta` in `class-amw-wholesale-activator.php`, HPOS-compatible (store IDs, not post_ids):

```sql
wp_amw_quotes
  id BIGINT PK, uuid CHAR(36) UNIQUE, customer_id BIGINT (wp_users.ID),
  status ENUM('draft','submitted','reviewing','approved','rejected','invoiced','paid','expired'),
  subtotal DECIMAL(12,2), tax DECIMAL(12,2), total DECIMAL(12,2),
  customer_notes TEXT, admin_notes TEXT,
  expires_at DATETIME, submitted_at DATETIME, decided_at DATETIME,
  created_at, updated_at,
  INDEX (customer_id, status), INDEX (status, expires_at), UNIQUE (uuid)

wp_amw_quote_items
  id BIGINT PK, quote_id BIGINT FK, product_id BIGINT, variation_id BIGINT NULL,
  sku VARCHAR(100), name VARCHAR(255),
  quantity INT, unit_price DECIMAL(12,2), line_total DECIMAL(12,2),
  meta JSON,
  INDEX (quote_id)

wp_amw_invoices
  id BIGINT PK, invoice_number VARCHAR(32) UNIQUE,
  quote_id BIGINT FK, wc_order_id BIGINT (HPOS order ID),
  customer_id BIGINT, total DECIMAL(12,2),
  pdf_path VARCHAR(500),
  status ENUM('issued','paid','void','overdue'),
  due_date DATE, paid_at DATETIME, issued_at DATETIME,
  INDEX (customer_id, status), UNIQUE (invoice_number)

wp_amw_pricing_rules
  id BIGINT PK, type VARCHAR(40), scope VARCHAR(40),
  config JSON, priority INT, enabled TINYINT,
  starts_at DATETIME NULL, ends_at DATETIME NULL,
  created_at, updated_at,
  INDEX (type, enabled, priority)

wp_amw_audit_log
  id BIGINT PK, actor_id BIGINT, action VARCHAR(64),
  subject_type VARCHAR(40), subject_id BIGINT,
  data JSON, ip VARCHAR(45), created_at,
  INDEX (subject_type, subject_id), INDEX (actor_id, created_at)
```

Indexes chosen for the three real query patterns: "my open quotes" (`customer_id + status`), "quotes to review" (`status + submitted_at`), "expiring soon" (`status + expires_at`). WholesaleX scans serialized `wp_options` for all of this — we can't repeat that.

**No** custom post types (WooCommerce orders remain the single source of truth for actual purchases). **No** postmeta for quote items (direct relational table). **Postmeta only** for per-product B2B tier pricing (extends existing WC product screen, no new CPTs).

---

## Core flow: quote → invoice

1. **Browse** — logged-in wholesale customer hits `/wholesale` (page registered via `register_rewrite_rule` + template loader). Renders table: SKU, name, stock, their tier price, qty input, line-subtotal, "Add to quote."
2. **Build quote** — vanilla JS maintains client-side list; "Submit Quote" POSTs to `POST /wp-json/amw/v1/quotes` (nonce + cap).
3. **Server creates quote** — `Quote_Service::submit()` validates stock, recomputes prices server-side (never trust client prices), inserts `wp_amw_quotes` + `wp_amw_quote_items`, sets status `submitted`, writes audit log.
4. **Notify** — emails fire: customer gets "Quote received," admin gets "New quote #XXX."
5. **Admin reviews** — WP admin → Wholesale → Quotes list (WP_List_Table). Click quote → edit screen: adjust line prices, add admin notes, approve/reject.
6. **Approve** — moves quote to `approved`. `Email_Quote_Approved` sends customer an accept link with nonced URL.
7. **Customer accepts** — single click at URL verifies nonce, triggers `Invoice_Service::generate_from_quote($quote_id)`:
   - Atomically (DB transaction): creates `wp_amw_invoices` row, generates sequential invoice number, creates a WooCommerce order (HPOS) marked `awaiting-payment`, renders PDF via Dompdf, saves to `uploads/amw-wholesale-invoices/{yyyy}/{mm}/INV-xxxxx.pdf` with `.htaccess` denying direct web access.
   - Emails customer PDF invoice + bank transfer details.
8. **Payment** — customer pays via bank transfer. Admin manually marks invoice `paid` → WC order moves to `completed` → stock decrement fires via standard WC hooks.

**Why this is less confusing than WholesaleX:**
- One page, one list, one button ("Add to Quote") — no guessing whether "Add to Cart" triggers a quote or a direct order
- Quote state machine is visible: customer sees `Submitted → Reviewing → Approved → Invoiced → Paid`
- Admin has one inbox (Quotes list), not WholesaleX's 14 admin screens scattered across menus
- Owners see clear money states: open quote value, unpaid invoice total, overdue count — all on a dashboard widget

---

## Pricing engine

`Pricing_Engine::get_price($product_id, $user_id, $qty)` iterates `wp_amw_pricing_rules` in priority order, each rule implementing `Price_Rule_Interface::apply(PriceContext $ctx): PriceContext`. Formal interface (contract-bound, unlike WholesaleX's duck-typed rule classes).

Three rule types at launch:
- **Role tier** — per-role per-product price override (reads `_amw_tier_{role_slug}_price` postmeta, set from the WC product edit screen)
- **Quantity break** — qty ≥ N → price = X (config JSON on rule row)
- **Category discount** — category Y → N% off for role Z

Cache key: `transient('amw_price_' . $product_id . '_' . $user_id, 15min)`. Invalidated on rule save, product save, user role change. Explicit TTLs always (WholesaleX's mistake: unset TTLs = eternal staleness).

---

## Security hardening (concrete checklist)

Shared helpers in `includes/helpers/`:
- `Sanitizer::email($raw)`, `::int($raw)`, `::money($raw)`, `::uuid($raw)`, `::html_fragment($raw)` — wrappers that `wp_unslash` + type-sanitize in one call.
- `Nonce::verify($action)` — dies with 403 on fail; every REST `permission_callback` calls this plus `current_user_can()`.

REST routes:
- Namespace: `amw/v1`
- Permission callback on every route (no `return true;` anywhere)
- Admin routes: `current_user_can('manage_woocommerce')`
- Customer routes: `current_user_can('read') && user_has_active_wholesale_account($user_id)` — via meta-cap filter
- Arg schemas use `sanitize_callback` + `validate_callback` per argument (APA pattern, `class-apa-rest-api.php:51-58`)

PDF generation:
- Dompdf run with remote assets **disabled** (no SSRF via `<img src="http://evil">`)
- Output directory has `index.php` + `.htaccess Deny from all`; access proxied through a nonced PHP handler that verifies the requesting user owns the invoice

File uploads (customer quote attachments, optional):
- `wp_handle_upload` with `test_form=false`, explicit `mimes` allowlist
- Filesize cap 10MB, MIME magic-byte check via `finfo_file`
- Stored outside web root if possible; otherwise in a `.htaccess`-protected dir

Database:
- Every query in `Quote_Repository` / `Invoice_Service` uses `$wpdb->prepare()` with `%d`/`%s`/`%f`/`%i` placeholders
- All state transitions wrapped in `$wpdb->query('START TRANSACTION')` / `COMMIT`
- UUIDs (not sequential IDs) on customer-facing URLs so enumerative access attempts fail

Audit log:
- Every quote/invoice state change writes to `wp_amw_audit_log` with actor, action, IP, before/after snapshots
- Admin UI tab shows per-quote history

---

## Compatibility-warning system (WP-Cron poll)

`Compat_Checker` registered via `wp_schedule_event('amw_compat_check', 'twicedaily')`:
1. Fetches `https://updates.vanturadigital.co.nz/amw-wholesale/compat.json` (static file we maintain — no dynamic server)
2. Compat JSON shape:
   ```json
   {
     "plugin_version": "1.0.0",
     "warnings": [
       { "severity": "warning", "match": { "wp": "<6.9" }, "message": "..." },
       { "severity": "critical", "match": { "wc": ">=9.5" }, "message": "known issue, patch pending" }
     ],
     "tested_up_to": { "wp": "6.9", "wc": "9.4" }
   }
   ```
3. Stored in `transient('amw_compat', 24h)`
4. `Admin_Notice` class evaluates `get_bloginfo('version')` vs `WC()->version` vs rules; shows dismissible banner
5. If fetch fails (timeout, 404), silently keeps last successful response — never blocks admin

Fetches use `wp_remote_get` with `timeout=10`, `redirection=2`, `sslverify=true`. URL hardcoded, not user-configurable — no SSRF surface.

---

## Critical files to create

Listed in build order (each depends only on earlier items):

1. `amw-wholesale.php` + `class-amw-wholesale-plugin.php` — bootstrap, HPOS declare, load order
2. `class-amw-wholesale-database.php` + `class-amw-wholesale-activator.php` — table creation, schema versioning
3. `helpers/class-sanitizer.php`, `helpers/class-nonce.php` — used everywhere downstream
4. `customers/class-customer-roles.php` — WP role + capability definitions (`amw_wholesale_customer`)
5. `pricing/class-price-rule-interface.php` + the 3 rule classes + `class-pricing-engine.php` + `class-pricing-cache.php`
6. `quotes/class-quote-repository.php` → `class-quote-state-machine.php` → `class-quote-service.php`
7. `invoices/class-invoice-service.php` + `class-invoice-pdf.php` + templates
8. `rest/class-rest-base.php` then the 4 REST controllers
9. `catalog/class-wholesale-catalog.php` + templates + vanilla JS
10. `admin/` classes (list table + editors) — last, because they depend on everything else
11. `emails/` classes — WC_Email subclasses
12. `compat/class-compat-checker.php` + cron hook
13. `customers/class-customer-account-pages.php` — My Account integration
14. `uninstall.php` — clean teardown

---

## Existing code to reuse

- **HPOS declaration pattern** — copy from `amw-rental-products/vantura-rental-system.php:51-55` and `advanced-product-addons/advanced-product-addons.php:51-55`
- **dbDelta migration pattern** — `amw-rental-products/includes/core/class-vrs-database.php:15` (migration hook), `:74` (dbDelta call)
- **REST permission callback style** — `advanced-product-addons/includes/class-apa-rest-api.php:31` (method ref), `:66` (cap check)
- **WP_List_Table subclass** — any existing admin list in `amw-rental-products/includes/admin/`
- **Schema-based input sanitization** — `amw-rental-products/includes/core/class-vrs-base-ajax.php:42-47` (schema array driving sanitizer) — adopt this for REST arg validation
- **WC_Email subclasses** — the 9 email classes in `wholesalex/includes/emails/` are a good skeletal reference even though we're not copying WholesaleX code

---

## Out of scope for v1 (deliberately)

Per scope decisions: **AMW internal only, PDF-only invoices, WP-Cron polling, quote-first, dedicated catalog page, full replacement**. Therefore these are **not** in v1:

- License server, customer activation, signed update delivery
- HMAC-signed webhook receiver
- Xero / MYOB accounting integration (manual mark-as-paid only)
- Sub-accounts, wallet, conversations, request-a-quote-on-product (WholesaleX Pro addons we don't need)
- B2B pricing on the main retail storefront (only the dedicated /wholesale page shows B2B prices)
- Migrating WholesaleX data — fresh start was chosen; ops team re-enters roles/tiers during cutover

---

## Verification plan

Run on a staging WordPress (redesign.vanturadigital.co.nz via `wordpress-siteground-admin`):

**Unit-level** — PHPUnit for:
- `Pricing_Engine::get_price()` across each rule type and precedence order
- `Quote_State_Machine` — valid/invalid transitions
- `Sanitizer::*` — boundary inputs (empty, oversized, invalid UTF-8, SQLi attempts)
- `Invoice_Service::generate_from_quote()` — atomicity (simulate DB failure mid-flow, confirm rollback)

**Integration** — happy path end-to-end:
1. Install plugin on staging, verify activator creates all 5 tables (`SHOW TABLES LIKE 'wp_amw_%'`)
2. Create 2 wholesale roles with different tier pricing
3. Approve a test customer in that role
4. Log in as customer, submit a 5-line quote
5. Confirm email received (both customer + admin)
6. Log in as admin, adjust one line price, approve
7. Click accept link as customer, verify invoice PDF emailed + downloadable via nonced URL, verify WC order created in `awaiting-payment`
8. Manually mark invoice paid, verify WC order moves to `completed`, verify stock decrements
9. Check audit log has every state transition with correct actor + IP

**Compat warning** — manually edit the compat JSON on updates.vanturadigital.co.nz to simulate a "known broken on WC 9.5" warning; clear transient; verify banner shows in admin within one cron cycle.

**Security** — run automated scans:
- `wpscan --url staging --enumerate vp` — plugin vulnerability scan
- `phpcs` with `WordPress-VIP-Go` ruleset on the plugin dir — zero high-severity findings required
- Manual CSRF test: try POSTing to each REST route without nonce — all must return 403
- Manual path-traversal test against invoice download handler — crafted URLs must 403
- Grep for `unserialize`, `eval`, `base64_decode`, `$_REQUEST` — should return zero matches in our code

**Load sanity** — seed 500 products, 50 customers, 200 historical quotes; time the `/wholesale` catalog render (<1.5s target) and quotes admin list (<1s target).

Release gate: all PHPUnit green, all integration steps pass, zero `phpcs` high-severity, zero items on CSRF/path-traversal manual checks.
