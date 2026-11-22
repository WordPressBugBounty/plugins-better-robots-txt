<?php

/**
 * OAI-AdsBot advertising crawl policy, shared by the Free and Pro builds.
 *
 * One stored preference drives generation, preview, saving, import and the
 * admin diagnostics: option robots_txt, path mode_0.ads_module.oai_adsbot_policy,
 * values inherit | allow | block. An absent or invalid value reads as inherit and
 * is never written implicitly.
 *
 * - inherit: no managed group. The generated robots.txt is left byte-identical,
 *   so custom groups and the general "*" rules keep deciding.
 * - allow: one managed "User-agent: OAI-AdsBot" group carrying exactly the Allow
 *   and Disallow rules of the general "*" policy of the final file. A crawler with
 *   its own group ignores "*" (RFC 9309), so copying the rules keeps every site
 *   exclusion. A bare "Allow: /" is never added: a site closed by "Disallow: /"
 *   stays closed.
 * - block: "User-agent: OAI-AdsBot" + "Disallow: /". Refused while another group
 *   naming OAI-AdsBot carries an Allow rule, which would win for its paths.
 *
 * The setting is independent of GPTBot, OAI-SearchBot, ChatGPT-User, content
 * signals, WordPress search-engine visibility and ads.txt / app-ads.txt.
 *
 * @package Pagup\BetterRobots
 * @since 3.1.8
 */
namespace Pagup\BetterRobots\Config;

final class OAIAdsBotPolicy
{
    const USER_AGENT = 'OAI-AdsBot';
    const TOKEN = 'oai-adsbot';

    const INHERIT = 'inherit';
    const ALLOW = 'allow';
    const BLOCK = 'block';
    const POLICIES = [self::INHERIT, self::ALLOW, self::BLOCK];

    /** Nothing managed; the output is unchanged. */
    const STATUS_INHERIT = 'inherit';
    /** The managed group is published. */
    const STATUS_EMITTED = 'emitted';
    /** Another group contradicts the choice; nothing is published. */
    const STATUS_CONFLICT = 'conflict';
    /** The file cannot be interpreted with certainty; nothing is published. */
    const STATUS_INDETERMINATE = 'indeterminate';

    /** Crawlers reported next to OAI-AdsBot so an AdsBot change can be checked in isolation. */
    const RELATED_AGENTS = ['OAI-AdsBot', 'OAI-SearchBot', 'GPTBot', 'ChatGPT-User'];

    /**
     * @param mixed $value
     */
    public static function isValid($value): bool
    {
        return is_string($value) && in_array($value, self::POLICIES, true);
    }

    /**
     * Raw stored value, or null when the key is absent.
     *
     * @param mixed $settings Whole robots_txt option.
     * @return mixed
     */
    public static function storedValue($settings)
    {
        if (!is_array($settings) || !isset($settings['mode_0']) || !is_array($settings['mode_0'])) {
            return null;
        }

        $ads = $settings['mode_0']['ads_module'] ?? null;

        if (!is_array($ads) || !array_key_exists('oai_adsbot_policy', $ads)) {
            return null;
        }

        return $ads['oai_adsbot_policy'];
    }

    /**
     * Effective policy of a whole option: absent or invalid values read as inherit.
     *
     * @param mixed $settings
     */
    public static function fromSettings($settings): string
    {
        $value = self::storedValue($settings);

        return self::isValid($value) ? $value : self::INHERIT;
    }

    /**
     * Effective policy of a mode_0 array.
     *
     * @param mixed $mode0
     */
    public static function fromMode0($mode0): string
    {
        return self::fromSettings(['mode_0' => $mode0]);
    }

    /**
     * Whether the option explicitly stores a valid choice.
     *
     * @param mixed $settings
     */
    public static function isExplicit($settings): bool
    {
        return self::isValid(self::storedValue($settings));
    }

    /**
     * Readings of the file that make an OAI-AdsBot decision uncertain.
     *
     * - ambiguous_user_agent: a user-agent value mentions OAI-AdsBot without naming
     *   it as RFC 9309 reads it (for example "GPTBot, OAI-AdsBot").
     * - ambiguous_grouping: strict RFC 9309 parsers and parsers that end a run of
     *   user-agent lines at any other record group the general rules, or the
     *   OAI-AdsBot rules, differently.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function interpretationIssues(string $text): array
    {
        $issues = [];
        $strict = RobotsTxtRules::parse($text, RobotsTxtRules::MODE_STRICT);
        $lenient = RobotsTxtRules::parse($text, RobotsTxtRules::MODE_LENIENT);

        foreach ($strict['groups'] as $group) {
            foreach ($group['agents'] as $agent) {
                if ($agent['token'] !== self::TOKEN && stripos($agent['value'], self::USER_AGENT) !== false) {
                    $issues[] = [
                        'code' => 'ambiguous_user_agent',
                        'line' => $agent['line'],
                        'value' => 'User-agent: ' . $agent['value'],
                    ];
                }
            }
        }

        $generalDiffers = RobotsTxtRules::ruleKeys(RobotsTxtRules::rulesFor($strict, RobotsTxtRules::WILDCARD))
            !== RobotsTxtRules::ruleKeys(RobotsTxtRules::rulesFor($lenient, RobotsTxtRules::WILDCARD));
        $adsBotDiffers = RobotsTxtRules::ruleKeys(RobotsTxtRules::rulesFor($strict, self::TOKEN))
            !== RobotsTxtRules::ruleKeys(RobotsTxtRules::rulesFor($lenient, self::TOKEN));
        $adsBotGroupsDiffer = (count(RobotsTxtRules::groupsFor($strict, self::TOKEN)) > 0)
            !== (count(RobotsTxtRules::groupsFor($lenient, self::TOKEN)) > 0);

        if ($generalDiffers || $adsBotDiffers || $adsBotGroupsDiffer) {
            $issues[] = [
                'code' => 'ambiguous_grouping',
                'line' => 0,
                'value' => '',
            ];
        }

        return $issues;
    }

    /**
     * Plan the managed OAI-AdsBot group for the robots.txt text generated so far.
     *
     * @return array<string, mixed>
     */
    public static function plan(string $text, string $policy): array
    {
        $plan = [
            'policy' => self::isValid($policy) ? $policy : self::INHERIT,
            'status' => self::STATUS_INHERIT,
            'group' => '',
            'rules' => [],
            'conflicts' => [],
            'issues' => [],
        ];

        if ($plan['policy'] === self::INHERIT) {
            return $plan;
        }

        $issues = self::interpretationIssues($text);

        if (!empty($issues)) {
            $plan['status'] = self::STATUS_INDETERMINATE;
            $plan['issues'] = $issues;

            return $plan;
        }

        $parsed = RobotsTxtRules::parse($text, RobotsTxtRules::MODE_STRICT);
        // Allow and Disallow records above the first User-agent line belong to no
        // group, but the managed group is written above them and they would join
        // it: they count as OAI-AdsBot rules.
        $existing = array_merge(RobotsTxtRules::rulesFor($parsed, self::TOKEN), $parsed['orphans']);
        $lines = [];

        if ($plan['policy'] === self::ALLOW) {
            $general = [];

            foreach (RobotsTxtRules::rulesFor($parsed, RobotsTxtRules::WILDCARD) as $rule) {
                $general[RobotsTxtRules::ruleKey($rule)] = $rule;
            }

            foreach ($existing as $rule) {
                // An empty value matches nothing, so it cannot change a decision.
                if ($rule['path'] !== '' && !isset($general[RobotsTxtRules::ruleKey($rule)])) {
                    $plan['conflicts'][] = [
                        'code' => 'adsbot_rule_outside_general_policy',
                        'line' => $rule['line'],
                        'rule' => RobotsTxtRules::ruleLine($rule),
                    ];
                }
            }

            foreach ($general as $rule) {
                $lines[] = RobotsTxtRules::ruleLine($rule);
            }

            if (empty($lines)) {
                // No general rule: everything is allowed. An empty Disallow matches
                // nothing and closes the group so it cannot absorb the next one.
                $lines[] = 'Disallow:';
            }

            $comment = '# ChatGPT Ads (OAI-AdsBot): follows the general rules, site exclusions kept';
        } else {
            foreach ($existing as $rule) {
                if ($rule['type'] === 'allow' && $rule['path'] !== '') {
                    $plan['conflicts'][] = [
                        'code' => 'adsbot_allow_rule_overrides_block',
                        'line' => $rule['line'],
                        'rule' => RobotsTxtRules::ruleLine($rule),
                    ];
                }
            }

            $lines[] = 'Disallow: /';
            $comment = '# ChatGPT Ads (OAI-AdsBot): crawling disallowed';
        }

        if (!empty($plan['conflicts'])) {
            $plan['status'] = self::STATUS_CONFLICT;

            return $plan;
        }

        $plan['rules'] = $lines;
        $plan['group'] = $comment . "\n# Managed by Better Robots.txt\nUser-agent: " . self::USER_AGENT . "\n" . implode("\n", $lines) . "\n";
        $plan['status'] = self::STATUS_EMITTED;

        return $plan;
    }

    /**
     * Put the managed group at the top of the file when the plan publishes it.
     *
     * The top is the only position where no earlier line can join the group: a
     * dangling custom User-agent line or a record without a User-agent line can
     * only attach to a group that comes before it. Records above the first
     * User-agent line follow the group and join it; plan() counts them.
     *
     * @param array<string, mixed> $plan
     */
    public static function apply(string $text, array $plan): string
    {
        if (($plan['status'] ?? '') !== self::STATUS_EMITTED || ($plan['group'] ?? '') === '') {
            return $text;
        }

        return rtrim((string) $plan['group'], "\n") . "\n\n" . ltrim($text, "\n");
    }

    /**
     * Plan, write and check the managed group on the exact text that is served.
     *
     * $text must be final: nothing may transform the file after this call, so
     * the plan, the published group and the diagnostics all describe the same
     * bytes. When the written file fails verify(), nothing is published.
     *
     * @return array{plan: array<string, mixed>, content: string}
     */
    public static function publish(string $text, string $policy): array
    {
        $plan = self::plan($text, $policy);

        if (($plan['status'] ?? '') !== self::STATUS_EMITTED) {
            return ['plan' => $plan, 'content' => $text];
        }

        $content = self::apply($text, $plan);
        $failures = self::verify($text, $content, (string) $plan['policy']);

        if (empty($failures)) {
            return ['plan' => $plan, 'content' => $content];
        }

        $plan['status'] = self::STATUS_INDETERMINATE;
        $plan['group'] = '';
        $plan['rules'] = [];
        $plan['issues'] = [[
            'code' => 'ambiguous_grouping',
            'line' => 0,
            'value' => '',
            'check' => 'published_file',
            'details' => $failures,
        ]];

        return ['plan' => $plan, 'content' => $text];
    }

    /**
     * Check a written file against the preference, as RFC 9309 parsers read it.
     *
     * Compares the file without the managed group ($before) and with it ($after)
     * under strict and lenient grouping. Every other crawler must keep its group
     * and rules; OAI-AdsBot must get exactly the general rules (allow) or be
     * closed with no Allow exception (block). Empty Allow and Disallow values
     * match nothing and are left out of the comparison.
     *
     * @return array<int, string> Failed checks; empty when the file is right.
     */
    public static function verify(string $before, string $after, string $policy): array
    {
        $failures = [];

        foreach ([RobotsTxtRules::MODE_STRICT, RobotsTxtRules::MODE_LENIENT] as $mode) {
            $old = RobotsTxtRules::parse($before, $mode);
            $new = RobotsTxtRules::parse($after, $mode);
            $oldRules = self::rulesByToken($old);
            $newRules = self::rulesByToken($new);

            foreach (array_keys($oldRules + $newRules) as $token) {
                $token = (string) $token;

                if ($token === self::TOKEN) {
                    continue;
                }

                // A token present on one side only gained or lost its own group.
                if (!isset($oldRules[$token], $newRules[$token]) || $oldRules[$token] !== $newRules[$token]) {
                    $failures[] = $mode . ': the rules of ' . $token . ' changed';
                }
            }

            $adsBot = self::effectiveKeys(RobotsTxtRules::rulesFor($new, self::TOKEN));

            if ($policy === self::ALLOW) {
                if ($adsBot !== self::effectiveKeys(RobotsTxtRules::rulesFor($new, RobotsTxtRules::WILDCARD))) {
                    $failures[] = $mode . ': OAI-AdsBot does not get exactly the general rules';
                }
            } elseif ($policy === self::BLOCK) {
                if (!in_array('disallow /', $adsBot, true)) {
                    $failures[] = $mode . ': OAI-AdsBot is not disallowed at /';
                }

                foreach ($adsBot as $key) {
                    if (strpos($key, 'allow ') === 0) {
                        $failures[] = $mode . ': OAI-AdsBot keeps an Allow rule (' . $key . ')';
                    }
                }
            }
        }

        return $failures;
    }

    /**
     * Sorted unique rule keys of every product token that has its own group, in
     * one pass over the groups (the full bad-bot list names hundreds of tokens).
     *
     * @param array{groups: array<int, array<string, mixed>>} $parsed
     * @return array<string, array<int, string>>
     */
    private static function rulesByToken(array $parsed): array
    {
        $map = [];

        foreach ($parsed['groups'] as $group) {
            $keys = [];

            foreach ($group['rules'] as $rule) {
                $keys[RobotsTxtRules::ruleKey($rule)] = true;
            }

            foreach ($group['agents'] as $agent) {
                if ($agent['token'] === '') {
                    continue;
                }

                $map[$agent['token']] = ($map[$agent['token']] ?? []) + $keys;
            }
        }

        foreach ($map as $token => $keys) {
            $list = array_map('strval', array_keys($keys));
            sort($list, SORT_STRING);
            $map[$token] = $list;
        }

        return $map;
    }

    /**
     * Rule keys that can decide a path: empty Allow and Disallow values are dropped.
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, string>
     */
    private static function effectiveKeys(array $rules): array
    {
        return RobotsTxtRules::ruleKeys(array_values(array_filter(
            $rules,
            static function (array $rule): bool {
                return (string) $rule['path'] !== '';
            }
        )));
    }

    /**
     * Whether a plan can be saved or applied.
     *
     * @param array<string, mixed> $plan
     */
    public static function isPublishable(array $plan): bool
    {
        $status = $plan['status'] ?? self::STATUS_INHERIT;

        return $status !== self::STATUS_CONFLICT && $status !== self::STATUS_INDETERMINATE;
    }

    /**
     * Path examined by the diagnostics, from a path or a full URL.
     *
     * @param mixed $value
     */
    public static function normalizeExaminedPath($value): string
    {
        if (!is_string($value)) {
            return '/';
        }

        $value = trim($value);

        if ($value === '') {
            return '/';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) === 1) {
            $parts = parse_url($value);
            $path = is_array($parts) && isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
            $value = $path . (is_array($parts) && isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        $value = preg_replace('/[\x00-\x20\x7F]/', '', $value);
        $value = is_string($value) ? $value : '/';

        if ($value === '' || $value[0] !== '/') {
            $value = '/' . ltrim($value, '/');
        }

        return substr($value, 0, 2048);
    }

    /**
     * Report how the preference resolves, for the admin UI, saving and import.
     *
     * Declarative only: decisions come from the generated rules. The HTTP response
     * a crawler would receive and the access of an authenticated OpenAI crawler are
     * never measured here.
     *
     * @param mixed $settings Settings the content was generated from.
     * @param array<string, mixed> $plan Plan computed while generating.
     * @return array<string, mixed>
     */
    public static function report($settings, array $plan, string $content, string $path = '/'): array
    {
        $path = self::normalizeExaminedPath($path);
        $parsed = RobotsTxtRules::parse($content, RobotsTxtRules::MODE_STRICT);
        $agents = [];

        foreach (self::RELATED_AGENTS as $userAgent) {
            $agents[$userAgent] = self::describe($parsed, $userAgent, $path);
        }

        $managed = [];

        if (($plan['status'] ?? '') === self::STATUS_EMITTED) {
            $managed = (array) ($plan['rules'] ?? []);
        }

        return [
            'policy' => self::fromSettings($settings),
            'policy_explicit' => self::isExplicit($settings),
            'status' => (string) ($plan['status'] ?? self::STATUS_INHERIT),
            'publishable' => self::isPublishable($plan),
            'managed_group' => $managed,
            'conflicts' => array_values((array) ($plan['conflicts'] ?? [])),
            'issues' => array_values(array_merge(
                (array) ($plan['issues'] ?? []),
                ($plan['status'] ?? '') === self::STATUS_INDETERMINATE ? [] : self::interpretationIssues($content)
            )),
            'evaluated_path' => $path,
            'agents' => $agents,
            'general' => self::describe($parsed, RobotsTxtRules::WILDCARD, $path),
            'http_probe' => 'not_run',
            'verified_openai_access' => 'not_verified',
        ];
    }

    /**
     * @param array{groups: array<int, array<string, mixed>>} $parsed
     * @return array<string, mixed>
     */
    private static function describe(array $parsed, string $userAgent, string $path): array
    {
        if ($userAgent === RobotsTxtRules::WILDCARD) {
            $groups = RobotsTxtRules::groupsFor($parsed, RobotsTxtRules::WILDCARD);
            $applicable = [
                'policy_source' => empty($groups) ? 'default' : 'wildcard_fallback',
                'rules' => RobotsTxtRules::rulesFor($parsed, RobotsTxtRules::WILDCARD),
                'groups' => $groups,
            ];
        } else {
            $applicable = RobotsTxtRules::applicable($parsed, $userAgent);
        }

        $decision = RobotsTxtRules::decide($applicable['rules'], $path);
        $rule = $decision['rule'];
        $lines = [];

        foreach ($applicable['groups'] as $group) {
            $lines[] = (int) $group['line'];
        }

        return [
            'decision' => $decision['decision'],
            'policy_source' => $applicable['policy_source'],
            'matched_group_lines' => $lines,
            'winning_rule' => $rule === null ? null : [
                'type' => $rule['type'],
                'path' => $rule['path'],
                'line' => (int) $rule['line'],
                'text' => RobotsTxtRules::ruleLine($rule),
            ],
        ];
    }
}
