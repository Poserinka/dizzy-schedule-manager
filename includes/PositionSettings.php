<?php

declare(strict_types=1);

namespace Dizzy\Schedule;

defined('ABSPATH') || exit;

final class PositionSettings
{
    private const OPTION = 'dizzy_schedule_employee_roles';

    /** @return array<int,string> */
    public function all(): array
    {
        $stored = get_option(self::OPTION, null);
        $positions = is_array($stored) ? $stored : [
            __('Bartender', 'dizzy-schedule-manager'),
            __('Kitchen', 'dizzy-schedule-manager'),
            __('Service', 'dizzy-schedule-manager'),
        ];

        $positions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => sanitize_text_field((string) $value),
            $positions
        ))));
        natcasesort($positions);

        return array_values($positions);
    }

    public function add(string $position): bool
    {
        $position = sanitize_text_field($position);

        if ($position === '') {
            return false;
        }

        $positions = $this->all();
        foreach ($positions as $existing) {
            if (strcasecmp($existing, $position) === 0) {
                return false;
            }
        }

        $positions[] = $position;
        return update_option(self::OPTION, array_values($positions), false);
    }

    public function delete(string $position): bool
    {
        $position = sanitize_text_field($position);
        $positions = array_values(array_filter(
            $this->all(),
            static fn (string $existing): bool => strcasecmp($existing, $position) !== 0
        ));

        return update_option(self::OPTION, $positions, false);
    }

    public function contains(string $position): bool
    {
        foreach ($this->all() as $existing) {
            if (strcasecmp($existing, sanitize_text_field($position)) === 0) {
                return true;
            }
        }

        return false;
    }
}
