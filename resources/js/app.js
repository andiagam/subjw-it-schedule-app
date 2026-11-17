import Alpine from 'alpinejs';

window.Alpine = Alpine;

const badgeClass = {
    SHIFT: 'badge-shift',
    OFF: 'badge-off',
    AL: 'badge-al',
    DP: 'badge-dp',
    SL: 'badge-sl',
    PH: 'badge-ph',
};

const statusLabel = {
    SHIFT: 'ON',
    OFF: 'OFF',
    AL: 'AL',
    DP: 'DP',
    SL: 'SL',
    PH: 'PH',
};

document.addEventListener('alpine:init', () => {
    Alpine.data('rosterPage', (initial = {}) => ({
        open: false,
        saving: false,
        userId: '',
        userName: '',
        date: '',
        dateLabel: '',
        status: 'SHIFT',
        taskOpen: Boolean(initial.taskOpen),
        taskEditing: false,
        taskDone: false,
        taskAction: '/roster/tasks',
        taskDate: initial.taskDate || '',
        taskDateLabel: initial.taskDateLabel || '',
        taskKind: initial.taskKind || 'task',
        taskTitle: initial.taskTitle || '',
        activityOpen: false,
        today: initial.today || '',
        actorName: initial.actorName || '',
        addHint: '',
        init() {
            this.$nextTick(() => {
                const board = this.$root.querySelector('[data-month-board]');

                if (board && window.matchMedia('(max-width: 767px)').matches) {
                    board.open = false;
                }

                const cell = this.$root.querySelector('[data-today="1"]');
                const scroller = this.$root.querySelector('[data-roster-scroll]');

                if (cell && scroller) {
                    scroller.scrollLeft = Math.max(0, cell.offsetLeft - 128);
                }
            });
        },
        openTask(date, label) {
            this.taskEditing = false;
            this.taskDone = false;
            this.taskAction = '/roster/tasks';
            this.taskDate = date;
            this.taskDateLabel = label;
            this.taskKind = 'task';
            this.taskTitle = '';
            this.taskOpen = true;
        },
        openEdit(task) {
            this.taskEditing = true;
            this.taskDone = Boolean(task.done);
            this.taskAction = `/roster/tasks/${task.id}`;
            this.taskDate = task.date;
            this.taskDateLabel = task.label;
            this.taskKind = task.kind;
            this.taskTitle = task.title;
            this.taskOpen = true;
        },
        openCell(element) {
            this.userId = element.dataset.userId;
            this.userName = element.dataset.userName;
            this.date = element.dataset.date;
            this.dateLabel = element.dataset.dateLabel;
            this.status = element.dataset.status || 'SHIFT';
            this.open = true;
        },
        async save() {
            const button = document.querySelector(`[data-cell="${CSS.escape(`${this.userId}-${this.date}`)}"]`);
            const previous = button ? this.paintCell(button, this.status) : '';
            this.open = false;

            try {
                const response = await fetch('/roster/entries', {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        user_id: Number(this.userId),
                        date: this.date,
                        status: this.status,
                        note: '',
                    }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (button) {
                        this.paintCell(button, previous);
                    }

                    const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                    this.toast(firstError || data.message || 'That cell could not be saved.', 'error');
                }
            } catch {
                if (button) {
                    this.paintCell(button, previous);
                }

                this.toast('That cell could not be saved. Check the connection and try again.', 'error');
            }
        },
        paintCell(button, status) {
            const previous = button.dataset.status || '';
            button.dataset.status = status || '';
            let badge = button.querySelector('[data-badge]');

            if (!badge) {
                badge = document.createElement('span');
                badge.dataset.badge = '';
                button.textContent = '';
                button.appendChild(badge);
            }

            if (!status) {
                badge.className = 'text-[11px] text-outline';
                badge.textContent = '+';

                return previous;
            }

            badge.className = `roster-badge ${badgeClass[status] || ''}`;
            badge.textContent = statusLabel[status] || status;

            return previous;
        },
        async saveTask() {
            if (!this.taskTitle.trim()) {
                this.toast('Add a title first.', 'error');

                return;
            }

            const editing = this.taskEditing;
            const id = editing ? this.taskId() : '';
            const snapshot = editing ? this.snapshotTask(id) : null;
            const payload = {
                date: this.taskDate,
                title: this.taskTitle.trim(),
                kind: this.taskKind,
                is_done: this.taskKind === 'task' && this.taskDone,
            };

            if (editing) {
                this.paintTask(id, payload);
            } else {
                this.insertPendingTask(payload);
                this.insertTodayTask(payload);
            }

            this.taskOpen = false;

            try {
                const response = await fetch(editing ? `/roster/tasks/${id}` : '/roster/tasks', {
                    method: editing ? 'PUT' : 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify(payload),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(this.errorText(data) || 'That calendar item could not be saved.');
                }

                if (!editing && data.task) {
                    this.stampPendingTask(data.task);
                }

                this.noteActivity(data.activity);
                this.applyByline(editing ? id : data.task?.id, data.task);

                if (data.task && (data.task.kind !== 'task' || data.task.date !== this.today)) {
                    document.querySelector(`[data-today-task="${CSS.escape(String(data.task.id))}"]`)?.remove();
                    this.showTodayEmpty();
                }

                if (data.task?.kind === 'holiday') {
                    this.paintHoliday(data.task.date);
                }
            } catch (error) {
                if (editing) {
                    this.restoreTask(snapshot);
                } else {
                    this.removePendingTask();
                }

                this.toast(error.message || 'That calendar item could not be saved.', 'error');
            }
        },
        async markTaskDone() {
            if (!this.taskEditing) {
                return;
            }

            const id = this.taskId();
            const snapshot = this.snapshotTask(id);
            const payload = {
                date: this.taskDate,
                title: this.taskTitle.trim() || snapshot[0]?.title || '',
                kind: this.taskKind,
                is_done: this.taskKind === 'task' && this.taskDone,
            };
            this.paintTask(id, payload);

            try {
                const response = await fetch(`/roster/tasks/${id}`, {
                    method: 'PUT',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify(payload),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(this.errorText(data) || 'That task could not be updated.');
                }

                this.noteActivity(data.activity);
                this.applyByline(id, data.task);
            } catch (error) {
                this.restoreTask(snapshot);
                this.taskDone = !this.taskDone;
                this.toast(error.message || 'That task could not be updated.', 'error');
            }
        },
        async deleteTask() {
            if (!confirm('Remove this from the calendar?')) {
                return;
            }

            const id = this.taskId();
            const removed = this.removeTask(id);
            this.taskOpen = false;

            try {
                const response = await fetch(`/roster/tasks/${id}`, {
                    method: 'DELETE',
                    headers: this.jsonHeaders(),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(this.errorText(data) || 'That calendar item could not be removed.');
                }

                this.noteActivity(data.activity);
                this.showTodayEmpty();
            } catch (error) {
                removed.forEach(({ parent, next, item }) => {
                    parent.insertBefore(item, next);
                });
                this.toast(error.message || 'That calendar item could not be removed.', 'error');
            }
        },
        taskId() {
            return String(this.taskAction).split('/').filter(Boolean).pop();
        },
        jsonHeaders() {
            return {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        },
        errorText(data) {
            if (data.errors) {
                return Object.values(data.errors).flat()[0];
            }

            return data.message || '';
        },
        snapshotTask(id) {
            return [...document.querySelectorAll(`[data-task-id="${CSS.escape(id)}"]`)].map((node) => ({
                node,
                className: node.className,
                title: (node.matches('[data-task-title]') ? node : node.querySelector('[data-task-title]'))?.textContent || '',
                parentClass: node.closest('li')?.className || '',
            }));
        },
        paintTask(id, task) {
            const done = task.kind === 'task' && Boolean(task.is_done);

            document.querySelectorAll(`[data-task-id="${CSS.escape(String(id))}"]`).forEach((node) => {
                const title = node.matches('[data-task-title]') ? node : node.querySelector('[data-task-title]');

                if (title) {
                    title.textContent = task.title;
                }

                node.classList.toggle('line-through', done);
                node.classList.toggle('opacity-60', done);
                node.closest('li')?.classList.toggle('line-through', done);
                node.closest('li')?.classList.toggle('opacity-60', done);
            });
        },
        restoreTask(snapshot) {
            snapshot?.forEach(({ node, className, title, parentClass }) => {
                node.className = className;
                const titleNode = node.matches('[data-task-title]') ? node : node.querySelector('[data-task-title]');

                if (titleNode) {
                    titleNode.textContent = title;
                }

                const item = node.closest('li');

                if (item) {
                    item.className = parentClass;
                }
            });
        },
        insertPendingTask(task) {
            const host = document.querySelector(`[data-calendar-day="${CSS.escape(task.date)}"] [data-day-items]`);

            if (!host) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.taskId = 'pending';
            button.dataset.pendingTask = '1';
            button.className = `w-full rounded-lg px-2 py-1.5 text-left text-xs leading-snug break-words whitespace-normal ${this.chipClass(task.kind)}`;
            button.innerHTML = `<span class="block text-[10px] font-medium tracking-wide">${this.kindLabel(task.kind)}</span><span class="block font-medium" data-task-title></span>`;
            button.querySelector('[data-task-title]').textContent = task.title;
            host.appendChild(button);
        },
        stampPendingTask(task) {
            document.querySelectorAll('[data-today-task="pending"]').forEach((row) => {
                row.dataset.todayTask = String(task.id);
            });

            document.querySelectorAll('[data-pending-task]').forEach((button) => {
                button.dataset.taskId = String(task.id);
                delete button.dataset.pendingTask;
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.openEdit({
                        id: task.id,
                        date: task.date,
                        label: this.taskDateLabel,
                        title: task.title,
                        kind: task.kind,
                        done: Boolean(task.is_done),
                    });
                });
            });

            this.applyByline(task.id, task);
        },
        removePendingTask() {
            [...document.querySelectorAll('[data-pending-task]')].forEach((node) => {
                (node.closest('[data-today-task]') || node).remove();
            });
            this.showTodayEmpty();
        },
        insertTodayTask(task) {
            if (task.kind !== 'task' || task.date !== this.today) {
                return;
            }

            const list = document.querySelector('[data-today-tasks]');

            if (!list) {
                return;
            }

            list.querySelector('[data-today-empty]')?.remove();
            const row = document.createElement('li');
            row.dataset.todayTask = 'pending';
            row.dataset.date = task.date;
            row.className = 'flex items-start gap-2 rounded-xl px-1 py-1';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'm3-check mt-1';
            checkbox.setAttribute('aria-label', `Mark ${task.title} complete`);
            checkbox.addEventListener('change', (event) => this.toggleTodayTask(event.target));

            const copy = document.createElement('div');
            copy.className = 'min-w-0 flex-1';
            const title = document.createElement('button');
            title.type = 'button';
            title.dataset.taskId = 'pending';
            title.dataset.pendingTask = '1';
            title.dataset.taskTitle = '';
            title.className = 'w-full text-left text-sm font-medium break-words';
            title.textContent = task.title;
            const byline = document.createElement('p');
            byline.dataset.taskByline = '';
            byline.className = 'text-xs text-on-surface-variant';
            byline.textContent = this.actorName ? `Added by ${this.actorName}` : '';
            copy.append(title, byline);

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'grid size-8 shrink-0 place-items-center rounded-full text-on-surface-variant hover:bg-surface-high';
            edit.setAttribute('aria-label', `Edit ${task.title}`);
            edit.textContent = 'edit';
            edit.classList.add('material-symbols-rounded', 'text-[18px]');
            edit.addEventListener('click', () => {
                if (row.dataset.todayTask === 'pending') {
                    return;
                }

                this.openEdit({
                    id: row.dataset.todayTask,
                    date: row.dataset.date,
                    label: this.taskDateLabel,
                    title: title.textContent,
                    kind: 'task',
                    done: checkbox.checked,
                });
            });

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'grid size-8 shrink-0 place-items-center rounded-full text-error hover:bg-error-container material-symbols-rounded text-[18px]';
            remove.setAttribute('aria-label', `Remove ${task.title}`);
            remove.textContent = 'delete';
            remove.addEventListener('click', () => {
                if (row.dataset.todayTask !== 'pending') {
                    this.removeTodayTask(row.dataset.todayTask);
                }
            });

            row.append(checkbox, copy, edit, remove);
            list.appendChild(row);
        },
        async toggleTodayTask(input) {
            const row = input.closest('[data-today-task]');

            if (!row || row.dataset.todayTask === 'pending') {
                input.checked = false;

                return;
            }

            const id = row.dataset.todayTask;
            const title = row.querySelector('[data-task-title]')?.textContent?.trim() || '';
            const done = input.checked;
            const snapshot = this.snapshotTask(id);
            this.paintTask(id, { title, kind: 'task', is_done: done });

            try {
                const response = await fetch(`/roster/tasks/${id}`, {
                    method: 'PUT',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        date: row.dataset.date,
                        title,
                        kind: 'task',
                        is_done: done,
                    }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(this.errorText(data) || 'That task could not be updated.');
                }

                this.noteActivity(data.activity);
                this.applyByline(id, data.task);
            } catch (error) {
                this.restoreTask(snapshot);
                input.checked = !done;
                this.toast(error.message || 'That task could not be updated.', 'error');
            }
        },
        async removeTodayTask(id) {
            if (!confirm('Remove this task?')) {
                return;
            }

            const removed = this.removeTask(id);
            this.showTodayEmpty();

            try {
                const response = await fetch(`/roster/tasks/${id}`, {
                    method: 'DELETE',
                    headers: this.jsonHeaders(),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(this.errorText(data) || 'That task could not be removed.');
                }

                this.noteActivity(data.activity);
            } catch (error) {
                removed.forEach(({ parent, next, item }) => {
                    parent.insertBefore(item, next);
                });
                document.querySelector('[data-today-empty]')?.remove();
                this.toast(error.message || 'That task could not be removed.', 'error');
            }
        },
        showTodayEmpty() {
            const list = document.querySelector('[data-today-tasks]');

            if (!list || list.querySelector('[data-today-task]') || list.querySelector('[data-today-empty]')) {
                return;
            }

            const empty = document.createElement('li');
            empty.dataset.todayEmpty = '';
            empty.className = 'text-sm text-on-surface-variant';
            empty.textContent = 'No tasks for today.';
            list.appendChild(empty);
        },
        noteActivity(activity) {
            const items = Array.isArray(activity) ? activity : [];
            const list = document.querySelector('[data-activity-log]');

            if (!list || items.length === 0) {
                return;
            }

            const labels = {
                added: 'Added',
                edited: 'Edited',
                completed: 'Completed',
                uncompleted: 'Uncompleted',
                removed: 'Removed',
            };

            list.querySelector('[data-activity-empty]')?.remove();
            items.forEach((entry) => {
                const item = document.createElement('li');
                item.className = 'border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800';
                const action = document.createElement('span');
                action.className = 'font-medium';
                action.textContent = labels[entry.action] || entry.action;
                const title = document.createElement('span');
                title.className = 'text-on-surface-variant';
                title.textContent = ` · ${entry.task_title}`;
                const meta = document.createElement('span');
                meta.className = 'mt-0.5 block text-xs text-on-surface-variant';
                meta.textContent = `${entry.actor_name} · ${entry.when}`;
                item.append(action, title, meta);
                list.prepend(item);
            });
        },
        applyByline(id, task) {
            if (!id || !task) {
                return;
            }

            const byline = document.querySelector(`[data-today-task="${CSS.escape(String(id))}"] [data-task-byline]`);

            if (!byline) {
                return;
            }

            const parts = [];

            if (task.created_by_name) {
                parts.push(`Added by ${task.created_by_name}`);
            }

            if (task.updated_by_name) {
                parts.push(`Edited by ${task.updated_by_name}`);
            }

            byline.textContent = parts.join(' · ');
            byline.hidden = parts.length === 0;
        },
        removeTask(id) {
            return [...document.querySelectorAll(`[data-task-id="${CSS.escape(String(id))}"]`)].map((node) => {
                const item = node.closest('li') || node;
                const place = { parent: item.parentNode, next: item.nextSibling, item };
                item.remove();

                return place;
            });
        },
        paintHoliday(date) {
            document.querySelectorAll(`[data-cell][data-date="${CSS.escape(date)}"]`).forEach((button) => {
                const status = button.dataset.status || '';

                if (status === '' || status === 'SHIFT') {
                    this.paintCell(button, 'PH');
                }
            });
        },
        chipClass(kind) {
            if (kind === 'event') {
                return 'bg-primary-container text-on-primary-container';
            }

            if (kind === 'holiday') {
                return 'bg-error-container text-on-error-container';
            }

            return 'bg-success-container text-on-success-container';
        },
        kindLabel(kind) {
            if (kind === 'event') {
                return 'Event';
            }

            if (kind === 'holiday') {
                return 'Public holiday';
            }

            return 'Task';
        },
        toast(message, kind) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, kind } }));
        },
    }));
});

const warmedPages = new Set();

function prefetchPage(url) {
    if (!url || warmedPages.has(url) || url === window.location.href) {
        return;
    }

    warmedPages.add(url);
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.as = 'document';
    link.href = url;
    document.head.appendChild(link);
}

function armNavigation() {
    const progress = document.getElementById('nav-progress');

    document.querySelectorAll('[data-nav]').forEach((link) => {
        const warm = () => prefetchPage(link.href);
        link.addEventListener('pointerenter', warm, { once: true });
        link.addEventListener('focus', warm, { once: true });
        link.addEventListener('touchstart', warm, { passive: true, once: true });
        link.addEventListener('click', () => {
            if (link.getAttribute('aria-current') !== 'page') {
                progress?.classList.remove('hidden');
            }
        });
    });

    const idle = window.requestIdleCallback || ((callback) => window.setTimeout(callback, 300));
    idle(() => document.querySelectorAll('[data-nav]').forEach((link) => prefetchPage(link.href)));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', armNavigation);
} else {
    armNavigation();
}

Alpine.start();
