const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

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

function eachDay(start, end) {
    const from = parseISO(start);
    const to = parseISO(end || start);

    if (!from || !to) {
        return [];
    }

    const [first, last] = to < from ? [to, from] : [from, to];
    const dates = [];
    const cursor = new Date(first.getFullYear(), first.getMonth(), first.getDate());

    while (cursor <= last) {
        dates.push(formatISO(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }

    return dates;
}

function isContiguous(dates) {
    if (dates.length === 0) {
        return true;
    }

    return inclusiveDays(dates[0], dates[dates.length - 1]) === dates.length;
}

function formatList(dates) {
    const parsed = dates.map(parseISO).filter(Boolean);
    const sameYear = parsed.every((date) => date.getFullYear() === parsed[0].getFullYear());

    if (sameYear) {
        const days = parsed.map((date) => date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })).join(', ');

        return `${days} ${parsed[0].getFullYear()}`;
    }

    return parsed.map((date) => date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })).join(', ');
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
        this.datesInput = root.querySelector('[data-picker-dates]');
        this.popover = root.querySelector('[data-picker-popover]');
        this.modes = this.popover.querySelector('[data-picker-modes]');
        this.months = this.popover.querySelector('[data-picker-months]');
        this.clearButton = this.popover.querySelector('[data-picker-clear]');
        this.view = parseISO(this.startInput.value) || parseISO(this.today) || new Date();
        this.view = new Date(this.view.getFullYear(), this.view.getMonth(), 1);
        this.selection = 'range';
        this.draftStart = '';
        this.draftEnd = '';
        this.draftDates = new Set();
        this.committedDates = [];
        this.hover = '';
        this.open = false;
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'date-picker-backdrop';
        this.backdrop.hidden = true;

        document.body.append(this.backdrop, this.popover);
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
        this.applyMode();

        this.observer = new MutationObserver(() => this.applyMode());
        this.observer.observe(root, { attributes: true, attributeFilter: ['data-mode', 'data-allow-multiple'] });
    }

    allowsMultiple() {
        return this.root.hasAttribute('data-allow-multiple') && !['0', 'false'].includes(this.root.dataset.allowMultiple || '');
    }

    applyMode() {
        const next = this.root.dataset.mode === 'single' ? 'single' : 'range';
        const allow = this.allowsMultiple();

        if (!allow && this.selection === 'multiple') {
            this.selection = 'range';
            this.draftDates = new Set();
        }

        if (!allow && this.committedDates.length > 0 && !isContiguous(this.committedDates)) {
            this.selection = 'range';
            this.commit('', '');
        }

        if (this.modes) {
            this.modes.hidden = !allow;
        }

        if (next === this.mode) {
            if (this.open) {
                this.render();
            }

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
        let dates = [];

        if (this.datesInput?.value) {
            try {
                const parsed = JSON.parse(this.datesInput.value);
                dates = Array.isArray(parsed) ? parsed.filter((date) => parseISO(date)).sort() : [];
            } catch {
                dates = [];
            }
        }

        if (dates.length === 0 && start) {
            dates = eachDay(start, end || start);
        }

        this.committedDates = dates;
        this.draftStart = start;
        this.draftEnd = end;
        this.draftDates = new Set(dates);
        this.paintField(dates.length ? dates : (start ? [start] : []));
    }

    set(start, end) {
        this.commit(start || '', end || start || '', false);
    }

    commit(start, end, notify = true) {
        const single = this.mode === 'single';
        const nextEnd = start ? (single ? start : (end || start)) : '';
        const dates = start ? eachDay(start, nextEnd) : [];
        this.write(start, nextEnd, dates, notify);
    }

    commitDates(dates, notify = true) {
        const sorted = [...new Set(dates)].filter((date) => parseISO(date)).sort();
        this.write(sorted[0] || '', sorted[sorted.length - 1] || '', sorted, notify);
    }

    write(start, end, dates, notify) {
        this.startInput.value = start;
        this.endInput.value = end;
        this.draftStart = start;
        this.draftEnd = end;
        this.draftDates = new Set(dates);
        this.committedDates = dates;

        if (this.datesInput) {
            this.datesInput.value = dates.length ? JSON.stringify(dates) : '';
        }

        this.paintField(dates);

        if (!notify) {
            return;
        }

        this.startInput.dispatchEvent(new Event('input', { bubbles: true }));
        this.endInput.dispatchEvent(new Event('input', { bubbles: true }));
        this.datesInput?.dispatchEvent(new Event('input', { bubbles: true }));
        this.root.dispatchEvent(new CustomEvent('date-picker:change', {
            bubbles: true,
            detail: { start, end, dates },
        }));
    }

    paintField(dates) {
        if (!dates.length) {
            this.label.textContent = this.mode === 'single' ? 'Select date' : 'Select dates';
            this.count.hidden = true;

            return;
        }

        this.label.textContent = isContiguous(dates) && dates[0] !== dates[dates.length - 1]
            ? `${formatLabel(dates[0])} → ${formatLabel(dates[dates.length - 1])}`
            : (dates.length === 1 ? formatLabel(dates[0]) : formatList(dates));
        this.count.hidden = false;
        this.count.textContent = dates.length === 1 ? '1 day' : `${dates.length} days`;
    }

    toggle() {
        if (this.open) {
            this.close(false);

            return;
        }

        DateRangePicker.current?.close(false);
        DateRangePicker.current = this;
        this.open = true;
        this.draftDates = new Set(this.committedDates);
        this.draftStart = this.startInput.value;
        this.draftEnd = this.selection === 'multiple' ? '' : this.endInput.value;
        this.hover = '';
        const anchor = parseISO(this.draftStart) || parseISO(this.today);
        this.view = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
        this.popover.hidden = false;
        this.render();
        this.position();
    }

    close(apply) {
        const count = this.selection === 'multiple'
            ? this.draftDates.size
            : inclusiveDays(this.draftStart, this.draftEnd || this.draftStart);

        if (apply && count > 0 && count <= 31 && this.selection === 'multiple') {
            this.commitDates([...this.draftDates]);
        } else if (apply && count > 0 && count <= 31 && this.draftStart) {
            this.commit(this.draftStart, this.draftEnd || this.draftStart);
        }

        this.open = false;
        this.hover = '';
        this.popover.hidden = true;
        this.backdrop.hidden = true;

        if (DateRangePicker.current === this) {
            DateRangePicker.current = null;
        }
    }

    position() {
        const mobile = window.matchMedia('(max-width: 639px)').matches;
        this.popover.classList.toggle('is-centered', mobile);
        this.backdrop.hidden = !mobile;

        if (mobile) {
            this.popover.style.left = '50%';
            this.popover.style.top = '50%';
            this.popover.style.transform = 'translate(-50%, -50%)';

            return;
        }

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

        this.popover.style.transform = '';
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
            if (this.selection === 'multiple') {
                this.draftDates = new Set([this.today]);
            } else {
                this.draftStart = this.today;
                this.draftEnd = this.today;
            }

            this.hover = '';
            const today = parseISO(this.today);
            this.view = new Date(today.getFullYear(), today.getMonth(), 1);
            this.render();

            return;
        }

        if (action === 'clear') {
            this.draftDates = new Set();
            this.commit('', '');
            this.close(false);

            return;
        }

        if (action === 'mode-range' || action === 'mode-multiple') {
            this.selection = action === 'mode-multiple' ? 'multiple' : 'range';
            this.draftStart = '';
            this.draftEnd = '';
            this.draftDates = new Set();
            this.hover = '';
            this.render();

            return;
        }

        if (action === 'apply') {
            this.close(true);
        }
    }

    onHover(event) {
        const day = event.target.closest('[data-date]');

        if (!day || this.selection === 'multiple' || this.mode === 'single' || !this.draftStart || this.draftEnd) {
            return;
        }

        if (this.hover === day.dataset.date) {
            return;
        }

        this.hover = day.dataset.date;
        this.render();
    }

    onDay(iso) {
        if (this.selection === 'multiple') {
            if (this.draftDates.has(iso)) {
                this.draftDates.delete(iso);
            } else if (this.draftDates.size < 31) {
                this.draftDates.add(iso);
            }

            this.render();

            return;
        }

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
        this.months.replaceChildren();
        this.months.appendChild(this.monthElement(this.view));
        this.modes?.querySelectorAll('[data-picker-action]').forEach((button) => {
            const selected = (button.dataset.pickerAction === 'mode-multiple' && this.selection === 'multiple')
                || (button.dataset.pickerAction === 'mode-range' && this.selection === 'range');
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });

        const apply = this.popover.querySelector('[data-picker-action="apply"]');

        if (apply) {
            const count = this.selection === 'multiple'
                ? this.draftDates.size
                : inclusiveDays(this.draftStart, this.draftEnd || this.draftStart);
            apply.disabled = count < 1 || count > 31;
        }

        if (this.open) {
            this.position();
        }
    }

    monthElement(month) {
        const section = document.createElement('section');
        section.className = 'date-picker-month';
        const header = document.createElement('div');
        header.className = 'date-picker-header';
        header.append(
            this.navButton('prev', 'Previous month'),
            this.monthTitle(month),
            this.navButton('next', 'Next month'),
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

    navButton(action, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'date-picker-nav';
        button.dataset.pickerAction = action;
        button.setAttribute('aria-label', label);
        button.innerHTML = `<span class="material-symbols-rounded text-[20px]">${action === 'prev' ? 'chevron_left' : 'chevron_right'}</span>`;

        return button;
    }

    monthTitle(month) {
        const title = document.createElement('h3');
        title.className = 'date-picker-title';
        title.textContent = month.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

        return title;
    }

    days(month) {
        const first = new Date(month.getFullYear(), month.getMonth(), 1);
        const offset = first.getDay();
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

        if (this.selection === 'multiple') {
            if (this.draftDates.has(iso)) {
                button.classList.add('is-start', 'is-single');
            }

            return button;
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

    if (current.backdrop && event.target === current.backdrop) {
        event.preventDefault();
        event.stopPropagation();
        current.close(false);

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
