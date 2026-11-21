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
    let draggedWidget = null;
    let saveTimer = null;

    const widgets = () => [...widgetGrid.querySelectorAll('.dashboard-widget')];
    const currentLayout = () => widgets().map((widget) => ({
        id: widget.dataset.widget,
        size: widget.dataset.widgetSize,
    }));

    const saveLayout = () => {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(async () => {
            if (!(saveStatus instanceof HTMLElement)) return;
            saveStatus.textContent = 'Saving layout…';

            try {
                const response = await fetch('/dashboard/layout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: new URLSearchParams({
                        _token: widgetGrid.dataset.csrf ?? '',
                        layout_json: JSON.stringify({ widgets: currentLayout() }),
                    }),
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

    try {
        const layout = JSON.parse(widgetGrid.dataset.layout ?? '[]');
        if (Array.isArray(layout)) {
            for (const entry of layout) {
                const widget = widgets().find((item) => item.dataset.widget === entry.id);
                if (widget && ['small', 'wide', 'full'].includes(entry.size)) {
                    widget.dataset.widgetSize = entry.size;
                    widgetGrid.append(widget);
                }
            }
        }
    } catch (error) {
        if (saveStatus instanceof HTMLElement) {
            saveStatus.textContent = 'Saved dashboard layout could not be loaded.';
        }
    }

    widgetGrid.addEventListener('dragstart', (event) => {
        const target = event.target instanceof Element ? event.target.closest('.dashboard-widget') : null;
        if (!(target instanceof HTMLElement) || event.target instanceof Element && event.target.closest('.widget-actions')) {
            event.preventDefault();
            return;
        }
        draggedWidget = target;
        target.classList.add('is-dragging');
        event.dataTransfer?.setData('text/plain', target.dataset.widget ?? '');
        if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    });

    widgetGrid.addEventListener('dragover', (event) => {
        if (!draggedWidget) return;
        event.preventDefault();
        const target = event.target instanceof Element ? event.target.closest('.dashboard-widget') : null;
        if (target instanceof HTMLElement && target !== draggedWidget) {
            const bounds = target.getBoundingClientRect();
            const insertAfter = event.clientY > bounds.top + bounds.height / 2;
            widgetGrid.insertBefore(draggedWidget, insertAfter ? target.nextElementSibling : target);
        }
    });

    widgetGrid.addEventListener('dragend', () => {
        draggedWidget?.classList.remove('is-dragging');
        draggedWidget = null;
        saveLayout();
    });

    widgetGrid.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('button') : null;
        if (!(button instanceof HTMLButtonElement)) return;
        const widget = button.closest('.dashboard-widget');
        if (!(widget instanceof HTMLElement)) return;

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
}

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
    const isTwelveHour = clockWidget instanceof HTMLElement && clockWidget.dataset.timeFormat === '12';
    const zone = document.querySelector('#workspace-zone');
    if (zone instanceof HTMLElement) {
        zone.textContent = (Intl.DateTimeFormat().resolvedOptions().timeZone ?? '').replace(/_/g, ' ');
    }
    const updateClock = () => {
        const now = new Date();
        clock.textContent = new Intl.DateTimeFormat(undefined, {
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
