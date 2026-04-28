<?php

declare(strict_types=1);

namespace Nano;

/**
 * UTM attribution.
 *
 * Captures the standard 5 UTM parameters (source, medium, campaign,
 * content, term) from incoming GET requests and persists them in the
 * session so a later form submission can be attributed to the campaign
 * that brought the visitor in.
 *
 * Behavior:
 *   - Captured on EVERY GET request that carries at least one utm_* param
 *   - The whole set is stored as one session entry (`utm`) — partial sets
 *     are fine; missing fields stay null
 *   - A new visit with utm_* params REPLACES previous values (last-touch
 *     attribution, the most common default)
 *   - Visits without utm_* params don't touch the session — the previously
 *     captured campaign continues to apply across navigation
 *
 * The 5 fields match Google Analytics conventions and cap at 255 chars
 * (matching the DB column width and GA's own limits).
 */
final class Utm
{
    public const FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
    private const SESSION_KEY = 'utm';
    private const MAX_LENGTH = 255;

    /**
     * Pull UTMs from the request query and persist if any are present.
     * No-op when no utm_* keys are in the URL — keeps existing session
     * values intact across regular navigation.
     */
    public static function captureFromRequest(Request $request, Session $session): void
    {
        if ($request->method !== 'GET') {
            return;
        }

        $captured = [];
        $sawAny = false;
        foreach (self::FIELDS as $field) {
            $raw = $request->query[$field] ?? null;
            if (!is_string($raw)) continue;
            $value = trim($raw);
            if ($value === '') continue;
            $sawAny = true;
            $captured[$field] = mb_substr($value, 0, self::MAX_LENGTH);
        }

        if (!$sawAny) {
            return;
        }

        // Last-touch: replace anything previously stored.
        $session->put(self::SESSION_KEY, $captured);
    }

    /**
     * Read the currently attributed UTM set. Returns an associative array
     * keyed by the 5 field names, with null for unset values. Always
     * returns all 5 keys so callers can `array_merge` with payloads
     * without checking each one.
     *
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_content: ?string, utm_term: ?string}
     */
    public static function fromSession(Session $session): array
    {
        $stored = (array) ($session->get(self::SESSION_KEY) ?? []);
        $out = [];
        foreach (self::FIELDS as $field) {
            $val = $stored[$field] ?? null;
            $out[$field] = is_string($val) && $val !== '' ? $val : null;
        }
        /** @var array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_content: ?string, utm_term: ?string} $out */
        return $out;
    }

    /**
     * Whether any UTM is currently attributed in the session.
     */
    public static function hasAny(Session $session): bool
    {
        $stored = (array) ($session->get(self::SESSION_KEY) ?? []);
        foreach (self::FIELDS as $field) {
            if (!empty($stored[$field])) return true;
        }
        return false;
    }

    /**
     * Forget the stored UTMs. Not used by core flows (we keep attribution
     * around indefinitely within the session lifetime), but exposed for
     * any custom theme code that wants to reset after a successful
     * conversion.
     */
    public static function clear(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
