import Alpine from 'alpinejs';

window.Alpine = Alpine;

const blockClass = {
    SHIFT: 'bg-emerald-200/85 text-emerald-950 dark:bg-emerald-900/75 dark:text-emerald-100',
    OFF: 'bg-red-200/85 text-red-950 dark:bg-red-900/75 dark:text-red-100',
    AL: 'bg-amber-200/90 text-amber-950 dark:bg-amber-900/75 dark:text-amber-100',
    DP: 'bg-blue-200/85 text-blue-950 dark:bg-blue-900/75 dark:text-blue-100',
    SL: 'bg-orange-200/90 text-orange-950 dark:bg-orange-900/75 dark:text-orange-100',
    PH: 'bg-purple-200/85 text-purple-950 dark:bg-purple-900/75 dark:text-purple-100',
    BLACK: 'bg-neutral-950 text-white hover:bg-neutral-800 dark:bg-black dark:border-neutral-700',
    '': 'bg-white text-slate-400 dark:bg-[#1E1F23]',
};

const statusLabel = {
    SHIFT: 'ON',
    OFF: 'OFF',
    AL: 'AL',
    DP: 'DP',
    SL: 'SL',
    PH: 'PH',
    BLACK: '',
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
        multiMode: false,
        selectedDates: [],
        selectedUserId: '',
        selectedUserName: '',
        anchorDate: '',
        dragging: false,
        dragMoved: false,
        dragUserId: '',
        dragUserName: '',
        dragStartDate: '',
        suppressClick: false,
        activityOpen: false,
        dayOpen: false,
        dayDate: '',
        dayLabel: '',
        today: initial.today || '',
        todayLabel: initial.todayLabel || '',
        actorName: initial.actorName || '',
        upcomingStart: initial.upcomingStart || '',
        upcomingEnd: initial.upcomingEnd || '',
        undo: [],
        trackingUndo: false,
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

            this.onPointerMove = (event) => this.dragOver(event);
            this.onPointerUp = () => this.endDrag();
            window.addEventListener('pointermove', this.onPointerMove);
            window.addEventListener('pointerup', this.onPointerUp);
            window.addEventListener('pointercancel', this.onPointerUp);
        },
        startDrag(event) {
            if (event.pointerType && event.pointerType !== 'mouse') {
                return;
            }

            if (event.button !== undefined && event.button !== 0) {
                return;
            }

            const cell = event.currentTarget;
            this.dragging = true;
            this.dragMoved = false;
            this.dragUserId = cell.dataset.userId;
            this.dragUserName = cell.dataset.userName;
            this.dragStartDate = cell.dataset.date;
        },
        dragOver(event) {
            if (!this.dragging) {
                return;
            }

            const cell = event.target?.closest?.('[data-cell]');

            if (!cell || cell.dataset.userId !== this.dragUserId || cell.dataset.date === this.dragStartDate) {
                return;
            }

            this.dragMoved = true;
            this.selectRange(this.dragUserId, this.dragUserName, this.dragStartDate, cell.dataset.date);
        },
        endDrag() {
            if (!this.dragging) {
                return;
            }

            if (this.dragMoved) {
                this.suppressClick = true;
            }

            this.dragging = false;
        },
        chooseCell(event) {
            const cell = event.currentTarget;

            if (this.suppressClick) {
                this.suppressClick = false;
                event.preventDefault();

                return;
            }

            if (event.shiftKey && this.anchorDate && this.selectedUserId === cell.dataset.userId) {
                this.selectRange(cell.dataset.userId, cell.dataset.userName, this.anchorDate, cell.dataset.date);

                return;
            }

            if (this.multiMode) {
                if (!this.anchorDate || this.selectedUserId !== cell.dataset.userId) {
                    this.anchorDate = cell.dataset.date;
                    this.selectRange(cell.dataset.userId, cell.dataset.userName, cell.dataset.date, cell.dataset.date);
                } else {
                    this.selectRange(cell.dataset.userId, cell.dataset.userName, this.anchorDate, cell.dataset.date);
                    this.anchorDate = '';
                }

                return;
            }

            this.anchorDate = cell.dataset.date;
            this.selectedUserId = cell.dataset.userId;
            this.selectedUserName = cell.dataset.userName;

            this.clearSelection();
            this.anchorDate = cell.dataset.date;
            this.selectedUserId = cell.dataset.userId;
            this.selectedUserName = cell.dataset.userName;
            this.openCell(cell);
        },
        selectRange(userId, userName, from, to) {
            const cells = [...document.querySelectorAll(`[data-cell][data-user-id="${CSS.escape(String(userId))}"]`)];
            const dates = cells.map((cell) => cell.dataset.date);
            const start = dates.indexOf(from);
            const end = dates.indexOf(to);

            if (start < 0 || end < 0) {
                return;
            }

            const [fromIndex, toIndex] = start < end ? [start, end] : [end, start];
            this.selectedUserId = String(userId);
            this.selectedUserName = userName;
            this.selectedDates = dates.slice(fromIndex, toIndex + 1);
            this.anchorDate = from;
            this.paintSelection();
        },
        paintSelection() {
            document.querySelectorAll('[data-cell].roster-selected').forEach((cell) => cell.classList.remove('roster-selected'));
            const chosen = new Set(this.selectedDates);
            document.querySelectorAll(`[data-cell][data-user-id="${CSS.escape(String(this.selectedUserId))}"]`).forEach((cell) => {
                cell.classList.toggle('roster-selected', chosen.has(cell.dataset.date));
            });
        },
        clearSelection() {
            this.selectedDates = [];
            this.selectedUserId = '';
            this.selectedUserName = '';
            document.querySelectorAll('[data-cell].roster-selected').forEach((cell) => cell.classList.remove('roster-selected'));
        },
        async applyBulk(status) {
            if (this.selectedDates.length === 0) {
                return;
            }

            const cells = [...document.querySelectorAll(`[data-cell][data-user-id="${CSS.escape(String(this.selectedUserId))}"]`)]
                .filter((cell) => this.selectedDates.includes(cell.dataset.date));
            const previous = cells.map((cell) => cell.dataset.status || '');
            cells.forEach((cell) => this.paintCell(cell, status || ''));

            try {
                const response = await fetch('/roster/bulk-update', {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        user_id: Number(this.selectedUserId),
                        dates: this.selectedDates,
                        status,
                    }),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    cells.forEach((cell, index) => this.paintCell(cell, previous[index]));
                    this.toast(this.errorText(data) || 'Those days could not be saved.', 'error');

                    return;
                }

                this.clearSelection();
                this.toast(status ? 'Schedule updated.' : 'Selection cleared.');
            } catch {
                cells.forEach((cell, index) => this.paintCell(cell, previous[index]));
                this.toast('Those days could not be saved. Check the connection and try again.', 'error');
            }
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
        openEvent(date, label) {
            this.openTask(date, label);
            this.taskKind = 'event';
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
            const selected = button.classList.contains('roster-selected');
            button.dataset.status = status || '';
            button.className = `roster-block h-full min-h-[46px] w-full border-0 p-0 flex items-center justify-center text-xs font-bold tracking-wide ${blockClass[status] || blockClass['']}${selected ? ' roster-selected' : ''}`;
            button.textContent = !status ? '+' : (statusLabel[status] ?? status);

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
                this.undo = [];
                this.trackingUndo = true;
                this.paintTask(id, payload);
                this.paintCalendarKind(id, payload);
                this.placeTodayItem(id, payload);
                this.syncUpcoming({ ...payload, id });
                this.trackingUndo = false;
                this.paintDayMarks(payload.date);
            } else {
                this.insertPendingTask(payload);
                this.insertTodayTask(payload);
                this.insertTodayEvent(payload);
                this.insertUpcoming(payload);
                this.paintDayMarks(payload.date);
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

                this.undo = [];
                this.noteActivity(data.activity);

                if (data.task) {
                    this.placeTodayItem(data.task.id, data.task);
                    this.paintCalendarKind(data.task.id, data.task);
                }

                this.applyByline(editing ? id : data.task?.id, data.task);

                if (data.task?.kind === 'holiday') {
                    this.paintHoliday(data.task.date);
                }

                if (data.task) {
                    this.syncUpcoming(data.task);
                    this.paintDayMarks(data.task.date);
                    this.refreshDaySheet();
                }
            } catch (error) {
                if (editing) {
                    this.revertUndo();
                    this.restoreTask(snapshot);
                } else {
                    this.removePendingTask();
                }

                this.paintDayMarks(payload.date);
                this.showUpcomingEmpty();
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
                document.querySelectorAll(`[data-today-task="${CSS.escape(String(id))}"] input[type="checkbox"]`).forEach((box) => {
                    box.checked = this.taskDone;
                });
                this.toast(error.message || 'That task could not be updated.', 'error');
            }
        },
        async deleteTask() {
            if (!confirm('Remove this from the calendar?')) {
                return;
            }

            const id = this.taskId();
            const date = this.taskDate;
            const removed = this.removeTask(id);
            this.taskOpen = false;
            this.paintDayMarks(date);
            this.showUpcomingEmpty();
            this.refreshDaySheet();

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
                this.showTodayEventsEmpty();
            } catch (error) {
                removed.forEach(({ parent, next, item }) => {
                    parent.insertBefore(item, next);
                });
                this.paintDayMarks(date);
                this.showUpcomingEmpty();
                this.refreshDaySheet();
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

                if (node.dataset.kind !== undefined) {
                    node.dataset.kind = task.kind || node.dataset.kind;
                    node.dataset.done = done ? '1' : '0';
                }
            });

            document.querySelectorAll(`[data-today-task="${CSS.escape(String(id))}"] input[type="checkbox"]`).forEach((box) => {
                box.checked = done;
            });

            document.querySelectorAll(`[data-today-task="${CSS.escape(String(id))}"] [aria-label], [data-today-event="${CSS.escape(String(id))}"] [aria-label]`).forEach((control) => {
                const label = control.getAttribute('aria-label') || '';

                if (label.startsWith('Edit ')) {
                    control.setAttribute('aria-label', `Edit ${task.title}`);
                } else if (label.startsWith('Remove ')) {
                    control.setAttribute('aria-label', `Remove ${task.title}`);
                } else if (label.startsWith('Mark ')) {
                    control.setAttribute('aria-label', `Mark ${task.title} complete`);
                }
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
            button.dataset.kind = task.kind;
            button.dataset.done = task.is_done ? '1' : '0';
            button.className = this.calendarChipClass(task.kind, false);
            button.innerHTML = `<span class="block text-[10px] font-medium tracking-wide">${this.kindLabel(task.kind)}</span><span class="line-clamp-2 block break-normal font-medium" data-task-title></span>`;
            button.querySelector('[data-task-title]').textContent = task.title;
            host.appendChild(button);
        },
        stampPendingTask(task) {
            document.querySelectorAll('[data-today-task="pending"], [data-today-event="pending"]').forEach((row) => {
                if (row.hasAttribute('data-today-task')) {
                    row.dataset.todayTask = String(task.id);
                }

                if (row.hasAttribute('data-today-event')) {
                    row.dataset.todayEvent = String(task.id);
                }
            });

            document.querySelectorAll('[data-pending-upcoming]').forEach((row) => {
                delete row.dataset.pendingUpcoming;
                row.dataset.id = String(task.id);
                row.dataset.date = task.date;
                row.dataset.upcomingId = String(task.id);
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
            const dates = new Set();
            document.querySelectorAll('[data-pending-task]').forEach((node) => {
                const day = node.closest('[data-calendar-day]');

                if (day) {
                    dates.add(day.dataset.calendarDay);
                }
            });
            [...document.querySelectorAll('[data-pending-task]')].forEach((node) => {
                (node.closest('[data-today-task], [data-today-event], [data-upcoming-id], [data-pending-upcoming]') || node).remove();
            });
            dates.forEach((date) => this.paintDayMarks(date));
            this.showTodayEmpty();
            this.showTodayEventsEmpty();
            this.showUpcomingEmpty();
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
            const pending = !task.id;
            const row = document.createElement('li');
            row.dataset.todayTask = pending ? 'pending' : String(task.id);
            row.dataset.date = task.date;
            row.dataset.createdBy = task.created_by_name || (pending ? this.actorName : '');
            row.dataset.updatedBy = task.updated_by_name || '';
            row.className = 'flex items-start gap-2 rounded-xl px-1 py-1';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'm3-check mt-1';
            checkbox.checked = Boolean(task.is_done);
            checkbox.setAttribute('aria-label', `Mark ${task.title} complete`);
            checkbox.addEventListener('change', (event) => this.toggleTodayTask(event.target));

            const copy = document.createElement('div');
            copy.className = 'min-w-0 flex-1';
            const title = document.createElement('button');
            title.type = 'button';
            title.dataset.taskId = pending ? 'pending' : String(task.id);
            title.dataset.taskTitle = '';
            title.className = `w-full text-left text-sm font-medium break-words${task.is_done ? ' line-through opacity-60' : ''}`;
            title.textContent = task.title;

            if (pending) {
                title.dataset.pendingTask = '1';
            }

            const byline = document.createElement('p');
            byline.dataset.taskByline = '';
            byline.className = 'text-xs text-on-surface-variant';
            byline.textContent = this.bylineText(task, pending);
            byline.hidden = byline.textContent === '';
            copy.append(title, byline);

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'grid size-8 shrink-0 place-items-center rounded-full text-on-surface-variant hover:bg-surface-high material-symbols-rounded text-[18px]';
            edit.setAttribute('aria-label', `Edit ${task.title}`);
            edit.textContent = 'edit';
            edit.addEventListener('click', () => {
                if (row.dataset.todayTask === 'pending') {
                    return;
                }

                this.openEdit({
                    id: row.dataset.todayTask,
                    date: row.dataset.date,
                    label: this.todayLabel || this.taskDateLabel,
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
            this.remember(() => row.remove());
        },
        insertTodayEvent(task) {
            if (task.kind !== 'event' || task.date !== this.today) {
                return;
            }

            const list = document.querySelector('[data-today-events]');

            if (!list) {
                return;
            }

            list.querySelector('[data-today-events-empty]')?.remove();
            const pending = !task.id;
            const row = document.createElement('li');
            row.dataset.todayEvent = pending ? 'pending' : String(task.id);
            row.dataset.date = task.date;
            row.dataset.createdBy = task.created_by_name || (pending ? this.actorName : '');
            row.dataset.updatedBy = task.updated_by_name || '';
            row.className = 'flex items-start gap-2 rounded-xl px-1 py-1';

            const badge = document.createElement('span');
            badge.className = 'm3-chip mt-0.5 shrink-0 border-0 bg-primary-container text-on-primary-container';
            badge.textContent = 'Event';

            const copy = document.createElement('div');
            copy.className = 'min-w-0 flex-1';
            const title = document.createElement('button');
            title.type = 'button';
            title.dataset.taskId = pending ? 'pending' : String(task.id);
            title.dataset.taskTitle = '';
            title.className = 'w-full text-left text-sm font-medium break-words';
            title.textContent = task.title;

            if (pending) {
                title.dataset.pendingTask = '1';
            }

            const byline = document.createElement('p');
            byline.dataset.taskByline = '';
            byline.className = 'text-xs text-on-surface-variant';
            byline.textContent = this.bylineText(task, pending);
            byline.hidden = byline.textContent === '';
            copy.append(title, byline);

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'grid size-8 shrink-0 place-items-center rounded-full text-on-surface-variant hover:bg-surface-high material-symbols-rounded text-[18px]';
            edit.setAttribute('aria-label', `Edit ${task.title}`);
            edit.textContent = 'edit';
            edit.addEventListener('click', () => {
                if (row.dataset.todayEvent === 'pending') {
                    return;
                }

                this.openEdit({
                    id: row.dataset.todayEvent,
                    date: row.dataset.date,
                    label: this.todayLabel || this.taskDateLabel,
                    title: title.textContent,
                    kind: 'event',
                    done: false,
                });
            });

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'grid size-8 shrink-0 place-items-center rounded-full text-error hover:bg-error-container material-symbols-rounded text-[18px]';
            remove.setAttribute('aria-label', `Remove ${task.title}`);
            remove.textContent = 'delete';
            remove.addEventListener('click', () => {
                if (row.dataset.todayEvent !== 'pending') {
                    this.removeTodayEvent(row.dataset.todayEvent);
                }
            });

            row.append(badge, copy, edit, remove);
            list.appendChild(row);
            this.remember(() => row.remove());
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
                this.paintDayMarks(row.dataset.date);
                this.refreshDaySheet();
            } catch (error) {
                this.restoreTask(snapshot);
                input.checked = !done;
                this.paintDayMarks(row.dataset.date);
                this.refreshDaySheet();
                this.toast(error.message || 'That task could not be updated.', 'error');
            }
        },
        async removeTodayTask(id) {
            await this.removeTodayItem(id, 'task');
        },
        async removeTodayEvent(id) {
            await this.removeTodayItem(id, 'event');
        },
        async removeTodayItem(id, noun) {
            if (!confirm(`Remove this ${noun}?`)) {
                return;
            }

            const date = document.querySelector(`[data-today-task="${CSS.escape(String(id))}"], [data-today-event="${CSS.escape(String(id))}"], [data-upcoming] [data-task-id="${CSS.escape(String(id))}"]`)?.dataset.date
                || document.querySelector(`[data-calendar-day] [data-task-id="${CSS.escape(String(id))}"]`)?.closest('[data-calendar-day]')?.dataset.calendarDay
                || '';
            const removed = this.removeTask(id);
            this.showTodayEmpty();
            this.showTodayEventsEmpty();
            this.paintDayMarks(date);
            this.showUpcomingEmpty();
            this.refreshDaySheet();

            try {
                const response = await fetch(`/roster/tasks/${id}`, {
                    method: 'DELETE',
                    headers: this.jsonHeaders(),
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(this.errorText(data) || `That ${noun} could not be removed.`);
                }

                this.noteActivity(data.activity);
            } catch (error) {
                removed.forEach(({ parent, next, item }) => {
                    parent.insertBefore(item, next);
                });
                document.querySelector('[data-today-empty]')?.remove();
                document.querySelector('[data-today-events-empty]')?.remove();
                this.showTodayEmpty();
                this.showTodayEventsEmpty();
                this.paintDayMarks(date);
                this.showUpcomingEmpty();
                this.refreshDaySheet();
                this.toast(error.message || `That ${noun} could not be removed.`, 'error');
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
            this.remember(() => empty.remove());
        },
        showTodayEventsEmpty() {
            const list = document.querySelector('[data-today-events]');

            if (!list || list.querySelector('[data-today-event]') || list.querySelector('[data-today-events-empty]')) {
                return;
            }

            const empty = document.createElement('li');
            empty.dataset.todayEventsEmpty = '';
            empty.className = 'text-sm text-on-surface-variant';
            empty.textContent = 'No events for today.';
            list.appendChild(empty);
            this.remember(() => empty.remove());
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

            const row = document.querySelector(`[data-today-task="${CSS.escape(String(id))}"], [data-today-event="${CSS.escape(String(id))}"]`);
            const byline = row?.querySelector('[data-task-byline]');

            if (!row || !byline) {
                return;
            }

            if (task.created_by_name) {
                row.dataset.createdBy = task.created_by_name;
            }

            if (task.updated_by_name) {
                row.dataset.updatedBy = task.updated_by_name;
            }

            byline.textContent = this.bylineText(task, false);
            byline.hidden = byline.textContent === '';
        },
        bylineText(task, pending) {
            const parts = [];
            const created = task.created_by_name || (pending ? this.actorName : '');

            if (created) {
                parts.push(`Added by ${created}`);
            }

            if (task.updated_by_name) {
                parts.push(`Edited by ${task.updated_by_name}`);
            }

            return parts.join(' · ');
        },
        placeTodayItem(id, task) {
            const key = String(id);
            const taskRow = document.querySelector(`[data-today-task="${CSS.escape(key)}"]`);
            const eventRow = document.querySelector(`[data-today-event="${CSS.escape(key)}"]`);
            const source = taskRow || eventRow;
            const next = {
                ...task,
                id: key,
                created_by_name: task.created_by_name || source?.dataset.createdBy || '',
                updated_by_name: task.updated_by_name || source?.dataset.updatedBy || '',
            };

            if (task.kind === 'task' && task.date === this.today) {
                if (eventRow) {
                    this.detach(eventRow);
                }

                if (!taskRow) {
                    this.insertTodayTask(next);
                }
            } else if (task.kind === 'event' && task.date === this.today) {
                if (taskRow) {
                    this.detach(taskRow);
                }

                if (!eventRow) {
                    this.insertTodayEvent(next);
                }
            } else {
                if (taskRow) {
                    this.detach(taskRow);
                }

                if (eventRow) {
                    this.detach(eventRow);
                }
            }

            this.showTodayEmpty();
            this.showTodayEventsEmpty();
        },
        paintCalendarKind(id, task) {
            const nodes = [...document.querySelectorAll(`[data-calendar-day] [data-task-id="${CSS.escape(String(id))}"]`)];

            if (nodes.length === 0) {
                this.insertCalendarChip(task, id);

                return;
            }

            nodes.forEach((node) => {
                const button = node.matches('button') ? node : node.closest('button');

                if (!button) {
                    return;
                }

                const group = button.closest('ol');

                if (group && task.kind === 'task') {
                    return;
                }

                if (group) {
                    const item = button.closest('li');
                    const wrap = group.parentElement;
                    this.detach(item);

                    if (wrap && !group.querySelector('li')) {
                        this.detach(wrap);
                    }

                    this.insertCalendarChip(task, id);

                    return;
                }

                const previousClass = button.className;
                const label = [...button.children].find((child) => child.tagName === 'SPAN' && !child.hasAttribute('data-task-title'));
                const previousLabel = label ? label.textContent : '';
                const done = task.kind === 'task' && Boolean(task.is_done);
                button.dataset.kind = task.kind;
                button.dataset.done = done ? '1' : '0';
                button.className = this.calendarChipClass(task.kind, done);

                if (label) {
                    label.textContent = this.kindLabel(task.kind);
                }

                this.remember(() => {
                    button.className = previousClass;

                    if (label) {
                        label.textContent = previousLabel;
                    }
                });
            });
        },
        insertCalendarChip(task, id) {
            const host = document.querySelector(`[data-calendar-day="${CSS.escape(task.date)}"] [data-day-items]`);

            if (!host || host.querySelector(`[data-task-id="${CSS.escape(String(id))}"]`)) {
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.taskId = String(id);
            button.dataset.kind = task.kind;
            const done = task.kind === 'task' && Boolean(task.is_done);
            button.dataset.done = done ? '1' : '0';
            button.className = this.calendarChipClass(task.kind, done);
            const label = document.createElement('span');
            label.className = 'block text-[10px] font-medium tracking-wide';
            label.textContent = this.kindLabel(task.kind);
            const title = document.createElement('span');
            title.className = 'line-clamp-2 block break-normal font-medium';
            title.dataset.taskTitle = '';
            title.textContent = task.title;
            button.append(label, title);
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                this.openEdit({
                    id,
                    date: task.date,
                    label: task.date === this.today ? (this.todayLabel || this.taskDateLabel) : this.taskDateLabel,
                    title: title.textContent,
                    kind: task.kind,
                    done: title.classList.contains('line-through'),
                });
            });
            host.appendChild(button);
            this.remember(() => button.remove());
            this.paintDayMarks(task.date);
        },
        detach(node) {
            if (!node?.parentNode) {
                return;
            }

            const parent = node.parentNode;
            const next = node.nextSibling;
            node.remove();
            this.remember(() => parent.insertBefore(node, next));
        },
        remember(undo) {
            if (this.trackingUndo) {
                this.undo.push(undo);
            }
        },
        revertUndo() {
            this.undo.reverse().forEach((undo) => undo());
            this.undo = [];
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
        openCalendarDay(event, date, label) {
            if (event.target.closest('button, a, input, label')) {
                return;
            }

            if (window.matchMedia('(max-width: 639px)').matches) {
                this.dayDate = date;
                this.dayLabel = label;
                this.dayOpen = true;
                this.$nextTick(() => this.renderDaySheet());

                return;
            }

            this.addHint = date;
        },
        refreshDaySheet() {
            if (this.dayOpen) {
                this.renderDaySheet();
            }
        },
        renderDaySheet() {
            const list = document.querySelector('[data-day-sheet]');

            if (!list || !this.dayDate) {
                return;
            }

            const items = [...document.querySelectorAll(`[data-calendar-day="${CSS.escape(this.dayDate)}"] [data-day-items] [data-task-id]`)];
            list.replaceChildren();

            if (items.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'text-sm text-on-surface-variant';
                empty.textContent = 'Nothing scheduled on this day.';
                list.appendChild(empty);

                return;
            }

            items.forEach((node) => {
                const id = node.dataset.taskId;
                const kind = node.dataset.kind || 'task';
                const title = (node.matches('[data-task-title]') ? node : node.querySelector('[data-task-title]'))?.textContent?.trim() || '';
                const done = node.dataset.done === '1';
                const row = document.createElement('li');
                row.className = 'rounded-2xl bg-surface-low px-3 py-3';

                const heading = document.createElement('div');
                heading.className = 'flex items-center gap-2';
                const chip = document.createElement('span');
                chip.className = `m3-chip border-0 ${this.chipClass(kind)}`;
                chip.textContent = this.kindLabel(kind);
                heading.appendChild(chip);

                const name = document.createElement('p');
                name.className = `min-w-0 flex-1 text-base font-medium${done ? ' line-through opacity-60' : ''}`;
                name.textContent = title;
                heading.appendChild(name);
                row.appendChild(heading);

                if (id !== 'pending') {
                    const actions = document.createElement('div');
                    actions.className = 'mt-3 flex flex-wrap gap-2';

                    if (kind === 'task') {
                        const complete = document.createElement('button');
                        complete.type = 'button';
                        complete.className = 'btn-tonal state-layer h-10 px-3 text-sm';
                        complete.textContent = done ? 'Completed' : 'Complete';
                        complete.addEventListener('click', () => this.setItemDone(id, this.dayDate, title, !done));
                        actions.appendChild(complete);
                    }

                    const edit = document.createElement('button');
                    edit.type = 'button';
                    edit.className = 'btn-outlined state-layer h-10 px-3 text-sm';
                    edit.textContent = 'Edit';
                    edit.addEventListener('click', () => {
                        this.dayOpen = false;
                        this.openEdit({
                            id,
                            date: this.dayDate,
                            label: this.dayLabel,
                            title,
                            kind,
                            done,
                        });
                    });

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn-text state-layer h-10 px-3 text-sm text-error';
                    remove.textContent = 'Remove';
                    remove.addEventListener('click', () => this.removeTodayItem(id, kind === 'event' ? 'event' : (kind === 'holiday' ? 'holiday' : 'task')));
                    actions.append(edit, remove);
                    row.appendChild(actions);
                }

                list.appendChild(row);
            });
        },
        async setItemDone(id, date, title, done) {
            const snapshot = this.snapshotTask(id);
            this.paintTask(id, { title, kind: 'task', is_done: done, date });
            this.paintDayMarks(date);
            this.refreshDaySheet();

            try {
                const response = await fetch(`/roster/tasks/${id}`, {
                    method: 'PUT',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        date,
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
                this.syncUpcoming(data.task || { id, date, title, kind: 'task', is_done: done });
            } catch (error) {
                this.restoreTask(snapshot);
                this.paintDayMarks(date);
                this.refreshDaySheet();
                this.toast(error.message || 'That task could not be updated.', 'error');
            }
        },
        paintDayMarks(date) {
            if (!date) {
                return;
            }

            const day = document.querySelector(`[data-calendar-day="${CSS.escape(date)}"]`);
            const marks = day?.querySelector('[data-day-marks]');

            if (!marks) {
                return;
            }

            const items = [...day.querySelectorAll('[data-day-items] [data-task-id]')];
            marks.replaceChildren();
            items.slice(0, 2).forEach((node) => {
                const bar = document.createElement('span');
                bar.className = `h-1.5 min-w-2 flex-1 rounded-full ${this.markClass(node.dataset.kind)}`;
                marks.appendChild(bar);
            });

            if (items.length > 2) {
                const more = document.createElement('span');
                more.className = 'text-[10px] font-semibold leading-none text-on-surface-variant';
                more.textContent = `+${items.length - 2}`;
                marks.appendChild(more);
            }
        },
        markClass(kind) {
            if (kind === 'event') {
                return 'bg-blue-500';
            }

            if (kind === 'holiday') {
                return 'bg-purple-600';
            }

            return 'bg-emerald-500';
        },
        inUpcoming(date) {
            return Boolean(date) && this.upcomingStart && this.upcomingEnd && date >= this.upcomingStart && date <= this.upcomingEnd;
        },
        insertUpcoming(task) {
            if (!this.inUpcoming(task.date)) {
                return;
            }

            const list = document.querySelector('[data-upcoming]');

            if (!list) {
                return;
            }

            const existing = task.id
                ? list.querySelector(`[data-task-id="${CSS.escape(String(task.id))}"]`)
                : null;

            if (existing) {
                return;
            }

            const pending = !task.id;
            const done = task.kind === 'task' && Boolean(task.is_done);
            const item = document.createElement('li');
            item.dataset.date = task.date;

            if (pending) {
                item.dataset.pendingUpcoming = '1';
            } else {
                item.dataset.id = String(task.id);
                item.dataset.upcomingId = String(task.id);
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.taskId = pending ? 'pending' : String(task.id);
            button.dataset.kind = task.kind;
            button.dataset.done = done ? '1' : '0';
            button.dataset.date = task.date;
            button.className = `row-hover w-full rounded-2xl px-2 py-2 text-left text-sm${done ? ' line-through opacity-60' : ''}`;

            if (pending) {
                button.dataset.pendingTask = '1';
            }

            const date = document.createElement('span');
            date.className = 'font-medium';
            date.dataset.upcomingDate = '';
            date.textContent = this.formatDay(task.date);
            const chip = document.createElement('span');
            chip.dataset.upcomingKind = '';
            chip.className = `ml-2 m3-chip border-0 ${this.chipClass(task.kind)}`;
            chip.textContent = this.kindLabel(task.kind);
            const title = document.createElement('span');
            title.className = 'ml-2';
            title.dataset.taskTitle = '';
            title.textContent = task.title;
            button.append(date, chip, title);

            if (!pending) {
                button.addEventListener('click', () => {
                    this.openEdit({
                        id: task.id,
                        date: task.date,
                        label: this.formatLongDay(task.date),
                        title: title.textContent,
                        kind: task.kind,
                        done: button.dataset.done === '1',
                    });
                });
            }

            item.appendChild(button);
            list.appendChild(item);
            this.remember(() => item.remove());
            this.renderOrSortUpcoming();
        },
        syncUpcoming(task) {
            if (!task?.date) {
                return;
            }

            const list = document.querySelector('[data-upcoming]');

            if (!list) {
                return;
            }

            const id = task.id ? String(task.id) : '';
            const node = id
                ? list.querySelector(`[data-id="${CSS.escape(id)}"], [data-task-id="${CSS.escape(id)}"]`)
                : list.querySelector('[data-pending-upcoming], [data-pending-task]');
            const row = node?.matches('li') ? node : node?.closest('li');

            if (!this.inUpcoming(task.date)) {
                row?.remove();
                this.renderOrSortUpcoming();

                return;
            }

            if (!row) {
                this.insertUpcoming(task);

                return;
            }

            row.dataset.date = task.date;

            if (id) {
                row.dataset.id = id;
                row.dataset.upcomingId = id;
            }

            const button = row.querySelector('button') || (node?.matches('button') ? node : null);
            const done = task.kind === 'task' && Boolean(task.is_done);

            if (button) {
                button.dataset.kind = task.kind;
                button.dataset.done = done ? '1' : '0';
                button.dataset.date = task.date;
                button.classList.toggle('line-through', done);
                button.classList.toggle('opacity-60', done);
                const chip = button.querySelector('[data-upcoming-kind]');

                if (chip) {
                    chip.textContent = this.kindLabel(task.kind);
                    chip.className = `ml-2 m3-chip border-0 ${this.chipClass(task.kind)}`;
                }

                const date = button.querySelector('[data-upcoming-date]');

                if (date) {
                    date.textContent = this.formatDay(task.date);
                }

                const title = button.querySelector('[data-task-title]');

                if (title) {
                    title.textContent = task.title;
                }
            }

            this.renderOrSortUpcoming();
        },
        renderOrSortUpcoming() {
            const list = document.querySelector('[data-upcoming]');

            if (!list) {
                return;
            }

            [...list.querySelectorAll(':scope > li[data-date]')].forEach((row) => {
                if (!this.inUpcoming(row.dataset.date)) {
                    row.remove();
                }
            });

            const rows = [...list.querySelectorAll(':scope > li[data-date]')];
            rows.sort((left, right) => {
                const byDate = (left.dataset.date || '').localeCompare(right.dataset.date || '');

                if (byDate !== 0) {
                    return byDate;
                }

                return Number(left.dataset.id || 0) - Number(right.dataset.id || 0);
            });
            rows.forEach((row) => list.appendChild(row));

            const empty = list.querySelector('[data-upcoming-empty]');

            if (rows.length > 0) {
                empty?.remove();

                return;
            }

            if (empty) {
                list.appendChild(empty);

                return;
            }

            const placeholder = document.createElement('li');
            placeholder.dataset.upcomingEmpty = '';
            placeholder.className = 'px-2 py-2 text-sm text-on-surface-variant';
            placeholder.textContent = 'No upcoming events or tasks in the next 30 days.';
            list.appendChild(placeholder);
        },
        showUpcomingEmpty() {
            this.renderOrSortUpcoming();
        },
        formatDay(value) {
            const [year, month, day] = String(value).split('-').map(Number);

            if (!year || !month || !day) {
                return value;
            }

            return new Date(year, month - 1, day).toLocaleDateString('en-GB', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        },
        formatLongDay(value) {
            const [year, month, day] = String(value).split('-').map(Number);

            if (!year || !month || !day) {
                return value;
            }

            return new Date(year, month - 1, day).toLocaleDateString('en-GB', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
        calendarChipClass(kind, done) {
            return `w-full rounded-lg px-2 py-1.5 text-left text-xs leading-snug ${this.chipClass(kind)}${done ? ' line-through opacity-60' : ''}`;
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
