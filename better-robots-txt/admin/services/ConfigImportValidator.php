<?php
namespace Pagup\BetterRobots\Services;

class ConfigImportValidator
{
    private const MAX_FILE_BYTES = 262144;
    private const FORMAT = 'better-robots-config';
    private const FORMAT_VERSION = '1.0';
    private const TARGET_PLUGIN = 'better-robots-txt';
    private const LOCAL_CRAWL_DELAY_MAX = 86400;

    private const PROFILE_IDS = [
        'ai-search-open-training-restricted',
        'maximum-visibility',
        'publisher-protective',
        'wordpress-safe-default',
        'strict-privacy',
    ];

    private const RECOMMENDATION_TYPES = [
        'ai_crawler_rule',
        'ai_signals',
        'search_engine_policy',
        'sitemap_policy',
        'crawl_delay',
    ];

    private const TARGETS = [
        'Googlebot',
        'Bingbot',
        'OAI-SearchBot',
        'Claude-SearchBot',
        'PerplexityBot',
        'GPTBot',
        'ClaudeBot',
        'Google-Extended',
        'Applebot-Extended',
        'anthropic-ai',
        'CCBot',
        'ChatGPT-User',
        'Claude-User',
        'Perplexity-User',
    ];

    private const BOT_EVIDENCE_TOKENS = [
        'googlebot',
        'bingbot',
        'oai_searchbot',
        'claude_searchbot',
        'perplexitybot',
        'gptbot',
        'claudebot',
        'google_extended',
        'applebot_extended',
        'anthropic_ai',
        'ccbot',
        'chatgpt_user',
        'claude_user',
        'perplexity_user',
    ];

    private const STATIC_SOURCE_EVIDENCE = [
        'profile_ai_search_open',
        'profile_maximum_visibility',
        'profile_publisher_protective',
        'profile_strict_privacy',
        'profile_training_restricted',
        'profile_default_search_visibility',
        'profile_protective_rate_limiting',
        'no_llms_txt_detected_on_site',
        'no_ai_policy_detected_on_site',
        'ai_policy_not_referenced_in_robots_txt',
        'yoast_seo_plugin_detected',
        'aioseo_plugin_detected',
        'rankmath_plugin_detected',
        'sitemap_detected',
    ];

    /**
     * Validate a raw better-robots-config JSON payload.
     *
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public function validateRaw($raw): array
    {
        $result = [
            'valid' => false,
            'can_apply' => false,
            'errors' => [],
            'warnings' => [],
            'apply_blockers' => [],
            'data' => null,
            'domain' => [
                'audited' => '',
                'site' => $this->getCurrentSiteDomain(),
                'mismatch' => false,
            ],
            'version' => [
                'installed' => $this->getInstalledPluginVersion(),
                'required' => '',
                'requires_update' => false,
            ],
        ];

        if (!is_string($raw) || trim($raw) === '') {
            $result['errors'][] = 'No JSON configuration was provided.';
            return $result;
        }

        $raw = wp_unslash($raw);

        if (strlen($raw) > self::MAX_FILE_BYTES) {
            $result['errors'][] = 'The JSON file is too large. Maximum size is 256 KB.';
            return $result;
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $result['errors'][] = 'Invalid JSON: ' . json_last_error_msg();
            return $result;
        }

        $data = $this->normalizeLegacyPayload($data, $result);
        $this->validateEnvelope($data, $result);

        $source = $data['source'] ?? [];
        if (is_array($source)) {
            $audited = isset($source['domain_audited']) ? (string) $source['domain_audited'] : '';
            $site = $result['domain']['site'];
            $result['domain']['audited'] = $audited;
            $result['domain']['mismatch'] = $this->normalizeDomain($audited) !== '' &&
                $this->normalizeDomain($site) !== '' &&
                $this->normalizeDomain($audited) !== $this->normalizeDomain($site);

            if ($result['domain']['mismatch']) {
                $result['warnings'][] = sprintf(
                    'This file was generated for %s, but the current site is %s.',
                    $audited,
                    $site
                );
            }
        }

        $target = $data['target'] ?? [];
        if (is_array($target) && isset($target['plugin_min_version'])) {
            $required = (string) $target['plugin_min_version'];
            $installed = (string) $result['version']['installed'];
            $result['version']['required'] = $required;

            if ($required !== '' && $installed !== '' && version_compare($required, $installed, '>')) {
                $result['version']['requires_update'] = true;
                $message = sprintf(
                    'This audit file requires Better Robots.txt %s or newer. Installed version is %s.',
                    $required,
                    $installed
                );
                $result['warnings'][] = $message;
                $result['apply_blockers'][] = $message;
            }
        }

        $result['valid'] = empty($result['errors']);
        $result['can_apply'] = $result['valid'] && empty($result['apply_blockers']);
        $result['data'] = $result['valid'] ? $data : null;

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $result
     */
    private function validateEnvelope(array $data, array &$result): void
    {
        $required = [
            'format',
            'format_version',
            'generated_at',
            'provenance',
            'source',
            'profile',
            'target',
            'recommendations',
            'audit_findings',
        ];
        $allowed = array_merge(['$schema'], $required);

        $this->rejectUnknownKeys($data, $allowed, 'config', $result['errors']);

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                $result['errors'][] = "Missing required field: {$field}.";
            }
        }

        if (($data['format'] ?? null) !== self::FORMAT) {
            $result['errors'][] = 'Invalid format. Expected better-robots-config.';
        }

        if (($data['format_version'] ?? null) !== self::FORMAT_VERSION) {
            $result['errors'][] = 'Unsupported format_version. Expected 1.0.';
        }

        if (!empty($data['$schema']) && !is_string($data['$schema'])) {
            $result['errors'][] = 'Invalid $schema value.';
        } elseif (!array_key_exists('$schema', $data)) {
            $result['warnings'][] = 'The $schema field is missing. Current files from https://better-robots.com/check include it, but validation continued.';
        }

        if (!empty($data['generated_at']) && (!is_string($data['generated_at']) || strtotime($data['generated_at']) === false)) {
            $result['errors'][] = 'generated_at must be an ISO-8601 timestamp.';
        }

        $this->validateProvenance($data['provenance'] ?? null, $result['errors']);
        $this->validateSource($data['source'] ?? null, $result['errors']);
        $this->validateProfile($data['profile'] ?? null, $result['errors']);
        $this->validateTarget($data['target'] ?? null, $result['errors']);
        $this->validateAuditFindings($data['audit_findings'] ?? null, $data['profile']['id'] ?? null, $result['errors']);
        $this->validateRecommendations($data['recommendations'] ?? null, $result);
    }

    /**
     * @param mixed $provenance
     * @param array<int, string> $errors
     */
    private function validateProvenance($provenance, array &$errors): void
    {
        $required = [
            'publisher',
            'publisher_url',
            'doctrinal_framework',
            'framework_url',
            'tool',
            'tool_version',
            'rules_version',
            'verification',
        ];

        if (!is_array($provenance)) {
            $errors[] = 'provenance must be an object.';
            return;
        }

        $this->rejectUnknownKeys($provenance, $required, 'provenance', $errors);

        foreach ($required as $field) {
            if (!isset($provenance[$field]) || !is_string($provenance[$field]) || trim($provenance[$field]) === '') {
                $errors[] = "Missing or invalid provenance.{$field}.";
            }
        }

        if (isset($provenance['verification']) && !in_array($provenance['verification'], ['unverified', 'verified'], true)) {
            $errors[] = 'provenance.verification must be unverified or verified.';
        }
    }

    /**
     * Older beta exports used format_version 1.0 before rules_version was folded into
     * the public contract. Accept only that known generator window, and keep a warning.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function normalizeLegacyPayload(array $data, array &$result): array
    {
        if (($data['format'] ?? null) !== self::FORMAT || ($data['format_version'] ?? null) !== self::FORMAT_VERSION) {
            return $data;
        }

        $provenance = $data['provenance'] ?? null;

        if (!is_array($provenance)) {
            return $data;
        }

        $rulesVersion = $provenance['rules_version'] ?? null;

        if (is_string($rulesVersion) && trim($rulesVersion) !== '') {
            return $data;
        }

        $tool = $provenance['tool'] ?? null;
        $toolVersion = $provenance['tool_version'] ?? null;

        if ($tool !== 'better-robots-check' || !is_string($toolVersion) || !preg_match('/^\d+\.\d+\.\d+$/', $toolVersion)) {
            return $data;
        }

        if (version_compare($toolVersion, '2.14.1', '>=')) {
            return $data;
        }

        $data['provenance']['rules_version'] = 'legacy-missing';
        $result['warnings'][] = 'This file was generated before provenance.rules_version was required. Validation continued in legacy compatibility mode; generate a fresh audit at https://better-robots.com/check when possible.';

        return $data;
    }

    /**
     * @param mixed $source
     * @param array<int, string> $errors
     */
    private function validateSource($source, array &$errors): void
    {
        $required = ['domain_audited', 'stack_detected', 'wordpress_plugins_detected'];

        if (!is_array($source)) {
            $errors[] = 'source must be an object.';
            return;
        }

        $this->rejectUnknownKeys($source, $required, 'source', $errors);

        foreach (['domain_audited', 'stack_detected'] as $field) {
            if (!isset($source[$field]) || !is_string($source[$field]) || trim($source[$field]) === '') {
                $errors[] = "Missing or invalid source.{$field}.";
            }
        }

        if (!isset($source['wordpress_plugins_detected']) || !is_array($source['wordpress_plugins_detected'])) {
            $errors[] = 'source.wordpress_plugins_detected must be an array.';
        } else {
            foreach ($source['wordpress_plugins_detected'] as $plugin) {
                if (!is_string($plugin)) {
                    $errors[] = 'source.wordpress_plugins_detected must contain strings only.';
                    break;
                }
            }
        }
    }

    /**
     * @param mixed $profile
     * @param array<int, string> $errors
     */
    private function validateProfile($profile, array &$errors): void
    {
        $required = ['id', 'display_name_en', 'display_name_fr', 'version'];

        if (!is_array($profile)) {
            $errors[] = 'profile must be an object.';
            return;
        }

        $this->rejectUnknownKeys($profile, $required, 'profile', $errors);

        foreach ($required as $field) {
            if (!isset($profile[$field]) || !is_string($profile[$field]) || trim($profile[$field]) === '') {
                $errors[] = "Missing or invalid profile.{$field}.";
            }
        }

        if (isset($profile['id']) && !in_array($profile['id'], self::PROFILE_IDS, true)) {
            $errors[] = 'Unknown profile.id: ' . (string) $profile['id'];
        }
    }

    /**
     * @param mixed $target
     * @param array<int, string> $errors
     */
    private function validateTarget($target, array &$errors): void
    {
        $required = ['plugin', 'plugin_min_version'];

        if (!is_array($target)) {
            $errors[] = 'target must be an object.';
            return;
        }

        $this->rejectUnknownKeys($target, $required, 'target', $errors);

        if (($target['plugin'] ?? null) !== self::TARGET_PLUGIN) {
            $errors[] = 'target.plugin must be better-robots-txt.';
        }

        if (!isset($target['plugin_min_version']) || !is_string($target['plugin_min_version']) || trim($target['plugin_min_version']) === '') {
            $errors[] = 'target.plugin_min_version is required.';
        }
    }

    /**
     * @param mixed $audit
     * @param mixed $profileId
     * @param array<int, string> $errors
     */
    private function validateAuditFindings($audit, $profileId, array &$errors): void
    {
        $required = [
            'profile_id',
            'profile_fit_before',
            'profile_fit_after_expected',
            'classic_score',
            'issues_fixed',
            'issues_unfixable_by_plugin',
        ];

        if (!is_array($audit)) {
            $errors[] = 'audit_findings must be an object.';
            return;
        }

        $this->rejectUnknownKeys($audit, $required, 'audit_findings', $errors);

        foreach ($required as $field) {
            if (!array_key_exists($field, $audit)) {
                $errors[] = "Missing audit_findings.{$field}.";
            }
        }

        if (isset($audit['profile_id']) && !in_array($audit['profile_id'], self::PROFILE_IDS, true)) {
            $errors[] = 'Unknown audit_findings.profile_id: ' . (string) $audit['profile_id'];
        }

        if (is_string($profileId) && isset($audit['profile_id']) && $audit['profile_id'] !== $profileId) {
            $errors[] = 'audit_findings.profile_id must match profile.id.';
        }

        foreach (['profile_fit_before', 'profile_fit_after_expected', 'classic_score'] as $field) {
            if (array_key_exists($field, $audit) && $audit[$field] !== null && (!is_int($audit[$field]) || $audit[$field] < 0 || $audit[$field] > 100)) {
                $errors[] = "audit_findings.{$field} must be an integer from 0 to 100 or null.";
            }
        }

        foreach (['issues_fixed', 'issues_unfixable_by_plugin'] as $field) {
            if (!isset($audit[$field]) || !is_array($audit[$field])) {
                $errors[] = "audit_findings.{$field} must be an array.";
                continue;
            }

            foreach ($audit[$field] as $issue) {
                if (!is_string($issue)) {
                    $errors[] = "audit_findings.{$field} must contain strings only.";
                    break;
                }
            }
        }
    }

    /**
     * @param mixed $recommendations
     * @param array<string, mixed> $result
     */
    private function validateRecommendations($recommendations, array &$result): void
    {
        if (!is_array($recommendations)) {
            $result['errors'][] = 'recommendations must be an array.';
            return;
        }

        foreach ($recommendations as $index => $recommendation) {
            $path = "recommendations[{$index}]";

            if (!is_array($recommendation)) {
                $result['errors'][] = "{$path} must be an object.";
                continue;
            }

            $this->validateCommonRecommendationFields($recommendation, $path, $result['errors']);

            $type = $recommendation['type'] ?? '';
            if (!is_string($type) || !in_array($type, self::RECOMMENDATION_TYPES, true)) {
                $result['errors'][] = "{$path}.type is unknown: " . (is_scalar($type) ? (string) $type : 'non-string');
                continue;
            }

            switch ($type) {
                case 'ai_crawler_rule':
                    $this->validateAICrawlerRule($recommendation, $path, $result['errors']);
                    break;
                case 'ai_signals':
                    $this->validateAISignals($recommendation, $path, $result['errors']);
                    break;
                case 'search_engine_policy':
                    $this->validateSearchEnginePolicy($recommendation, $path, $result['errors']);
                    break;
                case 'sitemap_policy':
                    $this->validateSitemapPolicy($recommendation, $path, $result['errors']);
                    break;
                case 'crawl_delay':
                    $this->validateCrawlDelay($recommendation, $path, $result);
                    break;
            }
        }
    }

    /**
     * @param array<string, mixed> $recommendation
     * @param string $path
     * @param array<int, string> $errors
     */
    private function validateCommonRecommendationFields(array $recommendation, string $path, array &$errors): void
    {
        foreach (['id', 'type', 'confidence', 'source_evidence', 'rationale'] as $field) {
            if (!array_key_exists($field, $recommendation)) {
                $errors[] = "{$path}.{$field} is required.";
            }
        }

        if (isset($recommendation['id']) && (!is_string($recommendation['id']) || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $recommendation['id']))) {
            $errors[] = "{$path}.id must be kebab-case.";
        }

        if (isset($recommendation['confidence']) && !in_array($recommendation['confidence'], ['high', 'medium'], true)) {
            $errors[] = "{$path}.confidence must be high or medium.";
        }

        if (isset($recommendation['rationale']) && !is_string($recommendation['rationale'])) {
            $errors[] = "{$path}.rationale must be a string.";
        }

        if (!isset($recommendation['source_evidence']) || !is_array($recommendation['source_evidence'])) {
            $errors[] = "{$path}.source_evidence must be an array.";
            return;
        }

        foreach ($recommendation['source_evidence'] as $evidence) {
            if (!is_string($evidence) || !$this->isKnownSourceEvidence($evidence)) {
                $errors[] = "{$path}.source_evidence contains an unknown token: " . (is_scalar($evidence) ? (string) $evidence : 'non-string');
            }
        }
    }

    /**
     * @param array<string, mixed> $recommendation
     * @param string $path
     * @param array<int, string> $errors
     */
    private function validateAICrawlerRule(array $recommendation, string $path, array &$errors): void
    {
        $this->rejectUnknownKeys(
            $recommendation,
            ['id', 'type', 'target', 'action', 'scope', 'confidence', 'source_evidence', 'rationale'],
            $path,
            $errors
        );

        if (!isset($recommendation['target']) || !is_string($recommendation['target']) || !in_array($recommendation['target'], self::TARGETS, true)) {
            $errors[] = "{$path}.target is not supported by this importer.";
        }

        if (($recommendation['scope'] ?? null) !== '/') {
            $errors[] = "{$path}.scope must be /.";
        }

        if (!isset($recommendation['action']) || !in_array($recommendation['action'], ['allow', 'disallow'], true)) {
            $errors[] = "{$path}.action must be allow or disallow.";
        }
    }

    /**
     * @param array<string, mixed> $recommendation
     * @param string $path
     * @param array<int, string> $errors
     */
    private function validateAISignals(array $recommendation, string $path, array &$errors): void
    {
        $this->rejectUnknownKeys(
            $recommendation,
            ['id', 'type', 'feature', 'action', 'confidence', 'source_evidence', 'rationale'],
            $path,
            $errors
        );

        if (!isset($recommendation['feature']) || !in_array($recommendation['feature'], ['llms_txt', 'ai_policy', 'ai_policy_pointer'], true)) {
            $errors[] = "{$path}.feature is unknown.";
        }

        if (($recommendation['action'] ?? null) !== 'enable') {
            $errors[] = "{$path}.action must be enable.";
        }
    }

    /**
     * @param array<string, mixed> $recommendation
     * @param string $path
     * @param array<int, string> $errors
     */
    private function validateSearchEnginePolicy(array $recommendation, string $path, array &$errors): void
    {
        $this->rejectUnknownKeys(
            $recommendation,
            ['id', 'type', 'level', 'confidence', 'source_evidence', 'rationale'],
            $path,
            $errors
        );

        if (!isset($recommendation['level']) || !in_array($recommendation['level'], ['minimal', 'recommended', 'extended'], true)) {
            $errors[] = "{$path}.level must be minimal, recommended, or extended.";
        }
    }

    /**
     * @param array<string, mixed> $recommendation
     * @param string $path
     * @param array<int, string> $errors
     */
    private function validateSitemapPolicy(array $recommendation, string $path, array &$errors): void
    {
        $this->rejectUnknownKeys(
            $recommendation,
            ['id', 'type', 'action', 'detected_provider', 'confidence', 'source_evidence', 'rationale'],
            $path,
            $errors
        );

        if (($recommendation['action'] ?? null) !== 'auto_detect') {
            $errors[] = "{$path}.action must be auto_detect.";
        }

        if (isset($recommendation['detected_provider']) && !in_array($recommendation['detected_provider'], ['yoast-seo', 'aioseo', 'rankmath'], true)) {
            $errors[] = "{$path}.detected_provider is unknown.";
        }
    }

    /**
     * @param array<string, mixed> $recommendation
     * @param string $path
     * @param array<string, mixed> $result
     */
    private function validateCrawlDelay(array $recommendation, string $path, array &$result): void
    {
        $this->rejectUnknownKeys(
            $recommendation,
            ['id', 'type', 'seconds', 'confidence', 'source_evidence', 'rationale'],
            $path,
            $result['errors']
        );

        if (!isset($recommendation['seconds']) || !is_int($recommendation['seconds'])) {
            $result['errors'][] = "{$path}.seconds must be an integer.";
            return;
        }

        if ($recommendation['seconds'] < 1) {
            $result['errors'][] = "{$path}.seconds must be at least 1.";
            return;
        }

        if ($recommendation['seconds'] > self::LOCAL_CRAWL_DELAY_MAX) {
            $result['warnings'][] = "{$path}.seconds is above the local maximum and will be clamped to " . self::LOCAL_CRAWL_DELAY_MAX . '.';
        }
    }

    private function isKnownSourceEvidence(string $evidence): bool
    {
        if (in_array($evidence, self::STATIC_SOURCE_EVIDENCE, true)) {
            return true;
        }

        $tokens = implode('|', array_map('preg_quote', self::BOT_EVIDENCE_TOKENS));
        return preg_match('/^existing_(' . $tokens . ')_(allowed|blocked)_inconsistent_with_profile$/', $evidence) === 1;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $allowed
     * @param string $path
     * @param array<int, string> $errors
     */
    private function rejectUnknownKeys(array $data, array $allowed, string $path, array &$errors): void
    {
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $allowed, true)) {
                $errors[] = "{$path} contains unknown field: {$key}.";
            }
        }
    }

    private function getCurrentSiteDomain(): string
    {
        $host = parse_url(home_url('/'), PHP_URL_HOST);
        return is_string($host) ? $host : '';
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return '';
        }

        if (strpos($domain, '://') !== false) {
            $host = parse_url($domain, PHP_URL_HOST);
            $domain = is_string($host) ? $host : '';
        } else {
            $domain = preg_replace('/[\/?#].*$/', '', $domain);
        }

        $domain = preg_replace('/:\d+$/', '', (string) $domain);
        $domain = rtrim($domain, '.');

        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    private function getInstalledPluginVersion(): string
    {
        $pluginFile = defined('ROBOTS_PLUGIN_ROOT')
            ? ROBOTS_PLUGIN_ROOT . 'better-robots-txt.php'
            : dirname(__DIR__, 2) . '/better-robots-txt.php';

        if (!is_readable($pluginFile)) {
            return '';
        }

        $contents = file_get_contents($pluginFile);

        if (!is_string($contents)) {
            return '';
        }

        if (preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/m', $contents, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
