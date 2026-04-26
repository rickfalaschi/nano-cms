<?php

declare(strict_types=1);

namespace Nano\Models;

use Nano\App;
use Nano\Config;

final class Page
{
    public int $id;
    public string $key;
    public string $title;
    public array $fields = [];
    public ?string $updatedAt = null;

    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id = (int) $row['id'];
        $p->key = (string) $row['page_key'];
        $p->title = (string) $row['title'];
        $p->fields = is_string($row['fields'] ?? null) ? (array) (json_decode($row['fields'], true) ?? []) : [];
        $p->updatedAt = $row['updated_at'] ?? null;
        return $p;
    }

    public static function findByKey(string $key): ?self
    {
        $row = App::instance()->db->fetch('SELECT * FROM pages WHERE page_key = ? LIMIT 1', [$key]);
        return $row === null ? null : self::fromRow($row);
    }

    public static function all(): array
    {
        $rows = App::instance()->db->fetchAll('SELECT * FROM pages ORDER BY page_key ASC');
        return array_map(fn($r) => self::fromRow($r), $rows);
    }

    /**
     * Resolve a URL path to a configured page. Compares against the
     * base-stripped path produced by Request::capture().
     * Returns ['key' => string, 'page' => array] or null.
     */
    public static function resolveByUrl(string $path, Config $config): ?array
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') $path = '/';
        foreach ($config->pages() as $key => $page) {
            if (self::pagePath((string) $key, $page) === $path) {
                return ['key' => (string) $key, 'page' => $page];
            }
        }
        return null;
    }

    /**
     * Routing path (no install base prefix). Used by the front router to
     * compare against the incoming request.
     */
    public static function pagePath(string $key, array $page): string
    {
        if (isset($page['url'])) {
            $url = (string) $page['url'];
            return $url === '/' ? '/' : '/' . trim($url, '/');
        }
        if ($key === 'home') {
            return '/';
        }
        return '/' . trim($key, '/');
    }

    /**
     * Public URL (includes the install base prefix). Use this in templates
     * and admin links — never for routing comparisons.
     */
    public static function pageUrl(string $key, array $page): string
    {
        $base = App::instance()->basePath;
        $path = self::pagePath($key, $page);
        if ($path === '/') {
            return $base === '' ? '/' : $base . '/';
        }
        return $base . $path;
    }

    public function field(string $name, mixed $default = null): mixed
    {
        return $this->fields[$name] ?? $default;
    }

    public static function upsert(string $key, string $title, array $fields): self
    {
        $db = App::instance()->db;
        $existing = self::findByKey($key);
        if ($existing !== null) {
            $db->update('pages', [
                'title' => $title,
                'fields' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            ], ['id' => $existing->id]);
            return self::findByKey($key) ?? throw new \RuntimeException('Failed to update page.');
        }
        $db->insert('pages', [
            'page_key' => $key,
            'title' => $title,
            'fields' => json_encode($fields, JSON_UNESCAPED_UNICODE),
        ]);
        return self::findByKey($key) ?? throw new \RuntimeException('Failed to create page.');
    }
}
