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

const clock = document.querySelector('#workspace-clock');
const date = document.querySelector('#workspace-date');
if (clock instanceof HTMLElement && date instanceof HTMLElement) {
    const clockWidget = clock.closest('[data-time-format]');
    const isTwelveHour = clockWidget instanceof HTMLElement && clockWidget.dataset.timeFormat === '12';
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
    window.setInterval(updateClock, 30_000);
}

const weatherWidget = document.querySelector('[data-weather-location]');
if (weatherWidget instanceof HTMLElement) {
    const location = weatherWidget.dataset.weatherLocation?.trim() ?? '';
    const weatherValue = document.querySelector('#weather-value');
    const weatherCaption = document.querySelector('#weather-caption');
    const weatherDescription = new Map([
        [0, 'Clear sky'], [1, 'Mainly clear'], [2, 'Partly cloudy'], [3, 'Overcast'],
        [45, 'Fog'], [48, 'Rime fog'], [51, 'Light drizzle'], [53, 'Drizzle'],
        [55, 'Heavy drizzle'], [61, 'Light rain'], [63, 'Rain'], [65, 'Heavy rain'],
        [71, 'Light snow'], [73, 'Snow'], [75, 'Heavy snow'], [80, 'Rain showers'],
        [81, 'Rain showers'], [82, 'Heavy rain showers'], [95, 'Thunderstorm'],
    ]);

    if (location && weatherValue instanceof HTMLElement && weatherCaption instanceof HTMLElement) {
        const loadWeather = async () => {
            try {
                const geoUrl = new URL('https://geocoding-api.open-meteo.com/v1/search');
                geoUrl.search = new URLSearchParams({ name: location, count: '1', language: 'en', format: 'json' });
                const geoResponse = await fetch(geoUrl);
                if (!geoResponse.ok) throw new Error('Weather location lookup failed.');
                const geoData = await geoResponse.json();
                const place = geoData.results?.[0];
                if (!place) throw new Error('Weather location was not found.');

                const weatherUrl = new URL('https://api.open-meteo.com/v1/forecast');
                weatherUrl.search = new URLSearchParams({
                    latitude: String(place.latitude),
                    longitude: String(place.longitude),
                    current: 'temperature_2m,weather_code',
                });
                const weatherResponse = await fetch(weatherUrl);
                if (!weatherResponse.ok) throw new Error('Weather service is unavailable.');
                const weatherData = await weatherResponse.json();
                weatherValue.textContent = `${Math.round(weatherData.current.temperature_2m)}°C`;
                weatherCaption.textContent = `${weatherDescription.get(weatherData.current.weather_code) ?? 'Current conditions'} · ${place.name}`;
            } catch (error) {
                weatherValue.textContent = 'Weather unavailable';
                weatherCaption.textContent = error instanceof Error ? error.message : 'Unable to load current weather.';
            }
        };
        loadWeather();
    }
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
