<?php

declare(strict_types=1);

namespace Dizzy\Schedule;

use RuntimeException;

defined('ABSPATH') || exit;

final class ShiftRepository
{
    public function between(string $from, string $to, ?int $employeeId = null): array
    {
        global $wpdb;
        $table = Database::table();
        $sql = "SELECT s.*,u.display_name,u.user_email
            FROM {$table} s
            INNER JOIN {$wpdb->users} u ON u.ID=s.employee_id
            WHERE s.shift_date BETWEEN %s AND %s";
        $args = [$from, $to];

        if ($employeeId !== null && $employeeId > 0) {
            $sql .= ' AND s.employee_id=%d';
            $args[] = $employeeId;
        }

        $sql .= ' ORDER BY s.shift_date,s.start_time,u.display_name,s.id';

        return $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . Database::table() . ' WHERE id=%d', $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function save(array $data): int
    {
        global $wpdb;
        $id = absint($data['id'] ?? 0);
        $record = [
            'employee_id' => absint($data['employee_id']),
            'shift_date' => (string) $data['shift_date'],
            'start_time' => (string) $data['start_time'],
            'end_time' => (string) $data['end_time'],
            'break_minutes' => absint($data['break_minutes'] ?? 0),
            'position' => sanitize_text_field((string) ($data['position'] ?? '')),
            'notes' => sanitize_textarea_field((string) ($data['notes'] ?? '')),
            'status' => 'published',
            'updated_at' => current_time('mysql'),
        ];

        if ($this->overlaps($record, $id)) {
            throw new RuntimeException(__('This employee already has an overlapping shift.', 'dizzy-schedule-manager'));
        }

        if ($id > 0) {
            if ($wpdb->update(Database::table(), $record, ['id' => $id]) === false) {
                throw new RuntimeException(__('The shift could not be updated.', 'dizzy-schedule-manager'));
            }
            return $id;
        }

        $record['created_by'] = get_current_user_id();
        $record['created_at'] = current_time('mysql');

        if ($wpdb->insert(Database::table(), $record) === false) {
            throw new RuntimeException(__('The shift could not be created.', 'dizzy-schedule-manager'));
        }

        return (int) $wpdb->insert_id;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete(Database::table(), ['id' => $id], ['%d']) === 1;
    }

    private function overlaps(array $record, int $ignoreId): bool
    {
        global $wpdb;
        $sql = 'SELECT COUNT(*) FROM ' . Database::table() . '
            WHERE employee_id=%d AND shift_date=%s
            AND start_time<%s AND end_time>%s';
        $args = [
            (int) $record['employee_id'],
            (string) $record['shift_date'],
            (string) $record['end_time'],
            (string) $record['start_time'],
        ];

        if ($ignoreId > 0) {
            $sql .= ' AND id<>%d';
            $args[] = $ignoreId;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$args)) > 0;
    }
}
