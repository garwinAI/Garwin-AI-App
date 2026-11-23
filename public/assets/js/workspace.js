const widgetGrid = document.querySelector('#dashboard-widgets');

document.addEventListener('submit', (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    const message = form?.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

if (widgetGrid instanceof HTMLElement) {
    const saveStatus = document.querySelector('#layout-save-status');
    const tray = document.querySelector('#widget-tray');
    const gallery = document.querySelector('[data-widget-gallery]');
    let draggedWidget = null;
    let galleryDragId = null;
    let saveTimer = null;
    let noteTimer = null;

    const widgets = () => [...widgetGrid.querySelectorAll(':scope > .dashboard-widget')];
    const allWidgets = () => [...document.querySelectorAll('.dashboard-widget')];
    const findWidget = (id) => allWidgets().find((item) => item.dataset.widget === id);
    const currentLayout = () => widgets().map((widget) => {
        const entry = {
            id: widget.dataset.widget,
            size: widget.dataset.widgetSize,
            title: widget.dataset.widgetCustomTitle ?? '',
        };
        if (widget.dataset.windowKind === 'note') {
            entry.kind = 'note';
            entry.note = widget.querySelector('[data-window-note]')?.value ?? '';
        } else if (widget.dataset.windowKind === 'page') {
            entry.kind = 'page';
            entry.page_id = Number(widget.dataset.pageId);
        }
        return entry;
    });

    const syncGallery = () => {
        if (!(gallery instanceof HTMLElement)) return;
        const onPage = new Set(widgets().map((widget) => widget.dataset.widget));
        gallery.querySelectorAll('[data-gallery-widget]').forEach((item) => {
            if (!(item instanceof HTMLElement)) return;
            const isOn = onPage.has(item.dataset.galleryWidget);
            item.classList.toggle('is-on-page', isOn);
            item.draggable = !isOn;
        });
        widgetGrid.classList.toggle('is-empty', onPage.size === 0);
    };

    let layoutPending = false;
    const layoutBody = () => new URLSearchParams({
        _token: widgetGrid.dataset.csrf ?? '',
        layout_json: JSON.stringify({ widgets: currentLayout() }),
    });
    // Leaving mid-edit (say, typing in a note): send the last change right away.
    window.addEventListener('pagehide', () => {
        if (!layoutPending) return;
        window.clearTimeout(saveTimer);
        window.clearTimeout(noteTimer);
        layoutPending = false;
        navigator.sendBeacon('/dashboard/layout', layoutBody());
    });

    const saveLayout = () => {
        syncGallery();
        layoutPending = true;
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(async () => {
            layoutPending = false;
            if (!(saveStatus instanceof HTMLElement)) return;
            saveStatus.textContent = 'Saving layout…';

            try {
                const response = await fetch('/dashboard/layout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: layoutBody(),
                });
                if (!response.ok) {
                    throw new Error(await response.text() || 'Unable to save dashboard layout.');
                }
                saveStatus.textContent = 'Layout saved.';
            } catch (error) {
                saveStatus.textContent = error instanceof Error ? error.message : 'Unable to save dashboard layout.';
            }
        }, 250);
    };

    // Place widgets in their saved order; anything not on the page waits in the tray.
    try {
        const layout = JSON.parse(widgetGrid.dataset.layout ?? '[]');
        const placed = new Set();
        if (Array.isArray(layout)) {
            for (const entry of layout) {
                const widget = findWidget(entry.id);
                if (widget && ['small', 'wide', 'full'].includes(entry.size)) {
                    widget.dataset.widgetSize = entry.size;
                    widgetGrid.append(widget);
                    placed.add(entry.id);
                }
            }
        }
        widgets().forEach((widget) => {
            if (!placed.has(widget.dataset.widget) && tray instanceof HTMLElement) tray.append(widget);
        });
    } catch (error) {
        if (saveStatus instanceof HTMLElement) {
            saveStatus.textContent = 'Saved dashboard layout could not be loaded.';
        }
    }
    syncGallery();

    const addWidget = (id, before = null) => {
        const widget = findWidget(id);
        if (!widget || widget.parentElement === widgetGrid) return;
        widgetGrid.insertBefore(widget, before);
        widget.classList.add('just-added');
        window.setTimeout(() => widget.classList.remove('just-added'), 900);
        saveLayout();
    };

    const insertionPoint = (event) => {
        const target = event.target instanceof Element ? event.target.closest('.dashboard-widget') : null;
        if (!(target instanceof HTMLElement) || target.parentElement !== widgetGrid) return undefined;
        const bounds = target.getBoundingClientRect();
        const after = event.clientX > bounds.left + bounds.width / 2 || event.clientY > bounds.top + bounds.height * 0.75;
        return after ? target.nextElementSibling : target;
    };

    widgetGrid.addEventListener('dragstart', (event) => {
        const target = event.target instanceof Element ? event.target.closest('.dashboard-widget') : null;
        if (!(target instanceof HTMLElement) || event.target instanceof Element && event.target.closest('.widget-actions, [contenteditable="true"], textarea, input')) {
            event.preventDefault();
            return;
        }
        draggedWidget = target;
        target.classList.add('is-dragging');
        event.dataTransfer?.setData('text/plain', target.dataset.widget ?? '');
        if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    });

    widgetGrid.addEventListener('dragover', (event) => {
        if (galleryDragId) {
            event.preventDefault();
            widgetGrid.classList.add('is-drop-target');
            return;
        }
        if (!draggedWidget) return;
        event.preventDefault();
        const point = insertionPoint(event);
        if (point !== undefined && point !== draggedWidget) {
            widgetGrid.insertBefore(draggedWidget, point);
        }
    });

    widgetGrid.addEventListener('dragleave', (event) => {
        if (!(event.relatedTarget instanceof Node) || !widgetGrid.contains(event.relatedTarget)) {
            widgetGrid.classList.remove('is-drop-target');
        }
    });

    widgetGrid.addEventListener('drop', (event) => {
        if (!galleryDragId) return;
        event.preventDefault();
        widgetGrid.classList.remove('is-drop-target');
        const point = insertionPoint(event);
        addWidget(galleryDragId, point === undefined ? null : point);
        galleryDragId = null;
    });

    widgetGrid.addEventListener('dragend', () => {
        if (!draggedWidget) return;
        draggedWidget.classList.remove('is-dragging');
        draggedWidget = null;
        saveLayout();
    });

    if (gallery instanceof HTMLDetailsElement) {
        gallery.addEventListener('dragstart', (event) => {
            const item = event.target instanceof Element ? event.target.closest('[data-gallery-widget]') : null;
            if (!(item instanceof HTMLElement) || item.classList.contains('is-on-page')) {
                event.preventDefault();
                return;
            }
            galleryDragId = item.dataset.galleryWidget ?? null;
            event.dataTransfer?.setData('text/plain', galleryDragId ?? '');
            if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy';
            // Let the page underneath take the drop.
            window.setTimeout(() => gallery.classList.add('is-dragging-out'), 0);
        });
        gallery.addEventListener('dragend', () => {
            galleryDragId = null;
            gallery.classList.remove('is-dragging-out');
            widgetGrid.classList.remove('is-drop-target');
            gallery.open = false;
        });
        gallery.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('[data-gallery-add]') : null;
            if (button instanceof HTMLElement) {
                addWidget(button.dataset.galleryAdd ?? '');
                gallery.open = false;
            }
        });
    }

    const startRename = (widget) => {
        const title = widget.querySelector('[data-widget-title]');
        if (!(title instanceof HTMLElement) || title.isContentEditable) return;
        const before = title.textContent ?? '';
        title.contentEditable = 'true';
        title.spellcheck = false;
        widget.draggable = false;
        title.focus();
        const range = document.createRange();
        range.selectNodeContents(title);
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(range);

        const finish = (keep) => {
            title.removeEventListener('keydown', onKey);
            title.removeEventListener('blur', onBlur);
            title.contentEditable = 'false';
            widget.draggable = true;
            const fallback = title.dataset.defaultTitle ?? before;
            let next = keep ? (title.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 40) : before;
            if (next === '') next = fallback;
            title.textContent = next;
            // Windows always keep their own name; built-in widgets store a blank for "use the default".
            const custom = widget.dataset.windowKind || next !== fallback ? next : '';
            if (keep && custom !== (widget.dataset.widgetCustomTitle ?? '')) {
                widget.dataset.widgetCustomTitle = custom;
                saveLayout();
            }
        };
        const onKey = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                finish(true);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                finish(false);
            }
        };
        const onBlur = () => finish(true);
        title.addEventListener('keydown', onKey);
        title.addEventListener('blur', onBlur);
    };

    widgetGrid.addEventListener('input', (event) => {
        if (!(event.target instanceof HTMLTextAreaElement) || !event.target.matches('[data-window-note]')) return;
        window.clearTimeout(noteTimer);
        layoutPending = true;
        noteTimer = window.setTimeout(saveLayout, 700);
    });

    widgetGrid.addEventListener('dblclick', (event) => {
        const title = event.target instanceof Element ? event.target.closest('[data-widget-title]') : null;
        const widget = title?.closest('.dashboard-widget');
        if (widget instanceof HTMLElement) startRename(widget);
    });

    widgetGrid.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('button') : null;
        if (!(button instanceof HTMLButtonElement)) return;
        const widget = button.closest('.dashboard-widget');
        if (!(widget instanceof HTMLElement)) return;

        if (button.classList.contains('widget-rename')) {
            startRename(widget);
            return;
        }

        if (button.classList.contains('widget-remove')) {
            if (widget.dataset.windowKind) {
                // Windows are deleted, not parked: a note's text goes with it.
                const note = widget.querySelector('[data-window-note]');
                if (note instanceof HTMLTextAreaElement && note.value.trim() !== '' && !window.confirm('Delete this note window and its text?')) {
                    return;
                }
                widget.remove();
            } else if (tray instanceof HTMLElement) {
                tray.append(widget);
            }
            saveLayout();
            return;
        }

        if (button.classList.contains('widget-size-toggle')) {
            const sizes = ['small', 'wide', 'full'];
            const current = sizes.indexOf(widget.dataset.widgetSize ?? 'small');
            widget.dataset.widgetSize = sizes[(current + 1) % sizes.length];
            saveLayout();
            return;
        }

        if (!button.classList.contains('widget-move')) return;
        if (button.dataset.direction === 'up' && widget.previousElementSibling) {
            widgetGrid.insertBefore(widget, widget.previousElementSibling);
            saveLayout();
        } else if (button.dataset.direction === 'down' && widget.nextElementSibling) {
            widgetGrid.insertBefore(widget.nextElementSibling, widget);
            saveLayout();
        }
    });

    // The Notify widget's Notifications row opens the top bar's notifications panel.
    widgetGrid.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href="/dashboard#notifications"]') : null;
        const bell = document.querySelector('.topbar-menu-popover');
        if (link && bell instanceof HTMLDetailsElement) {
            event.preventDefault();
            bell.open = true;
            bell.querySelector('summary')?.focus();
        }
    });
}

// Toolbar popovers (Apps, Widgets, Pages) and the top bar bell: one open at a time,
// closed by clicking elsewhere or pressing Escape.
const popovers = () => [...document.querySelectorAll('[data-toolbar-popover], .topbar-menu-popover')];
document.addEventListener('toggle', (event) => {
    const opened = event.target;
    if (!(opened instanceof HTMLDetailsElement) || !opened.open || !popovers().includes(opened)) return;
    popovers().forEach((other) => {
        if (other !== opened && other instanceof HTMLDetailsElement) other.open = false;
    });
}, true);
document.addEventListener('click', (event) => {
    popovers().forEach((popover) => {
        if (popover instanceof HTMLDetailsElement && popover.open && event.target instanceof Node && !popover.contains(event.target)) {
            popover.open = false;
        }
    });
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    popovers().forEach((popover) => {
        if (popover instanceof HTMLDetailsElement && popover.open) {
            popover.open = false;
            popover.querySelector('summary')?.focus();
        }
    });
});

// Greeting and today's date in the dashboard header use the viewer's own clock.
const greeting = document.querySelector('[data-greeting]');
if (greeting instanceof HTMLElement) {
    const hour = new Date().getHours();
    const part = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    greeting.textContent = `${part}, ${greeting.dataset.firstName ?? ''}.`;
    const today = document.querySelector('[data-today]');
    if (today instanceof HTMLElement) {
        today.textContent = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }).format(new Date());
    }
}

const clock = document.querySelector('#workspace-clock');
const date = document.querySelector('#workspace-date');
if (clock instanceof HTMLElement && date instanceof HTMLElement) {
    const clockWidget = clock.closest('[data-time-format]');
    const isTwelveHour = !(clockWidget instanceof HTMLElement && clockWidget.dataset.timeFormat === '24');
    const zone = document.querySelector('#workspace-zone');
    if (zone instanceof HTMLElement) {
        zone.textContent = (Intl.DateTimeFormat().resolvedOptions().timeZone ?? '').replace(/_/g, ' ');
    }
    const updateClock = () => {
        const now = new Date();
        clock.textContent = new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: isTwelveHour,
        }).format(now);
        date.textContent = new Intl.DateTimeFormat(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
        }).format(now);
    };
    updateClock();
    window.setInterval(updateClock, 15_000);
}

// Weather: a saved Settings location wins; otherwise the browser's location,
// falling back to the city of the device's time zone so something shows straight away.
const weatherWidget = document.querySelector('[data-weather-location]');
if (weatherWidget instanceof HTMLElement) {
    const savedLocation = weatherWidget.dataset.weatherLocation?.trim() ?? '';
    const els = {
        icon: document.querySelector('#weather-icon'),
        temp: document.querySelector('#weather-value'),
        condition: document.querySelector('#weather-condition'),
        place: document.querySelector('#weather-caption'),
        forecast: document.querySelector('#weather-forecast'),
    };
    const region = (navigator.language || 'en-US').split('-')[1]?.toUpperCase();
    const fahrenheit = ['US', 'LR', 'MM', 'BS', 'BZ', 'KY', 'PW'].includes(region ?? '');
    const unitLabel = fahrenheit ? '°F' : '°C';
    const icons = {
        sun: '<circle cx="12" cy="12" r="4.5"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/>',
        partly: '<circle cx="9" cy="9" r="3.5"/><path d="M9 2.5v1.5M3.5 9H2M4.8 4.8l1 1M13.2 4.8l-1 1"/><path d="M8 20h9a3.5 3.5 0 0 0 0-7 5 5 0 0 0-9.6 1.5A2.8 2.8 0 0 0 8 20Z"/>',
        cloud: '<path d="M7 19h10.5a4 4 0 0 0 .4-8 6 6 0 0 0-11.6 1.6A3.3 3.3 0 0 0 7 19Z"/>',
        fog: '<path d="M7 14h10.5a4 4 0 0 0 .4-8 6 6 0 0 0-11.6 1.6A3.3 3.3 0 0 0 7 14ZM4 18h16M7 21.5h10"/>',
        rain: '<path d="M7 15h10.5a4 4 0 0 0 .4-8 6 6 0 0 0-11.6 1.6A3.3 3.3 0 0 0 7 15ZM8 18l-1 3M12 18l-1 3M16 18l-1 3"/>',
        snow: '<path d="M7 15h10.5a4 4 0 0 0 .4-8 6 6 0 0 0-11.6 1.6A3.3 3.3 0 0 0 7 15ZM8 19h.01M12 21h.01M16 19h.01M10 22h.01M14 22h.01"/>',
        storm: '<path d="M7 14h10.5a4 4 0 0 0 .4-8 6 6 0 0 0-11.6 1.6A3.3 3.3 0 0 0 7 14ZM12.5 15l-2.5 4h4l-2.5 4"/>',
    };
    const describe = (code) => {
        if (code === 0) return ['Clear', 'sun'];
        if (code === 1) return ['Mainly clear', 'sun'];
        if (code === 2) return ['Partly cloudy', 'partly'];
        if (code === 3) return ['Overcast', 'cloud'];
        if (code === 45 || code === 48) return ['Fog', 'fog'];
        if (code >= 51 && code <= 57) return ['Drizzle', 'rain'];
        if (code >= 61 && code <= 67) return [code === 65 ? 'Heavy rain' : code === 61 ? 'Light rain' : 'Rain', 'rain'];
        if (code >= 71 && code <= 77) return [code === 75 ? 'Heavy snow' : 'Snow', 'snow'];
        if (code >= 80 && code <= 82) return ['Showers', 'rain'];
        if (code === 85 || code === 86) return ['Snow showers', 'snow'];
        if (code >= 95) return ['Thunderstorm', 'storm'];
        return ['Current conditions', 'cloud'];
    };
    const svg = (name, className) => `<svg class="${className}" viewBox="0 0 24 24" aria-hidden="true">${icons[name] ?? icons.cloud}</svg>`;

    const geocode = async (name) => {
        const url = new URL('https://geocoding-api.open-meteo.com/v1/search');
        url.search = new URLSearchParams({ name, count: '1', language: 'en', format: 'json' });
        const response = await fetch(url);
        if (!response.ok) throw new Error('Weather location lookup failed.');
        const place = (await response.json()).results?.[0];
        if (!place) throw new Error('Weather location was not found.');
        return { latitude: place.latitude, longitude: place.longitude, label: [place.name, place.admin1].filter(Boolean).join(', ') };
    };

    const show = async ({ latitude, longitude, label }) => {
        const url = new URL('https://api.open-meteo.com/v1/forecast');
        url.search = new URLSearchParams({
            latitude: String(latitude),
            longitude: String(longitude),
            current: 'temperature_2m,apparent_temperature,weather_code',
            daily: 'weather_code,temperature_2m_max,temperature_2m_min',
            temperature_unit: fahrenheit ? 'fahrenheit' : 'celsius',
            timezone: 'auto',
            forecast_days: '4',
        });
        const response = await fetch(url);
        if (!response.ok) throw new Error('Weather service is unavailable.');
        const data = await response.json();
        const [conditionText, iconName] = describe(data.current.weather_code);
        if (els.icon instanceof HTMLElement) els.icon.innerHTML = svg(iconName, 'weather-icon-svg');
        if (els.temp instanceof HTMLElement) els.temp.textContent = `${Math.round(data.current.temperature_2m)}${unitLabel}`;
        if (els.condition instanceof HTMLElement) {
            els.condition.textContent = `${conditionText} · Feels like ${Math.round(data.current.apparent_temperature)}°`;
        }
        if (els.place instanceof HTMLElement) els.place.textContent = label;
        if (els.forecast instanceof HTMLElement) {
            els.forecast.replaceChildren();
            const days = data.daily?.time ?? [];
            days.forEach((day, index) => {
                const item = document.createElement('li');
                const name = document.createElement('span');
                name.className = 'forecast-day';
                name.textContent = index === 0 ? 'Today' : new Intl.DateTimeFormat(undefined, { weekday: 'short' }).format(new Date(`${day}T12:00:00`));
                const iconHolder = document.createElement('span');
                iconHolder.innerHTML = svg(describe(data.daily.weather_code[index])[1], 'forecast-icon');
                const temps = document.createElement('span');
                temps.className = 'forecast-temps';
                temps.textContent = `${Math.round(data.daily.temperature_2m_max[index])}° / ${Math.round(data.daily.temperature_2m_min[index])}°`;
                item.append(name, iconHolder, temps);
                els.forecast.append(item);
            });
        }
    };

    const fail = (message) => {
        if (els.temp instanceof HTMLElement) els.temp.textContent = '—';
        if (els.condition instanceof HTMLElement) els.condition.textContent = message;
        if (els.place instanceof HTMLElement) els.place.innerHTML = '<a class="widget-link" href="/settings">Set a location in Settings →</a>';
    };

    const timezoneCity = () => {
        const zone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
        const city = zone.includes('/') ? zone.split('/').pop()?.replace(/_/g, ' ') : '';
        return city && !/^(UTC|GMT|Etc)/i.test(zone) ? city : '';
    };

    const fromBrowserLocation = () => new Promise((resolve, reject) => {
        if (!('geolocation' in navigator)) {
            reject(new Error('Location is not available.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({
                latitude: Math.round(position.coords.latitude * 100) / 100,
                longitude: Math.round(position.coords.longitude * 100) / 100,
                label: 'Your location',
            }),
            () => reject(new Error('Location permission was not given.')),
            { timeout: 8000, maximumAge: 30 * 60 * 1000 },
        );
    });

    const load = async () => {
        if (savedLocation) {
            try {
                await show(await geocode(savedLocation));
            } catch (error) {
                fail(error instanceof Error ? error.message : 'Weather unavailable.');
            }
            return;
        }

        let permission = 'prompt';
        try {
            permission = (await navigator.permissions?.query({ name: 'geolocation' }))?.state ?? 'prompt';
        } catch {
            permission = 'prompt';
        }

        if (permission === 'granted') {
            try {
                await show(await fromBrowserLocation());
                return;
            } catch {
                // Fall through to the time-zone city.
            }
        }

        const city = timezoneCity();
        let shown = false;
        if (city) {
            try {
                await show(await geocode(city));
                shown = true;
            } catch {
                shown = false;
            }
        }
        if (permission === 'prompt') {
            try {
                await show(await fromBrowserLocation());
                shown = true;
            } catch {
                // Keep the time-zone weather if the visitor declines.
            }
        }
        if (!shown) {
            fail('Weather unavailable for your area.');
        }
    };
    load();
}

document.querySelectorAll('.star-rating-options').forEach((group) => {
    if (!(group instanceof HTMLElement)) return;
    const labels = [...group.querySelectorAll('label[for]')];
    const paintStars = (rating, className) => {
        labels.forEach((label) => {
            const value = Number(label.htmlFor.match(/rating-(\d+)$/)?.[1] ?? 0);
            label.classList.toggle(className, value > 0 && value <= rating);
        });
    };

    group.addEventListener('change', (event) => {
        const input = event.target;
        if (input instanceof HTMLInputElement && input.checked) {
            paintStars(Number(input.value), 'is-rated');
        }
    });
    group.addEventListener('mouseover', (event) => {
        const label = event.target instanceof Element ? event.target.closest('label[for]') : null;
        if (label instanceof HTMLLabelElement) {
            const rating = Number(label.htmlFor.match(/rating-(\d+)$/)?.[1] ?? 0);
            labels.forEach((star) => star.classList.remove('is-hovered'));
            paintStars(rating, 'is-hovered');
        }
    });
    group.addEventListener('mouseleave', () => {
        labels.forEach((label) => label.classList.remove('is-hovered'));
    });
    const selected = group.querySelector('input:checked');
    if (selected instanceof HTMLInputElement) {
        paintStars(Number(selected.value), 'is-rated');
    }
});
