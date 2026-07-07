import { initLiveDashboard } from "./js/live-dashboard.js";
import { initRainRadar } from "./js/rain-radar.js";
import { createWeatherChart } from "./js/weather-chart.js";

document.addEventListener("DOMContentLoaded", () => {
    registerServiceWorker();

    const weatherChart = createWeatherChart();
    window.changeRange = weatherChart.changeRange;
    window.shiftChartDate = weatherChart.shiftChartDate;
    window.resetChartDate = weatherChart.resetChartDate;
    window.changeMode = weatherChart.changeMode;
    window.toggleSeries = weatherChart.toggleSeries;

    initRainRadar();
    initLiveDashboard();
    weatherChart.loadChartData();
});

function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return;

    navigator.serviceWorker
        .register("sw.js")
        .then((reg) => console.log("PWA ServiceWorker aktiv:", reg.scope))
        .catch((err) => console.error("ServiceWorker fehlgeschlagen:", err));
}
