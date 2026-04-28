# Nano CMS — Agent Instructions

This file is the contract between Nano CMS and AI coding agents (Claude Code,
GPT engineers, etc). Read it before touching anything in this project.

## What Nano is

A minimalist, schema-driven CMS designed for AI-driven workflows:

- **Pure PHP 8.2+**, no Composer required, runs on any cheap shared hosting.
- **MySQL** storage. Custom fields are stored as JSON columns (not the
  `wp_postmeta` pattern).
- **`site.json` is the source of truth** for the site's structure: pages, item
  types, taxonomies, custom fields. The admin panel is generated from it.
- **Templates are pure PHP** with helper functions. No DSL, no build step.
- **No magic from WordPress**: no hooks/filters, no plugins, no core
  content/thumbnail fields. Title, slug and publish date are the only built-ins;
  everything else is a custom field.

## Repo layout

```
core/                      Engine. Do not edit unless extending the platform.
  Bootstrap.php            Boot entry
  App.php                  Service container
  Config.php               site.json + env loader
  Database.php             PDO wrapper
  Router.php               URL routing
  FrontRouter.php          Public site routing
  helpers.php              Global helpers used by templates
  Models/                  Page, Item, Term, Media, User
  Fields/                  (reserved) future per-type field classes
  FieldRenderer.php        Renders form inputs + collects POST values
  Admin/                   Admin panel (controllers, views, CSS, JS)
  Migrator.php             Migration runner
config/
  env.php                  App + DB config (do not commit secrets)
  site.json                Site schema. Edit this to define structure.
migrations/                Versioned SQL migrations (timestamped files)
storage/uploads/           Uploaded media (served at /uploads/...)
storage/cache/             HTML cache (future)
storage/logs/              Logs
theme/                     Project theme — a SEPARATE package, like WordPress.
                           Not part of Nano core (gitignored). Each project
                           drops its own theme here.
  site.json                Schema: pages, item types, taxonomies, fields.
                           Lives WITH the theme because it's part of the theme.
  style.css                Theme stylesheet
  templates/               Page, single, archive, taxonomy templates
  partials/                header.php, footer.php, etc.
public/                    Document root
  index.php                Single front controller
  .htaccess                URL rewriting
bin/nano                   CLI tool
```

## The flow when building a site

1. Edit `theme/site.json` — define pages, item types, taxonomies, fields.
2. Run `./bin/nano schema:validate` to verify the JSON.
3. Run `./bin/nano page:sync` to seed page records into the DB.
4. Run `./bin/nano migrate` if any new migration files were added.
5. Create or edit templates in `theme/templates/` — these are referenced
   **by file name** from `site.json`. Never associate templates by slug.
6. Use the admin panel to create items and edit page content.

## site.json — the core schema

```json
{
  "site": {
    "name": "...",
    "language": "pt-BR"
  },
  // Note: the canonical site URL is NOT declared here. The theme is
  // project-neutral; deployment URL goes in `.env` as `APP_URL` and is
  // read by Sitemap, absolute_url(), and any other code that needs the
  // canonical origin.

  "field_groups": {
    "seo": {
      "label": "SEO",
      "fields": [ {"name": "...", "type": "...", "label": "..."} ]
    }
  },

  "image_sizes": {
    "thumb":  {"width": 400, "height": 400, "crop": true},
    "hero":   {"width": 1920, "height": 1080, "crop": true}
  },

  "pages": {
    "home": {
      "label": "Home",
      "template": "page-home.php",
      "url": "/",                      // optional, defaults to "/" for "home", "/{key}" otherwise
      "fields": [ ... ]
    }
  },

  "item_types": {
    "post": {
      "label": "Posts",
      "label_singular": "Post",
      "slug": "blog",                  // URL prefix (e.g., /blog/{item-slug})
      "template": "single-post.php",
      "archive_template": "archive-post.php",
      "taxonomies": ["categoria", "tag"],
      "templates": [
        {"key": "destaque", "label": "Post destacado", "file": "single-post-destaque.php"}
      ],
      "fields": [
        {"name": "thumbnail", "type": "image", "label": "Imagem destacada"},
        {"name": "content", "type": "richtext", "label": "Conteúdo"},
        {"group": "seo"}
      ]
    }
  },

  "taxonomies": {
    "categoria": {
      "label": "Categorias",
      "label_singular": "Categoria",
      "slug": "categoria",
      "hierarchical": true,
      "template": "taxonomy-categoria.php"
    }
  }
}
```

### Rules of site.json

- **Templates are linked by file name only**, not by slug. If you change an
  item's slug, the template still works because the binding is in the JSON.
- **Custom item templates** (e.g., a special "destaque" layout for a single
  post) are listed under `item_types.{type}.templates`. The user picks one in
  the admin panel when creating/editing the item.
- **No standard `content` or `thumbnail`.** If you want them, declare them
  as custom fields. This is intentional — every site decides what fields
  matter to it.
- **Field groups** are reusable. Reference them with `{"group": "seo"}` inside
  any `fields` array; they are inlined at render time.

### Available field types

| Type | Description |
|------|-------------|
| `text` | Single-line text input |
| `textarea` | Multi-line plain text |
| `richtext` | TipTap WYSIWYG (HTML output) |
| `image` | Reference to a media library entry. Stored as **integer ID**, not URL. |
| `select` | Dropdown — define `options` as `{value: label}` |
| `boolean` | Checkbox (true/false) |
| `number` | Numeric input |
| `email`, `url`, `date` | HTML5 input variants |
| `repeater` | Repeating group of subfields. Define `fields` array on the field. |

Field common options: `name` (required), `label`, `help`, `required`,
`placeholder`. Repeaters: `fields` (array of subfield defs).

## Templates

### Resolution order

1. **Pages**: `template` from `site.json` → fallback `page.php`.
2. **Items single**: if the item has a `template` key matching one of the
   registered custom templates, use its `file`. Otherwise the item type's
   `template` from `site.json`. Fallback `single.php`.
3. **Item archives**: `archive_template` from `site.json` → fallback `archive.php`.
4. **Taxonomy archives**: taxonomy's `template` from `site.json` → fallback `taxonomy.php`.

**Never associate templates by slug.** Always reference template files in
`site.json`. Slugs change; bindings shouldn't break.

### Template skeleton

Every template should follow this pattern:

```php
<?php /** @var \Nano\TemplateContext $item */ ?>
<?php
\with_context($item, function () {
    get_header();
    ?>
    <article>
        <h1><?= e(the_title()) ?></h1>
        <div><?= field('content') /* trusted HTML from TipTap */ ?></div>
    </article>
    <?php
    get_footer();
});
?>
```

Wrapping in `\with_context($context, fn ...)` is what makes `field()`,
`the_title()`, etc. work without passing the record around.

### Template variables

The template receives a single variable depending on its type:

| Template type | Variable | What |
|---------------|----------|------|
| `page-*.php` | `$page` | `TemplateContext`, `$page->record` is `Page` |
| `single-*.php` | `$item` | `TemplateContext`, `$item->record` is `Item` |
| `archive-*.php` | `$archive` | `TemplateContext`, `$archive->records` is `list<Item>` |
| `taxonomy-*.php` | `$term` | `TemplateContext`, `$term->record` is `Term`, `$term->records` is `list<Item>` |

## Helpers

All helpers are in `core/helpers.php` and globally available in templates.

### Output

- `e($value)` — **escape-by-default**. Use ALL the time:
  `<?= e($value) ?>`. The only exception: trusted HTML (TipTap richtext output,
  which is HTML on purpose). Comment when you skip escaping.
- `dd(...$values)` — debug dump and exit.

### Context

- `current_context()` — current `TemplateContext` if any
- `with_context($context, fn() => ...)` — push a context for a block
- `field($name, $default = null)` — read a custom field from current context
- `the_title()` — title from current context
- `the_slug()` — slug from current context
- `the_url()` — URL from current context

### Items / Terms

- `items($type, $args = [])` — query items. Args: `limit`, `offset`, `status`,
  `term`, `order`. Default status = `published`.
- `terms($taxonomy)` — all terms in a taxonomy.
- `the_terms($taxonomy)` — terms attached to current item.

### URLs / media

All URL helpers automatically prepend the install **base path** (the URL prefix
when Nano is mounted in a subdirectory like `/nano/`). So every internal link
keeps working whether the site is at `https://site.com/` or
`https://site.com/nano/`.

- `url($path = '/')` — root-relative URL for an internal route. Includes base
  path. Use this for navigation links, never hardcode `/sobre`.
- `absolute_url($path = '/')` — full scheme + host URL. Reads `app.url` from
  env. Use for canonical tags, og:url, sitemap.
- `admin_url($path = '/')` — admin URL with the configured admin prefix.
- `asset($path)` — root-relative asset URL (CSS, JS, images in `public/`,
  `theme/...`, `uploads/...`).
- `base_path()` — the prefix itself (for raw concatenation if needed).
- `image_url($value, $size = 'full')` — resolve an image-field value to a URL.
  Accepts: **integer media id** (canonical), absolute URL, root-relative path,
  bare filename, or arrays. Returns `''` when the media is missing/deleted.
- `image_alt($value, $fallback = '')` — alt text saved on the media record.
- `media($id)` — return the `Media` model for that id (or `null`). Useful when
  you need width/height, mime, etc.

**Image field idiom** — `field('image_field')` returns the raw value (an
integer id). Always go through `image_url()` and check the result is non-empty
before rendering, so deleted media doesn't produce broken `<img>` tags:

```php
<?php $src = image_url(field('thumbnail'), 'hero'); ?>
<?php if ($src !== ''): ?>
    <img src="<?= e($src) ?>" alt="<?= e(image_alt(field('thumbnail'))) ?>">
<?php endif; ?>
```

Image sizes (`thumb`, `hero`, etc.) come from `image_sizes` in `site.json` and
are generated lazily on first request, then cached on disk.

### Forms (admin only)

- `csrf_token()`, `csrf_field()` — CSRF protection. **Always include
  `<?= csrf_field() ?>` in admin forms.**

### Layout

- `get_header()`, `get_footer()` — include `theme/partials/header.php` /
  `footer.php`.
- `partial($name, $data = [])` — include `theme/partials/{name}.php`.

### Misc

- `slugify($string)` — turn a string into a slug.
- `config($key)` / `site($key)` — config access via dot notation.

## Database

Tables (see `migrations/` for full schema):

- `users` — admin users
- `pages` — one row per `pages.{key}` from `site.json`
- `items` — every post/case/etc. `fields` is JSON.
- `terms` — taxonomy terms. `fields` is JSON.
- `item_term` — N:N relation
- `media` — uploaded files
- `settings` — key/value JSON store
- `migrations` — applied migration log

**`fields` columns are JSON.** Items and terms have native MySQL JSON columns
that hold all custom field values. To query inside JSON, use MySQL's
`->>'$.field_name'` operator.

## CLI commands

```
./bin/nano migrate                       Run pending migrations
./bin/nano migrate:status                Show pending migrations
./bin/nano user:create <email> <pw> <nm> Create a new admin user
./bin/nano page:sync                     Seed page records from site.json
./bin/nano schema:validate               Validate site.json
./bin/nano serve [port]                  Start built-in dev server (default 8080)
```

## URL conventions

These paths are relative to the install — if Nano is mounted at `/nano/`, prepend
that prefix.

- `/` → home page (the page with key `home` in `site.json`, or with `url: "/"`)
- `/{page-key}` → other pages (or use explicit `url` in the page def)
- `/{item-type-slug}` → archive of an item type
- `/{item-type-slug}/{item-slug}` → single item
- `/{taxonomy-slug}/{term-slug}` → taxonomy term archive
- `/admin` → admin panel
- `/uploads/...` → media files
- `/theme/...` → theme assets (CSS, JS, images)

### Subdirectory installs

Nano detects its base path automatically from `$_SERVER['SCRIPT_NAME']`. For
hosts where autodetect fails, set `app.base_path` in `config/env.php`:

```php
'base_path' => '/nano',  // or '' for root install, null to autodetect
```

When writing templates, **always** go through helpers (`url()`, `asset()`,
`admin_url()`, `image_url()`, `Item::url()`, `Term::url()`,
`Page::pageUrl()`). These all prepend the base path. Never hardcode root
paths like `/blog` — they break in subdirectory installs.

## Security

- **Always escape** with `e()`. The only allowed unescaped output is trusted
  HTML (TipTap richtext output). Mark these explicitly:
  `<?= field('content') /* trusted */ ?>`.
- **CSRF protection** via `csrf_field()` is enforced on all admin POST routes.
- **PDO with prepared statements** throughout. No string interpolation in SQL.
- Sensitive files (`config/env.php`, `*.json`, `*.md`, `*.lock`) are blocked
  by `public/.htaccess`.

## Do / Don't

**Do**

- Edit `site.json` to add fields, types, taxonomies.
- Use `e()` everywhere except trusted rich-text HTML.
- Create new template files and bind them in `site.json`.
- Use the helpers — they hide details and stay stable.
- When adding a custom item template, also register it in
  `item_types.{type}.templates` so the admin can pick it.
- Run `schema:validate` after editing `site.json`.

**Don't**

- Don't bind templates to slugs. Slugs are content; templates are code.
- Don't add hooks/filters or plugin systems. Extensibility goes through
  `theme/functions.php` (load-time only) and explicit helpers.
- Don't introduce a `content` or `thumbnail` "default field." Declare them as
  custom fields if needed.
- Don't write SQL with string interpolation. Use prepared statements.
- Don't bypass the FieldRenderer when creating custom field UIs without
  understanding how `collect()` will read them back.
- Don't put PHP files inside `theme/` and expect them to be web-accessible.
  Only static files are served via `/theme/...`.

## Common tasks

### "Add a new section to the home page"

1. Open `theme/site.json`. Find `pages.home.fields`. Add the new field:
   ```json
   {"name": "testimonial", "type": "richtext", "label": "Depoimento"}
   ```
2. The admin form regenerates automatically. No code change needed in admin.
3. Edit `theme/templates/page-home.php` to render the value:
   ```php
   <?= field('testimonial') /* trusted */ ?>
   ```

### "Add a new item type 'service'"

1. In `site.json`, under `item_types`, add:
   ```json
   "service": {
     "label": "Serviços",
     "label_singular": "Serviço",
     "slug": "servicos",
     "template": "single-service.php",
     "archive_template": "archive-service.php",
     "fields": [
       {"name": "icon", "type": "text", "label": "Ícone"},
       {"name": "description", "type": "richtext", "label": "Descrição"}
     ]
   }
   ```
2. Create `theme/templates/single-service.php` and
   `theme/templates/archive-service.php`.
3. Run `./bin/nano schema:validate`.
4. The sidebar in admin now shows "Serviços" automatically.

### "Add a custom template for a specific item"

1. Create the template file, e.g., `theme/templates/single-post-destaque.php`.
2. Register it under the item type's `templates`:
   ```json
   "templates": [
     {"key": "destaque", "label": "Destaque", "file": "single-post-destaque.php"}
   ]
   ```
3. In the admin item editor, a "Template" dropdown appears, letting the
   editor pick "Destaque" for that specific item.

### "Add a SEO group to multiple types"

1. Define the group under `field_groups.seo`.
2. Reference it inside any item type / page fields with `{"group": "seo"}`.
3. The fields are inlined in the admin form.

## Media library

The library lives at `/admin/media`. It supports:

- Upload (drop files or click) — POST `/admin/media/upload`, JSON response.
  Allowed mimes: jpeg, png, gif, webp, svg, pdf. Limit: 25 MB.
- Detail panel: alt text edit, copy URL, delete (with sized-variant cleanup).
- Picker mode (`/admin/media?picker=1`) — embed-layout view used inside an
  iframe modal opened by image fields. Selecting an item calls
  `window.parent.__nanoPickerCallback(data)` which the image field registers
  before opening the modal.

### Storage layout

```
storage/uploads/
├── {filename}.{ext}              originals
├── thumb/{filename}.{ext}        lazy-generated by GD on first request
└── hero/{filename}.{ext}         lazy-generated by GD on first request
```

Sizes are defined in `site.json → image_sizes`. Each entry: `width`, `height`,
and optional `crop` (boolean — when true, output exactly `width×height`,
otherwise scale-to-fit within the box).

Filenames use a slugified version of the original name (`Foo Bar.JPG` →
`foo-bar.jpg`). Collisions get `-1`, `-2`, … suffix.

## Options pages

Site-wide global fields — like contact info, footer texts, social links —
declared in `theme/site.json → options`. Each entry becomes a sidebar link
in the admin and a fields-only edit form (no slugs, no URLs, no list view).
Inspired by ACF Options Pages.

### Schema

```json
"options": {
  "contato": {
    "label": "Contato",
    "description": "Informações de contato exibidas no rodapé.",
    "fields": [
      { "name": "address", "type": "textarea", "label": "Endereço" },
      { "name": "phone",   "type": "text",     "label": "Telefone" },
      { "name": "email",   "type": "email",    "label": "Email" },
      { "name": "social",  "type": "repeater", "label": "Redes sociais", "fields": [
        { "name": "network", "type": "text", "label": "Rede" },
        { "name": "url",     "type": "url",  "label": "URL" }
      ]}
    ]
  }
}
```

Field types are the same set used by item types and pages (text, textarea,
richtext, image, select, boolean, repeater, etc.). Field groups (`{"group": "seo"}`)
work too.

### Reading values in templates

```php
<?= e(option('contato.email')) ?>
<?= e(option('contato.phone')) ?>

<?php foreach ((option('contato.social') ?? []) as $social): ?>
    <a href="<?= e($social['url']) ?>"><?= e($social['network']) ?></a>
<?php endforeach; ?>

<?php $footer = options('rodape'); ?>
<?= e($footer['tagline'] ?? '') ?>
```

- `option($path, $default = null)` — dot path access. First segment is the
  options page key, rest navigates the JSON. Works with nested fields,
  groups, and repeater rows: `option('contato.social.0.url')`.
- `options($key)` — full values array for one options page.

### Storage

One row in the `settings` table per options page (`setting_key = "options.{key}"`,
`value` = JSON of all fields). Reads are cached in-process, so calling
`option()` 50 times in a template runs one query.

## Forms

Forms are declared in `theme/site.json → forms` and rendered manually in
templates. The connection is the form ID.

### Schema

```json
"forms": {
  "contato": {
    "label": "Contato",
    "subject": "Novo contato pelo site: {{name}}",
    "success_message": "Mensagem enviada — em breve entramos em contato.",
    "fields": [
      { "name": "name",    "type": "text",     "label": "Nome",     "required": true },
      { "name": "email",   "type": "email",    "label": "Email",    "required": true },
      { "name": "phone",   "type": "tel",      "label": "Telefone" },
      { "name": "message", "type": "textarea", "label": "Mensagem", "required": true }
    ]
  }
}
```

Field types: `text`, `email`, `tel`, `url`, `number`, `textarea`, `checkbox`,
`hidden`, `select`. Each field also accepts `required` and `maxlength`.

The `subject` template supports `{{field_name}}` placeholders that get
substituted with the submitted values.

### HTML template

Theme dev writes the markup. Use `form_url($id)` for the action and
`csrf_field()` + `form_honeypot()` for spam protection. `form_status($id)`
returns the flash result of the previous submission (success/error +
field errors when validation fails).

```php
<?php $r = form_status('contato'); ?>

<?php if ($r && $r['status'] === 'success'): ?>
    <p class="success"><?= e($r['message']) ?></p>
<?php endif; ?>

<form action="<?= e(form_url('contato')) ?>" method="post">
    <?= csrf_field() ?>
    <?= form_honeypot() ?>

    <input type="text" name="name" value="<?= e($r['values']['name'] ?? '') ?>" required>
    <?php if (isset($r['errors']['name'])): ?>
        <small class="error"><?= e($r['errors']['name']) ?></small>
    <?php endif; ?>
    <!-- ... -->
    <button type="submit">Enviar</button>
</form>
```

### Submission lifecycle

1. POST `/forms/{id}` validates CSRF, honeypot, and field rules.
2. The submission is saved to `form_submissions` (DB column `data` is JSON).
3. The configured recipients (set in `/admin/forms/{id}`) receive an email
   with all submitted values formatted as a table.
4. The user is redirected back to the referrer; theme reads the result via
   `form_status($id)` to render success / error.

If email fails (no recipients, SMTP error), the submission is still saved —
the failure is recorded in `email_status` / `email_error` columns and visible
in the admin.

### Admin

`/admin/forms` lists all forms with submission counts. `/admin/forms/{id}`
manages recipients (add/remove inline) and shows every submission with full
data, IP, timestamp, and email status.

## Future / stubs

These are designed-but-not-built; do not pretend they exist:

- Static page cache + invalidation
- Multi-site / i18n
- Field types: `relationship`, `gallery`, `file`, `color`
- Sitemap.xml / robots.txt auto-gen
- Migrations CLI scaffolder (`make:migration`)

When asked to add any of the above, treat as a real feature design task.
