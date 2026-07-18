# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Blockwerk Orange** — a small, extensible CMS written in dependency-free PHP 8 (no Composer, no framework) with a MySQL/MariaDB backend and a vanilla-JS drag-and-drop content editor. The product name "Blockwerk Orange" appears in the admin shell, login/install screens, installer and docs; the backend design and logo use orange tones. UI language is German — keep all user-facing strings (admin UI, flash messages, seed content) in German.

## Running

No build step, no package manager, no test suite (yet). Local dev server:

```bash
php -S localhost:8000 -t public public/index.php
```

Requires a local MySQL/MariaDB. On first request the install wizard (`/install`) creates the schema, seed data, and writes `config/config.php` (gitignored — its absence means "not installed", which redirects everything to `/install`). `install.php` at the repo root is a separate standalone bootstrapper (upload one file → it downloads and extracts the CMS zip, then hands over to the wizard); it is not part of the app runtime.

Syntax check: `php -l <file>` and `node --check <file>` for the JS assets. There is no linter config.

## Architecture

Front controller `public/index.php` autoloads classes by namespace path (`Core\App` → `app/Core/App.php`), loads `app/helpers.php` (global helpers: `e()`, `url()`, `redirect()`, `flash()`, `csrf_*()`, `slugify()`, `format_date_de()`), then runs `Core\App`.

- **`Core\App`** — resolves the base URL (handles both docroot=`public/` and root-`.htaccess`-rewrite setups), enforces the installed/not-installed redirect, runs a global CSRF check on every POST (`_csrf` field or `X-CSRF-Token` header), and registers all routes. Routes map to `[ControllerClass::class, 'method']`; the site catch-alls (`/news/{slug}`, `/events/{slug}`, `/{slug}`) must stay registered last.
- **Controllers** (`app/Controllers/`) — admin controllers extend `Admin\AdminController` (constructor enforces login; `view()` wraps content in the `admin/_shell` sidebar layout). News and Events share `Admin\PostControllerBase` (one `posts` table with a `type` enum). Models (`app/Models/`) are thin static PDO wrappers over `Core\Database::pdo()`.
- **Rendering pipeline** (`Core\Renderer`) — a page = layout HTML (from DB) with placeholders replaced: `{{content}}`, `{{title}}`, `{{site_name}}`, `{{base_url}}`, `{{year}}`, `{{menu}}`, and `{{template:key}}` (recursive, depth-capped). The renderer also **auto-injects** `cms-blocks.css`/`cms-blocks.js` plus a `<style>` block with the layout's design (colors/fonts as CSS variables `--cms-primary`, `--cms-font-body`, …) and `<link>`s for locally stored fonts. News/event detail pages (`Renderer::renderPost`) use the first layout.
- **Design system** — each layout has a JSON `design` column (colors from color pickers + font ids), edited in the layout form. Block "Designvorlagen" (variants) render as `cms-v-<variant>` classes; their styles in `public/assets/css/cms-blocks.css` consume the CSS variables, so variants automatically follow the layout colors. `FontController` downloads Google Fonts (css2 + woff2) into `public/uploads/fonts/<slug>/` and rewrites the CSS to local paths (GDPR-friendly self-hosting).
- **Blocks are registered in two places** — server-side rendering in `Core\BlockRegistry` and editor definition (label, defaults, inspector fields, optional `items` list spec) in `blockDefs` in `public/assets/js/editor.js`. A new block type requires both; `BlockRegistry::types()` is also the server-side whitelist used by `PageController::sanitizeContent()`. Block data may contain scalars plus one level of item lists (arrays of objects with scalar values — see `sanitizeBlockData()`); anything deeper is stripped on save.
- **Editor** (`public/assets/js/editor.js`, view `admin/pages/editor.php`) — holds the content JSON (`rows[] → columns[] (span 1–12) → blocks[]`, plus optional `css` string and per-row `style`) as client state and saves via `POST /admin/pages/{id}/content` with the CSRF token in a header. The canvas is **WYSIWYG**: block HTML comes from `POST /admin/preview/blocks` (`PreviewController`, same `BlockRegistry::render` as the frontend), batched and cached client-side, debounced on edits. The editor page loads `cms-blocks.css` + the layout's `designHead()`; frontend CSS is written with `:where(body)` zero-specificity selectors so admin styles win outside the canvas. Per-block graphical settings live in `data._style` (margins, padding, align, colors, radius → inline-style wrapper `div.cms-block`, see `BlockRegistry::styleAttr()`); per-row settings in `row.style` (full-width background section `div.cms-section`, paddings). Initial state comes from a `<script type="application/json">` tag (encoded with `JSON_HEX_TAG`). Shared admin widgets (media picker modal, rich text editor) live in `public/assets/js/admin-tools.js` (`window.AdminTools`), used by both the editor and the news/event forms; `window.CMS_BASE` is set in `admin/_shell`.
- **Updates** — `Core\Updater` downloads the package zip (default: GitHub main archive, overridable via settings), extracts it over the installation while never touching `config/` and `public/uploads/`, then re-runs `createSchema()`. Version = `VERSION` file at repo root, checked against the raw `VERSION` URL. Admin UI at `/admin/update`.
- **AI assistant** — `Core\Ai` (client for the central `ai-server/` service, license key + token balance in settings), `Core\AiSchema` (system prompt with full block catalog + live site context, Anthropic tool definitions), `Admin\AiController` (agent loop executing create/update/get for pages, layouts, posts (news/events), global blocks and templates, plus generate_image, list_media and load_font (Google Fonts via `FontController::download`) — content validated via the same `BlockRegistry::sanitizeTree` as the editor). `ai-server/` is a standalone vendor-side PHP+SQLite service (licenses, token accounting, Claude/image API passthrough, mock mode for tests) — not part of customer installs.
- **Media** — uploads go to `public/uploads/` (gitignored), validated by finfo MIME against a whitelist; `GET /admin/media/list` serves the JSON for the picker.
- **Shop** (optional, off by default) — tables `shop_categories`, `shop_products`, `shop_shipping`, `shop_orders`, `shop_order_items`. Prices are stored in **cents (int)**; `Core\Shop` centralises settings (enabled flag, root page, currency, payment toggles, PayPal creds) plus `formatPrice`/`parsePrice`/`url`. `Core\Cart` is a session cart keyed by product id. Admin under `/admin/shop/*` (products, categories, orders, settings incl. shipping methods; nav group "Shop"). The storefront is served by `Controllers\ShopController`, rendered through the site layout via `Renderer::renderRaw()` (views in `app/Views/shop/`). Frontend routes are registered in `App::registerRoutes` **only when the shop is enabled**, under the slug of the chosen root page (`/{slug}`, `/{slug}/kategorie/{s}`, `/{slug}/produkt/{s}`, `/{slug}/warenkorb`, `/{slug}/kasse`, `/{slug}/bestellung/{token}`, `/{slug}/paypal/*`) — registered before the `/{slug}` catch-all. Payment: invoice + prepayment complete the order directly; PayPal uses the JS SDK buttons with server-side create/capture (`Core\PayPal`, Orders v2). Shop frontend styles live in `cms-blocks.css`.

Views are plain PHP templates under `app/Views/`, rendered via `Core\View::render($template, $data, $shell)`. Always escape output with `e()`; block/layout/template HTML from the DB is intentionally rendered raw (admin-authored).

## Conventions

- Schema changes go into `Core\Database::createSchema()` (uses `CREATE TABLE IF NOT EXISTS`; there is no migration system — for new columns, note that existing installs need manual ALTERs).
- Redirect-after-POST with `flash('success'|'error', msg)` for all admin form actions.
- All URLs in PHP go through `url()` / `redirect()` so subdirectory installs keep working; never hardcode absolute paths.
- Seed data (default layout, main-menu template, start page) lives in `InstallController::seed()`.
- **Every released change bumps `VERSION` (semver) and gets an entry in `CHANGELOG.md`** — the in-app updater compares against `VERSION` and users read the changelog to see what changed.
- Frontend block structure/styles belong in `cms-blocks.css` (auto-injected everywhere); `site.css` is only the default layout's theme (header/menu/footer) and both consume the `--cms-*` variables with fallbacks.
