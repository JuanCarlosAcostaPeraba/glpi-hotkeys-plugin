<?php
declare(strict_types=1);

/**
 * GLPI Hotkeys Plugin - bootstrap setup
 *
 * @package GLPI Hotkeys Plugin
 * @author Juan Carlos Acosta Peraba
 * @license GPL-3.0-or-later
 */

define('PLUGIN_HOTKEYS_VERSION', '1.0.2');

/**
 * Returns metadata about the plugin.
 *
 * @return array
 */
function plugin_version_hotkeys(): array {
    return [
        'name'           => 'Hotkeys',
        'version'        => PLUGIN_HOTKEYS_VERSION,
        'author'         => 'Juan Carlos Acosta Peraba',
        'license'        => 'GPL-3.0-or-later',
        'homepage'       => 'https://github.com/JuanCarlosAcostaPeraba/glpi-hotkeys-plugin',
        'requirements'   => [
            'glpi' => [
                'min' => '11.0.0',
                'max' => '11.99.99'
            ],
            'php' => [
                'min' => '8.2.0'
            ]
        ]
    ];
}

/**
 * Initializes the hooks for the plugin.
 *
 * @return void
 */
function plugin_init_hotkeys(): void {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['hotkeys'] = true;
    $PLUGIN_HOOKS['config_page']['hotkeys'] = 'front/config.form.php';

    // Verify hooks classes exist in current GLPI runtime environment
    $js_hook = class_exists(\Glpi\Plugin\Hooks::class) && defined('\Glpi\Plugin\Hooks::ADD_JAVASCRIPT')
        ? \Glpi\Plugin\Hooks::ADD_JAVASCRIPT
        : 'add_javascript';

    $css_hook = class_exists(\Glpi\Plugin\Hooks::class) && defined('\Glpi\Plugin\Hooks::ADD_CSS')
        ? \Glpi\Plugin\Hooks::ADD_CSS
        : 'add_css';

    $header_tag_hook = class_exists(\Glpi\Plugin\Hooks::class) && defined('\Glpi\Plugin\Hooks::ADD_HEADER_TAG')
        ? \Glpi\Plugin\Hooks::ADD_HEADER_TAG
        : 'add_header_tag';

    // Register JS and CSS assets
    $PLUGIN_HOOKS[$js_hook]['hotkeys'] = ['js/hotkeys.js'];
    $PLUGIN_HOOKS[$css_hook]['hotkeys'] = ['css/hotkeys.css'];

    // Load config helper manually to ensure it's available for all user roles/sessions
    require_once __DIR__ . '/src/Config.php';
    $config = \GlpiPlugin\Hotkeys\Config::getSafeConfig();
    
    $PLUGIN_HOOKS[$header_tag_hook]['hotkeys'] = [
        [
            'tag'        => 'meta',
            'properties' => [
                'name'    => 'glpi-hotkeys-config',
                'content' => json_encode($config),
            ],
        ],
    ];
}

/**
 * Check if prerequisites are met.
 *
 * @return bool
 */
function plugin_hotkeys_check_prerequisites(): bool {
    if (version_compare(GLPI_VERSION, '11.0.0', '<')) {
        echo "This plugin requires GLPI 11.0.0 or higher.";
        return false;
    }
    if (version_compare(PHP_VERSION, '8.2.0', '<')) {
        echo "This plugin requires PHP 8.2.0 or higher.";
        return false;
    }
    return true;
}

/**
 * Checks config and returns true/false.
 *
 * @param bool $verbose
 * @return bool
 */
function plugin_hotkeys_check_config(bool $verbose = false): bool {
    if ($verbose) {
        echo "Config checks are valid.";
    }
    return true;
}
