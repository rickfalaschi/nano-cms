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

`install` copies `.htaccess.example`, `public/.htaccess.example`, and
`public/robots.txt.example` into their live counterparts. These live files
are **gitignored** — edit them freely (custom redirects, security headers,
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
`public/index.php`, which loads the engine and routes the request.

### Apache (most shared hosts)

The repo ships with two `.htaccess` files:

- **[public/.htaccess](public/.htaccess)** — front controller + security
  headers + asset caching. Always required on Apache/LiteSpeed.
- **[.htaccess](.htaccess)** (root) — only relevant when the document root
  can't be set to `public/`. Forwards all requests into `public/` and blocks
  direct access to `core/`, `config/`, `migrations/`, `storage/`, `bin/`, and
  theme PHP files.

**Two deployment shapes:**

1. **Recommended** — point the document root at `public/`. Delete the root
   `.htaccess`, keep only `public/.htaccess`. The cleanest setup, available
   on cPanel ("Document Root" setting), DirectAdmin, Plesk, Vercel, VPS.
2. **Locked-doc-root shared hosts** (HostGator, Locaweb, KingHost, etc.) —
   upload the entire project into `public_html/`. The root `.htaccess` does
   the rewrite into `public/` and blocks the rest. URLs stay clean.

Make sure `mod_rewrite` is enabled (it is by default on every shared host
that runs Apache/LiteSpeed).

### Nginx / PHP-FPM

See [nginx.conf.example](nginx.conf.example) for a server block. Set the
`root` directive to `.../nano/public` and `try_files $uri $uri/ /index.php`.

### Built-in PHP server (development only)

```bash
./bin/nano serve 8080
```

This bypasses Apache/Nginx entirely and routes through `public/index.php`,
so neither `.htaccess` file is consulted during development.
