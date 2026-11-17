// Codebase editor: line numbers, Tab indentation, Ctrl/Cmd+S, download, unsaved-change warning.
const codeForm = document.querySelector('[data-code-form]');

if (codeForm instanceof HTMLFormElement) {
    const textarea = codeForm.querySelector('[data-code-input]');
    const gutter = codeForm.querySelector('[data-code-gutter]');
    const stateLabel = codeForm.querySelector('[data-code-state]');
    const nameInput = codeForm.querySelector('input[name="name"]');
    const indent = '    ';
    let dirty = false;

    const updateGutter = () => {
        const lines = textarea.value.split('\n').length;
        gutter.textContent = Array.from({ length: lines }, (_, index) => index + 1).join('\n');
    };
    const syncScroll = () => { gutter.scrollTop = textarea.scrollTop; };
    const markDirty = () => {
        dirty = true;
        stateLabel.textContent = 'Unsaved changes';
        stateLabel.classList.add('is-dirty');
    };
    const replaceSelection = (text, selectStart, selectEnd) => {
        textarea.setRangeText(text, textarea.selectionStart, textarea.selectionEnd, 'end');
        if (selectStart !== undefined) textarea.setSelectionRange(selectStart, selectEnd);
        markDirty();
        updateGutter();
    };

    textarea.addEventListener('input', () => { markDirty(); updateGutter(); });
    textarea.addEventListener('scroll', syncScroll);
    nameInput.addEventListener('input', markDirty);

    textarea.addEventListener('keydown', (event) => {
        const { selectionStart: start, selectionEnd: end, value } = textarea;
        if (event.key === 'Tab') {
            event.preventDefault();
            const lineStart = value.lastIndexOf('\n', start - 1) + 1;
            if (start === end && !event.shiftKey) {
                replaceSelection(indent);
                return;
            }
            const block = value.slice(lineStart, end);
            const changed = event.shiftKey
                ? block.replace(/^( {1,4}|\t)/gm, '')
                : block.replace(/^/gm, indent);
            textarea.setSelectionRange(lineStart, end);
            replaceSelection(changed, lineStart, lineStart + changed.length);
        } else if (['}', ']', ')'].includes(event.key) && start === end) {
            const lineStart = value.lastIndexOf('\n', start - 1) + 1;
            const before = value.slice(lineStart, start);
            if (/^ +$/.test(before)) {
                event.preventDefault();
                textarea.setSelectionRange(Math.max(lineStart, start - indent.length), start);
                replaceSelection(event.key);
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const lineStart = value.lastIndexOf('\n', start - 1) + 1;
            const currentIndent = value.slice(lineStart, start).match(/^[ \t]*/)[0];
            const extra = /[{[(:]\s*$/.test(value.slice(lineStart, start)) ? indent : '';
            replaceSelection(`\n${currentIndent}${extra}`);
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            codeForm.requestSubmit();
        }
    });

    codeForm.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (event) => { if (dirty) event.preventDefault(); });

    codeForm.querySelector('[data-code-download]')?.addEventListener('click', () => {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([textarea.value], { type: 'text/plain;charset=utf-8' }));
        link.download = nameInput.value.trim() || 'file.txt';
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    });

    updateGutter();
}
