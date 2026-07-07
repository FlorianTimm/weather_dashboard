import Chart from "chart.js/auto";
import { addDays, formatDateParam, formatTimeOnly } from "./format.js";
import { createWindRose } from "./wind-rose.js";

const defaultSeries = ["temp", "rain_rate", "dew_out", "solar_meas"];

const chartPresets = {
    klima: ["temp", "temp_in", "wind", "rain_rate", "traffic"],
    feuchte: ["dew_in", "dew_out", "hum_in"],
    solar: ["solar_theo", "solar_meas", "cloudiness"],
};

const chartSeries = {
    temp: createLineSeries("Temperatur Außen (°C)", "temp", "#e74c3c", "temperature", {
        backgroundColor: "rgba(231, 76, 60, 0.05)",
        fill: true,
        tension: 0.3,
    }),
    temp_in: createLineSeries("Temperatur Innen (°C)", "temp_in", "#d35400", "temperature", { tension: 0.3 }),
    wind: createLineSeries("Windböen (km/h)", "wind", "#3498db", "wind", { tension: 0.3 }),
    rain_rate: createLineSeries("Regenrate (mm/h)", "rain_rate", "#003ada", "rain", {
        backgroundColor: "rgba(26, 64, 188, 0.32)",
        fill: true,
        tension: 0.15,
    }),
    traffic: createLineSeries("Autobahn-Verkehrsfluss (%)", "traffic", "#2ecc71", "percent", {
        borderWidth: 1.5,
        borderDash: [2, 2],
        tension: 0.2,
    }),
    dew_in: createLineSeries("Taupunkt Innen (°C)", "dew_in", "#2c3e50", "temperature", {
        borderWidth: 2.5,
        tension: 0.2,
    }),
    dew_out: createLineSeries("Taupunkt Außen (°C)", "dew_out", "#9b59b6", "temperature", { tension: 0.2 }),
    hum_in: createLineSeries("Rel. Feuchte Innen (%)", "hum_in", "#f1c40f", "percent", {
        borderWidth: 1.5,
        borderDash: [4, 4],
    }),
    solar_theo: createLineSeries("Theoretische Einstrahlung (W/m²)", "solar_theo", "#e67e22", "solar", {
        borderWidth: 1.5,
        borderDash: [6, 4],
    }),
    solar_meas: createLineSeries("Gemessene Einstrahlung (W/m²)", "solar_meas", "#f1c40f", "solar", {
        backgroundColor: "rgba(241, 196, 15, 0.15)",
        borderWidth: 2.5,
        fill: true,
        tension: 0.2,
    }),
    cloudiness: createLineSeries("Berechneter Bewölkungsgrad (%)", "cloudiness", "#7f8c8d", "percent", {
        tension: 0.2,
    }),
};

const chartScales = {
    temperature: createScale("left", "Temperatur °C"),
    wind: createScale("right", "Wind km/h", { min: 0, suggestedMax: 70 }),
    rain: createScale("right", "Regen mm/h", { min: 0, suggestedMax: 20 }),
    percent: createScale("right", "Prozent %", { min: 0, max: 100 }),
    solar: createScale("right", "Solar W/m²", { min: 0, suggestedMax: 1000 }),
};

export function createWeatherChart() {
    let currentRange = "24h";
    let selectedChartDate = new Date();
    let activeSeries = new Set(defaultSeries);
    let chartInstance = null;
    let cachedChartData = null;
    const renderWindRose = createWindRose();

    async function loadChartData() {
        try {
            const response = await fetch(
                `api.php?action=chart&range=${currentRange}&date=${formatDateParam(selectedChartDate)}`,
            );
            cachedChartData = await response.json();
            syncChartDateControls();
            renderChart();
            renderWindRose(cachedChartData);
        } catch (error) {
            console.error("Diagramm-Fehler", error);
        }
    }

    function changeRange(range, btn) {
        document.querySelectorAll("#range-buttons .btn").forEach((button) => button.classList.remove("active"));
        btn.classList.add("active");
        currentRange = range;
        loadChartData();
    }

    function shiftChartDate(direction) {
        selectedChartDate = addDays(selectedChartDate, direction * getRangeDays());
        loadChartData();
    }

    function resetChartDate() {
        selectedChartDate = new Date();
        loadChartData();
    }

    function changeMode(mode, btn) {
        document.querySelectorAll("#mode-buttons .btn").forEach((button) => button.classList.remove("active"));
        btn.classList.add("active");
        activeSeries = new Set(chartPresets[mode]);
        syncSeriesButtons();
        renderChart();
    }

    function toggleSeries(seriesId, btn) {
        if (activeSeries.has(seriesId) && activeSeries.size > 1) {
            activeSeries.delete(seriesId);
        } else {
            activeSeries.add(seriesId);
        }

        btn.classList.toggle("active", activeSeries.has(seriesId));
        syncPresetButtons();
        renderChart();
    }

    function renderChart() {
        if (!cachedChartData) return;
        const ctx = document.getElementById("mainChart")?.getContext("2d");
        if (!ctx) return;
        if (chartInstance) chartInstance.destroy();

        const isMobile = window.matchMedia("(max-width: 700px)").matches;
        const selectedSeries = Array.from(activeSeries);
        const datasets = selectedSeries.map((seriesId) => ({
            ...chartSeries[seriesId],
            data: buildTimedData(chartSeries[seriesId].dataKey),
        }));

        chartInstance = new Chart(ctx, {
            type: "line",
            data: { labels: cachedChartData.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: {
                        display: !isMobile,
                        position: isMobile ? "bottom" : "top",
                        labels: {
                            boxWidth: isMobile ? 16 : 40,
                            font: { size: isMobile ? 11 : 12 },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            title: (items) => (items.length ? formatTooltipTime(items[0].parsed.x) : ""),
                        },
                    },
                },
                scales: buildScales(selectedSeries, isMobile),
            },
        });
    }

    function buildTimedData(dataKey) {
        return cachedChartData[dataKey].map((value, index) => ({
            x: cachedChartData.timestamps[index],
            y: value,
        }));
    }

    function getRangeDays() {
        if (currentRange === "7d") return 7;
        if (currentRange === "30d") return 30;
        return 1;
    }

    function syncChartDateControls() {
        const label = document.getElementById("chart-date-label");
        const nextButton = document.getElementById("chart-next-btn");
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selected = new Date(selectedChartDate);
        selected.setHours(0, 0, 0, 0);

        if (label) label.innerText = formatChartDateLabel(selected);
        if (nextButton) nextButton.disabled = selected >= today;
    }

    function formatChartDateLabel(selected) {
        const formatter = new Intl.DateTimeFormat("de-DE", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
        });

        if (currentRange === "24h") {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const yesterday = addDays(today, -1);

            if (selected.getTime() === today.getTime()) return `Heute, ${formatter.format(selected)}`;
            if (selected.getTime() === yesterday.getTime()) return `Gestern, ${formatter.format(selected)}`;
            return formatter.format(selected);
        }

        const startDate = addDays(selected, -(getRangeDays() - 1));
        return `${formatter.format(startDate)} bis ${formatter.format(selected)}`;
    }

    function formatTimeTick(value) {
        if (currentRange === "30d") {
            return new Date(value).toLocaleDateString("de-DE", { day: "2-digit", month: "2-digit" });
        }

        return formatTimeOnly(value);
    }

    function formatTooltipTime(value) {
        return new Date(value).toLocaleString("de-DE", {
            day: "2-digit",
            month: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    function buildScales(selectedSeries, isMobile) {
        return selectedSeries.reduce(
            (usedScales, seriesId) => {
                const axisId = chartSeries[seriesId].yAxisID;
                usedScales[axisId] = getResponsiveScale(chartScales[axisId], isMobile);
                return usedScales;
            },
            {
                x: {
                    type: "linear",
                    ticks: {
                        autoSkip: true,
                        maxRotation: isMobile ? 45 : 0,
                        maxTicksLimit: isMobile ? 8 : 12,
                        callback: (value) => formatTimeTick(value),
                    },
                },
            },
        );
    }

    return { loadChartData, changeRange, shiftChartDate, resetChartDate, changeMode, toggleSeries };
}

function createLineSeries(label, dataKey, borderColor, yAxisID, overrides = {}) {
    return {
        label,
        dataKey,
        borderColor,
        yAxisID,
        borderWidth: 2,
        pointRadius: 0,
        fill: false,
        ...overrides,
    };
}

function createScale(position, title, overrides = {}) {
    return {
        type: "linear",
        display: true,
        position,
        grid: position === "right" ? { drawOnChartArea: false } : undefined,
        title: { display: true, text: title },
        ...overrides,
    };
}

function getResponsiveScale(scale, isMobile) {
    return {
        ...scale,
        title: scale.title ? { ...scale.title, display: scale.title.display && !isMobile } : scale.title,
        ticks: {
            ...scale.ticks,
            font: { size: isMobile ? 10 : 12 },
            maxTicksLimit: isMobile ? 6 : 10,
        },
    };
}
