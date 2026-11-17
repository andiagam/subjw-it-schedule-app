import Alpine from 'alpinejs';

window.Alpine = Alpine;

const badgeClass = {
    SHIFT: 'bg-emerald-100 text-emerald-800',
    OFF: 'bg-slate-200 text-slate-700',
    AL: 'bg-amber-100 text-amber-900',
    DP: 'bg-indigo-100 text-indigo-800',
    SL: 'bg-pink-100 text-pink-800',
    PH: 'bg-rose-200 text-rose-900',
};

const statusLabel = {
    SHIFT: 'ON',
    OFF: 'OFF',
    AL: 'AL',
    DP: 'DP',
    SL: 'SL',
    PH: 'PH',
};

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

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
            this.saving = true;

            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                const response = await fetch('/roster/entries', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        user_id: Number(this.userId),
                        date: this.date,
                        status: this.status,
                        note: '',
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                    this.toast(firstError || data.message || 'That cell could not be saved.', 'error');
                    return;
                }

                const button = document.querySelector(`[data-cell="${CSS.escape(`${this.userId}-${this.date}`)}"]`);

                if (button) {
                    button.dataset.status = data.entry.status;
                    const label = statusLabel[data.entry.status] || data.entry.status;
                    button.innerHTML = `<span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${badgeClass[data.entry.status] || ''}">${escapeHtml(label)}</span>`;
                }

                this.open = false;
                this.toast(data.message || 'Schedule updated.', 'success');
            } catch {
                this.toast('That cell could not be saved.', 'error');
            } finally {
                this.saving = false;
            }
        },
        toast(message, kind) {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, kind } }));
        },
    }));
});

Alpine.start();
