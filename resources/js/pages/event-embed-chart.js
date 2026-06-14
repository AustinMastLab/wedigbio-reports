import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function initEmbedChart() {
    const root = document.getElementById('embed-chart-root');
    if (!root) {
        return;
    }

    const startsAt = new Date(root.dataset.startsAt);
    const chartType = root.dataset.chartType;
    const hasData = root.dataset.hasData === '1';
    const isLive = root.dataset.isLive === '1';
    const reloadMs = Number.parseInt(root.dataset.reloadMs ?? '900000', 10);
    const noDataReloadMs = Number.parseInt(root.dataset.noDataReloadMs ?? '60000', 10);
    const countdownMessage = document.getElementById('countdown-message');
    const countdownHours = document.getElementById('countdown-hours');
    const countdownMinutes = document.getElementById('countdown-minutes');
    const noDataMessage = document.getElementById('no-data-message');
    const chartContainer = document.getElementById('chart-container');
    let noDataReloadScheduled = false;

    const scheduleNoDataReload = () => {
        if (hasData || noDataReloadScheduled) {
            return;
        }

        noDataReloadScheduled = true;
        setTimeout(() => location.reload(), Number.isNaN(noDataReloadMs) ? 60000 : noDataReloadMs);
    };

    const updateCountdown = () => {
        const hasValidStart = !Number.isNaN(startsAt.getTime());
        const diffMs = hasValidStart ? (startsAt.getTime() - Date.now()) : 0;

        if (hasValidStart && diffMs > 0) {
            const totalSeconds = Math.floor(diffMs / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);

            if (countdownMessage) {
                countdownMessage.style.display = 'block';
            }
            if (countdownHours) {
                countdownHours.textContent = String(hours);
            }
            if (countdownMinutes) {
                countdownMinutes.textContent = String(minutes);
            }
            if (chartContainer) {
                chartContainer.style.display = 'none';
            }
            if (noDataMessage) {
                noDataMessage.style.display = 'none';
            }

            return;
        }

        if (countdownMessage) {
            countdownMessage.style.display = 'none';
        }

        if (hasData) {
            if (chartContainer) {
                chartContainer.style.display = 'block';
            }
            if (noDataMessage) {
                noDataMessage.style.display = 'none';
            }
        } else {
            if (chartContainer) {
                chartContainer.style.display = 'none';
            }
            if (noDataMessage) {
                noDataMessage.style.display = 'block';
            }
            scheduleNoDataReload();
        }
    };

    updateCountdown();
    setInterval(updateCountdown, 1000);

    if (!hasData) {
        return;
    }

    const timezoneToggle = document.getElementById('timezone-toggle');
    const tzUtc = document.getElementById('tz-utc');
    const tzLocal = document.getElementById('tz-local');
    const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'Local';
    const timezoneStorageKey = 'wedigbio.chart.timezoneMode';
    const safeStorage = {
        get(key) {
            try {
                return window.localStorage.getItem(key);
            } catch {
                return null;
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, value);
            } catch {
                // Ignore storage errors in restricted iframe contexts.
            }
        },
    };
    const savedTimezoneMode = safeStorage.get(timezoneStorageKey);
    let useUtcTime = savedTimezoneMode !== 'local';

    if (tzLocal) {
        tzLocal.textContent = userTimeZone || 'Local';
    }

    const api = {
        hourly: root.dataset.apiHourly,
        byCenter: root.dataset.apiByCenter,
    };

    let chart = null;

    // Light theme colors (forced light mode for embeds)
    const chartTextColor = '#334155';
    const chartGridColor = 'rgba(148, 163, 184, 0.35)';
    const tooltipBackgroundColor = '#ffffff';
    const tooltipTitleColor = '#0f172a';
    const tooltipBodyColor = '#334155';
    const tooltipBorderColor = 'rgba(245, 158, 11, 0.45)';
    const accentColor = '#f59e0b';

    const updateTimezoneToggleLabel = () => {
        if (timezoneToggle) {
            timezoneToggle.checked = !useUtcTime;
        }

        if (tzUtc) {
            tzUtc.style.fontWeight = useUtcTime ? '600' : 'normal';
            tzUtc.style.color = useUtcTime ? '#b45309' : '#64748b';
        }

        if (tzLocal) {
            tzLocal.style.fontWeight = useUtcTime ? 'normal' : '600';
            tzLocal.style.color = useUtcTime ? '#64748b' : '#b45309';
        }
    };

    const formatHourLabel = (value) => {
        const options = {
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
            hourCycle: 'h23',
        };

        if (useUtcTime) {
            options.timeZone = 'UTC';
        }

        return new Date(value).toLocaleString('en-US', options);
    };

    const tickCallbackFactory = (step) => function (_value, index) {
        return index % step === 0 ? this.getLabelForValue(index) : '';
    };

    const baseOptions = () => ({
        maintainAspectRatio: false,
        interaction: {
            mode: 'nearest',
            intersect: false,
        },
        plugins: {
            legend: {
                labels: {
                    color: chartTextColor,
                },
            },
            tooltip: {
                backgroundColor: tooltipBackgroundColor,
                borderColor: tooltipBorderColor,
                borderWidth: 1,
                titleColor: tooltipTitleColor,
                bodyColor: tooltipBodyColor,
            },
        },
        scales: {
            x: {
                ticks: {
                    color: chartTextColor,
                    autoSkip: true,
                    maxTicksLimit: 10,
                },
                grid: { color: chartGridColor },
            },
            y: {
                ticks: { color: chartTextColor },
                grid: { color: chartGridColor },
            },
        },
    });

    const centerColors = (index) => {
        const palette = ['#f59e0b', '#60a5fa', '#34d399', '#f472b6', '#a78bfa', '#f87171', '#22d3ee', '#facc15', '#4ade80', '#fb7185'];
        return palette[index % palette.length];
    };

    const hexToRgba = (hex, alpha = 0.12) => {
        const cleanHex = hex.replace('#', '');
        const bigint = Number.parseInt(cleanHex, 16);
        const r = (bigint >> 16) & 255;
        const g = (bigint >> 8) & 255;
        const b = bigint & 255;
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };

    // Ensure at least 2 points for visible line/bar
    function padHourlySeries(series) {
        if (series.length >= 2) return series;
        if (series.length === 0) return series;
        const first = new Date(series[0].hour);
        first.setHours(first.getHours() - 1);
        return [{ hour: first.toISOString(), weighted: 0, raw: 0 }, ...series];
    }

    function padCenterPoints(points) {
        if (points.length >= 2) return points;
        if (points.length === 0) return points;
        const first = new Date(points[0].hour);
        first.setHours(first.getHours() - 1);
        return [{ hour: first.toISOString(), weighted: 0, cumulative_weighted: 0, raw: 0, cumulative_raw: 0 }, ...points];
    }

    async function renderTotalActivityChart() {
        const response = await fetch(api.hourly);
        if (!response.ok) {
            throw new Error(`Hourly activity request failed: ${response.status}`);
        }
        const json = await response.json();
        const series = padHourlySeries(json.series ?? []);
        let cumulative = 0;
        const labels = series.map((point) => formatHourLabel(point.hour));
        const data = series.map((point) => {
            cumulative += Number(point.weighted ?? 0);
            return Number(cumulative.toFixed(4));
        });

        const options = baseOptions();
        options.plugins.legend.display = false;
        options.scales.x.ticks.callback = tickCallbackFactory(Math.max(1, Math.ceil(labels.length / 10)));

        if (chart) {
            chart.destroy();
        }

        chart = new Chart(document.getElementById('embed-chart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Total contributions',
                    data,
                    borderColor: accentColor,
                    backgroundColor: 'rgba(245, 158, 11, 0.18)',
                    pointRadius: 0,
                    fill: true,
                    tension: 0.25,
                    borderWidth: 3,
                }],
            },
            options,
        });
    }

    async function renderActivityByCenterChart() {
        const response = await fetch(api.byCenter);
        if (!response.ok) {
            throw new Error(`Activity-by-center request failed: ${response.status}`);
        }
        const json = await response.json();
        const paddedSeries = (json.series ?? []).map((center) => ({
            ...center,
            points: padCenterPoints(center.points ?? []),
        }));
        const allHours = [...new Set(paddedSeries.flatMap((center) => center.points.map((point) => point.hour)))].sort();
        const labels = allHours.map(formatHourLabel);

        const datasets = paddedSeries.map((center, index) => {
            const valuesByHour = Object.fromEntries(center.points.map((point) => [point.hour, point.cumulative_weighted]));
            const color = centerColors(index);

            return {
                label: center.center,
                data: allHours.map((hour) => valuesByHour[hour] ?? null),
                borderColor: color,
                backgroundColor: hexToRgba(color, 0.10),
                pointRadius: 0,
                pointHoverRadius: 4,
                borderWidth: 2,
                tension: 0.2,
                fill: true,
                spanGaps: true,
            };
        });

        const options = baseOptions();
        options.scales.x.ticks.callback = tickCallbackFactory(Math.max(1, Math.ceil(labels.length / 8)));
        options.plugins.legend.position = 'bottom';
        options.plugins.legend.labels.color = chartTextColor;
        options.plugins.legend.labels.usePointStyle = true;
        options.plugins.legend.labels.boxWidth = 10;
        options.plugins.legend.labels.padding = 14;

        if (chart) {
            chart.destroy();
        }

        chart = new Chart(document.getElementById('embed-chart'), {
            type: 'line',
            data: { labels, datasets },
            options,
        });
    }

    async function renderChart() {
        try {
            if (chartType === 'total-activity') {
                await renderTotalActivityChart();
            } else if (chartType === 'activity-by-center') {
                await renderActivityByCenterChart();
            }
        } catch (error) {
            console.error('Error rendering embed chart:', error);
        }
    }

    // Initial render
    renderChart();
    updateTimezoneToggleLabel();

    // Timezone toggle listener
    if (timezoneToggle) {
        timezoneToggle.addEventListener('change', async () => {
            useUtcTime = !timezoneToggle.checked;
            safeStorage.set(timezoneStorageKey, useUtcTime ? 'utc' : 'local');
            updateTimezoneToggleLabel();
            await renderChart();
        });
    }

    if (isLive) {
        setInterval(renderChart, Number.isNaN(reloadMs) ? 900000 : reloadMs);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEmbedChart);
} else {
    initEmbedChart();
}
