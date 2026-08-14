<?php
/**
 * Plugin Name: Dizzy Schedule Manager
 * Plugin URI: https://github.com/Poserinka/dizzy-schedule-manager
 * Description: Private employee schedules and shift planning for WordPress.
 * Version: 2.1.1
 * Author: Poserinka Design
 * Text Domain: dizzy-schedule-manager
 * Requires PHP: 8.2
 * Update URI: https://github.com/Poserinka/dizzy-schedule-manager
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('DIZZY_SCHEDULE_VERSION', '2.1.1');
define('DIZZY_SCHEDULE_FILE', __FILE__);
define('DIZZY_SCHEDULE_PATH', plugin_dir_path(__FILE__));
define('DIZZY_SCHEDULE_URL', plugin_dir_url(__FILE__));

require_once DIZZY_SCHEDULE_PATH . 'includes/EmployeeRole.php';
require_once DIZZY_SCHEDULE_PATH . 'includes/Database.php';
require_once DIZZY_SCHEDULE_PATH . 'includes/ShiftRepository.php';
require_once DIZZY_SCHEDULE_PATH . 'includes/AjaxController.php';
require_once DIZZY_SCHEDULE_PATH . 'includes/PositionSettings.php';
require_once DIZZY_SCHEDULE_PATH . 'includes/Admin/SchedulePage.php';
require_once DIZZY_SCHEDULE_PATH . 'includes/Admin/SettingsPage.php';
require_once DIZZY_SCHEDULE_PATH . 'includes/GitHubUpdater.php';

register_activation_hook(__FILE__, static function (): void {
    \Dizzy\Schedule\EmployeeRole::activate();
    \Dizzy\Schedule\Database::migrate();
});

add_action('init', static function (): void {
    load_plugin_textdomain(
        'dizzy-schedule-manager',
        false,
        dirname(plugin_basename(DIZZY_SCHEDULE_FILE)) . '/languages'
    );
}, 5);

add_action('plugins_loaded', static function (): void {
    $role = new \Dizzy\Schedule\EmployeeRole();
    $role->register();
    add_action('init', [\Dizzy\Schedule\Database::class, 'migrate'], 6);

    $repository = new \Dizzy\Schedule\ShiftRepository();
    $positions = new \Dizzy\Schedule\PositionSettings();
    (new \Dizzy\Schedule\AjaxController($repository, $positions))->register();

    if (is_admin()) {
        (new \Dizzy\Schedule\Admin\SchedulePage($role, $positions))->register();
        (new \Dizzy\Schedule\Admin\SettingsPage($positions))->register();
    }
});

(new \Dizzy\Schedule\GitHubUpdater(
    __FILE__,
    'dizzy-schedule-manager',
    'Poserinka/dizzy-schedule-manager',
    DIZZY_SCHEDULE_VERSION
))->register();
