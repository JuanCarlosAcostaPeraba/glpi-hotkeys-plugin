<?php
declare(strict_types=1);

namespace GlpiPlugin\Hotkeys;

use Config as GlpiConfig;
use Glpi\Application\View\TemplateRenderer;
use Session;

class Config {
    /**
     * Default configurations.
     */
    public const DEFAULTS = [
        'smart_save_enabled' => '1',
        'smart_save_shortcut' => '{"key":"s","ctrlOrMeta":true,"alt":false,"shift":false}',
        'force_save_enabled' => '1',
        'force_save_shortcut' => '{"key":"s","ctrlOrMeta":true,"alt":true,"shift":false}',
        'feedback_enabled' => '1'
    ];

    /**
     * Get validated plugin configuration.
     *
     * @return array
     */
    public static function getSafeConfig(): array {
        $config = [];
        
        // Fetch raw values from DB using GLPI Config wrapper
        $db_values = GlpiConfig::getConfigurationValues('plugin:hotkeys');
        
        foreach (self::DEFAULTS as $key => $default_val) {
            $val = $db_values[$key] ?? $default_val;
            
            // Normalize values based on expected type
            if ($key === 'smart_save_enabled' || $key === 'force_save_enabled' || $key === 'feedback_enabled') {
                $config[$key] = ($val === '1' || $val === 1 || $val === true) ? 1 : 0;
            } else {
                // For shortcuts, validate JSON structure. If invalid, use default.
                if (!self::validateShortcutJson((string)$val)) {
                    $config[$key] = json_decode($default_val, true);
                } else {
                    $config[$key] = json_decode((string)$val, true);
                }
            }
        }

        // Add translation keys for JavaScript alerts
        $config['locales'] = [
            'saving_ticket' => __('Saving ticket...', 'hotkeys'),
            'saving_task'   => __('Saving task...', 'hotkeys'),
            'saving'        => __('Saving...', 'hotkeys'),
        ];

        return $config;
    }

    /**
     * Save configuration changes from POST data.
     *
     * @param array $post_data
     * @return array Array of error messages, empty if success
     */
    public function updateConfig(array $post_data): array {
        $errors = [];

        // 1. Validate Smart Save toggles and shortcut
        $smart_enabled = isset($post_data['smart_save_enabled']) ? '1' : '0';
        $smart_shortcut_raw = $post_data['smart_save_shortcut'] ?? '';

        // 2. Validate Force Save toggles and shortcut
        $force_enabled = isset($post_data['force_save_enabled']) ? '1' : '0';
        $force_shortcut_raw = $post_data['force_save_shortcut'] ?? '';

        // 3. Validate Visual Feedback toggle
        $feedback_enabled = isset($post_data['feedback_enabled']) ? '1' : '0';

        // Shortcut validations
        if (!self::validateShortcutJson($smart_shortcut_raw, $smart_error)) {
            $errors[] = __('Smart save: ', 'hotkeys') . $smart_error;
        }
        if (!self::validateShortcutJson($force_shortcut_raw, $force_error)) {
            $errors[] = __('Force-save ticket: ', 'hotkeys') . $force_error;
        }

        if (empty($errors)) {
            $smart_shortcut = json_decode($smart_shortcut_raw, true);
            $force_shortcut = json_decode($force_shortcut_raw, true);

            // Check if they are identical
            if (self::isIdenticalShortcut($smart_shortcut, $force_shortcut)) {
                $errors[] = __('Smart save and force-save ticket shortcuts must be different', 'hotkeys');
            }
        }

        if (empty($errors)) {
            GlpiConfig::setConfigurationValues('plugin:hotkeys', [
                'smart_save_enabled'  => $smart_enabled,
                'smart_save_shortcut' => $smart_shortcut_raw,
                'force_save_enabled'  => $force_enabled,
                'force_save_shortcut' => $force_shortcut_raw,
                'feedback_enabled'    => $feedback_enabled
            ]);
            
            Session::addMessageAfterRedirect(__('Settings saved successfully', 'hotkeys'), true, INFO);
        }

        return $errors;
    }

    /**
     * Restore configuration to factory defaults.
     *
     * @return void
     */
    public function restoreDefaults(): void {
        GlpiConfig::setConfigurationValues('plugin:hotkeys', self::DEFAULTS);
        Session::addMessageAfterRedirect(__('Default settings restored', 'hotkeys'), true, INFO);
    }

    /**
     * Validates a shortcut JSON string.
     *
     * @param string $json
     * @param string|null $error_msg Output variable for validation failure explanation
     * @return bool
     */
    public static function validateShortcutJson(string $json, ?string &$error_msg = null): bool {
        $data = json_decode($json, true);
        if ($data === null || !is_array($data)) {
            $error_msg = __('Shortcut cannot be empty', 'hotkeys');
            return false;
        }

        $required_keys = ['key', 'ctrlOrMeta', 'alt', 'shift'];
        foreach ($required_keys as $k) {
            if (!isset($data[$k])) {
                $error_msg = __('Shortcut format is invalid', 'hotkeys');
                return false;
            }
        }

        $key = trim((string)$data['key']);
        $ctrl = (bool)$data['ctrlOrMeta'];
        $alt = (bool)$data['alt'];
        $shift = (bool)$data['shift'];

        if ($key === '') {
            $error_msg = __('Shortcut cannot be empty', 'hotkeys');
            return false;
        }

        // Must require Ctrl or Cmd (ctrlOrMeta)
        if (!$ctrl) {
            $error_msg = __('Shortcut must require at least one modifier key (Ctrl or Cmd)', 'hotkeys');
            return false;
        }

        // Cannot be modifier-only (e.g. key is Control, Alt, Shift, Meta)
        $lower_key = strtolower($key);
        $modifiers = ['control', 'shift', 'alt', 'meta', 'command'];
        if (in_array($lower_key, $modifiers, true)) {
            $error_msg = __('Modifier-only shortcuts are not allowed', 'hotkeys');
            return false;
        }

        // Block Ctrl/Cmd + Shift + S
        if ($ctrl && $shift && $lower_key === 's') {
            $error_msg = __('Ctrl/Cmd + Shift + S is blocked to prevent conflicts', 'hotkeys');
            return false;
        }

        // Dangerous/reserved browser shortcuts
        $dangerous = ['w', 't', 'n', 'q', 'r', 'f', 'p', 'h', 'o'];
        if ($ctrl && !$alt && !$shift && in_array($lower_key, $dangerous, true)) {
            $error_msg = __('This shortcut is reserved or dangerous', 'hotkeys');
            return false;
        }

        return true;
    }

    /**
     * Compare two shortcut arrays to check if they are identical.
     *
     * @param array $a
     * @param array $b
     * @return bool
     */
    private static function isIdenticalShortcut(array $a, array $b): bool {
        return strtolower((string)$a['key']) === strtolower((string)$b['key']) &&
               (bool)$a['ctrlOrMeta'] === (bool)$b['ctrlOrMeta'] &&
               (bool)$a['alt'] === (bool)$b['alt'] &&
               (bool)$a['shift'] === (bool)$b['shift'];
    }

    /**
     * Display configuration form using Twig templates.
     *
     * @return void
     */
    public function showForm(): void {
        TemplateRenderer::getInstance()->display('@hotkeys/config.html.twig', [
            'config' => self::getSafeConfig()
        ]);
    }
}
