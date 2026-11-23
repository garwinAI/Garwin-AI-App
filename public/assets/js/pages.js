// Workspace page editors: Wordbase doc, spreadsheet and calendar. Every change autosaves.
(() => {
    const editor = document.querySelector('[data-page-editor]');
    if (!(editor instanceof HTMLElement)) {
        return;
    }

    const type = editor.dataset.pageType;
    const titleInput = editor.querySelector('[data-page-title]');
    const status = editor.querySelector('[data-page-status]');
    const readContent = () => {
        const holder = editor.querySelector('[data-page-content]');
        try {
            return JSON.parse(holder?.textContent ?? '{}') ?? {};
        } catch {
            return {};
        }
    };

    let getContent = () => '';
    let saveTimer = null;
    let pending = false;
    let saving = Promise.resolve();
    const setStatus = (text, isError = false) => {
        if (!(status instanceof HTMLElement)) return;
        status.textContent = text;
        status.classList.toggle('is-error', isError);
    };
    const payload = () => ({
        _token: editor.dataset.csrf ?? '',
        page_id: editor.dataset.pageId ?? '',
        title: titleInput instanceof HTMLInputElement ? titleInput.value : '',
        content: getContent(),
    });
    const save = () => {
        window.clearTimeout(saveTimer);
        pending = true;
        setStatus('Saving…');
        saveTimer = window.setTimeout(() => {
            pending = false;
            const body = new URLSearchParams(payload());
            saving = saving.then(async () => {
                try {
                    const response = await fetch('/pages/save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                        body,
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(result.error || 'Not saved. Check your connection.');
                    if (!pending) setStatus('All changes saved');
                } catch (error) {
                    setStatus(error instanceof Error ? error.message : 'Not saved.', true);
                }
            });
        }, 600);
    };
    // Leaving with a change still waiting: send it right away instead of asking.
    const flush = () => {
        if (!pending) return;
        window.clearTimeout(saveTimer);
        pending = false;
        const data = new FormData();
        Object.entries(payload()).forEach(([name, value]) => data.append(name, value));
        navigator.sendBeacon('/pages/save', data);
    };
    window.addEventListener('pagehide', flush);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flush();
    });
    document.addEventListener('submit', flush, true);

    if (titleInput instanceof HTMLInputElement) {
        titleInput.addEventListener('input', save);
        titleInput.addEventListener('change', () => {
            // Keep the sidebar and tab title in step with the new name.
            const name = titleInput.value.trim() || 'Untitled page';
            document.title = document.title.replace(/^[^·]*/, `${name} `);
            const current = document.querySelector('.sidebar .nav-link[aria-current="page"] .nav-text');
            if (current) current.textContent = name;
        });
    }

    if (type === 'doc') {
        const doc = editor.querySelector('[data-page-doc]');
        if (doc instanceof HTMLTextAreaElement) {
            getContent = () => doc.value;
            doc.addEventListener('input', save);
        }
        return;
    }

    if (type === 'sheet') {
        const cells = { ...(readContent().cells ?? {}) };
        const inputs = new Map([...editor.querySelectorAll('[data-cell]')].map((input) => [input.dataset.cell, input]));
        const formulaBar = editor.querySelector('[data-sheet-formula]');
        const refLabel = editor.querySelector('[data-sheet-ref]');
        let activeRef = 'A1';
        getContent = () => JSON.stringify({ cells });

        const columnIndex = (letter) => letter.charCodeAt(0) - 65;
        const expandRange = (from, to) => {
            const [, c1, r1] = from.match(/^([A-Z])(\d+)$/) ?? [];
            const [, c2, r2] = to.match(/^([A-Z])(\d+)$/) ?? [];
            if (!c1 || !c2) return [];
            const refs = [];
            for (let c = Math.min(columnIndex(c1), columnIndex(c2)); c <= Math.max(columnIndex(c1), columnIndex(c2)); c++) {
                for (let r = Math.min(+r1, +r2); r <= Math.max(+r1, +r2); r++) {
                    refs.push(String.fromCharCode(65 + c) + r);
                }
            }
            return refs;
        };

        // A small, safe formula evaluator: numbers, cell refs, + - * / ( ), and SUM/AVERAGE/MIN/MAX/COUNT.
        const evaluate = (ref, seen = new Set()) => {
            const raw = cells[ref] ?? '';
            if (!raw.startsWith('=')) {
                const number = Number(raw.replace(/,/g, ''));
                return raw.trim() !== '' && Number.isFinite(number) ? number : raw;
            }
            if (seen.has(ref)) throw new Error('#CYCLE');
            seen.add(ref);
            const tokens = raw.slice(1).toUpperCase().match(/[A-Z]+\(|[A-Z]\d+:[A-Z]\d+|[A-Z]\d+|\d*\.?\d+|[-+*/(),]/g) ?? [];
            let i = 0;
            const valueOf = (cellRef) => {
                const value = evaluate(cellRef, new Set(seen));
                return typeof value === 'number' ? value : 0;
            };
            const list = () => {
                const values = [];
                while (i < tokens.length && tokens[i] !== ')') {
                    const token = tokens[i];
                    if (/^[A-Z]\d+:[A-Z]\d+$/.test(token)) {
                        const [a, b] = token.split(':');
                        expandRange(a, b).forEach((cellRef) => {
                            const value = evaluate(cellRef, new Set(seen));
                            if (typeof value === 'number') values.push(value);
                        });
                        i++;
                    } else {
                        values.push(expression());
                    }
                    if (tokens[i] === ',') i++;
                }
                if (tokens[i] !== ')') throw new Error('#ERROR');
                i++;
                return values;
            };
            const primary = () => {
                const token = tokens[i++];
                if (token === undefined) throw new Error('#ERROR');
                if (token === '-') return -primary();
                if (token === '(') {
                    const value = expression();
                    if (tokens[i++] !== ')') throw new Error('#ERROR');
                    return value;
                }
                if (/^\d*\.?\d+$/.test(token)) return Number(token);
                if (/^[A-Z]\d+$/.test(token)) return valueOf(token);
                const fn = token.slice(0, -1);
                if (token.endsWith('(')) {
                    const values = list();
                    if (fn === 'SUM') return values.reduce((a, b) => a + b, 0);
                    if (fn === 'AVERAGE' || fn === 'AVG') return values.length ? values.reduce((a, b) => a + b, 0) / values.length : 0;
                    if (fn === 'MIN') return values.length ? Math.min(...values) : 0;
                    if (fn === 'MAX') return values.length ? Math.max(...values) : 0;
                    if (fn === 'COUNT') return values.length;
                }
                throw new Error('#NAME?');
            };
            const term = () => {
                let value = primary();
                while (tokens[i] === '*' || tokens[i] === '/') {
                    const op = tokens[i++];
                    const next = primary();
                    if (op === '/' && next === 0) throw new Error('#DIV/0!');
                    value = op === '*' ? value * next : value / next;
                }
                return value;
            };
            const expression = () => {
                let value = term();
                while (tokens[i] === '+' || tokens[i] === '-') {
                    const op = tokens[i++];
                    value = op === '+' ? value + term() : value - term();
                }
                return value;
            };
            const result = expression();
            if (i !== tokens.length) throw new Error('#ERROR');
            return result;
        };
        const display = (ref) => {
            try {
                const value = evaluate(ref);
                return typeof value === 'number' ? String(Math.round(value * 1e10) / 1e10) : value;
            } catch (error) {
                return error instanceof Error && error.message.startsWith('#') ? error.message : '#ERROR';
            }
        };
        const render = () => {
            inputs.forEach((input, ref) => {
                if (document.activeElement === input) return;
                input.value = display(ref);
                input.classList.toggle('is-formula', (cells[ref] ?? '').startsWith('='));
                input.classList.toggle('is-number', /^-?[\d.]+$/.test(input.value));
            });
        };
        const setCell = (ref, value) => {
            if (value === '') delete cells[ref];
            else cells[ref] = value;
            render();
            save();
        };

        inputs.forEach((input, ref) => {
            input.addEventListener('focus', () => {
                activeRef = ref;
                input.value = cells[ref] ?? '';
                if (refLabel) refLabel.textContent = ref;
                if (formulaBar instanceof HTMLInputElement) formulaBar.value = cells[ref] ?? '';
            });
            input.addEventListener('input', () => {
                if (formulaBar instanceof HTMLInputElement) formulaBar.value = input.value;
            });
            input.addEventListener('blur', () => setCell(ref, input.value));
            input.addEventListener('keydown', (event) => {
                const [, column, row] = ref.match(/^([A-Z])(\d+)$/) ?? [];
                let next = null;
                if (event.key === 'Enter' || event.key === 'ArrowDown') next = column + (+row + 1);
                else if (event.key === 'ArrowUp') next = column + (+row - 1);
                else if (event.key === 'Tab') next = String.fromCharCode(column.charCodeAt(0) + (event.shiftKey ? -1 : 1)) + row;
                else if (event.key === 'Escape') {
                    input.value = cells[ref] ?? '';
                    input.blur();
                    return;
                }
                const target = next ? inputs.get(next) : null;
                if (target) {
                    event.preventDefault();
                    target.focus();
                }
            });
        });
        if (formulaBar instanceof HTMLInputElement) {
            formulaBar.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    setCell(activeRef, formulaBar.value);
                    inputs.get(activeRef)?.focus();
                }
            });
            formulaBar.addEventListener('change', () => setCell(activeRef, formulaBar.value));
        }
        render();
        return;
    }

    if (type === 'calendar') {
        const events = { ...(readContent().events ?? {}) };
        const grid = editor.querySelector('[data-cal-grid]');
        const monthLabel = editor.querySelector('[data-cal-month]');
        const today = new Date();
        let shown = new Date(today.getFullYear(), today.getMonth(), 1);
        getContent = () => JSON.stringify({ events });
        const key = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

        const draw = () => {
            if (!(grid instanceof HTMLElement)) return;
            if (monthLabel) monthLabel.textContent = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(shown);
            grid.replaceChildren();
            ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach((name) => {
                const head = document.createElement('div');
                head.className = 'page-cal-dow';
                head.textContent = name;
                grid.append(head);
            });
            const start = new Date(shown);
            start.setDate(1 - start.getDay());
            for (let n = 0; n < 42; n++) {
                const day = new Date(start.getFullYear(), start.getMonth(), start.getDate() + n);
                const dayKey = key(day);
                const cell = document.createElement('div');
                cell.className = 'page-cal-day';
                cell.classList.toggle('is-other-month', day.getMonth() !== shown.getMonth());
                cell.classList.toggle('is-today', dayKey === key(today));
                cell.dataset.day = dayKey;
                const number = document.createElement('span');
                number.className = 'page-cal-date';
                number.textContent = String(day.getDate());
                cell.append(number);
                (events[dayKey] ?? []).forEach((text, index) => {
                    const item = document.createElement('div');
                    item.className = 'page-cal-event';
                    const label = document.createElement('span');
                    label.textContent = text;
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = '×';
                    remove.title = 'Remove event';
                    remove.setAttribute('aria-label', `Remove ${text}`);
                    remove.addEventListener('click', (event) => {
                        event.stopPropagation();
                        events[dayKey].splice(index, 1);
                        if (events[dayKey].length === 0) delete events[dayKey];
                        draw();
                        save();
                    });
                    item.append(label, remove);
                    cell.append(item);
                });
                cell.addEventListener('click', () => {
                    if (cell.querySelector('input')) return;
                    if ((events[dayKey] ?? []).length >= 20) return;
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.maxLength = 200;
                    input.placeholder = 'New event';
                    input.className = 'page-cal-input';
                    cell.append(input);
                    input.focus();
                    let done = false;
                    const commit = () => {
                        if (done) return;
                        done = true;
                        const text = input.value.trim();
                        input.remove();
                        if (text) {
                            (events[dayKey] ??= []).push(text);
                            draw();
                            save();
                        }
                    };
                    input.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') commit();
                        if (event.key === 'Escape') {
                            done = true;
                            input.remove();
                        }
                    });
                    input.addEventListener('blur', commit);
                });
                grid.append(cell);
            }
        };
        editor.querySelector('[data-cal-prev]')?.addEventListener('click', () => {
            shown = new Date(shown.getFullYear(), shown.getMonth() - 1, 1);
            draw();
        });
        editor.querySelector('[data-cal-next]')?.addEventListener('click', () => {
            shown = new Date(shown.getFullYear(), shown.getMonth() + 1, 1);
            draw();
        });
        editor.querySelector('[data-cal-today]')?.addEventListener('click', () => {
            shown = new Date(today.getFullYear(), today.getMonth(), 1);
            draw();
        });
        draw();
    }
})();
