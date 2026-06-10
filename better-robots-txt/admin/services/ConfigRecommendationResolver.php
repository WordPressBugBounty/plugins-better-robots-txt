<?php
namespace Pagup\BetterRobots\Services;

class ConfigRecommendationResolver
{
    private const LOCAL_CRAWL_DELAY_MAX = 86400;

    private const TARGET_MAP = [
        'Googlebot' => ['module' => 'search_engine_module', 'key' => 'googlebot'],
        'Bingbot' => ['module' => 'search_engine_module', 'key' => 'bingbot'],
        'OAI-SearchBot' => ['module' => 'ai_module', 'key' => 'oai_searchbot'],
        'Claude-SearchBot' => ['module' => 'ai_module', 'key' => 'claude_searchbot'],
        'PerplexityBot' => ['module' => 'ai_module', 'key' => 'perplexitybot'],
        'GPTBot' => ['module' => 'ai_module', 'key' => 'gptbot'],
        'ClaudeBot' => ['module' => 'ai_module', 'key' => 'claudebot'],
        'Google-Extended' => ['module' => 'ai_module', 'key' => 'google_extended'],
        'Applebot-Extended' => ['module' => 'ai_module', 'key' => 'applebot_extended'],
        'anthropic-ai' => ['module' => 'ai_module', 'key' => 'anthropic_ai'],
        'CCBot' => ['module' => 'ai_module', 'key' => 'ccbot'],
        'ChatGPT-User' => ['module' => 'ai_module', 'key' => 'chatgpt_user'],
        'Claude-User' => ['module' => 'ai_module', 'key' => 'claude_user'],
        'Perplexity-User' => ['module' => 'ai_module', 'key' => 'perplexity_user'],
    ];

    /**
     * Resolve a validated config into current/proposed v3 settings.
     *
     * @param array<string, mixed> $config
     * @param mixed $currentSettings
     * @return array<string, mixed>
     */
    public function resolve(array $config, $currentSettings = null): array
    {
        $current = $this->hydrateSettings(is_array($currentSettings) ? $currentSettings : get_option('robots_txt'));
        $proposed = $current;
        $applied = [];
        $skipped = [];
        $warnings = [];

        foreach (($config['recommendations'] ?? []) as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $before = $proposed;
            $result = $this->applyRecommendation($proposed, $recommendation, $warnings);

            if ($result['status'] === 'applied') {
                $result['changed'] = $before !== $proposed;
                $applied[] = $result;
            } else {
                $skipped[] = $result;
            }
        }

        return [
            'current_settings' => $current,
            'proposed_settings' => $proposed,
            'applied' => $applied,
            'skipped' => $skipped,
            'warnings' => $warnings,
            'manual_followups' => $this->arrayOfStrings($config['audit_findings']['issues_unfixable_by_plugin'] ?? []),
            'profile_scores' => [
                'before' => $config['audit_findings']['profile_fit_before'] ?? null,
                'after_expected' => $config['audit_findings']['profile_fit_after_expected'] ?? null,
                'classic' => $config['audit_findings']['classic_score'] ?? null,
            ],
            'has_changes' => $current !== $proposed,
        ];
    }

    /**
     * Hydrate any missing or legacy settings into a complete v3 shape.
     *
     * @param mixed $settings
     * @return array<string, mixed>
     */
    public function hydrateSettings($settings): array
    {
        $defaults = $this->defaultSettings();
        $settings = is_array($settings) ? $settings : [];
        $legacyPhysicalMode = isset($settings['create_physical_file']) && $settings['create_physical_file'] === 'yes';
        $hasV3FileMode = isset($settings['mode_0']['robotstxt_infrastructure']['file_mode']) ||
            isset($settings['mode_0']['global_settings']['robots_txt_type']);
        $hydrated = $this->mergeDeep($defaults, $settings);

        $hydrated['settings_version'] = '3.0';
        $hydrated['active_mode'] = 0;
        $hydrated['remove_settings'] = !empty($hydrated['remove_settings']);
        $hydrated['mode_0']['active_mode'] = 0;

        if ($legacyPhysicalMode && !$hasV3FileMode) {
            $hydrated['mode_0']['global_settings']['robots_txt_type'] = 'physical';
        }

        $this->normalizeLegacyBotKeys($hydrated);

        return $hydrated;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $recommendation
     * @param array<int, string> $warnings
     * @return array<string, mixed>
     */
    private function applyRecommendation(array &$settings, array $recommendation, array &$warnings): array
    {
        $type = (string) ($recommendation['type'] ?? '');

        switch ($type) {
            case 'ai_crawler_rule':
                return $this->applyAICrawlerRule($settings, $recommendation);
            case 'search_engine_policy':
                return $this->applySearchEnginePolicy($settings, $recommendation);
            case 'sitemap_policy':
                return $this->applySitemapPolicy($settings, $recommendation);
            case 'crawl_delay':
                return $this->applyCrawlDelay($settings, $recommendation, $warnings);
            case 'ai_signals':
                return $this->applyAISignals($settings, $recommendation, $warnings);
        }

        return [
            'status' => 'skipped',
            'id' => (string) ($recommendation['id'] ?? ''),
            'type' => $type,
            'label' => 'Unsupported recommendation',
            'reason' => 'This recommendation type is not supported by this importer.',
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $recommendation
     * @return array<string, mixed>
     */
    private function applyAICrawlerRule(array &$settings, array $recommendation): array
    {
        $target = (string) ($recommendation['target'] ?? '');
        $action = (string) ($recommendation['action'] ?? '');
        $mapping = self::TARGET_MAP[$target] ?? null;

        if (!$mapping || !in_array($action, ['allow', 'disallow'], true)) {
            return [
                'status' => 'skipped',
                'id' => (string) ($recommendation['id'] ?? ''),
                'type' => 'ai_crawler_rule',
                'target' => $target,
                'label' => $this->formatCrawlerRuleLabel($target, $action),
                'reason' => 'The bot target or action is not supported.',
            ];
        }

        $module = $mapping['module'];
        $key = $mapping['key'];
        $old = $settings['mode_0'][$module]['custom_bots'][$key] ?? 'global';

        $settings['mode_0'][$module]['custom_bots'][$key] = $action;
        $settings['mode_0'][$module]['show_advanced'] = true;

        return [
            'status' => 'applied',
            'id' => (string) ($recommendation['id'] ?? ''),
            'type' => 'ai_crawler_rule',
            'target' => $target,
            'action' => $action,
            'module' => $module,
            'path' => "mode_0.{$module}.custom_bots.{$key}",
            'from' => $old,
            'to' => $action,
            'label' => $this->formatCrawlerRuleLabel($target, $action),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $recommendation
     * @return array<string, mixed>
     */
    private function applySearchEnginePolicy(array &$settings, array $recommendation): array
    {
        $level = (string) ($recommendation['level'] ?? 'recommended');
        $old = $settings['mode_0']['search_engine_module']['preset_level'] ?? 'recommended';
        $settings['mode_0']['search_engine_module']['preset_level'] = $level;

        return [
            'status' => 'applied',
            'id' => (string) ($recommendation['id'] ?? ''),
            'type' => 'search_engine_policy',
            'level' => $level,
            'path' => 'mode_0.search_engine_module.preset_level',
            'from' => $old,
            'to' => $level,
            'label' => sprintf('Set search engine visibility to %s', $level),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $recommendation
     * @return array<string, mixed>
     */
    private function applySitemapPolicy(array &$settings, array $recommendation): array
    {
        $old = !empty($settings['mode_0']['global_settings']['sitemaps']['auto_detect_sitemap']);
        $settings['mode_0']['global_settings']['sitemaps']['auto_detect_sitemap'] = true;

        return [
            'status' => 'applied',
            'id' => (string) ($recommendation['id'] ?? ''),
            'type' => 'sitemap_policy',
            'action' => 'auto_detect',
            'detected_provider' => $recommendation['detected_provider'] ?? '',
            'path' => 'mode_0.global_settings.sitemaps.auto_detect_sitemap',
            'from' => $old,
            'to' => true,
            'label' => 'Enable automatic sitemap detection',
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $recommendation
     * @param array<int, string> $warnings
     * @return array<string, mixed>
     */
    private function applyCrawlDelay(array &$settings, array $recommendation, array &$warnings): array
    {
        $seconds = (int) ($recommendation['seconds'] ?? 0);
        $clamped = max(1, min(self::LOCAL_CRAWL_DELAY_MAX, $seconds));

        if ($seconds !== $clamped) {
            $warnings[] = sprintf('Crawl-delay was clamped from %d to %d seconds.', $seconds, $clamped);
        }

        $old = (int) ($settings['mode_0']['advanced_settings']['crawl_delay'] ?? 0);
        $settings['mode_0']['advanced_settings']['crawl_delay'] = $clamped;

        return [
            'status' => 'applied',
            'id' => (string) ($recommendation['id'] ?? ''),
            'type' => 'crawl_delay',
            'seconds' => $clamped,
            'path' => 'mode_0.advanced_settings.crawl_delay',
            'from' => $old,
            'to' => $clamped,
            'label' => sprintf('Set crawl-delay to %d seconds', $clamped),
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $recommendation
     * @return array<string, mixed>
     */
    private function applyAISignals(array &$settings, array $recommendation, array &$warnings): array
    {
        $feature = (string) ($recommendation['feature'] ?? '');
        $aiFiles = &$settings['mode_0']['ai_files_module'];

        switch ($feature) {
            case 'llms_txt':
                $old = !empty($aiFiles['llms_txt_enabled']);
                $aiFiles['llms_txt_enabled'] = true;

                if (trim((string) ($aiFiles['llms_txt_content'] ?? '')) === '') {
                    $warnings[] = 'llms.txt was enabled, but no content was imported. Paste the reviewed draft from better-robots.com/check before publishing /llms.txt.';
                }

                return [
                    'status' => 'applied',
                    'id' => (string) ($recommendation['id'] ?? ''),
                    'type' => 'ai_signals',
                    'feature' => $feature,
                    'action' => 'enable',
                    'path' => 'mode_0.ai_files_module.llms_txt_enabled',
                    'from' => $old,
                    'to' => true,
                    'label' => 'Enable llms.txt; add reviewed content before publishing',
                ];

            case 'ai_policy':
                $old = !empty($aiFiles['ai_policy_enabled']);
                $aiFiles['ai_policy_enabled'] = true;
                $this->ensureAIPolicySlug($aiFiles);

                if (trim((string) ($aiFiles['ai_policy_content'] ?? '')) === '') {
                    $warnings[] = 'AI usage policy was enabled, but no policy content was imported. Add reviewed content before publishing the policy page.';
                }

                return [
                    'status' => 'applied',
                    'id' => (string) ($recommendation['id'] ?? ''),
                    'type' => 'ai_signals',
                    'feature' => $feature,
                    'action' => 'enable',
                    'path' => 'mode_0.ai_files_module.ai_policy_enabled',
                    'from' => $old,
                    'to' => true,
                    'label' => 'Enable the AI usage policy module; add reviewed content before publishing',
                ];

            case 'ai_policy_pointer':
                $old = !empty($aiFiles['ai_policy_pointer_enabled']);
                $aiFiles['ai_policy_enabled'] = true;
                $aiFiles['ai_policy_pointer_enabled'] = true;
                $this->ensureAIPolicySlug($aiFiles);

                if (trim((string) ($aiFiles['ai_policy_content'] ?? '')) === '') {
                    $warnings[] = 'AI usage policy reference was enabled, but no policy content was imported. Add reviewed content before publishing the policy page or its robots.txt reference.';
                }

                return [
                    'status' => 'applied',
                    'id' => (string) ($recommendation['id'] ?? ''),
                    'type' => 'ai_signals',
                    'feature' => $feature,
                    'action' => 'enable',
                    'path' => 'mode_0.ai_files_module.ai_policy_pointer_enabled',
                    'from' => $old,
                    'to' => true,
                    'label' => 'Enable the AI usage policy reference; publish reviewed content before adding it to robots.txt',
                ];
        }

        return [
            'status' => 'skipped',
            'id' => (string) ($recommendation['id'] ?? ''),
            'type' => 'ai_signals',
            'feature' => $feature,
            'label' => 'Unsupported AI signal',
            'reason' => 'This AI signal feature is not supported by this importer.',
        ];
    }

    /**
     * @param array<string, mixed> $aiFiles
     */
    private function ensureAIPolicySlug(array &$aiFiles): void
    {
        if (empty($aiFiles['ai_policy_slug'])) {
            $aiFiles['ai_policy_slug'] = 'ai-usage-policy';
        }
    }

    private function formatCrawlerRuleLabel(string $target, string $action): string
    {
        if ($action === 'allow') {
            return sprintf('Allow %s at /', $target);
        }

        if ($action === 'disallow') {
            return sprintf('Disallow %s at /', $target);
        }

        return sprintf('Update %s crawler rule', $target);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function arrayOfStrings($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static function ($item) {
            return is_string($item) && trim($item) !== '';
        }));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function normalizeLegacyBotKeys(array &$settings): void
    {
        if (!isset($settings['mode_0']['ai_module']['custom_bots']) || !is_array($settings['mode_0']['ai_module']['custom_bots'])) {
            $settings['mode_0']['ai_module']['custom_bots'] = [];
            return;
        }

        $customBots = &$settings['mode_0']['ai_module']['custom_bots'];

        if (isset($customBots['anthropicbot']) && !isset($customBots['anthropic_ai'])) {
            $customBots['anthropic_ai'] = $customBots['anthropicbot'];
        }

        unset($customBots['anthropicbot']);
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function mergeDeep(array $target, array $source): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value) && isset($target[$key]) && is_array($target[$key]) && $this->isAssoc($value)) {
                $target[$key] = $this->mergeDeep($target[$key], $value);
                continue;
            }

            $target[$key] = $value;
        }

        return $target;
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(): array
    {
        return [
            'settings_version' => '3.0',
            'active_mode' => 0,
            'remove_settings' => false,
            'mode_0' => [
                'active_mode' => 0,
                'global_settings' => [
                    'robots_txt_type' => 'virtual',
                    'sitemaps' => [
                        'manual_sitemap_url' => '',
                        'auto_detect_sitemap' => true,
                    ],
                    'footer_signature' => [
                        'enabled' => true,
                        'custom_text' => '',
                    ],
                    'ssa_header_links' => [
                        'enabled' => false,
                    ],
                    'show_size_warning' => true,
                    'estimated_size' => 0,
                    'core_rules' => [
                        'block_wp_admin' => true,
                        'block_wp_includes' => true,
                        'block_readme' => true,
                        'block_xmlrpc' => true,
                        'block_login' => true,
                        'block_attachments' => true,
                        'block_disclaimer' => true,
                    ],
                ],
                'content_signals' => [
                    'enabled' => false,
                    'cloudflare_managed' => false,
                    'search' => 'not-set',
                    'ai_input' => 'not-set',
                    'ai_train' => 'not-set',
                ],
                'search_engine_module' => [
                    'preset_level' => 'recommended',
                    'show_advanced' => false,
                    'custom_bots' => [],
                ],
                'ai_module' => [
                    'ai_training_protection' => true,
                    'ai_search_policy' => 'allow_all',
                    'content_signals' => [
                        'enabled' => false,
                        'cloudflare_managed' => false,
                        'search' => 'not-set',
                        'ai_input' => 'not-set',
                        'ai_train' => 'not-set',
                    ],
                    'block_all_training_bots' => false,
                    'show_advanced' => false,
                    'custom_bots' => [],
                    'custom_ai_crawlers' => '',
                ],
                'seo_tools_module' => [
                    'block_basic_tools' => false,
                    'block_extra_tools' => false,
                    'show_advanced' => false,
                    'custom_bots' => [],
                ],
                'bad_bots_module' => [
                    'enabled' => false,
                    'use_full_list' => false,
                    'use_condensed' => false,
                ],
                'social_media_module' => [
                    'enabled' => false,
                    'show_advanced' => false,
                    'custom_bots' => [],
                ],
                'archive_module' => [
                    'policy' => 'allow',
                    'show_advanced' => false,
                    'custom_bots' => [],
                ],
                'spam_feeds_module' => [
                    'block_feeds_spam' => false,
                    'block_author_archives' => false,
                    'block_comment_spam' => false,
                ],
                'ecommerce_module' => [
                    'cleanup_level' => '',
                ],
                'crawl_traps_module' => [
                    'block_search' => false,
                    'block_trap_params' => false,
                ],
                'resources_module' => [
                    'allow_css_js' => true,
                    'allow_images' => true,
                ],
                'ads_module' => [
                    'allow_ads_txt' => true,
                    'allow_app_ads_txt' => true,
                ],
                'ai_files_module' => [
                    'llms_txt_enabled' => false,
                    'llms_txt_content' => '',
                    'ai_policy_enabled' => false,
                    'ai_policy_slug' => 'ai-usage-policy',
                    'ai_policy_content' => '',
                    'ai_policy_pointer_enabled' => false,
                    'ai_sitemap_enabled' => false,
                ],
                'advanced_settings' => [
                    'crawl_delay' => 0,
                    'custom_rules' => '',
                    'consolidate_user_agents' => false,
                    'ensure_search_engine_visibility' => true,
                ],
                'ai_readiness_score' => [
                    'total_score' => 0,
                    'max_possible' => 40,
                    'breakdown' => [
                        'content_signals' => 0,
                        'llms_txt' => 0,
                        'ai_policy' => 0,
                        'ai_module' => 0,
                        'ai_bots_configured' => 0,
                        'archive_blocked' => 0,
                        'crawl_traps' => 0,
                        'woocommerce_rules' => 0,
                        'bad_bots_full' => 0,
                    ],
                    'suggestions' => [],
                ],
            ],
            'mode_1' => [],
            'mode_2' => [],
            'mode_3' => [],
        ];
    }
}
