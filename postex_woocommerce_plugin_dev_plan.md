
# PostEx WooCommerce Plugin – Development Plan

## 1. Objective
Deliver a WordPress plugin that lets WooCommerce merchants create PostEx shipments and fetch printable airway bills directly from the order admin—removing the need to visit the PostEx merchant portal.

## 2. High‑Level Milestones & Timeline

| Week | Milestone | Key Deliverables | Acceptance Criteria |
|------|-----------|-----------------|---------------------|
| 0 | Project Setup | Git repo, dev environment, PostEx sandbox creds | Repo cloned, sample commit passes CI |
| 1 | Plugin Scaffold & Settings | Plugin header, activation hook, Settings page (API key, pickup address, initial ref #) | Settings persist; “Test Connection” returns **200** |
| 2 | Order Action UI | Order‑detail button, React/JS modal, nonce handling | Button visible, modal pre‑fills Woo fields |
| 3 | PostEx “Create Order” Flow | API wrapper, JSON payload mapper, auto‑increment ref #, order meta storage | Tracking number saved; success note added |
| 4 | Un‑booked List & PDF Download | Admin submenu, grid view, bulk select, `/get-invoice` fetcher | Selected PDFs stream ≤10 TNs per call |
| 5 | Background Jobs & Status Sync | WP‑Cron tasks, status update column in Woo list | Status column shows latest PostEx state |
| 6 | Hardening & QA | Input sanitization, error logging, PHPUnit & E2E tests | Tests ≥90 % pass; no WP‑DEBUG notices |
| 7 | Docs & Release | README, screenshots/GIF, version tag, .zip package | Plugin installs & runs on staging site |

## 3. Detailed Task Breakdown

### Phase 0 – Setup
- Initialize Git repo; configure **GitHub Actions** for PHP/JS lint + PHPUnit.
- Add `.editorconfig`, `.gitignore`, Composer and NPM scaffolding.
- Store PostEx sandbox token as repository secret for CI tests.

### Phase 1 – Plugin Scaffold & Settings
- Create `postex-woocommerce.php` with header & activation/deactivation hooks.
- Build settings page via *Settings API* under **WooCommerce → Settings → Shipping → PostEx**.
- Fields: `api_key`, `pickup_address`, `next_ref_number`, `auto_increment` toggle, `environment`.
- “Test Connection” button triggers AJAX → `/v1/ping` (or small harmless endpoint).

### Phase 2 – Order‑Detail Action
- Hook `woocommerce_admin_order_actions` to append a “Create PostEx Order” icon.
- Register `admin_enqueue_scripts` to inject React modal assets only on order screens.
- Use `wp_create_nonce`/`wp_verify_nonce` for AJAX security.

### Phase 3 – Create‑Order Integration
- Class `PostEx_Client` → `create_order()`, `list_unbooked()`, `download_awb()`.
- Map Woo order data → PostEx payload (`orderRefNumber`, COD amount, weight, dimensions).
- After **200** response:
  - Save `trackingNumber` & payload JSON to order meta.
  - Increment `next_ref_number`.
  - Add order note “PostEx order created – TN ###”.

### Phase 4 – Un‑booked Orders Page
- Add submenu: **WooCommerce → PostEx → Airway Bills**.
- Call `list_unbooked()`; cache with 5‑min transient.
- Render table (WP List Table) with checkboxes, “Download Selected”.
- Batch selected TNs into groups ≤10; stream PDF via forced download.

### Phase 5 – Background Sync
- WP‑Cron every 12 h:
  - Pull status for orders in states *Pending*, *Booked*, *Shipped*.
  - Update order meta & note on status change.

### Phase 6 – Hardening & QA
- Sanitize & escape all input/output.
- Log API errors to `wp-content/postex‑log.php` (rotated daily) and order notes.
- PHPUnit for helpers; Playwright or Cypress for browser tests (modal & settings page).

### Phase 7 – Documentation & Release
- Add `README.md` (features, screenshots, FAQ).
- Prepare translation template `.pot`.
- Tag `v1.0.0`; create GitHub Release with plugin ZIP.

## 4. Technical Notes

- **HTTP Library**: Use `wp_remote_get/post`; set header `Authorization: Bearer {token}`.
- **Data Storage**:
  - `wp_options`: single option array `postex_settings`.
  - Order meta keys: `_postex_tracking`, `_postex_status`, `_postex_payload`.
  - Transient: `_postex_unbooked_cache`.
- **Ref # Auto‑Increment**: atomic update with `update_option` and returned `$autoload = false` to avoid race in high‑volume shops.
- **Error Handling**: Map PostEx error codes to user‑friendly messages; include original code in developer log.

## 5. Testing Matrix

| Test Type | Tool | Scope |
|-----------|------|-------|
| Unit | PHPUnit | Helpers, payload mapper |
| Integration | PHPUnit + MockPress | API client, settings persistence |
| E2E | Playwright | Admin UI flows |
| Compatibility | Local Docker WP stack | PHP 7.4–8.3, Woo 6.x–8.x |

## 6. Risks & Mitigations
- **API downtime** → Graceful fallback, retry, admin notices.
- **Rate limits** → Cache un‑booked list, back‑off with exponential delay.
- **Large stores** → Use REST pagination & async batch‑processing with Action Scheduler.

---

_Last updated: 2025‑07‑08_
