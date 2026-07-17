# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

A small, extensible CMS written in dependency-free PHP 8 (no Composer, no framework) with a MySQL/MariaDB backend and a vanilla-JS drag-and-drop content editor. UI language is German — keep all user-facing strings (admin UI, flash messages, seed content) in German.

## Running

No build step, no package manager, no test suite (yet). Local dev server:

```bash
php -S localhost:8000 -t public public/index.php
```

Requires a local MySQL/MariaDB. On first request the install wizard (`/install`) creates the schema, seed data, and writes `config/config.php` (gitignored — its absence means "not installed", which redirects everything to `/install`).

Syntax check: `php -l <file>`. There is no linter config.

## Architecture

Front controller `public/index.php` autoloads classes by namespace path (`Core\App` → `app/Core/App.php`), loads `app/helpers.php` (global helpers: `e()`, `url()`, `redirect()`, `flash()`, `csrf_*()`, `slugify()`), then runs `Core\App`.

- **`Core\App`** — resolves the base URL (handles both docroot=`public/` and root-`.htaccess`-rewrite setups), enforces the installed/not-installed redirect, runs a global CSRF check on every POST (`_csrf` field or `X-CSRF-Token` header), and registers all routes. Routes map to `[ControllerClass::class, 'method']`; the site catch-all `GET /{slug}` must stay registered last.
- **Controllers** (`app/Controllers/`) — admin controllers extend `Admin\AdminController`, whose constructor enforces login and whose `view()` wraps content in the `admin/_shell` sidebar layout. Models (`app/Models/`) are thin static PDO wrappers over `Core\Database::pdo()`.
- **Rendering pipeline** (`Core\Renderer`) — a page = layout HTML (from DB) with placeholders replaced: `{{content}}`, `{{title}}`, `{{site_name}}`, `{{base_url}}`, `{{year}}`, `{{menu}}`, and `{{template:key}}` which recursively embeds DB-stored templates (depth-capped). Page content itself is JSON: `rows[] → columns[] (span 1–12) → blocks[] {type, data}`.
- **Blocks are registered in two places** — server-side rendering in `Core\BlockRegistry` and editor definition (label, defaults, inspector fields) in `blockDefs` in `public/assets/js/editor.js`. A new block type requires both; `BlockRegistry::types()` is also the server-side whitelist used when sanitizing saved content (`PageController::sanitizeContent()`).
- **Editor** (`public/assets/js/editor.js`, view `admin/pages/editor.php`) — holds the content JSON as client state, renders the 12-grid live, and saves via `POST /admin/pages/{id}/content` with the CSRF token in a header. It reads the initial state from a `<script type="application/json">` tag (encoded with `JSON_HEX_TAG`).

Views are plain PHP templates under `app/Views/`, rendered via `Core\View::render($template, $data, $shell)`. Always escape output with `e()`; block/layout/template HTML from the DB is intentionally rendered raw (admin-authored).

## Conventions

- Schema changes go into `Core\Database::createSchema()` (uses `CREATE TABLE IF NOT EXISTS`; there is no migration system — for new columns, note that existing installs need manual ALTERs).
- Redirect-after-POST with `flash('success'|'error', msg)` for all admin form actions.
- All URLs in PHP go through `url()` / `redirect()` so subdirectory installs keep working; never hardcode absolute paths.
- Seed data (default layout, main-menu template, start page) lives in `InstallController::seed()`.
