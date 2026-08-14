<?php

declare(strict_types=1);

namespace Dizzy\Schedule\Admin;

use Dizzy\Schedule\EmployeeRole;

defined('ABSPATH') || exit;

final class SchedulePage
{
    public const SLUG = 'dizzy-schedule-manager';
    private string $pageHook = '';

    public function __construct(private EmployeeRole $role)
    {
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

        $user = wp_get_current_user();
        ?>
        <div class="wrap dizzy-schedule-wrap">
            <header class="dizzy-schedule-header">
                <div>
                    <h1><?php esc_html_e('Schedule', 'dizzy-schedule-manager'); ?></h1>
                    <p><?php esc_html_e('Employee shift planning', 'dizzy-schedule-manager'); ?></p>
                </div>
                <span class="dizzy-schedule-user"><?php echo esc_html($user->display_name); ?></span>
            </header>
            <main class="dizzy-schedule-empty">
                <span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
                <h2><?php esc_html_e('Schedule setup completed', 'dizzy-schedule-manager'); ?></h2>
                <p><?php esc_html_e('The Employee role and protected Schedule workspace are ready. Calendar views will be added in the next step.', 'dizzy-schedule-manager'); ?></p>
            </main>
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
