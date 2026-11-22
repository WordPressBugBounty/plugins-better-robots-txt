<?php

/**
 * Minimal robots.txt reader following RFC 9309.
 *
 * It never fetches anything. It reads text that the plugin generated or that an
 * administrator typed, groups the records, selects the group(s) that apply to a
 * crawler and evaluates a path with the most specific match, Allow winning ties.
 *
 * Two grouping modes are available:
 * - strict: RFC 9309 section 2.2. A user-agent line opens a new group only once
 *   the current group has received an Allow or Disallow rule. Other records
 *   (Crawl-delay, Content-signal, unknown records) never close a group, and a
 *   Sitemap record is global.
 * - lenient: parsers that end a run of user-agent lines at any other record,
 *   Sitemap included (Google's open-source parser reads files this way). Rules
 *   that follow still belong to the open group.
 *
 * When both readings disagree for a crawler, the file is ambiguous for it.
 *
 * @package Pagup\BetterRobots
 * @since 3.1.8
 */
namespace Pagup\BetterRobots\Config;

final class RobotsTxtRules
{
    const MODE_STRICT = 'strict';
    const MODE_LENIENT = 'lenient';

    const WILDCARD = '*';

    /**
     * Parse robots.txt text into groups.
     *
     * @return array{groups: array<int, array<string, mixed>>, orphans: array<int, array<string, mixed>>}
     */
    public static function parse(string $text, string $mode = self::MODE_STRICT): array
    {
        $groups = [];
        $orphans = [];
        $current = null;
        $closed = false;
        $lines = preg_split('/\r\n|\r|\n/', $text);

        if (!is_array($lines)) {
            $lines = [];
        }

        foreach ($lines as $index => $rawLine) {
            $lineNumber = $index + 1;
            $line = (string) $rawLine;
            $hash = strpos($line, '#');

            if ($hash !== false) {
                $line = substr($line, 0, $hash);
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $key = strtolower(trim(substr($line, 0, $colon)));
            $value = trim(substr($line, $colon + 1));

            if ($key === 'user-agent') {
                if ($current === null || $closed) {
                    $groups[] = [
                        'agents' => [],
                        'rules' => [],
                        'others' => [],
                        'line' => $lineNumber,
                    ];
                    $current = count($groups) - 1;
                    $closed = false;
                }

                $groups[$current]['agents'][] = [
                    'value' => $value,
                    'token' => self::productToken($value),
                    'line' => $lineNumber,
                ];
                continue;
            }

            if ($key === 'allow' || $key === 'disallow') {
                $rule = [
                    'type' => $key,
                    'path' => $value,
                    'line' => $lineNumber,
                ];

                if ($current === null) {
                    $orphans[] = $rule;
                    continue;
                }

                $groups[$current]['rules'][] = $rule;
                $closed = true;
                continue;
            }

            if ($key === 'sitemap') {
                // Global record. The lenient reading still ends the user-agent run.
                if ($current !== null && $mode === self::MODE_LENIENT) {
                    $closed = true;
                }

                continue;
            }

            if ($current === null) {
                continue;
            }

            $groups[$current]['others'][] = [
                'key' => $key,
                'value' => $value,
                'line' => $lineNumber,
            ];

            if ($mode === self::MODE_LENIENT) {
                $closed = true;
            }
        }

        return [
            'groups' => $groups,
            'orphans' => $orphans,
        ];
    }

    /**
     * Product token of a user-agent line value, lower-cased.
     *
     * RFC 9309 matches the crawler's product token case-insensitively. Only the
     * leading run of letters, underscores and hyphens is a token, so a value such
     * as "OAI-AdsBot/1.0" names OAI-AdsBot while "GPTBot, OAI-AdsBot" names GPTBot.
     */
    public static function productToken(string $value): string
    {
        $value = trim($value);

        if ($value === self::WILDCARD) {
            return self::WILDCARD;
        }

        if (preg_match('/^[A-Za-z_-]+/', $value, $match) === 1) {
            return strtolower($match[0]);
        }

        return '';
    }

    /**
     * Groups naming the given token (use "*" for the general groups).
     *
     * @param array{groups: array<int, array<string, mixed>>} $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function groupsFor(array $parsed, string $token): array
    {
        $token = $token === self::WILDCARD ? self::WILDCARD : strtolower($token);
        $matches = [];

        foreach ($parsed['groups'] as $group) {
            foreach ($group['agents'] as $agent) {
                if ($agent['token'] === $token) {
                    $matches[] = $group;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Rules of every group naming the token, combined as RFC 9309 requires.
     *
     * @param array{groups: array<int, array<string, mixed>>} $parsed
     * @return array<int, array<string, mixed>>
     */
    public static function rulesFor(array $parsed, string $token): array
    {
        $rules = [];

        foreach (self::groupsFor($parsed, $token) as $group) {
            foreach ($group['rules'] as $rule) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Rules that apply to a crawler, with where they come from.
     *
     * policy_source is explicit_group when a group names the crawler,
     * wildcard_fallback when only the general "*" group(s) apply, and default
     * when no group applies at all (everything is allowed).
     *
     * @param array{groups: array<int, array<string, mixed>>} $parsed
     * @return array{policy_source: string, rules: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>}
     */
    public static function applicable(array $parsed, string $userAgent): array
    {
        $token = self::productToken($userAgent);
        $groups = $token !== '' ? self::groupsFor($parsed, $token) : [];

        if (!empty($groups)) {
            $source = 'explicit_group';
        } else {
            $groups = self::groupsFor($parsed, self::WILDCARD);
            $source = empty($groups) ? 'default' : 'wildcard_fallback';
        }

        $rules = [];

        foreach ($groups as $group) {
            foreach ($group['rules'] as $rule) {
                $rules[] = $rule;
            }
        }

        return [
            'policy_source' => $source,
            'rules' => $rules,
            'groups' => $groups,
        ];
    }

    /**
     * Decide a path against a rule set: the longest matching pattern wins and an
     * Allow rule wins a tie. An empty pattern matches nothing.
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array{decision: string, rule: array<string, mixed>|null}
     */
    public static function decide(array $rules, string $path): array
    {
        $path = self::normalize($path);
        $best = null;
        $bestLength = -1;

        foreach ($rules as $rule) {
            $pattern = (string) ($rule['path'] ?? '');

            if ($pattern === '' || !self::matches($pattern, $path)) {
                continue;
            }

            $length = strlen(self::normalize($pattern));

            if (
                $best === null
                || $length > $bestLength
                || ($length === $bestLength && $rule['type'] === 'allow' && $best['type'] !== 'allow')
            ) {
                $best = $rule;
                $bestLength = $length;
            }
        }

        return [
            'decision' => ($best === null || $best['type'] === 'allow') ? 'allow' : 'disallow',
            'rule' => $best,
        ];
    }

    /**
     * Whether a robots.txt path pattern matches a normalized path.
     * "*" matches any sequence of characters and a final "$" anchors the end.
     */
    public static function matches(string $pattern, string $path): bool
    {
        $pattern = self::normalize($pattern);
        $anchored = substr($pattern, -1) === '$';

        if ($anchored) {
            $pattern = substr($pattern, 0, -1);
        }

        $regex = '';

        foreach (explode('*', $pattern) as $position => $part) {
            if ($position > 0) {
                $regex .= '.*';
            }

            $regex .= preg_quote($part, '#');
        }

        return preg_match('#^' . $regex . ($anchored ? '$' : '') . '#s', $path) === 1;
    }

    /**
     * Percent-encoding normalization applied to paths and patterns before they
     * are compared (RFC 9309 section 2.2.2 with RFC 3986): bytes outside printable
     * ASCII are encoded, encoded unreserved characters are decoded and the
     * remaining escapes use upper-case hexadecimal digits.
     */
    public static function normalize(string $value): string
    {
        $encoded = preg_replace_callback(
            '/[^\x21-\x7E]/',
            static function (array $match): string {
                return '%' . strtoupper(bin2hex($match[0]));
            },
            $value
        );

        $encoded = is_string($encoded) ? $encoded : $value;

        $decoded = preg_replace_callback(
            '/%([0-9A-Fa-f]{2})/',
            static function (array $match): string {
                $character = chr((int) hexdec($match[1]));

                if (preg_match('/^[A-Za-z0-9\-._~]$/', $character) === 1) {
                    return $character;
                }

                return '%' . strtoupper($match[1]);
            },
            $encoded
        );

        return is_string($decoded) ? $decoded : $encoded;
    }

    /**
     * Sorted unique "type path" keys of a rule set, used to compare rule sets.
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, string>
     */
    public static function ruleKeys(array $rules): array
    {
        $keys = [];

        foreach ($rules as $rule) {
            $keys[self::ruleKey($rule)] = true;
        }

        $keys = array_keys($keys);
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param array<string, mixed> $rule
     */
    public static function ruleKey(array $rule): string
    {
        return $rule['type'] . ' ' . $rule['path'];
    }

    /**
     * Canonical robots.txt line of a rule.
     *
     * @param array<string, mixed> $rule
     */
    public static function ruleLine(array $rule): string
    {
        $directive = $rule['type'] === 'allow' ? 'Allow' : 'Disallow';

        return $rule['path'] === '' ? $directive . ':' : $directive . ': ' . $rule['path'];
    }
}
