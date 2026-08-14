<?php

declare(strict_types=1);

namespace Dizzy\Schedule;

defined('ABSPATH') || exit;

final class Database
{
    public const VERSION = '1.0.0';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'dizzy_schedule_shifts';
    }

    public static function migrate(): void
    {
        if ((string) get_option('dizzy_schedule_db_version', '') === self::VERSION) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            employee_id bigint(20) unsigned NOT NULL,
            shift_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            break_minutes smallint(5) unsigned NOT NULL DEFAULT 0,
            position varchar(120) NOT NULL DEFAULT '',
            notes text NULL,
            status varchar(20) NOT NULL DEFAULT 'published',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY employee_date (employee_id,shift_date),
            KEY shift_date (shift_date),
            KEY status (status)
        ) {$charset};");

        update_option('dizzy_schedule_db_version', self::VERSION, false);
    }
}
