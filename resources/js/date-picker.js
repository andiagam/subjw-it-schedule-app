const WEEKDAYS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

function parseISO(value) {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatISO(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

function formatLabel(iso) {
    const date = parseISO(iso);

    if (!date) {
        return '';
    }

    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function addMonths(date, count) {
    return new Date(date.getFullYear(), date.getMonth() + count, 1);
}

function inclusiveDays(start, end) {
    const from = parseISO(start);
    const to = parseISO(end);

    if (!from || !to) {
        return 0;
    }

    const ms = Date.UTC(to.getFullYear(), to.getMonth(), to.getDate()) - Date.UTC(from.getFullYear(), from.getMonth(), from.getDate());

    return Math.round(ms / 86400000) + 1;
}

class DateRangePicker {
    constructor(root) {
        this.root = root;
        this.mode = root.dataset.mode === 'single' ? 'single' : 'range';
        this.clearable = root.hasAttribute('data-clearable');
        this.today = root.dataset.today || formatISO(new Date());
        this.trigger = root.querySelector('[data-picker-open]');
        this.label = root.querySelector('[data-picker-label]');
        this.count = root.querySelector('[data-picker-count]');
        this.startInput = root.querySelector('[data-picker-start]');
        this.endInput = root.querySelector('[data-picker-end]');
        this.popover = root.querySelector('[data-picker-popover]');
        this.months = this.popover.querySelector('[data-picker-months]');
        this.clearButton = this.popover.querySelector('[data-picker-clear]');
        this.view = parseISO(this.startInput.value) || parseISO(this.today) || new Date();
        this.view = new Date(this.view.getFullYear(), this.view.getMonth(), 1);
        this.draftStart = '';
        this.draftEnd = '';
        this.hover = '';
        this.open = false;

        document.body.appendChild(this.popover);
        this.clearButton.hidden = !this.clearable;
        this.popover.addEventListener('click', (event) => this.onPopoverClick(event));
        this.popover.addEventListener('mouseover', (event) => this.onHover(event));
        this.months.addEventListener('mouseleave', () => {
            if (this.hover) {
                this.hover = '';
                this.render();
            }
        });
        this.trigger.addEventListener('click', () => this.toggle());
        this.read();

        this.observer = new MutationObserver(() => this.applyMode());
        this.observer.observe(root, { attributes: true, attributeFilter: ['data-mode'] });
    }

    applyMode() {
        const next = this.root.dataset.mode === 'single' ? 'single' : 'range';

        if (next === this.mode) {
            return;
        }

        this.mode = next;

        if (this.mode === 'single' && this.startInput.value) {
            this.commit(this.startInput.value, this.startInput.value);
        }

        if (this.open) {
            this.draftStart = this.startInput.value;
            this.draftEnd = this.mode === 'single' ? this.startInput.value : (this.endInput.value || '');
            this.render();
        }
    }

    read() {
        const start = this.startInput.value || '';
        const end = this.endInput.value || start;
        this.draftStart = start;
        this.draftEnd = end;
        this.paintField(start, end);
    }

    set(start, end) {
        this.commit(start || '', end || start || '', false);
    }

    commit(start, end, notify = true) {
        const single = this.mode === 'single';
        const nextEnd = start ? (single ? start : (end || start)) : '';
        this.startInput.value = start;
        this.endInput.value = nextEnd;
        this.draftStart = start;
        this.draftEnd = nextEnd;
        this.paintField(start, nextEnd);

        if (notify) {
            this.startInput.dispatchEvent(new Event('input', { bubbles: true }));
            this.endInput.dispatchEvent(new Event('input', { bubbles: true }));
            this.root.dispatchEvent(new CustomEvent('date-picker:change', {
                bubbles: true,
                detail: { start, end: nextEnd },
            }));
        }
    }

    paintField(start, end) {
        if (!start) {
            this.label.textContent = this.mode === 'single' ? 'Select date' : 'Select dates';
            this.count.hidden = true;

            return;
        }

        const finish = end || start;
        this.label.textContent = start === finish
            ? formatLabel(start)
            : `${formatLabel(start)} → ${formatLabel(finish)}`;
        const days = inclusiveDays(start, finish);
        this.count.hidden = false;
        this.count.textContent = days === 1 ? '1 day' : `${days} days`;
    }

    toggle() {
        if (this.open) {
            this.close(false);

            return;
        }

        DateRangePicker.current?.close(false);
        DateRangePicker.current = this;
        this.open = true;
        this.draftStart = this.startInput.value;
        this.draftEnd = this.endInput.value;
        this.hover = '';
        const anchor = parseISO(this.draftStart) || parseISO(this.today);
        this.view = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
        this.popover.hidden = false;
        this.render();
        this.position();
    }

    close(apply) {
        if (apply && this.draftStart) {
            this.commit(this.draftStart, this.draftEnd || this.draftStart);
        }

        this.open = false;
        this.hover = '';
        this.popover.hidden = true;

        if (DateRangePicker.current === this) {
            DateRangePicker.current = null;
        }
    }

    position() {
        const rect = this.trigger.getBoundingClientRect();
        const margin = 8;
        const width = this.popover.offsetWidth;
        const height = this.popover.offsetHeight;
        let left = rect.left;
        let top = rect.bottom + margin;

        if (left + width > window.innerWidth - margin) {
            left = window.innerWidth - margin - width;
        }

        if (left < margin) {
            left = margin;
        }

        if (top + height > window.innerHeight - margin) {
            top = Math.max(margin, rect.top - height - margin);
        }

        this.popover.style.left = `${left}px`;
        this.popover.style.top = `${top}px`;
    }

    onPopoverClick(event) {
        const action = event.target.closest('[data-picker-action]');
        const day = event.target.closest('[data-date]');

        if (action) {
            event.preventDefault();
            this.onAction(action.dataset.pickerAction);

            return;
        }

        if (day) {
            event.preventDefault();
            this.onDay(day.dataset.date);
        }
    }

    onAction(action) {
        if (action === 'prev') {
            this.view = addMonths(this.view, -1);
            this.render();

            return;
        }

        if (action === 'next') {
            this.view = addMonths(this.view, 1);
            this.render();

            return;
        }

        if (action === 'today') {
            this.draftStart = this.today;
            this.draftEnd = this.today;
            this.hover = '';
            const today = parseISO(this.today);
            this.view = new Date(today.getFullYear(), today.getMonth(), 1);
            this.render();

            return;
        }

        if (action === 'clear') {
            this.commit('', '');
            this.close(false);

            return;
        }

        if (action === 'apply' && this.draftStart) {
            this.close(true);
        }
    }

    onHover(event) {
        const day = event.target.closest('[data-date]');

        if (!day || this.mode === 'single' || !this.draftStart || this.draftEnd) {
            return;
        }

        if (this.hover === day.dataset.date) {
            return;
        }

        this.hover = day.dataset.date;
        this.render();
    }

    onDay(iso) {
        if (this.mode === 'single') {
            this.draftStart = iso;
            this.draftEnd = iso;
            this.hover = '';
            this.render();

            return;
        }

        if (!this.draftStart || this.draftEnd) {
            this.draftStart = iso;
            this.draftEnd = '';
            this.hover = '';
        } else if (iso < this.draftStart) {
            this.draftEnd = this.draftStart;
            this.draftStart = iso;
            this.hover = '';
        } else {
            this.draftEnd = iso;
            this.hover = '';
        }

        this.render();
    }

    render() {
        const dual = window.matchMedia('(min-width: 640px)').matches;
        this.popover.classList.toggle('is-dual', dual);
        const months = dual ? [this.view, addMonths(this.view, 1)] : [this.view];
        this.months.replaceChildren();

        months.forEach((month, index) => {
            this.months.appendChild(this.monthElement(month, index === 0, index === months.length - 1));
        });

        const apply = this.popover.querySelector('[data-picker-action="apply"]');

        if (apply) {
            apply.disabled = !this.draftStart;
        }

        if (this.open) {
            this.position();
        }
    }

    monthElement(month, isFirst, isLast) {
        const section = document.createElement('section');
        section.className = 'date-picker-month';
        const header = document.createElement('div');
        header.className = 'date-picker-header';
        header.append(
            this.navButton('prev', 'Previous month', isFirst),
            this.monthTitle(month),
            this.navButton('next', 'Next month', isLast),
        );

        const weekdays = document.createElement('div');
        weekdays.className = 'date-picker-weekdays';
        WEEKDAYS.forEach((day) => {
            const cell = document.createElement('span');
            cell.textContent = day;
            weekdays.appendChild(cell);
        });

        const grid = document.createElement('div');
        grid.className = 'date-picker-grid';
        this.days(month).forEach((day) => grid.appendChild(this.dayButton(day, month)));
        section.append(header, weekdays, grid);

        return section;
    }

    navButton(action, label, visible) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'date-picker-nav';
        button.dataset.pickerAction = action;
        button.setAttribute('aria-label', label);
        button.innerHTML = `<span class="material-symbols-rounded text-[20px]">${action === 'prev' ? 'chevron_left' : 'chevron_right'}</span>`;

        if (!visible) {
            button.hidden = true;
        }

        return button;
    }

    monthTitle(month) {
        const title = document.createElement('h3');
        title.className = 'date-picker-title';
        title.textContent = month.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });

        return title;
    }

    days(month) {
        const first = new Date(month.getFullYear(), month.getMonth(), 1);
        const offset = (first.getDay() + 6) % 7;
        const start = new Date(month.getFullYear(), month.getMonth(), 1 - offset);
        const days = [];

        for (let index = 0; index < 42; index += 1) {
            days.push(new Date(start.getFullYear(), start.getMonth(), start.getDate() + index));
        }

        return days;
    }

    dayButton(date, month) {
        const iso = formatISO(date);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'date-picker-day';
        button.dataset.date = iso;
        button.textContent = String(date.getDate());

        if (date.getMonth() !== month.getMonth()) {
            button.classList.add('is-outside');
        }

        if (iso === this.today) {
            button.classList.add('is-today');
        }

        const start = this.draftStart;
        const end = this.draftEnd || (!this.draftEnd && this.hover && this.draftStart ? this.hover : '');
        const [from, to] = start && end && end < start ? [end, start] : [start, end];

        if (start && iso === start) {
            button.classList.add('is-start');
        }

        if (this.draftEnd && iso === this.draftEnd) {
            button.classList.add('is-end');
        }

        if (from && to && iso > from && iso < to) {
            button.classList.add(this.draftEnd ? 'is-range' : 'is-hover');
        }

        if (!this.draftEnd && this.hover && iso === this.hover && iso !== start) {
            button.classList.add('is-hover');
        }

        if (start && this.draftEnd && start === this.draftEnd && iso === start) {
            button.classList.add('is-single');
        }

        return button;
    }
}

export function initDatePickers(root = document) {
    root.querySelectorAll('[data-date-picker]').forEach((element) => {
        if (!element._picker) {
            element._picker = new DateRangePicker(element);
        }
    });
}

document.addEventListener('pointerdown', (event) => {
    const current = DateRangePicker.current;

    if (!current?.open) {
        return;
    }

    if (current.popover.contains(event.target) || current.root.contains(event.target)) {
        return;
    }

    current.close(false);

    if (event.target.classList?.contains('m3-scrim')) {
        event.preventDefault();
        event.stopPropagation();
    }
}, true);

document.addEventListener('keydown', (event) => {
    const current = DateRangePicker.current;

    if (event.key !== 'Escape' || !current?.open) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    current.close(false);
}, true);

window.addEventListener('resize', () => {
    if (DateRangePicker.current?.open) {
        DateRangePicker.current.render();
    }
});
