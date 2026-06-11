import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function initEventShowDashboard() {
    const root = document.getElementById('event-dashboard-root');
    if (!root) {
        return;
    }

    const hasData = root.dataset.hasData === '1';
    if (!hasData) {
        return;
    }

    const isLive = root.dataset.isLive === '1';
    const reloadMs = Number.parseInt(root.dataset.reloadMs ?? '900000', 10);
    const timezoneToggle = document.getElementById('chart-timezone-toggle');
    const timezoneLabelUtc = document.getElementById('chart-timezone-label-utc');
    const timezoneLabelLocal = document.getElementById('chart-timezone-label-local');
    const userTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'Local';
    const timezoneStorageKey = 'wedigbio.chart.timezoneMode';
    const savedTimezoneMode = window.localStorage.getItem(timezoneStorageKey);
    let useUtcTime = savedTimezoneMode !== 'local';

    if (timezoneLabelLocal) {
        timezoneLabelLocal.textContent = userTimeZone || 'Local';
    }

    const api = {
        summary: root.dataset.apiSummary,
        total: root.dataset.apiTotal,
        hourly: root.dataset.apiHourly,
        byCenter: root.dataset.apiByCenter,
    };

    const isDarkTheme = document.documentElement.classList.contains('dark');
    const chartTextColor = isDarkTheme ? '#d4d4d8' : '#334155';
    const chartGridColor = isDarkTheme ? 'rgba(113, 113, 122, 0.35)' : 'rgba(148, 163, 184, 0.35)';
    const tooltipBackgroundColor = isDarkTheme ? '#18181b' : '#ffffff';
    const tooltipTitleColor = isDarkTheme ? '#ffffff' : '#0f172a';
    const tooltipBodyColor = isDarkTheme ? '#e4e4e7' : '#334155';
    const tooltipBorderColor = isDarkTheme ? 'rgba(245, 158, 11, 0.35)' : 'rgba(245, 158, 11, 0.45)';
    const accentColor = '#f59e0b';

    let totalChart = null;
    let hourlyChart = null;
    let centerChart = null;

    const updateTimezoneToggleLabel = () => {
        if (timezoneToggle) {
            timezoneToggle.checked = !useUtcTime;
        }

        if (timezoneLabelUtc) {
            timezoneLabelUtc.className = useUtcTime
                ? 'font-semibold text-amber-700 dark:text-amber-300'
                : 'text-slate-500 dark:text-gray-400';
        }

        if (timezoneLabelLocal) {
            timezoneLabelLocal.className = useUtcTime
                ? 'text-slate-500 dark:text-gray-400'
                : 'font-semibold text-amber-700 dark:text-amber-300';
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

    async function loadSummary() {
        const response = await fetch(api.summary);
        const json = await response.json();
        const summary = json.summary;

        document.getElementById('card-weighted').textContent = Number(summary.weighted_total ?? 0).toLocaleString();
        document.getElementById('card-raw').textContent = Number(summary.raw_total ?? 0).toLocaleString();
        document.getElementById('card-centers').textContent = Number(summary.center_count ?? 0).toLocaleString();
    }

    // Ensures at least 2 points so Chart.js can render a line/bar when all
    // records arrive in a single bucket (common during short live ingest runs).
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

    async function renderTotalChart() {
        const response = await fetch(api.hourly);
        const json = await response.json();
        const series = padHourlySeries(json.series);
        let cumulative = 0;
        const labels = series.map((point) => formatHourLabel(point.hour));
        const data = series.map((point) => {
            cumulative += Number(point.weighted ?? 0);

            return Number(cumulative.toFixed(4));
        });

        const options = baseOptions();
        options.plugins.legend.display = false;
        options.scales.x.ticks.callback = tickCallbackFactory(Math.max(1, Math.ceil(labels.length / 10)));

        if (totalChart) {
            totalChart.destroy();
        }

        totalChart = new Chart(document.getElementById('chart-total'), {
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

    async function renderHourlyChart() {
        const response = await fetch(api.hourly);
        const json = await response.json();
        const series = padHourlySeries(json.series);
        const labels = series.map((point) => formatHourLabel(point.hour));
        const data = series.map((point) => point.weighted);

        const options = baseOptions();
        options.plugins.legend.display = false;
        options.scales.x.ticks.maxRotation = 0;
        options.scales.x.ticks.minRotation = 0;
        options.scales.x.ticks.callback = tickCallbackFactory(Math.max(1, Math.ceil(labels.length / 8)));
        options.scales.y.beginAtZero = true;

        if (hourlyChart) {
            hourlyChart.destroy();
        }

        hourlyChart = new Chart(document.getElementById('chart-hourly'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Hourly total',
                    data,
                    backgroundColor: 'rgba(245, 158, 11, 0.8)',
                    borderColor: accentColor,
                    borderWidth: 1,
                    barPercentage: 0.82,
                    categoryPercentage: 0.92,
                    maxBarThickness: 26,
                }],
            },
            options,
        });
    }

    async function renderCenterChart() {
        const response = await fetch(api.byCenter);
        const json = await response.json();
        const paddedSeries = json.series.map((center) => ({
            ...center,
            points: padCenterPoints(center.points),
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

        if (centerChart) {
            centerChart.destroy();
        }

        centerChart = new Chart(document.getElementById('chart-by-center'), {
            type: 'line',
            data: { labels, datasets },
            options,
        });
    }

    async function loadAll() {
        await loadSummary();
        await Promise.all([
            renderTotalChart(),
            renderHourlyChart(),
            renderCenterChart(),
        ]);
    }

    loadAll();

    updateTimezoneToggleLabel();

    if (timezoneToggle) {
        timezoneToggle.addEventListener('change', async () => {
            useUtcTime = !timezoneToggle.checked;
            window.localStorage.setItem(timezoneStorageKey, useUtcTime ? 'utc' : 'local');
            updateTimezoneToggleLabel();
            await loadAll();
        });
    }

    if (isLive) {
        setInterval(loadAll, Number.isNaN(reloadMs) ? 900000 : reloadMs);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEventShowDashboard);
} else {
    initEventShowDashboard();
}
