(() => {
    'use strict';

    const config = window.dizzySchedule;
    const app = document.getElementById('dizzy-schedule-app');
    if (!config || !app) return;

    const calendar = app.querySelector('[data-calendar]');
    const feedback = app.querySelector('.dizzy-schedule-feedback');
    const periodLabel = app.querySelector('[data-period-label]');
    const viewControl = app.querySelector('[data-control="view"]');
    const modal = app.querySelector('[data-modal]');
    const form = app.querySelector('[data-shift-form]');
    let view = 'week';
    let scope = config.canManage ? 'full' : 'mine';
    let cursor;
    let shifts = [];

    const pad = value => String(value).padStart(2, '0');
    const iso = date => date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    const parseDate = value => {
        const parts = String(value).split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2], 12);
    };
    cursor = parseDate(config.today);

    const addDays = (date, days) => {
        const next = new Date(date);
        next.setDate(next.getDate() + days);
        return next;
    };
    const monday = date => addDays(date, -((date.getDay() + 6) % 7));
    const formatDay = date => new Intl.DateTimeFormat(undefined, {weekday: 'short', day: 'numeric', month: 'short'}).format(date);
    const formatMonth = date => new Intl.DateTimeFormat(undefined, {month: 'long', year: 'numeric'}).format(date);
    const range = () => {
        if (view === 'day') return [cursor, cursor];
        if (view === 'week') {
            const start = monday(cursor);
            return [start, addDays(start, 6)];
        }
        return [new Date(cursor.getFullYear(), cursor.getMonth(), 1, 12), new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0, 12)];
    };
    const employeeMap = () => new Map(config.employees.map(item => [Number(item.id), item]));
    const visibleEmployees = () => {
        if (scope === 'mine') {
            return config.employees.filter(item => Number(item.id) === Number(config.currentUserId));
        }
        return config.employees;
    };
    const request = async (action, data = {}) => {
        const body = new URLSearchParams({action, nonce: config.nonce, ...data});
        const response = await fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body});
        const json = await response.json();
        if (!json.success) throw new Error(json.data?.message || config.strings.error);
        return json.data;
    };

    async function load() {
        const [from, to] = range();
        feedback.textContent = config.strings.loading;
        calendar.setAttribute('aria-busy', 'true');
        try {
            shifts = await request('dizzy_schedule_list', {
                from: iso(from), to: iso(to),
                employee_id: scope === 'mine' ? String(config.currentUserId) : '0'
            });
            render();
            feedback.textContent = '';
        } catch (error) {
            feedback.textContent = error.message;
        } finally {
            calendar.removeAttribute('aria-busy');
        }
    }

    function render() {
        const [from, to] = range();
        periodLabel.textContent = view === 'month' ? formatMonth(cursor) : view === 'day' ? formatDay(cursor) : formatDay(from) + ' – ' + formatDay(to);
        calendar.innerHTML = '';
        if (view === 'month') renderMonth(from, to);
        else if (view === 'day') renderDay(from);
        else renderWeek(from);
    }

    function shiftButton(shift) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'dizzy-shift';
        button.innerHTML = '<strong>' + escapeHtml(shift.start_time) + '–' + escapeHtml(shift.end_time) + '</strong><span>' + escapeHtml(shift.position || 'Shift') + '</span>';
        button.addEventListener('click', event => {
            event.stopPropagation();
            if (config.canManage) openModal(shift);
        });
        return button;
    }

    function renderWeek(start) {
        const employees = visibleEmployees();
        const grid = document.createElement('div');
        grid.className = 'dizzy-week-grid';
        grid.style.setProperty('--days', '7');
        grid.appendChild(cell('', 'dizzy-grid-head'));
        for (let d = 0; d < 7; d++) grid.appendChild(cell(formatDay(addDays(start, d)), 'dizzy-grid-head'));

        employees.forEach(employee => {
            const employeeCell = cell(employee.name, 'dizzy-employee-cell');
            const total = totalHours(shifts.filter(s => Number(s.employee_id) === Number(employee.id)));
            employeeCell.insertAdjacentHTML('beforeend', '<small>' + total + ' scheduled hours</small>');
            grid.appendChild(employeeCell);
            for (let d = 0; d < 7; d++) {
                const date = iso(addDays(start, d));
                const slot = cell('', 'dizzy-schedule-slot');
                slot.dataset.date = date;
                slot.dataset.employeeId = employee.id;
                shifts.filter(s => Number(s.employee_id) === Number(employee.id) && s.shift_date === date).forEach(s => slot.appendChild(shiftButton(s)));
                if (config.canManage) slot.addEventListener('click', () => openModal({employee_id: employee.id, shift_date: date}));
                grid.appendChild(slot);
            }
        });
        showEmpty(employees.length);
        calendar.appendChild(grid);
    }

    function renderDay(date) {
        const employees = visibleEmployees();
        const grid = document.createElement('div');
        grid.className = 'dizzy-day-grid';
        grid.appendChild(cell('Employee', 'dizzy-grid-head'));
        for (let hour = 16; hour <= 23; hour++) grid.appendChild(cell(pad(hour) + ':00', 'dizzy-grid-head'));

        employees.forEach(employee => {
            grid.appendChild(cell(employee.name, 'dizzy-employee-cell'));
            for (let hour = 16; hour <= 23; hour++) {
                const slot = cell('', 'dizzy-schedule-slot');
                const dateValue = iso(date);
                slot.dataset.date = dateValue;
                slot.dataset.employeeId = employee.id;
                const matches = shifts.filter(s => Number(s.employee_id) === Number(employee.id) && s.shift_date === dateValue && Number(s.start_time.slice(0, 2)) === hour);
                matches.forEach(s => slot.appendChild(shiftButton(s)));
                if (config.canManage) slot.addEventListener('click', () => openModal({employee_id: employee.id, shift_date: dateValue, start_time: pad(hour) + ':00', end_time: pad(Math.min(hour + 5, 23)) + ':00'}));
                grid.appendChild(slot);
            }
        });
        showEmpty(employees.length);
        calendar.appendChild(grid);
    }

    function renderMonth(from, to) {
        const grid = document.createElement('div');
        grid.className = 'dizzy-month-grid';
        ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach(day => grid.appendChild(cell(day, 'dizzy-grid-head')));
        let date = monday(from);
        const finish = addDays(monday(to), 6);
        while (date <= finish) {
            const dateValue = iso(date);
            const slot = cell(String(date.getDate()), 'dizzy-month-day' + (date.getMonth() !== cursor.getMonth() ? ' is-outside' : ''));
            slot.dataset.date = dateValue;
            shifts.filter(s => s.shift_date === dateValue).forEach(s => {
                const item = shiftButton(s);
                item.insertAdjacentHTML('afterbegin', '<em>' + escapeHtml(s.employee_name) + '</em>');
                slot.appendChild(item);
            });
            if (config.canManage) slot.addEventListener('click', event => {
                if (!event.target.closest('.dizzy-shift')) openModal({shift_date: dateValue});
            });
            grid.appendChild(slot);
            date = addDays(date, 1);
        }
        calendar.appendChild(grid);
    }

    function cell(text, className) {
        const element = document.createElement('div');
        element.className = className;
        element.textContent = text;
        return element;
    }

    function showEmpty() {
        if (!shifts.length) feedback.textContent = config.strings.empty;
    }

    function totalHours(rows) {
        const minutes = rows.reduce((sum, item) => {
            const start = item.start_time.split(':').map(Number);
            const end = item.end_time.split(':').map(Number);
            return sum + (end[0] * 60 + end[1]) - (start[0] * 60 + start[1]) - Number(item.break_minutes || 0);
        }, 0);
        return Math.max(0, minutes / 60).toFixed(minutes % 60 ? 1 : 0);
    }

    function openModal(shift = {}) {
        if (!modal || !form) return;
        form.reset();
        form.elements.id.value = shift.id || 0;
        form.elements.employee_id.innerHTML = config.employees.map(item => '<option value="' + item.id + '">' + escapeHtml(item.name) + '</option>').join('');
        form.elements.employee_id.value = shift.employee_id || config.employees[0]?.id || '';
        form.elements.shift_date.value = shift.shift_date || iso(cursor);
        form.elements.start_time.value = shift.start_time || '18:00';
        form.elements.end_time.value = shift.end_time || '23:00';
        form.elements.break_minutes.value = shift.break_minutes || 0;
        form.elements.position.value = shift.position || '';
        form.elements.notes.value = shift.notes || '';
        modal.querySelector('h2').textContent = shift.id ? config.strings.editShift : config.strings.newShift;
        modal.querySelector('[data-action="delete-shift"]').hidden = !shift.id;
        modal.hidden = false;
        form.elements.employee_id.focus();
    }

    function closeModal() {
        if (modal) modal.hidden = true;
    }

    if (form) form.addEventListener('submit', async event => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(form).entries());
        try {
            await request('dizzy_schedule_save', data);
            closeModal();
            await load();
        } catch (error) {
            window.alert(error.message);
        }
    });

    app.addEventListener('click', async event => {
        const action = event.target.closest('[data-action]')?.dataset.action;
        if (!action) return;
        if (action === 'new-shift') openModal();
        if (action === 'close-modal') closeModal();
        if (action === 'today') { cursor = parseDate(config.today); load(); }
        if (action === 'previous' || action === 'next') {
            const direction = action === 'previous' ? -1 : 1;
            cursor = view === 'month' ? new Date(cursor.getFullYear(), cursor.getMonth() + direction, 1, 12) : addDays(cursor, direction * (view === 'week' ? 7 : 1));
            load();
        }
        if (action === 'delete-shift' && form && Number(form.elements.id.value) > 0 && window.confirm(config.strings.confirmDelete)) {
            try {
                await request('dizzy_schedule_delete', {id: form.elements.id.value});
                closeModal();
                await load();
            } catch (error) {
                window.alert(error.message);
            }
        }
    });

    app.querySelectorAll('[data-scope]').forEach(button => button.addEventListener('click', () => {
        scope = button.dataset.scope;
        app.querySelectorAll('[data-scope]').forEach(item => item.classList.toggle('is-active', item === button));
        load();
    }));
    viewControl.addEventListener('change', () => { view = viewControl.value; load(); });
    load();

    function escapeHtml(value) {
        const span = document.createElement('span');
        span.textContent = String(value ?? '');
        return span.innerHTML;
    }
})();
