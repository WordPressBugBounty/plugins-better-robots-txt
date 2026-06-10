<?php
namespace Pagup\BetterRobots\Controllers;

use Pagup\BetterRobots\Services\ConfigImportHistory;
use Pagup\BetterRobots\Services\ConfigImportValidator;
use Pagup\BetterRobots\Services\ConfigRecommendationResolver;

require_once dirname(__DIR__) . '/services/ConfigImportValidator.php';
require_once dirname(__DIR__) . '/services/ConfigRecommendationResolver.php';
require_once dirname(__DIR__) . '/services/ConfigImportHistory.php';

class ImportController
{
    private ConfigImportValidator $validator;
    private ConfigRecommendationResolver $resolver;
    private ConfigImportHistory $history;

    public function __construct()
    {
        $this->validator = new ConfigImportValidator();
        $this->resolver = new ConfigRecommendationResolver();
        $this->history = new ConfigImportHistory();
    }

    public function validate_config(): void
    {
        $this->guardAdminRequest();

        $validation = $this->validator->validateRaw($this->getConfigJsonFromRequest());
        $payload = $this->validationResponse($validation);
        $payload['history_state'] = $this->history->getPublicState();

        if (!$validation['valid']) {
            wp_send_json_error($payload, 400);
        }

        wp_send_json_success($payload);
    }

    public function preview_config(): void
    {
        $this->guardAdminRequest();

        $validation = $this->validator->validateRaw($this->getConfigJsonFromRequest());

        if (!$validation['valid']) {
            wp_send_json_error($this->validationResponse($validation), 400);
        }

        $config = $validation['data'];
        $currentOption = get_option('robots_txt');
        $resolved = $this->resolver->resolve($config, $currentOption);
        $robotsController = new RobotsController();
        $isPro = $this->hasProAccess();
        $previewWarnings = [];
        $currentRobots = $this->getCurrentRobotsPreview($currentOption, $previewWarnings);
        $proposedRobots = $isPro
            ? $robotsController->generate($resolved['proposed_settings'])
            : $robotsController->generateProPreview($resolved['proposed_settings']);
        $warnings = array_values(array_merge($validation['warnings'], $resolved['warnings'], $previewWarnings));

        if (!$isPro) {
            $warnings[] = 'The proposed robots.txt preview shows the Pro output that will be applied after upgrading. Your current robots.txt remains unchanged until a Pro user applies the import.';
        }

        wp_send_json_success([
            'validation' => $this->validationResponse($validation),
            'preview' => [
                'current_robots_txt' => $currentRobots,
                'proposed_robots_txt' => $proposedRobots,
                'applied' => $resolved['applied'],
                'skipped' => $resolved['skipped'],
                'warnings' => $warnings,
                'manual_followups' => $resolved['manual_followups'],
                'profile_scores' => $resolved['profile_scores'],
                'has_changes' => $resolved['has_changes'],
            ],
            'fresh_audit_url' => $this->buildFreshAuditUrl($config, (string) ($validation['domain']['site'] ?? '')),
            'history_state' => $this->history->getPublicState(),
        ]);
    }

    public function apply_config(): void
    {
        $this->guardAdminRequest();
        $this->requirePro();

        $validation = $this->validator->validateRaw($this->getConfigJsonFromRequest());

        if (!$validation['valid']) {
            wp_send_json_error($this->validationResponse($validation), 400);
        }

        if (empty($validation['can_apply'])) {
            wp_send_json_error([
                'message' => $validation['apply_blockers'][0] ?? 'This configuration cannot be applied.',
                'validation' => $this->validationResponse($validation),
            ], 409);
        }

        if (!empty($validation['domain']['mismatch']) && !$this->boolFromRequest('domain_mismatch_confirmed')) {
            wp_send_json_error([
                'message' => 'Please confirm that you want to apply recommendations generated for a different domain.',
                'code' => 'domain_mismatch_confirmation_required',
                'validation' => $this->validationResponse($validation),
            ], 409);
        }

        $config = $validation['data'];
        $currentOption = get_option('robots_txt');
        $resolved = $this->resolver->resolve($config, $currentOption);
        $hadPreviousSettings = is_array($currentOption);
        $previousSettings = is_array($currentOption) ? $currentOption : [];
        $proposedSettings = $resolved['proposed_settings'];

        $physicalPreflight = $this->validatePhysicalRobotsFileSync($proposedSettings);
        if (empty($physicalPreflight['ok'])) {
            wp_send_json_error($this->persistenceErrorPayload(
                (string) ($physicalPreflight['message'] ?? 'The physical robots.txt file could not be updated.'),
                (string) ($physicalPreflight['code'] ?? 'physical_file_error')
            ), 409);
        }

        if (!$this->saveSettingsOption($proposedSettings)) {
            wp_send_json_error($this->persistenceErrorPayload(
                'The recommendations could not be saved. Please try again.',
                'settings_save_failed'
            ), 500);
        }

        $physicalSync = $this->syncPhysicalRobotsFile($proposedSettings);
        if (empty($physicalSync['ok'])) {
            if ($hadPreviousSettings) {
                update_option('robots_txt', $previousSettings);
            } else {
                delete_option('robots_txt');
            }

            wp_send_json_error($this->persistenceErrorPayload(
                (string) ($physicalSync['message'] ?? 'The physical robots.txt file could not be updated.'),
                (string) ($physicalSync['code'] ?? 'physical_file_error')
            ), 500);
        }

        $backup = $this->history->createBackup($previousSettings, $proposedSettings, $config);
        $historyItem = $this->history->recordImport($config, $resolved, $validation, $backup);
        $robotsController = new RobotsController();

        wp_send_json_success([
            'message' => 'Audit recommendations applied successfully.',
            'settings' => $proposedSettings,
            'robots_txt' => $robotsController->generate($proposedSettings),
            'applied' => $resolved['applied'],
            'skipped' => $resolved['skipped'],
            'fresh_audit_url' => $this->buildFreshAuditUrl($config, (string) ($validation['domain']['site'] ?? '')),
            'history_item' => $historyItem,
            'history_state' => $this->history->getPublicState(),
            'physical_file' => $this->getPhysicalFileStatus(),
        ]);
    }

    public function rollback_config(): void
    {
        $this->guardAdminRequest();
        $this->requirePro();

        $backup = $this->history->getBackup();

        if (!$backup || !array_key_exists('settings', $backup) || !is_array($backup['settings'])) {
            wp_send_json_error([
                'message' => 'No import backup is available for rollback.',
            ], 404);
        }

        if ($this->history->isBackupExpired($backup)) {
            wp_send_json_error([
                'message' => 'The rollback window has expired.',
                'history_state' => $this->history->getPublicState(),
            ], 410);
        }

        $current = get_option('robots_txt');
        $hadCurrentSettings = is_array($current);
        $currentHash = is_array($current) ? $this->history->hashSettings($current) : '';
        $appliedHash = (string) ($backup['applied_settings_hash'] ?? '');

        if ($appliedHash !== '' && $currentHash !== '' && $currentHash !== $appliedHash && !$this->boolFromRequest('confirm_current_changes')) {
            wp_send_json_error([
                'message' => 'Settings changed after the import. Confirm rollback to restore the pre-import backup.',
                'code' => 'current_settings_changed',
                'history_state' => $this->history->getPublicState(),
            ], 409);
        }

        $restored = $backup['settings'];
        $physicalPreflight = $this->validatePhysicalRobotsFileSync($restored);
        if (empty($physicalPreflight['ok'])) {
            wp_send_json_error($this->persistenceErrorPayload(
                (string) ($physicalPreflight['message'] ?? 'The physical robots.txt file could not be updated.'),
                (string) ($physicalPreflight['code'] ?? 'physical_file_error')
            ), 409);
        }

        if (!$this->saveSettingsOption($restored)) {
            wp_send_json_error($this->persistenceErrorPayload(
                'The pre-import settings could not be restored. Please try again.',
                'settings_restore_failed'
            ), 500);
        }

        $physicalSync = $this->syncPhysicalRobotsFile($restored);
        if (empty($physicalSync['ok'])) {
            if ($hadCurrentSettings) {
                update_option('robots_txt', $current);
            } else {
                delete_option('robots_txt');
            }

            wp_send_json_error($this->persistenceErrorPayload(
                (string) ($physicalSync['message'] ?? 'The physical robots.txt file could not be updated.'),
                (string) ($physicalSync['code'] ?? 'physical_file_error')
            ), 500);
        }

        $historyItem = $this->history->recordRollback($backup, $currentHash);
        $this->history->consumeBackup();

        wp_send_json_success([
            'message' => 'Pre-import settings restored.',
            'settings' => $restored,
            'robots_txt' => $this->generateRobotsForSettings($restored),
            'history_item' => $historyItem,
            'history_state' => $this->history->getPublicState(),
            'physical_file' => $this->getPhysicalFileStatus(),
        ]);
    }

    private function guardAdminRequest(): void
    {
        if (check_ajax_referer('rt__nonce', 'nonce', false) == false) {
            wp_send_json_error(['message' => 'Invalid nonce'], 401);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized user'], 403);
        }
    }

    private function requirePro(): void
    {
        if (!$this->hasProAccess()) {
            wp_send_json_error([ 'message' => 'Import requires Better Robots.txt Pro.' ], 403);
        }
    }

    private function hasProAccess(): bool
    {
        return rtf_fs()->is__premium_only() && rtf_fs()->is_plan_or_trial('betterrobotstxtpro');
    }

    private function getConfigJsonFromRequest(): string
    {
        if (isset($_POST['config_json'])) {
            return (string) $_POST['config_json'];
        }

        if (!empty($_FILES['config_file']['tmp_name']) && is_uploaded_file($_FILES['config_file']['tmp_name'])) {
            $contents = file_get_contents($_FILES['config_file']['tmp_name']);
            return is_string($contents) ? $contents : '';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $validation
     * @return array<string, mixed>
     */
    private function validationResponse(array $validation): array
    {
        unset($validation['data']);
        return $validation;
    }

    private function boolFromRequest(string $key): bool
    {
        if (!isset($_POST[$key])) {
            return false;
        }

        $value = $_POST[$key];
        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }

    /**
     * Keep the physical robots.txt file aligned after import or rollback.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function syncPhysicalRobotsFile(array $settings): array
    {
        $robotsFilePath = ABSPATH . 'robots.txt';
        $preflight = $this->validatePhysicalRobotsFileSync($settings);

        if (empty($preflight['ok'])) {
            return $preflight;
        }

        if ($this->wantsPhysicalRobotsFile($settings)) {
            $content = $this->generateRobotsForSettings($settings);
            $result = file_put_contents($robotsFilePath, $content);

            if ($result === false) {
                return [
                    'ok' => false,
                    'code' => 'physical_file_write_failed',
                    'message' => 'The physical robots.txt file could not be written. Check file permissions and try again.',
                ];
            }

            update_option('robots_txt_physical_created_by_plugin', current_time('timestamp'));
            update_option('robots_txt_physical_file_hash', md5_file($robotsFilePath));

            return [
                'ok' => true,
                'action' => 'written',
            ];
        }

        if (file_exists($robotsFilePath) && $this->verifyFileOwnership($robotsFilePath)) {
            if (!unlink($robotsFilePath)) {
                return [
                    'ok' => false,
                    'code' => 'physical_file_delete_failed',
                    'message' => 'The plugin-owned physical robots.txt file could not be removed. Check file permissions and try again.',
                ];
            }

            delete_option('robots_txt_physical_created_by_plugin');
            delete_option('robots_txt_physical_file_hash');

            return [
                'ok' => true,
                'action' => 'deleted',
            ];
        }

        return [
            'ok' => true,
            'action' => 'none',
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function validatePhysicalRobotsFileSync(array $settings): array
    {
        $robotsFilePath = ABSPATH . 'robots.txt';

        if ($this->wantsPhysicalRobotsFile($settings)) {
            if (file_exists($robotsFilePath) && !$this->verifyFileOwnership($robotsFilePath)) {
                return [
                    'ok' => false,
                    'code' => 'physical_file_conflict',
                    'message' => 'A manually managed robots.txt file already exists in the site root. The import did not overwrite it.',
                ];
            }

            if (file_exists($robotsFilePath) && !is_writable($robotsFilePath)) {
                return [
                    'ok' => false,
                    'code' => 'physical_file_not_writable',
                    'message' => 'The physical robots.txt file is not writable. Check file permissions and try again.',
                ];
            }

            if (!file_exists($robotsFilePath) && !is_writable(dirname($robotsFilePath))) {
                return [
                    'ok' => false,
                    'code' => 'physical_file_directory_not_writable',
                    'message' => 'The site root is not writable, so a physical robots.txt file cannot be created.',
                ];
            }

            return [
                'ok' => true,
            ];
        }

        if (
            file_exists($robotsFilePath)
            && $this->verifyFileOwnership($robotsFilePath)
            && !is_writable($robotsFilePath)
            && !is_writable(dirname($robotsFilePath))
        ) {
            return [
                'ok' => false,
                'code' => 'physical_file_not_removable',
                'message' => 'The plugin-owned physical robots.txt file is not removable. Check file permissions and try again.',
            ];
        }

        return [
            'ok' => true,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function wantsPhysicalRobotsFile(array $settings): bool
    {
        if (isset($settings['mode_0']['robotstxt_infrastructure']['file_mode'])) {
            return $settings['mode_0']['robotstxt_infrastructure']['file_mode'] === 'physical_robotstxt';
        }

        if (isset($settings['mode_0']['global_settings']['robots_txt_type'])) {
            return $settings['mode_0']['global_settings']['robots_txt_type'] === 'physical';
        }

        if (isset($settings['create_physical_file'])) {
            return $settings['create_physical_file'] === 'yes';
        }

        return false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function saveSettingsOption(array $settings): bool
    {
        update_option('robots_txt', $settings);

        $saved = get_option('robots_txt');

        return is_array($saved) && $this->history->hashSettings($saved) === $this->history->hashSettings($settings);
    }

    /**
     * @return array<string, mixed>
     */
    private function persistenceErrorPayload(string $message, string $code): array
    {
        return [
            'message' => $message,
            'code' => $code,
            'history_state' => $this->history->getPublicState(),
            'physical_file' => $this->getPhysicalFileStatus(),
        ];
    }

    private function verifyFileOwnership(string $filePath): bool
    {
        if (!get_option('robots_txt_physical_created_by_plugin') || !file_exists($filePath)) {
            return false;
        }

        $fileContent = file_get_contents($filePath);
        $fileContent = is_string($fileContent) ? $fileContent : '';
        $hasSignature = strpos($fileContent, '# This robots.txt file was created by Better Robots.txt') !== false ||
            strpos($fileContent, '# Generated by Better Robots.txt') !== false;

        $storedHash = get_option('robots_txt_physical_file_hash');
        $currentHash = md5_file($filePath);

        return $hasSignature || ($storedHash && $storedHash === $currentHash);
    }

    /**
     * @return array<string, bool>
     */
    private function getPhysicalFileStatus(): array
    {
        $robotsFilePath = ABSPATH . 'robots.txt';
        $exists = file_exists($robotsFilePath);

        return [
            'exists' => $exists,
            'is_plugin_owned' => $exists ? $this->verifyFileOwnership($robotsFilePath) : false,
            'is_writable' => $exists ? (is_writable($robotsFilePath) || is_writable(dirname($robotsFilePath))) : false,
        ];
    }

    /**
     * Preview the current robots.txt exactly as the site is serving it now.
     *
     * Prefer a physical file from the site root when present, because that is
     * what visitors and crawlers receive. Fall back to the generated output
     * from the saved plugin settings when the site is using virtual output.
     *
     * @param mixed $settings
     * @param array<int, string> $warnings
     */
    private function getCurrentRobotsPreview($settings, array &$warnings): string
    {
        $robotsFilePath = ABSPATH . 'robots.txt';
        $liveRobots = $this->fetchLiveRobotsTxt();

        if ($liveRobots !== null) {
            if (file_exists($robotsFilePath) && !$this->verifyFileOwnership($robotsFilePath)) {
                $warnings[] = 'The current robots.txt preview is showing the live file served from the site root. Import apply will not overwrite a manually managed robots.txt file.';
            }

            return $liveRobots;
        }

        if (file_exists($robotsFilePath)) {
            $content = file_get_contents($robotsFilePath);

            if (is_string($content)) {
                if (!$this->verifyFileOwnership($robotsFilePath)) {
                    $warnings[] = 'The current robots.txt preview is showing the physical file found in the site root. Import apply will not overwrite a manually managed robots.txt file.';
                }

                return $content;
            }
        }

        return $this->generateRobotsForSettings(is_array($settings) ? $settings : []);
    }

    private function fetchLiveRobotsTxt(): ?string
    {
        $url = add_query_arg(
            [
                'better_robots_import_preview' => time(),
            ],
            home_url('/robots.txt')
        );

        $response = wp_safe_remote_get($url, [
            'timeout' => 5,
            'redirection' => 3,
            'headers' => [
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        return is_string($body) && $body !== '' ? $body : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildFreshAuditUrl(array $config, string $fallbackDomain = ''): string
    {
        $domain = $this->sanitizeAuditDomain((string) ($config['source']['domain_audited'] ?? ''));
        if ($domain === '') {
            $domain = $this->sanitizeAuditDomain($fallbackDomain);
        }

        $query = [
            'domain' => $domain,
        ];

        $profileId = trim((string) ($config['profile']['id'] ?? ''));
        if ($profileId !== '') {
            $query['profile'] = $profileId;
        }

        $query['fresh'] = '1';

        return esc_url_raw(add_query_arg($query, 'https://better-robots.com/check'));
    }

    private function sanitizeAuditDomain(string $domain): string
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

        return rtrim((string) $domain, '.');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function generateRobotsForSettings(array $settings): string
    {
        if (isset($settings['settings_version']) && $settings['settings_version'] === '3.0') {
            $robotsController = new RobotsController();
            return $robotsController->generate($settings);
        }

        $legacyController = new RobotsControllerLegacy();
        return $legacyController->generate_robots_content($settings);
    }
}
