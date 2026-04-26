<?php

declare(strict_types=1);

namespace Nano\Models;

use Nano\App;

/**
 * Wraps the `settings` table for site-wide "options pages" — admin forms
 * that hold global custom fields (contact info, footer texts, etc.) defined
 * by the theme in `site.json → options`.
 *
 * Storage layout: one row per options page, with key `options.{pageKey}`
 * and a JSON blob of all field values.
 */
final class Option
{
    private const PREFIX = 'options.';

    /** Cached JSON values per group key. */
    private static array $cache = [];

    public static function getGroup(string $key): array
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $row = App::instance()->db->fetch(
            'SELECT value FROM settings WHERE setting_key = ? LIMIT 1',
            [self::PREFIX . $key]
        );
        $values = [];
        if ($row !== null && is_string($row['value'])) {
            $decoded = json_decode($row['value'], true);
            if (is_array($decoded)) {
                $values = $decoded;
            }
        }
        return self::$cache[$key] = $values;
    }

    public static function setGroup(string $key, array $values): void
    {
        $db = App::instance()->db;
        $existing = $db->fetch('SELECT setting_key FROM settings WHERE setting_key = ? LIMIT 1', [self::PREFIX . $key]);
        $json = json_encode($values, JSON_UNESCAPED_UNICODE);
        if ($existing === null) {
            $db->insert('settings', ['setting_key' => self::PREFIX . $key, 'value' => $json]);
        } else {
            $db->update('settings', ['value' => $json], ['setting_key' => self::PREFIX . $key]);
        }
        self::$cache[$key] = $values;
    }

    /**
     * Read a value by dot path: `option('contact.email')` returns
     * `getGroup('contact')['email']`. Supports nested paths into repeaters
     * and groups: `option('contact.social.0.url')`.
     */
    public static function getPath(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        if ($segments === []) return $default;

        $groupKey = array_shift($segments);
        if ($groupKey === '') return $default;

        $value = self::getGroup($groupKey);
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    /** Reset the in-process cache (useful between tests). */
    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
