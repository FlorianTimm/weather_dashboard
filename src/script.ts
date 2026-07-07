import { LiveDashboard } from "./js/live-dashboard";
import { RainRadar } from "./js/rain-radar";
import { WeatherChart } from "./js/weather-chart";
import type { RangeId, SeriesId } from "./js/types";

declare global {
    interface Window {
        changeRange: (range: RangeId, btn: HTMLElement) => void;
        shiftChartDate: (direction: number) => void;
        resetChartDate: () => void;
        changeMode: (mode: string, btn: HTMLElement) => void;
        toggleSeries: (seriesId: SeriesId, btn: HTMLElement) => void;
    }
}

document.addEventListener("DOMContentLoaded", () => {
    registerServiceWorker();

    const weatherChart = new WeatherChart();
    window.changeRange = weatherChart.changeRange.bind(weatherChart);
    window.shiftChartDate = weatherChart.shiftChartDate.bind(weatherChart);
    window.resetChartDate = weatherChart.resetChartDate.bind(weatherChart);
    window.changeMode = weatherChart.changeMode.bind(weatherChart);
    window.toggleSeries = weatherChart.toggleSeries.bind(weatherChart);

    new RainRadar().init();
    new LiveDashboard().init();
    void weatherChart.loadChartData();
    registerUIHandlers();
});

function registerUIHandlers(): void {
    // Range buttons
    document.querySelectorAll('#range-buttons [data-range]').forEach((el) => {
        const btn = el as HTMLElement;
        btn.addEventListener('click', () => {
            const range = btn.dataset.range as RangeId | undefined;
            if (range && window.changeRange) window.changeRange(range, btn);
            document.querySelectorAll('#range-buttons .btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    // Chart date navigation (shift)
    document.querySelectorAll('[data-shift]').forEach((el) => {
        const btn = el as HTMLElement;
        btn.addEventListener('click', () => {
            const v = btn.dataset.shift;
            if (!v) return;
            const dir = Number(v);
            if (window.shiftChartDate) window.shiftChartDate(dir);
        });
    });

    // Reset (Heute)
    document.querySelectorAll('[data-reset]').forEach((el) => {
        const btn = el as HTMLElement;
        btn.addEventListener('click', () => {
            if (window.resetChartDate) window.resetChartDate();
        });
    });

    // Mode buttons
    document.querySelectorAll('#mode-buttons [data-mode]').forEach((el) => {
        const btn = el as HTMLElement;
        btn.addEventListener('click', () => {
            const mode = btn.dataset.mode;
            if (mode && window.changeMode) window.changeMode(mode, btn);
            document.querySelectorAll('#mode-buttons .preset-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    // Series toggle buttons
    document.querySelectorAll('#series-buttons [data-series]').forEach((el) => {
        const btn = el as HTMLElement;
        btn.addEventListener('click', () => {
            const series = btn.dataset.series as SeriesId | undefined;
            if (series && window.toggleSeries) window.toggleSeries(series, btn);
            btn.classList.toggle('active');
        });
    });
}

function registerServiceWorker(): void {
    if (!("serviceWorker" in navigator)) return;

    navigator.serviceWorker
        .register("sw.js")
        .then((reg) => console.log("PWA ServiceWorker aktiv:", reg.scope))
        .catch((err: unknown) => console.error("ServiceWorker fehlgeschlagen:", err));
}

export {};
