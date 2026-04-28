<?php

declare(strict_types=1);

namespace Nano;

use Nano\Models\Item;
use Nano\Models\Page;
use Nano\Models\Term;

/**
 * Sitemap.xml generator.
 *
 * Produces a single urlset (sitemaps protocol 0.9) covering:
 *   - All configured pages (their resolved paths)
 *   - For every item type with `has_page: true`:
 *       - The archive (e.g. /blog)
 *       - Each published item single (e.g. /blog/post-slug)
 *   - For every taxonomy referenced by a paged item type, every term page
 *     (e.g. /categoria/term-slug)
 *
 * URLs are emitted as canonical absolute URLs using `APP_URL` from `.env`
 * when set, falling back to the request scheme/host so sitemaps still work
 * in local dev. The canonical origin is intentionally NOT read from the
 * theme — the theme is project-neutral; deployment URL belongs to `.env`.
 */
final class Sitemap
{
    public static function generate(Config $config): string
    {
        $base = self::resolveBaseUrl($config);
        $entries = [];
        // Dedupe by `loc` as a defensive measure — same URL only emitted
        // once even if some odd config produces overlapping paths.
        $seen = [];

        $add = function (string $loc, ?string $lastmod) use (&$entries, &$seen): void {
            if (isset($seen[$loc])) return;
            $seen[$loc] = true;
            $entries[] = ['loc' => $loc, 'lastmod' => $lastmod];
        };

        // 1. Pages — every configured page becomes a sitemap entry. Some pages
        //    pull lastmod from their DB row (Page::findByKey), others (not yet
        //    persisted) just get the entry without lastmod.
        foreach ($config->pages() as $key => $pageDef) {
            $key = (string) $key;
            $path = Page::pagePath($key, (array) $pageDef);
            $add($base . self::normalizePathForUrl($path), self::pageLastmod($key));
        }

        // 2. Item types with has_page: true — emit each published single.
        //    Item types don't have automatic archive pages anymore; a "blog
        //    archive" comes from a regular configured page (handled in step
        //    1) and is just another URL in the sitemap.
        foreach ($config->itemTypes() as $type => $def) {
            if (($def['has_page'] ?? true) === false) {
                continue;
            }

            $type = (string) $type;
            $typeSlug = trim((string) ($def['slug'] ?? $type), '/');
            if ($typeSlug === '') {
                continue;
            }

            // Published items only — no drafts. 5000 is a generous ceiling
            // (sitemaps are limited to 50k URLs each by spec; once we approach
            // that we'd need to split into multiple sitemaps, but Nano sites
            // are nowhere near that scale).
            $items = Item::published($type, 5000);
            foreach ($items as $item) {
                $add($base . '/' . $typeSlug . '/' . $item->slug, self::itemLastmod($item));
            }
        }

        // 3. Taxonomies — only those linked to at least one paged item type.
        //    Orphan taxonomies (no items) get no public archive worth listing.
        $referenced = self::pagedTaxonomies($config);
        foreach ($referenced as $taxKey) {
            $taxDef = $config->taxonomy($taxKey);
            if ($taxDef === null) continue;

            $taxSlug = trim((string) ($taxDef['slug'] ?? $taxKey), '/');
            if ($taxSlug === '') continue;

            $terms = Term::all($taxKey);
            foreach ($terms as $term) {
                $add($base . '/' . $taxSlug . '/' . $term->slug, null);
            }
        }

        return self::buildXml($entries);
    }

    /**
     * Canonical base URL with no trailing slash. Read from `APP_URL` in `.env`
     * (the deployment-specific origin). Falls back to request scheme/host +
     * install base path so dev installs at /nano still produce valid
     * (if non-canonical) sitemaps.
     *
     * The theme intentionally does NOT declare a URL — themes are reusable
     * across projects, and the canonical origin is a deployment concern.
     */
    private static function resolveBaseUrl(Config $config): string
    {
        $configured = trim((string) ($config->get('app.url') ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = $scheme . '://' . $host;

        $basePath = App::instance()->basePath;
        if ($basePath !== '') {
            $base .= '/' . trim($basePath, '/');
        }
        return rtrim($base, '/');
    }

    /**
     * Page paths come back as either `/` (home) or `/something`.
     * For sitemap output we keep `/` for home and emit the rest verbatim.
     */
    private static function normalizePathForUrl(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        return '/' . trim($path, '/');
    }

    private static function pageLastmod(string $key): ?string
    {
        $page = Page::findByKey($key);
        if ($page === null || empty($page->updatedAt)) {
            return null;
        }
        return self::formatDate($page->updatedAt);
    }

    private static function itemLastmod(Item $item): ?string
    {
        $stamp = $item->updatedAt !== '' ? $item->updatedAt : $item->publishedAt;
        return $stamp !== null && $stamp !== '' ? self::formatDate($stamp) : null;
    }

    /**
     * Convert a MySQL DATETIME to W3C Datetime (the format sitemaps expect).
     * Bad input falls through silently — better to skip lastmod than to
     * crash the whole sitemap.
     */
    private static function formatDate(string $stamp): ?string
    {
        $ts = strtotime($stamp);
        if ($ts === false) return null;
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    /**
     * Set of taxonomy keys that are linked to at least one paged item type.
     * Used so sitemaps don't list term archives for taxonomies whose items
     * are all embed-only (those archives wouldn't render anyway).
     *
     * @return list<string>
     */
    private static function pagedTaxonomies(Config $config): array
    {
        $referenced = [];
        foreach ($config->itemTypes() as $type => $def) {
            if (($def['has_page'] ?? true) === false) continue;
            foreach ((array) ($def['taxonomies'] ?? []) as $tax) {
                $referenced[(string) $tax] = true;
            }
        }
        return array_keys($referenced);
    }

    /**
     * @param list<array{loc:string, lastmod:?string}> $entries
     */
    private static function buildXml(array $entries): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= "    <lastmod>" . $entry['lastmod'] . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . "\n";
        return $xml;
    }
}
