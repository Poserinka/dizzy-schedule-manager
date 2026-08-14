# Dizzy Schedule Manager

Private employee schedules and shift planning for WordPress.

## Version 2.1

- Adds an **Employee** WordPress user role.
- Provides protected **Schedule** admin access with third-party notices suppressed.
- Day, week and month calendar views.
- Full Schedule and My Schedule scopes.
- AJAX shift creation, editing and deletion without page reloads.
- Empty calendar cells open a pre-filled Add Shift dialog.
- Existing shifts open the Edit Shift dialog.
- Employee, date, start/end time, break, position and notes fields.
- Adds **Schedule → Settings** for adding and deleting Employee Roles (shift positions).
- Shift Position is selected from the configured Employee Roles dropdown.
- Overlapping shifts for the same employee are rejected.
- Employee users can only view their own schedule.
- Administrators can manage all Employee schedules.
- Includes the standard GitHub Releases update integration.

## Roles and capabilities

- Employee role: `dizzy_employee`
- View schedule: `dizzy_view_schedule`
- Manage schedule: `dizzy_manage_schedule`

## Requirements

- WordPress 6.7+
- PHP 8.2+
