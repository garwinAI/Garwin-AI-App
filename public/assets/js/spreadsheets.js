// Spreadsheets: cell editing, formulas (=SUM(A1:A5), =B2*C2 …), saving and CSV export.
const sheetForm = document.querySelector('[data-sheet-form]');

if (sheetForm instanceof HTMLFormElement) {
    const grid = sheetForm.querySelector('[data-sheet-grid]');
    const jsonField = sheetForm.querySelector('[data-sheet-json]');
    const stateLabel = sheetForm.querySelector('[data-sheet-state]');
    const formulaRef = sheetForm.querySelector('[data-formula-ref]');
    const formulaInput = sheetForm.querySelector('[data-formula-input]');
    const columns = Number(grid.dataset.columns);
    const rows = Number(grid.dataset.rows);
    const inputs = new Map([...grid.querySelectorAll('input[data-cell]')].map((input) => [input.dataset.cell, input]));
    const raw = new Map();
    let active = 'A1';
    let dirty = false;

    inputs.forEach((input, ref) => { if (input.value !== '') raw.set(ref, input.value); });

    class SheetError extends Error {}

    const columnIndex = (ref) => ref.charCodeAt(0) - 65;
    const rowNumber = (ref) => Number(ref.slice(1));
    const refAt = (col, row) => `${String.fromCharCode(65 + col)}${row}`;
    const isValidRef = (ref) => /^[A-Z][1-9][0-9]?$/.test(ref) && columnIndex(ref) < columns && rowNumber(ref) <= rows;

    const tokenize = (text) => {
        const tokens = [];
        const pattern = /\s*(?:(\d+(?:\.\d+)?|\.\d+)|([A-Za-z]+\d+(?::[A-Za-z]+\d+)?)|([A-Za-z]+)|(.))/y;
        let match;
        while (pattern.lastIndex < text.length && (match = pattern.exec(text)) !== null) {
            if (match[1] !== undefined) tokens.push({ type: 'num', value: Number(match[1]) });
            else if (match[2] !== undefined) tokens.push({ type: 'ref', value: match[2].toUpperCase() });
            else if (match[3] !== undefined) tokens.push({ type: 'name', value: match[3].toUpperCase() });
            else if (match[4].trim() !== '') tokens.push({ type: 'op', value: match[4] });
        }
        return tokens;
    };

    const numbersIn = (values) => values.flat(Infinity).filter((value) => typeof value === 'number');
    const functions = {
        SUM: (args) => numbersIn(args).reduce((total, value) => total + value, 0),
        AVERAGE: (args) => {
            const values = numbersIn(args);
            if (values.length === 0) throw new SheetError('#DIV/0!');
            return values.reduce((total, value) => total + value, 0) / values.length;
        },
        MIN: (args) => { const values = numbersIn(args); return values.length ? Math.min(...values) : 0; },
        MAX: (args) => { const values = numbersIn(args); return values.length ? Math.max(...values) : 0; },
        COUNT: (args) => numbersIn(args).length,
    };

    const evaluateCell = (ref, stack) => {
        if (!isValidRef(ref)) throw new SheetError('#REF!');
        if (stack.has(ref)) throw new SheetError('#CYCLE!');
        const content = (raw.get(ref) ?? '').trim();
        if (content === '') return null;
        if (content.startsWith('=')) {
            stack.add(ref);
            try {
                return evaluateFormula(content.slice(1), stack);
            } finally {
                stack.delete(ref);
            }
        }
        const number = Number(content.replace(/,/g, ''));
        return content !== '' && Number.isFinite(number) ? number : content;
    };

    const rangeValues = (range, stack) => {
        const [start, end] = range.split(':');
        if (!isValidRef(start) || !isValidRef(end)) throw new SheetError('#REF!');
        const values = [];
        for (let col = Math.min(columnIndex(start), columnIndex(end)); col <= Math.max(columnIndex(start), columnIndex(end)); col++) {
            for (let row = Math.min(rowNumber(start), rowNumber(end)); row <= Math.max(rowNumber(start), rowNumber(end)); row++) {
                values.push(evaluateCell(refAt(col, row), stack));
            }
        }
        return values;
    };

    const evaluateFormula = (text, stack) => {
        const tokens = tokenize(text);
        let position = 0;
        const peek = () => tokens[position];
        const take = (value) => {
            const token = tokens[position];
            if (value !== undefined && (token?.type !== 'op' || token.value !== value)) throw new SheetError('#ERR!');
            position++;
            return token;
        };
        const asNumber = (value) => {
            if (value === null) return 0;
            if (typeof value === 'number') return value;
            throw new SheetError('#VALUE!');
        };

        const primary = () => {
            const token = take();
            if (!token) throw new SheetError('#ERR!');
            if (token.type === 'num') return token.value;
            if (token.type === 'ref') {
                if (token.value.includes(':')) throw new SheetError('#VALUE!');
                return evaluateCell(token.value, stack);
            }
            if (token.type === 'name') {
                const fn = functions[token.value];
                if (!fn) throw new SheetError('#NAME?');
                take('(');
                const args = [];
                if (!(peek()?.type === 'op' && peek().value === ')')) {
                    do {
                        const next = peek();
                        if (next?.type === 'ref' && next.value.includes(':')) {
                            position++;
                            args.push(rangeValues(next.value, stack));
                        } else {
                            args.push(expression());
                        }
                    } while (peek()?.type === 'op' && peek().value === ',' && take(','));
                }
                take(')');
                return fn(args);
            }
            if (token.value === '(') {
                const value = expression();
                take(')');
                return value;
            }
            if (token.value === '-') return -asNumber(power());
            if (token.value === '+') return asNumber(power());
            throw new SheetError('#ERR!');
        };
        const power = () => {
            let value = primary();
            while (peek()?.type === 'op' && peek().value === '^') {
                take('^');
                value = asNumber(value) ** asNumber(primary());
            }
            return value;
        };
        const term = () => {
            let value = power();
            while (peek()?.type === 'op' && ['*', '/'].includes(peek().value)) {
                const operator = take().value;
                const right = asNumber(power());
                if (operator === '/' && right === 0) throw new SheetError('#DIV/0!');
                value = operator === '*' ? asNumber(value) * right : asNumber(value) / right;
            }
            return value;
        };
        const expression = () => {
            let value = term();
            while (peek()?.type === 'op' && ['+', '-'].includes(peek().value)) {
                const operator = take().value;
                const right = asNumber(term());
                value = operator === '+' ? asNumber(value) + right : asNumber(value) - right;
            }
            return value;
        };

        const result = expression();
        if (position < tokens.length) throw new SheetError('#ERR!');
        return result;
    };

    const display = (ref) => {
        try {
            const value = evaluateCell(ref, new Set());
            if (value === null) return { text: '', kind: '' };
            if (typeof value === 'number') {
                if (!Number.isFinite(value)) return { text: '#NUM!', kind: 'is-error' };
                return { text: String(Math.round(value * 1e10) / 1e10), kind: 'is-number' };
            }
            return { text: String(value), kind: '' };
        } catch (error) {
            return { text: error instanceof SheetError ? error.message : '#ERR!', kind: 'is-error' };
        }
    };

    const renderCell = (ref) => {
        const input = inputs.get(ref);
        if (!input || input === document.activeElement) return;
        const shown = display(ref);
        input.value = shown.text;
        input.classList.toggle('is-number', shown.kind === 'is-number');
        input.classList.toggle('is-error', shown.kind === 'is-error');
        input.classList.toggle('is-formula', (raw.get(ref) ?? '').trim().startsWith('='));
    };
    const renderAll = () => inputs.forEach((_, ref) => renderCell(ref));

    const markDirty = () => {
        dirty = true;
        stateLabel.textContent = 'Unsaved changes';
        stateLabel.classList.add('is-dirty');
    };

    const setRaw = (ref, value) => {
        if (value.trim() === '') raw.delete(ref);
        else raw.set(ref, value);
        markDirty();
    };

    const focusCell = (ref) => inputs.get(ref)?.focus();

    inputs.forEach((input, ref) => {
        input.addEventListener('focus', () => {
            active = ref;
            input.closest('td')?.classList.add('is-active');
            input.value = raw.get(ref) ?? '';
            formulaRef.textContent = ref;
            formulaInput.value = input.value;
            input.select();
        });
        input.addEventListener('input', () => {
            setRaw(ref, input.value);
            formulaInput.value = input.value;
        });
        input.addEventListener('blur', () => {
            input.closest('td')?.classList.remove('is-active');
            renderAll();
        });
        input.addEventListener('keydown', (event) => {
            const col = columnIndex(ref);
            const row = rowNumber(ref);
            const moves = { Enter: [0, event.shiftKey ? -1 : 1], ArrowDown: [0, 1], ArrowUp: [0, -1] };
            if (moves[event.key]) {
                event.preventDefault();
                const [dc, dr] = moves[event.key];
                const target = refAt(col + dc, Math.min(rows, Math.max(1, row + dr)));
                focusCell(target);
            } else if (event.key === 'Escape') {
                input.blur();
            }
        });
    });

    formulaInput.addEventListener('input', () => {
        setRaw(active, formulaInput.value);
        renderAll();
    });
    formulaInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            focusCell(active);
        }
    });

    sheetForm.addEventListener('submit', () => {
        jsonField.value = JSON.stringify(Object.fromEntries(raw));
        dirty = false;
    });

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            sheetForm.requestSubmit();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty) event.preventDefault();
    });

    sheetForm.querySelector('[data-sheet-export]')?.addEventListener('click', () => {
        let lastRow = 0;
        let lastCol = -1;
        raw.forEach((_, ref) => {
            lastRow = Math.max(lastRow, rowNumber(ref));
            lastCol = Math.max(lastCol, columnIndex(ref));
        });
        const escape = (text) => (/[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text);
        const lines = [];
        for (let row = 1; row <= lastRow; row++) {
            const values = [];
            for (let col = 0; col <= lastCol; col++) values.push(escape(display(refAt(col, row)).text));
            lines.push(values.join(','));
        }
        const title = sheetForm.querySelector('input[name="title"]')?.value.trim() || 'spreadsheet';
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' }));
        link.download = `${title.replace(/[^\w\- ]+/g, '').trim() || 'spreadsheet'}.csv`;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    });

    sheetForm.querySelector('input[name="title"]')?.addEventListener('input', markDirty);
    renderAll();
}
