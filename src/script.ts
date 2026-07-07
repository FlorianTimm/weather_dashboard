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
});

function registerServiceWorker(): void {
    if (!("serviceWorker" in navigator)) return;

    navigator.serviceWorker
        .register("sw.js")
        .then((reg) => console.log("PWA ServiceWorker aktiv:", reg.scope))
        .catch((err: unknown) => console.error("ServiceWorker fehlgeschlagen:", err));
}

export {};
