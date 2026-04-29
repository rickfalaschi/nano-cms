# Nano CMS

Minimalist PHP CMS designed for AI-driven workflows. Schema-defined sites,
no plugins, no Composer required.

## Quick start

```bash
# 1. Configure DB credentials in config/env.php
# 2. Create the database
mysql -u root -p -e "CREATE DATABASE nano CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 3. Full install (DB schema + per-project files + initial user)
./bin/nano install --email=you@example.com --password='your-password' --name='Your Name'

# 4. Serve
./bin/nano serve 8080
```

`install` copies `.htaccess.example` and `robots.txt.example` into their
live counterparts (`.htaccess`, `robots.txt`). These live files are
**gitignored** — edit them freely (custom redirects, security headers,
crawler rules) and future Nano updates won't overwrite your changes.

To pick up new templates added in later updates, run `./bin/nano files:init`.

### Step-by-step (alternative to `install`)

```bash
./bin/nano migrate                         # schema
./bin/nano files:init                      # copy .example → live
./bin/nano user:create you@... pwd Name    # admin user
./bin/nano page:sync                       # seed page records
```

Visit:
- `http://localhost:8080/` — front-end
- `http://localhost:8080/admin` — admin panel

## Read this next

- [AGENTS.md](AGENTS.md) — full guide for AI agents working in this repo
- [INSTALL.md](INSTALL.md) — installation guide (CLI and auto-install paths)
- [theme/site.json](theme/site.json) — site schema (lives WITH the theme)
- [theme/templates/](theme/templates/) — theme templates

## Deployment

Nano follows the front-controller pattern: every request goes through
`index.php` at the project root, which loads the engine and routes the
request. The `.htaccess` at the same level handles URL rewriting AND
blocks direct access to internal directories (`core/`, `config/`,
`migrations/`, `storage/`, `bin/`, `vendor/`, plus all PHP files inside
`theme/`).

### Apache (most shared hosts)

Point the document root at the project root. The single `.htaccess`
takes care of everything — pretty URLs, asset pass-through, and security.
Make sure `mod_rewrite` is enabled (it is by default on every shared host
that runs Apache/LiteSpeed).

If your host locks the document root to `public_html/`, just upload the
entire project there — the `.htaccess` works the same regardless.

### Nginx / PHP-FPM

See [nginx.conf.example](nginx.conf.example) for a server block. Set the
`root` directive to the project root and `try_files $uri $uri/ /index.php`.

### Built-in PHP server (development only)

```bash
./bin/nano serve 8080
```

Routes through `index.php` directly — `.htaccess` isn't consulted during
development. The dev server falls back to the front controller for any
path that isn't a real file.
