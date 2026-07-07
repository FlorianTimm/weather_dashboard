import Chart from "chart.js/auto";
import { byId } from "./dom.js";
import { formatNumber } from "./format.js";

const windSectorLabels = ["N", "NO", "O", "SO", "S", "SW", "W", "NW"];

export function createWindRose() {
    let windRoseInstance = null;

    return function renderWindRose(chartData) {
        if (!chartData || !chartData.wind_dir) return;

        const isMobile = window.matchMedia("(max-width: 700px)").matches;
        const { sectorAverages, sectorCounts } = buildSectorAverages(chartData);
        const dominantIndex = sectorAverages.indexOf(Math.max(...sectorAverages));
        const validCount = sectorCounts.reduce((sum, count) => sum + count, 0);
        const summary = byId("wind-rose-summary");

        if (summary) {
            summary.innerText = validCount
                ? `Dominante Windrichtung: ${windSectorLabels[dominantIndex]} mit Ø ${formatNumber(sectorAverages[dominantIndex])} km/h Böen.`
                : "Für diesen Zeitraum liegen keine Windrichtungsdaten vor.";
        }

        const ctx = byId("windRoseChart")?.getContext("2d");
        if (!ctx) return;
        if (windRoseInstance) windRoseInstance.destroy();

        windRoseInstance = new Chart(ctx, {
            type: "polarArea",
            data: {
                labels: windSectorLabels,
                datasets: [
                    {
                        label: "Ø Windböen (km/h)",
                        data: sectorAverages,
                        backgroundColor: [
                            "#2c3e50",
                            "#1f77b4",
                            "#3498db",
                            "#2ecc71",
                            "#f1c40f",
                            "#e67e22",
                            "#e74c3c",
                            "#9b59b6",
                        ],
                        borderColor: "#ffffff",
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: !isMobile, position: "right" },
                    tooltip: {
                        callbacks: {
                            label: (context) =>
                                `${context.label}: ${formatNumber(context.raw)} km/h Ø Böen (${sectorCounts[context.dataIndex]} Messpunkte)`,
                        },
                    },
                },
                scales: {
                    r: { beginAtZero: true, ticks: { backdropColor: "transparent" } },
                },
            },
        });
    };
}

function buildSectorAverages(chartData) {
    const sectorTotals = new Array(windSectorLabels.length).fill(0);
    const sectorCounts = new Array(windSectorLabels.length).fill(0);

    chartData.wind_dir.forEach((direction, index) => {
        const windSpeed = Number(chartData.wind[index]);
        const degrees = Number(direction);
        if (!Number.isFinite(windSpeed) || !Number.isFinite(degrees)) return;

        const sectorIndex = Math.round((((degrees % 360) + 360) % 360) / 45) % windSectorLabels.length;
        sectorTotals[sectorIndex] += windSpeed;
        sectorCounts[sectorIndex] += 1;
    });

    return {
        sectorAverages: sectorTotals.map((total, index) =>
            sectorCounts[index] ? Number((total / sectorCounts[index]).toFixed(1)) : 0,
        ),
        sectorCounts,
    };
}
