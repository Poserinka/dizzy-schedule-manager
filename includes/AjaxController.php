<?php

declare(strict_types=1);

namespace Dizzy\Schedule;

use RuntimeException;
use Throwable;

defined('ABSPATH') || exit;

final class AjaxController
{
    public function __construct(
        private ShiftRepository $repository,
        private PositionSettings $positions
    ) {
    }

    public function register(): void
    {
        add_action('wp_ajax_dizzy_schedule_list', [$this, 'listing']);
        add_action('wp_ajax_dizzy_schedule_save', [$this, 'save']);
        add_action('wp_ajax_dizzy_schedule_delete', [$this, 'delete']);
    }

    public function listing(): void
    {
        $this->authorizeView();
        $from = $this->dateParam('from');
        $to = $this->dateParam('to');

        if ($from > $to) {
            wp_send_json_error(['message' => __('Invalid schedule range.', 'dizzy-schedule-manager')], 400);
        }

        $employeeId = current_user_can(EmployeeRole::MANAGE_CAP)
            ? absint($_POST['employee_id'] ?? 0)
            : get_current_user_id();

        $rows = $this->repository->between($from, $to, $employeeId > 0 ? $employeeId : null);
        wp_send_json_success(array_map([$this, 'present'], $rows));
    }

    public function save(): void
    {
        $this->authorizeManage();

        $id = absint($_POST['id'] ?? 0);
        $employeeId = absint($_POST['employee_id'] ?? 0);
        $date = $this->dateParam('shift_date');
        $start = $this->timeParam('start_time');
        $end = $this->timeParam('end_time');

        if ($employeeId <= 0 || ! in_array(EmployeeRole::ROLE, (array) get_userdata($employeeId)?->roles, true)) {
            wp_send_json_error(['message' => __('Please select a valid Employee user.', 'dizzy-schedule-manager')], 400);
        }

        $crossesMidnight = $end <= $start;

        if ($crossesMidnight && ($start < '16:00:00' || $end > '02:00:00')) {
            wp_send_json_error([
                'message' => __('Overnight shifts must end no later than 02:00.', 'dizzy-schedule-manager'),
            ], 400);
        }

        $position = sanitize_text_field(wp_unslash((string) ($_POST['position'] ?? '')));

        if (! $this->positions->contains($position)) {
            wp_send_json_error(['message' => __('Please select a valid employee role.', 'dizzy-schedule-manager')], 400);
        }

        try {
            $savedId = $this->repository->save([
                'id' => $id,
                'employee_id' => $employeeId,
                'shift_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'break_minutes' => min(480, absint($_POST['break_minutes'] ?? 0)),
                'position' => $position,
                'notes' => wp_unslash((string) ($_POST['notes'] ?? '')),
            ]);
            wp_send_json_success(['id' => $savedId]);
        } catch (RuntimeException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 409);
        } catch (Throwable) {
            wp_send_json_error(['message' => __('Unexpected error while saving the shift.', 'dizzy-schedule-manager')], 500);
        }
    }

    public function delete(): void
    {
        $this->authorizeManage();
        $id = absint($_POST['id'] ?? 0);

        if ($id <= 0 || ! $this->repository->delete($id)) {
            wp_send_json_error(['message' => __('The shift could not be deleted.', 'dizzy-schedule-manager')], 400);
        }

        wp_send_json_success();
    }

    private function authorizeView(): void
    {
        check_ajax_referer('dizzy_schedule_admin', 'nonce');

        if (! current_user_can(EmployeeRole::VIEW_CAP)) {
            wp_send_json_error(['message' => __('Access denied.', 'dizzy-schedule-manager')], 403);
        }
    }

    private function authorizeManage(): void
    {
        check_ajax_referer('dizzy_schedule_admin', 'nonce');

        if (! current_user_can(EmployeeRole::MANAGE_CAP)) {
            wp_send_json_error(['message' => __('Only schedule managers can change shifts.', 'dizzy-schedule-manager')], 403);
        }
    }

    private function dateParam(string $key): string
    {
        $value = sanitize_text_field(wp_unslash((string) ($_POST[$key] ?? '')));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            wp_send_json_error(['message' => __('Invalid date.', 'dizzy-schedule-manager')], 400);
        }

        return $value;
    }

    private function timeParam(string $key): string
    {
        $value = sanitize_text_field(wp_unslash((string) ($_POST[$key] ?? '')));

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            wp_send_json_error(['message' => __('Invalid time.', 'dizzy-schedule-manager')], 400);
        }

        return $value . ':00';
    }

    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'employee_id' => (int) $row['employee_id'],
            'employee_name' => (string) $row['display_name'],
            'shift_date' => (string) $row['shift_date'],
            'start_time' => substr((string) $row['start_time'], 0, 5),
            'end_time' => substr((string) $row['end_time'], 0, 5),
            'break_minutes' => (int) $row['break_minutes'],
            'position' => (string) $row['position'],
            'notes' => (string) $row['notes'],
            'status' => (string) $row['status'],
        ];
    }
}
