<?php

declare(strict_types=1);

namespace Dizzy\Schedule;

defined('ABSPATH') || exit;

final class EmployeeRole
{
    public const ROLE = 'dizzy_employee';
    public const VIEW_CAP = 'dizzy_view_schedule';
    public const MANAGE_CAP = 'dizzy_manage_schedule';

    public static function activate(): void
    {
        self::ensureRoleAndCapabilities();
    }

    public function register(): void
    {
        add_action('init', [self::class, 'ensureRoleAndCapabilities'], 5);
    }

    public static function ensureRoleAndCapabilities(): void
    {
        $role = get_role(self::ROLE);

        if ($role === null) {
            add_role(self::ROLE, __('Employee', 'dizzy-schedule-manager'), [
                'read' => true,
                self::VIEW_CAP => true,
            ]);
            $role = get_role(self::ROLE);
        }

        $role?->add_cap('read');
        $role?->add_cap(self::VIEW_CAP);
        get_role('administrator')?->add_cap(self::VIEW_CAP);
        get_role('administrator')?->add_cap(self::MANAGE_CAP);
    }

    public function isEmployee(): bool
    {
        return in_array(self::ROLE, (array) wp_get_current_user()->roles, true);
    }
}
