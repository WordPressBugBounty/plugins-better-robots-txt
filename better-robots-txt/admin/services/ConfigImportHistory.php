<?php
namespace Pagup\BetterRobots\Services;

class ConfigImportHistory
{
    private const BACKUP_OPTION = 'robots_txt_backup_pre_import';
    private const HISTORY_OPTION = 'robots_txt_import_history';
    private const ROLLBACK_WINDOW_SECONDS = 604800;
    private const HISTORY_LIMIT = 20;

    /**
     * @param array<string, mixed> $previousSettings
     * @param array<string, mixed> $appliedSettings
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function createBackup(array $previousSettings, array $appliedSettings, array $config): array
    {
        $now = time();
        $backup = [
            'id' => 'import_' . gmdate('YmdHis', $now) . '_' . wp_generate_password(6, false, false),
            'created_at' => gmdate('c', $now),
            'expires_at' => gmdate('c', $now + self::ROLLBACK_WINDOW_SECONDS),
            'user_id' => get_current_user_id(),
            'source_domain' => (string) ($config['source']['domain_audited'] ?? ''),
            'profile_id' => (string) ($config['profile']['id'] ?? ''),
            'settings' => $previousSettings,
            'previous_settings_hash' => $this->hashSettings($previousSettings),
            'applied_settings_hash' => $this->hashSettings($appliedSettings),
        ];

        update_option(self::BACKUP_OPTION, $backup, false);

        return $this->publicBackup($backup);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $resolved
     * @param array<string, mixed> $validation
     * @param array<string, mixed> $backup
     * @return array<string, mixed>
     */
    public function recordImport(array $config, array $resolved, array $validation, array $backup): array
    {
        $now = time();
        $item = [
            'type' => 'import',
            'imported_at' => gmdate('c', $now),
            'user_id' => get_current_user_id(),
            'source_domain' => (string) ($config['source']['domain_audited'] ?? ''),
            'site_domain' => (string) ($validation['domain']['site'] ?? ''),
            'domain_mismatch' => !empty($validation['domain']['mismatch']),
            'profile_id' => (string) ($config['profile']['id'] ?? ''),
            'profile_display_name' => (string) ($config['profile']['display_name_en'] ?? ''),
            'profile_fit_before' => $config['audit_findings']['profile_fit_before'] ?? null,
            'profile_fit_after_expected' => $config['audit_findings']['profile_fit_after_expected'] ?? null,
            'classic_score' => $config['audit_findings']['classic_score'] ?? null,
            'tool_version' => (string) ($config['provenance']['tool_version'] ?? ''),
            'rules_version' => (string) ($config['provenance']['rules_version'] ?? ''),
            'recommendations_imported' => $this->recommendationIds($resolved['applied'] ?? []),
            'recommendations_skipped' => $this->skippedRecommendations($resolved['skipped'] ?? []),
            'issues_fixed' => $this->arrayOfStrings($config['audit_findings']['issues_fixed'] ?? []),
            'issues_unfixable_by_plugin' => $this->arrayOfStrings($config['audit_findings']['issues_unfixable_by_plugin'] ?? []),
            'plugin_version' => $this->getInstalledPluginVersion(),
            'rollback_expires_at' => (string) ($backup['expires_at'] ?? ''),
            'backup_id' => (string) ($backup['id'] ?? ''),
        ];

        $history = $this->getHistory();
        array_unshift($history, $item);
        $history = array_slice($history, 0, self::HISTORY_LIMIT);
        update_option(self::HISTORY_OPTION, $history, false);

        return $item;
    }

    /**
     * @param array<string, mixed> $backup
     * @param string $currentHashBeforeRollback
     * @return array<string, mixed>
     */
    public function recordRollback(array $backup, string $currentHashBeforeRollback): array
    {
        $item = [
            'type' => 'rollback',
            'rolled_back_at' => gmdate('c'),
            'user_id' => get_current_user_id(),
            'backup_id' => (string) ($backup['id'] ?? ''),
            'source_domain' => (string) ($backup['source_domain'] ?? ''),
            'profile_id' => (string) ($backup['profile_id'] ?? ''),
            'current_settings_hash_before_rollback' => $currentHashBeforeRollback,
            'restored_settings_hash' => (string) ($backup['previous_settings_hash'] ?? ''),
            'plugin_version' => $this->getInstalledPluginVersion(),
        ];

        $history = $this->getHistory();
        array_unshift($history, $item);
        $history = array_slice($history, 0, self::HISTORY_LIMIT);
        update_option(self::HISTORY_OPTION, $history, false);

        return $item;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(): array
    {
        $history = get_option(self::HISTORY_OPTION, []);

        return is_array($history) ? array_values($history) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicState(): array
    {
        $backup = $this->getBackup();

        return [
            'history' => $this->getHistory(),
            'backup' => $backup ? $this->publicBackup($backup) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBackup()
    {
        $backup = get_option(self::BACKUP_OPTION, null);

        if (!is_array($backup)) {
            return null;
        }

        return $backup;
    }

    public function consumeBackup(): void
    {
        delete_option(self::BACKUP_OPTION);
    }

    public function isBackupExpired(array $backup): bool
    {
        $expires = strtotime((string) ($backup['expires_at'] ?? ''));

        return !$expires || time() > $expires;
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function hashSettings(array $settings): string
    {
        return md5((string) wp_json_encode($settings));
    }

    /**
     * @param array<string, mixed> $backup
     * @return array<string, mixed>
     */
    private function publicBackup(array $backup): array
    {
        $current = get_option('robots_txt');
        $currentHash = is_array($current) ? $this->hashSettings($current) : '';
        $appliedHash = (string) ($backup['applied_settings_hash'] ?? '');

        return [
            'id' => (string) ($backup['id'] ?? ''),
            'created_at' => (string) ($backup['created_at'] ?? ''),
            'expires_at' => (string) ($backup['expires_at'] ?? ''),
            'user_id' => (int) ($backup['user_id'] ?? 0),
            'source_domain' => (string) ($backup['source_domain'] ?? ''),
            'profile_id' => (string) ($backup['profile_id'] ?? ''),
            'can_rollback' => !$this->isBackupExpired($backup),
            'current_settings_changed' => $appliedHash !== '' && $currentHash !== '' && $currentHash !== $appliedHash,
        ];
    }

    /**
     * @param mixed $applied
     * @return array<int, string>
     */
    private function recommendationIds($applied): array
    {
        if (!is_array($applied)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item) {
            return is_array($item) ? (string) ($item['id'] ?? '') : '';
        }, $applied)));
    }

    /**
     * @param mixed $skipped
     * @return array<int, array<string, string>>
     */
    private function skippedRecommendations($skipped): array
    {
        if (!is_array($skipped)) {
            return [];
        }

        return array_values(array_map(static function ($item) {
            if (!is_array($item)) {
                return [
                    'id' => '',
                    'reason' => 'Unknown skipped recommendation.',
                ];
            }

            return [
                'id' => (string) ($item['id'] ?? ''),
                'reason' => (string) ($item['reason'] ?? ''),
            ];
        }, $skipped));
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
