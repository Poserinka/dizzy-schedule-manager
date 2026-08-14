<?php

declare(strict_types=1);

namespace Dizzy\Schedule\Admin;

use Dizzy\Schedule\EmployeeRole;

defined('ABSPATH') || exit;

final class SchedulePage
{
    public const SLUG = 'dizzy-schedule-manager';
    private string $pageHook = '';

    public function __construct(
        private EmployeeRole $role,
        private \Dizzy\Schedule\PositionSettings $positions
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_menu', [$this, 'limitEmployeeMenu'], 999);
        add_action('admin_init', [$this, 'protectEmployeeAdmin'], 1);
        add_action('admin_init', [$this, 'suppressThirdPartyNotices'], 999);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_filter('admin_body_class', [$this, 'bodyClass']);
        add_filter('show_admin_bar', [$this, 'hideEmployeeAdminBar']);
    }

    public function menu(): void
    {
        $this->pageHook = (string) add_menu_page(
            __('Schedule', 'dizzy-schedule-manager'),
            __('Schedule', 'dizzy-schedule-manager'),
            EmployeeRole::VIEW_CAP,
            self::SLUG,
            [$this, 'render'],
            'dashicons-calendar-alt',
            26
        );
    }

    public function limitEmployeeMenu(): void
    {
        if (! $this->role->isEmployee()) {
            return;
        }

        global $menu;

        foreach ((array) $menu as $item) {
            $slug = (string) ($item[2] ?? '');
            if ($slug !== '' && $slug !== self::SLUG) {
                remove_menu_page($slug);
            }
        }
    }

    public function protectEmployeeAdmin(): void
    {
        if (! $this->role->isEmployee()) {
            return;
        }

        global $pagenow;

        if (in_array($pagenow, ['admin-ajax.php', 'admin-post.php', 'async-upload.php'], true)) {
            return;
        }

        $isSchedule = $pagenow === 'admin.php'
            && isset($_GET['page'])
            && sanitize_key(wp_unslash((string) $_GET['page'])) === self::SLUG;

        if (! $isSchedule) {
            wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
            exit;
        }
    }

    public function suppressThirdPartyNotices(): void
    {
        if (! $this->isScheduleRequest()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');
        remove_all_actions('network_admin_notices');
        remove_all_actions('user_admin_notices');
    }

    public function assets(string $hook): void
    {
        if ($hook !== $this->pageHook) {
            return;
        }

        wp_enqueue_style('dizzy-schedule-admin', DIZZY_SCHEDULE_URL . 'assets/admin.css', [], DIZZY_SCHEDULE_VERSION);
        wp_enqueue_script('dizzy-schedule-admin', DIZZY_SCHEDULE_URL . 'assets/admin.js', [], DIZZY_SCHEDULE_VERSION, true);

        $employees = get_users([
            'role' => EmployeeRole::ROLE,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name'],
        ]);

        wp_localize_script('dizzy-schedule-admin', 'dizzySchedule', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dizzy_schedule_admin'),
            'canManage' => current_user_can(EmployeeRole::MANAGE_CAP),
            'currentUserId' => get_current_user_id(),
            'today' => current_time('Y-m-d'),
            'employees' => array_map(static fn (object $user): array => [
                'id' => (int) $user->ID,
                'name' => (string) $user->display_name,
            ], $employees),
            'strings' => [
                'loading' => __('Loading schedule…', 'dizzy-schedule-manager'),
                'empty' => __('No shifts in this period.', 'dizzy-schedule-manager'),
                'newShift' => __('Add new shift', 'dizzy-schedule-manager'),
                'editShift' => __('Edit shift', 'dizzy-schedule-manager'),
                'confirmDelete' => __('Delete this shift?', 'dizzy-schedule-manager'),
                'error' => __('The schedule could not be loaded.', 'dizzy-schedule-manager'),
            ],
        ]);
    }

    public function bodyClass(string $classes): string
    {
        return $this->isScheduleRequest() ? $classes . ' dizzy-schedule-admin-page' : $classes;
    }

    public function hideEmployeeAdminBar(bool $show): bool
    {
        return $this->role->isEmployee() ? false : $show;
    }

    public function render(): void
    {
        if (! current_user_can(EmployeeRole::VIEW_CAP)) {
            wp_die(esc_html__('You are not allowed to view the schedule.', 'dizzy-schedule-manager'));
        }

        $canManage = current_user_can(EmployeeRole::MANAGE_CAP);
        ?>
        <div class="wrap dizzy-schedule-wrap" id="dizzy-schedule-app">
            <header class="dizzy-schedule-header">
                <div>
                    <h1><?php esc_html_e('Schedule', 'dizzy-schedule-manager'); ?></h1>
                    <p><?php esc_html_e('Employee shift planning', 'dizzy-schedule-manager'); ?></p>
                </div>
                <?php if ($canManage) : ?>
                    <button type="button" class="button button-primary" data-action="new-shift">
                        <?php esc_html_e('Add new shift', 'dizzy-schedule-manager'); ?>
                    </button>
                <?php endif; ?>
            </header>

            <nav class="dizzy-schedule-tabs" aria-label="<?php esc_attr_e('Schedule scope', 'dizzy-schedule-manager'); ?>">
                <?php if ($canManage) : ?>
                    <button type="button" class="is-active" data-scope="full"><?php esc_html_e('Full schedule', 'dizzy-schedule-manager'); ?></button>
                <?php endif; ?>
                <button type="button" <?php echo $canManage ? '' : 'class="is-active"'; ?> data-scope="mine">
                    <?php esc_html_e('My schedule', 'dizzy-schedule-manager'); ?>
                </button>
            </nav>

            <div class="dizzy-schedule-toolbar">
                <button type="button" class="button" data-action="previous" aria-label="<?php esc_attr_e('Previous period', 'dizzy-schedule-manager'); ?>">‹</button>
                <button type="button" class="button" data-action="next" aria-label="<?php esc_attr_e('Next period', 'dizzy-schedule-manager'); ?>">›</button>
                <select data-control="view" aria-label="<?php esc_attr_e('Calendar view', 'dizzy-schedule-manager'); ?>">
                    <option value="day"><?php esc_html_e('Day', 'dizzy-schedule-manager'); ?></option>
                    <option value="week" selected><?php esc_html_e('Week', 'dizzy-schedule-manager'); ?></option>
                    <option value="month"><?php esc_html_e('Month', 'dizzy-schedule-manager'); ?></option>
                </select>
                <strong data-period-label></strong>
                <button type="button" class="button" data-action="today"><?php esc_html_e('Today', 'dizzy-schedule-manager'); ?></button>
            </div>

            <div class="dizzy-schedule-feedback" role="status" aria-live="polite"></div>
            <div class="dizzy-schedule-calendar" data-calendar></div>

            <?php if ($canManage) : ?>
                <div class="dizzy-schedule-modal" data-modal hidden>
                    <div class="dizzy-schedule-modal-backdrop" data-action="close-modal"></div>
                    <section class="dizzy-schedule-dialog" role="dialog" aria-modal="true" aria-labelledby="dizzy-shift-title">
                        <header>
                            <h2 id="dizzy-shift-title"><?php esc_html_e('Add new shift', 'dizzy-schedule-manager'); ?></h2>
                            <button type="button" class="dizzy-schedule-close" data-action="close-modal" aria-label="<?php esc_attr_e('Close', 'dizzy-schedule-manager'); ?>">×</button>
                        </header>
                        <form data-shift-form>
                            <input type="hidden" name="id" value="0">
                            <label>
                                <span><?php esc_html_e('Employee', 'dizzy-schedule-manager'); ?></span>
                                <select name="employee_id" required></select>
                            </label>
                            <label>
                                <span><?php esc_html_e('Date', 'dizzy-schedule-manager'); ?></span>
                                <input type="date" name="shift_date" required>
                            </label>
                            <div class="dizzy-schedule-form-row">
                                <label><span><?php esc_html_e('Start', 'dizzy-schedule-manager'); ?></span><input type="time" name="start_time" step="1800" required></label>
                                <label><span><?php esc_html_e('End', 'dizzy-schedule-manager'); ?></span><input type="time" name="end_time" step="1800" required></label>
                            </div>
                            <label>
                                <span><?php esc_html_e('Break', 'dizzy-schedule-manager'); ?></span>
                                <select name="break_minutes">
                                    <option value="0"><?php esc_html_e('No break', 'dizzy-schedule-manager'); ?></option>
                                    <option value="15">15 min</option><option value="30">30 min</option>
                                    <option value="45">45 min</option><option value="60">60 min</option>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e('Position', 'dizzy-schedule-manager'); ?></span>
                                <select name="position" required>
                                    <?php foreach ($this->positions->all() as $position) : ?>
                                        <option value="<?php echo esc_attr($position); ?>"><?php echo esc_html($position); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label><span><?php esc_html_e('Notes', 'dizzy-schedule-manager'); ?></span><textarea name="notes" rows="3"></textarea></label>
                            <div class="dizzy-schedule-dialog-actions">
                                <button type="button" class="button button-link-delete" data-action="delete-shift" hidden><?php esc_html_e('Delete', 'dizzy-schedule-manager'); ?></button>
                                <span></span>
                                <button type="button" class="button" data-action="close-modal"><?php esc_html_e('Cancel', 'dizzy-schedule-manager'); ?></button>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save shift', 'dizzy-schedule-manager'); ?></button>
                            </div>
                        </form>
                    </section>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function isScheduleRequest(): bool
    {
        global $pagenow;

        return is_admin()
            && $pagenow === 'admin.php'
            && isset($_GET['page'])
            && sanitize_key(wp_unslash((string) $_GET['page'])) === self::SLUG;
    }
}
