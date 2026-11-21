// App shell: collapsible + resizable sidebar, expandable sections, mobile drawer.
// Layout choices are a per-browser convenience, so they live in localStorage.
(() => {
    const shell = document.querySelector('[data-app-shell]');
    if (!shell) {
        return;
    }

    const STORAGE_KEY = 'garwin.sidebar';
    const MIN_WIDTH = 200;
    const MAX_WIDTH = 420;
    const DEFAULT_WIDTH = 248;
    const mobileQuery = window.matchMedia('(max-width: 680px)');
    const toggle = shell.querySelector('[data-sidebar-toggle]');
    const resizer = shell.querySelector('[data-sidebar-resizer]');
    const backdrop = shell.querySelector('[data-sidebar-backdrop]');

    const load = () => {
        try {
            const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
            return saved && typeof saved === 'object' ? saved : {};
        } catch {
            return {};
        }
    };
    const state = Object.assign({ collapsed: false, width: DEFAULT_WIDTH, closed: [] }, load());
    if (!Array.isArray(state.closed)) {
        state.closed = [];
    }
    const save = () => {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch {
            // Storage can be unavailable (private mode); the layout still works for this page.
        }
    };

    const clampWidth = (width) => Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, Math.round(Number(width) || DEFAULT_WIDTH)));
    const applyWidth = () => shell.style.setProperty('--sidebar-width', `${clampWidth(state.width)}px`);
    const syncToggle = () => {
        const expanded = mobileQuery.matches ? shell.classList.contains('sidebar-open') : !state.collapsed;
        toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };
    const applyCollapsed = () => {
        shell.classList.toggle('sidebar-collapsed', Boolean(state.collapsed));
        syncToggle();
    };

    applyWidth();
    applyCollapsed();

    toggle?.addEventListener('click', () => {
        if (mobileQuery.matches) {
            shell.classList.toggle('sidebar-open');
        } else {
            state.collapsed = !state.collapsed;
            applyCollapsed();
            save();
        }
        syncToggle();
    });
    backdrop?.addEventListener('click', () => {
        shell.classList.remove('sidebar-open');
        syncToggle();
    });
    mobileQuery.addEventListener('change', () => {
        shell.classList.remove('sidebar-open');
        syncToggle();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && shell.classList.contains('sidebar-open')) {
            shell.classList.remove('sidebar-open');
            syncToggle();
        }
    });

    // Drag the right edge of the sidebar to resize it.
    if (resizer) {
        let dragging = false;
        resizer.addEventListener('pointerdown', (event) => {
            if (state.collapsed || mobileQuery.matches) {
                return;
            }
            dragging = true;
            resizer.setPointerCapture(event.pointerId);
            shell.classList.add('sidebar-resizing');
            event.preventDefault();
        });
        resizer.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }
            state.width = clampWidth(event.clientX - shell.getBoundingClientRect().left);
            applyWidth();
        });
        const stop = () => {
            if (!dragging) {
                return;
            }
            dragging = false;
            shell.classList.remove('sidebar-resizing');
            save();
        };
        resizer.addEventListener('pointerup', stop);
        resizer.addEventListener('pointercancel', stop);
        resizer.addEventListener('dblclick', () => {
            state.width = DEFAULT_WIDTH;
            applyWidth();
            save();
        });
        resizer.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }
            state.width = clampWidth(clampWidth(state.width) + (event.key === 'ArrowRight' ? 16 : -16));
            applyWidth();
            save();
            event.preventDefault();
        });
    }

    // Expandable sections (Workspace, Apps) and items (Storage, Messenger).
    const sections = [
        ...[...shell.querySelectorAll('[data-nav-group]')].map((element) => ({
            element, key: `group:${element.dataset.navGroup}`, button: element.querySelector('[data-group-toggle]'),
        })),
        ...[...shell.querySelectorAll('[data-nav-tree]')].map((element) => ({
            element, key: `tree:${element.dataset.navTree}`, button: element.querySelector('[data-tree-toggle]'),
        })),
    ];
    sections.forEach(({ element, key, button }) => {
        const setClosed = (closed) => {
            element.classList.toggle('is-closed', closed);
            button?.setAttribute('aria-expanded', closed ? 'false' : 'true');
        };
        setClosed(state.closed.includes(key));
        button?.addEventListener('click', () => {
            const closed = !element.classList.contains('is-closed');
            setClosed(closed);
            state.closed = state.closed.filter((entry) => entry !== key);
            if (closed) {
                state.closed.push(key);
            }
            save();
        });
    });
})();
