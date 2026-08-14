<?php

declare(strict_types=1);

namespace Dizzy\Schedule\Admin;

use Dizzy\Schedule\EmployeeRole;
use Dizzy\Schedule\PositionSettings;

defined('ABSPATH') || exit;

final class SettingsPage
{
    public const SLUG = 'dizzy-schedule-settings';

    public function __construct(private PositionSettings $positions)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_dizzy_schedule_add_position', [$this, 'add']);
        add_action('admin_post_dizzy_schedule_delete_position', [$this, 'delete']);
    }

    public function menu(): void
    {
        add_submenu_page(
            SchedulePage::SLUG,
            __('Schedule Settings', 'dizzy-schedule-manager'),
            __('Settings', 'dizzy-schedule-manager'),
            EmployeeRole::MANAGE_CAP,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function add(): void
    {
        $this->authorize('dizzy_schedule_add_position');
        $position = sanitize_text_field(wp_unslash((string) ($_POST['position'] ?? '')));
        $result = $this->positions->add($position);
        $this->redirect($result ? 'added' : 'not_added');
    }

    public function delete(): void
    {
        $this->authorize('dizzy_schedule_delete_position');
        $position = sanitize_text_field(wp_unslash((string) ($_POST['position'] ?? '')));
        $this->positions->delete($position);
        $this->redirect('deleted');
    }

    public function render(): void
    {
        if (! current_user_can(EmployeeRole::MANAGE_CAP)) {
            wp_die(esc_html__('You are not allowed to manage schedule settings.', 'dizzy-schedule-manager'));
        }

        $status = sanitize_key(wp_unslash((string) ($_GET['dizzy_schedule_status'] ?? '')));
        ?>
        <div class="wrap dizzy-schedule-settings">
            <h1><?php esc_html_e('Schedule Settings', 'dizzy-schedule-manager'); ?></h1>

            <?php if ($status !== '') : ?>
                <div class="dizzy-schedule-settings-message">
                    <?php
                    echo esc_html(match ($status) {
                        'added' => __('Employee role added.', 'dizzy-schedule-manager'),
                        'deleted' => __('Employee role deleted.', 'dizzy-schedule-manager'),
                        default => __('The employee role was not added. It may already exist.', 'dizzy-schedule-manager'),
                    });
                    ?>
                </div>
            <?php endif; ?>

            <section class="dizzy-schedule-settings-panel">
                <h2><?php esc_html_e('Employee Roles', 'dizzy-schedule-manager'); ?></h2>
                <p><?php esc_html_e('These roles are available in the Position dropdown when creating a shift.', 'dizzy-schedule-manager'); ?></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dizzy-position-add">
                    <input type="hidden" name="action" value="dizzy_schedule_add_position">
                    <?php wp_nonce_field('dizzy_schedule_add_position'); ?>
                    <label for="dizzy-position-name"><?php esc_html_e('Role name', 'dizzy-schedule-manager'); ?></label>
                    <input id="dizzy-position-name" type="text" name="position" maxlength="120" required placeholder="<?php esc_attr_e('For example: Bartender', 'dizzy-schedule-manager'); ?>">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Add role', 'dizzy-schedule-manager'); ?></button>
                </form>

                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Employee role', 'dizzy-schedule-manager'); ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($this->positions->all() as $position) : ?>
                        <tr>
                            <td><?php echo esc_html($position); ?></td>
                            <td class="dizzy-position-delete">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="dizzy_schedule_delete_position">
                                    <input type="hidden" name="position" value="<?php echo esc_attr($position); ?>">
                                    <?php wp_nonce_field('dizzy_schedule_delete_position'); ?>
                                    <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete', 'dizzy-schedule-manager'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
        <?php
    }

    private function authorize(string $action): void
    {
        if (! current_user_can(EmployeeRole::MANAGE_CAP)) {
            wp_die(esc_html__('Access denied.', 'dizzy-schedule-manager'));
        }

        check_admin_referer($action);
    }

    private function redirect(string $status): void
    {
        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'dizzy_schedule_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }
}
