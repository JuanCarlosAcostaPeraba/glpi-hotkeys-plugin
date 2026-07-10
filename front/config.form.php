<?php
declare(strict_types=1);

/**
 * GLPI Hotkeys Plugin - Configuration form entry point
 *
 * @package GLPI Hotkeys Plugin
 * @author Juan Carlos Acosta Peraba
 * @license GPL-3.0-or-later
 */

define('GLPI_ROOT', '../../..');
include(GLPI_ROOT . '/inc/includes.php');

// Restrict access to administrators with config update permissions
Session::checkRight('config', UPDATE);

$config = new \GlpiPlugin\Hotkeys\Config();

if (isset($_POST['save'])) {
    // Validate and update configuration settings
    $errors = $config->updateConfig($_POST);
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            Session::addMessageAfterRedirect($error, false, ERROR);
        }
    }
    Html::back();
} elseif (isset($_POST['restore_defaults'])) {
    // Restore default settings
    $config->restoreDefaults();
    Html::back();
}

// Render the standard GLPI Page Headers
Html::header(
    __('Hotkeys Plugin Configuration', 'hotkeys'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

// Display the Twig-based configuration form
$config->showForm();

// Render Page Footers
Html::footer();
