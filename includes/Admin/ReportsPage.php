<?php

declare(strict_types=1);

namespace Dizzy\Schedule\Admin;

use DateTimeImmutable;
use Dizzy\Schedule\EmployeeRole;
use Dizzy\Schedule\ShiftRepository;

defined('ABSPATH') || exit;

final class ReportsPage
{
    public const SLUG = 'dizzy-schedule-reports';
    private string $pageHook = '';

    public function __construct(private ShiftRepository $repository)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_init', [$this, 'suppressThirdPartyNotices'], 999);
        add_filter('admin_body_class', [$this, 'bodyClass']);
    }

    public function menu(): void
    {
        $this->pageHook = (string) add_submenu_page(
            SchedulePage::SLUG,
            __('Schedule Reports', 'dizzy-schedule-manager'),
            __('Reports', 'dizzy-schedule-manager'),
            EmployeeRole::MANAGE_CAP,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function assets(string $hook): void
    {
        if ($hook !== $this->pageHook) {
            return;
        }

        wp_enqueue_style(
            'dizzy-schedule-admin',
            DIZZY_SCHEDULE_URL . 'assets/admin.css',
            [],
            DIZZY_SCHEDULE_VERSION
        );
    }

    public function suppressThirdPartyNotices(): void
    {
        if (! $this->isRequest()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');
    }

    public function bodyClass(string $classes): string
    {
        return $this->isRequest() ? $classes . ' dizzy-schedule-admin-page' : $classes;
    }

    public function render(): void
    {
        if (! current_user_can(EmployeeRole::MANAGE_CAP)) {
            wp_die(esc_html__('You are not allowed to view schedule reports.', 'dizzy-schedule-manager'));
        }

        $range = sanitize_key(wp_unslash((string) ($_GET['range'] ?? 'week')));
        $range = in_array($range, ['week', 'month'], true) ? $range : 'week';
        $anchor = $this->anchorDate();
        [$from, $to] = $this->period($anchor, $range);
        $rows = $this->summaries($this->repository->between(
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        ));

        $step = $range === 'month' ? 'month' : 'week';
        $previous = $anchor->modify('-1 ' . $step);
        $next = $anchor->modify('+1 ' . $step);
        $totalMinutes = array_sum(array_column($rows, 'minutes'));
        $totalShifts = array_sum(array_column($rows, 'shifts'));
        ?>
        <div class="wrap dizzy-schedule-reports">
            <header class="dizzy-reports-heading">
                <div>
                    <h1><?php esc_html_e('Schedule Reports', 'dizzy-schedule-manager'); ?></h1>
                    <p><?php esc_html_e('Scheduled hours and shifts by employee.', 'dizzy-schedule-manager'); ?></p>
                </div>
            </header>

            <div class="dizzy-reports-toolbar">
                <a class="button" href="<?php echo esc_url($this->url($range, $previous)); ?>" aria-label="<?php esc_attr_e('Previous period', 'dizzy-schedule-manager'); ?>">‹</a>
                <a class="button" href="<?php echo esc_url($this->url($range, $next)); ?>" aria-label="<?php esc_attr_e('Next period', 'dizzy-schedule-manager'); ?>">›</a>
                <form method="get">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <select name="range" onchange="this.form.submit()">
                        <option value="week" <?php selected($range, 'week'); ?>><?php esc_html_e('Weekly', 'dizzy-schedule-manager'); ?></option>
                        <option value="month" <?php selected($range, 'month'); ?>><?php esc_html_e('Monthly', 'dizzy-schedule-manager'); ?></option>
                    </select>
                    <input type="date" name="date" value="<?php echo esc_attr($anchor->format('Y-m-d')); ?>" onchange="this.form.submit()">
                </form>
                <strong><?php echo esc_html($this->periodLabel($from, $to, $range)); ?></strong>
                <a class="button" href="<?php echo esc_url($this->url($range, new DateTimeImmutable('today', wp_timezone()))); ?>"><?php esc_html_e('Today', 'dizzy-schedule-manager'); ?></a>
            </div>

            <div class="dizzy-reports-table-wrap">
                <table class="widefat dizzy-reports-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Employee', 'dizzy-schedule-manager'); ?></th>
                            <th><?php esc_html_e('Scheduled Hours', 'dizzy-schedule-manager'); ?></th>
                            <th><?php esc_html_e('Scheduled Shifts', 'dizzy-schedule-manager'); ?></th>
                            <th><?php esc_html_e('Est. Cost', 'dizzy-schedule-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []) : ?>
                            <tr><td colspan="4" class="dizzy-reports-empty"><?php esc_html_e('No shifts in this period.', 'dizzy-schedule-manager'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($rows as $row) : ?>
                                <tr>
                                    <td>
                                        <span class="dizzy-report-employee">
                                            <img src="<?php echo esc_url((string) get_avatar_url($row['employee_id'], ['size' => 72, 'default' => 'mystery'])); ?>" alt="">
                                            <strong><?php echo esc_html($row['name']); ?></strong>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($this->hours($row['minutes'])); ?></td>
                                    <td><?php echo esc_html((string) $row['shifts']); ?></td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th><?php esc_html_e('Total', 'dizzy-schedule-manager'); ?></th>
                            <th><?php echo esc_html($this->hours($totalMinutes)); ?></th>
                            <th><?php echo esc_html((string) $totalShifts); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php
    }

    private function summaries(array $records): array
    {
        $summary = [];

        foreach ($records as $record) {
            $employeeId = (int) $record['employee_id'];

            if (! isset($summary[$employeeId])) {
                $summary[$employeeId] = [
                    'employee_id' => $employeeId,
                    'name' => (string) $record['display_name'],
                    'minutes' => 0,
                    'shifts' => 0,
                ];
            }

            $summary[$employeeId]['minutes'] += $this->shiftMinutes(
                (string) $record['start_time'],
                (string) $record['end_time'],
                (int) $record['break_minutes']
            );
            $summary[$employeeId]['shifts']++;
        }

        $rows = array_values($summary);
        usort($rows, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        return $rows;
    }

    private function shiftMinutes(string $start, string $end, int $breakMinutes): int
    {
        $startParts = array_map('intval', explode(':', $start));
        $endParts = array_map('intval', explode(':', $end));
        $startMinutes = ($startParts[0] * 60) + $startParts[1];
        $endMinutes = ($endParts[0] * 60) + $endParts[1];

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return max(0, $endMinutes - $startMinutes - $breakMinutes);
    }

    private function hours(int $minutes): string
    {
        $hours = $minutes / 60;

        return number_format($hours, $minutes % 60 === 0 ? 0 : 1, '.', '');
    }

    private function anchorDate(): DateTimeImmutable
    {
        $value = sanitize_text_field(wp_unslash((string) ($_GET['date'] ?? '')));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());

        if ($date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value) {
            return $date;
        }

        return new DateTimeImmutable('today', wp_timezone());
    }

    private function period(DateTimeImmutable $anchor, string $range): array
    {
        if ($range === 'month') {
            return [
                $anchor->modify('first day of this month'),
                $anchor->modify('last day of this month'),
            ];
        }

        return [
            $anchor->modify('monday this week'),
            $anchor->modify('sunday this week'),
        ];
    }

    private function periodLabel(DateTimeImmutable $from, DateTimeImmutable $to, string $range): string
    {
        if ($range === 'month') {
            return wp_date('F Y', $from->getTimestamp(), wp_timezone());
        }

        return wp_date('j M', $from->getTimestamp(), wp_timezone())
            . ' – '
            . wp_date('j M Y', $to->getTimestamp(), wp_timezone());
    }

    private function url(string $range, DateTimeImmutable $date): string
    {
        return add_query_arg([
            'page' => self::SLUG,
            'range' => $range,
            'date' => $date->format('Y-m-d'),
        ], admin_url('admin.php'));
    }

    private function isRequest(): bool
    {
        global $pagenow;

        return is_admin()
            && $pagenow === 'admin.php'
            && isset($_GET['page'])
            && sanitize_key(wp_unslash((string) $_GET['page'])) === self::SLUG;
    }
}
