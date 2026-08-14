<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

remove_role('dizzy_employee');
get_role('administrator')?->remove_cap('dizzy_view_schedule');
get_role('administrator')?->remove_cap('dizzy_manage_schedule');
