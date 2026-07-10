<?php
declare(strict_types=1);

/**
 * GLPI Hotkeys Plugin - hooks installation/uninstallation
 *
 * @package GLPI Hotkeys Plugin
 * @author Juan Carlos Acosta Peraba
 * @license GPL-3.0-or-later
 */

/**
 * Installs the plugin and seeds default configuration values.
 *
 * @return bool
 */
function plugin_hotkeys_install(): bool {
    global $DB;

    // Retrieve existing configuration to support upgrades without overwriting settings
    $existing = [];
    $iterator = $DB->request([
        'FROM'  => 'glpi_configs',
        'WHERE' => ['context' => 'plugin:hotkeys']
    ]);

    foreach ($iterator as $row) {
        $existing[$row['name']] = $row['value'];
    }

    $defaults = [
        'smart_save_enabled' => '1',
        'smart_save_shortcut' => json_encode([
            'key' => 's',
            'ctrlOrMeta' => true,
            'alt' => false,
            'shift' => false
        ]),
        'force_save_enabled' => '1',
        'force_save_shortcut' => json_encode([
            'key' => 's',
            'ctrlOrMeta' => true,
            'alt' => true,
            'shift' => false
        ]),
        'feedback_enabled' => '1'
    ];

    foreach ($defaults as $name => $value) {
        if (!isset($existing[$name])) {
            $DB->insert('glpi_configs', [
                'context' => 'plugin:hotkeys',
                'name'    => $name,
                'value'   => $value
            ]);
        }
    }

    return true;
}

/**
 * Uninstalls the plugin, removing all configurations.
 *
 * @return bool
 */
function plugin_hotkeys_uninstall(): bool {
    global $DB;

    // Safe deletion of only the configurations belonging to this plugin
    $DB->delete('glpi_configs', ['context' => 'plugin:hotkeys']);

    return true;
}
