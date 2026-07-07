import Chart from "chart.js/auto";
import type { Chart as ChartInstance, ChartDataset, TooltipItem } from "chart.js";
import { addDays, formatDateParam, formatTimeOnly } from "./format";
import type { AxisId, ChartDataKey, ChartPayload, RangeId, SeriesId } from "./types";
import { WindRoseChart } from "./wind-rose";

type PresetId = "klima" | "feuchte" | "solar";
type LineDataset = ChartDataset<"line", Array<{ x: number; y: number | null }>> & {
    dataKey: ChartDataKey;
    yAxisID: AxisId;
};
type AppScaleOptions = {
    type: "linear";
    display?: boolean;
    position?: "left" | "right";
    grid?: { drawOnChartArea: boolean };
    min?: number;
    max?: number;
    suggestedMax?: number;
    title?: { display: boolean; text: string };
    ticks?: Record<string, unknown>;
};

const defaultSeries: SeriesId[] = ["temp", "rain_rate", "dew_out", "solar_meas"];

const chartPresets: Record<PresetId, SeriesId[]> = {
    klima: ["temp", "rain_rate", "dew_out", "solar_meas"],
    feuchte: ["dew_in", "dew_out", "hum_in"],
    solar: ["solar_theo", "solar_meas", "cloudiness"],
};

const chartSeries: Record<SeriesId, LineDataset> = {
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
    traffic_flow: createLineSeries("Autobahn-Verkehrsfluss (%)", "traffic_flow", "#2ecc71", "percent", {
        borderWidth: 1.5,
        borderDash: [2, 2],
        tension: 0.2,
    }),
    traffic_noise: createLineSeries("Verkehrslautstärke (%)", "traffic_noise", "#e74c3c", "percent", {
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

const chartScales: Record<AxisId, AppScaleOptions> = {
    temperature: createScale("left", "Temperatur °C"),
    wind: createScale("right", "Wind km/h", { min: 0, suggestedMax: 70 }),
    rain: createScale("right", "Regen mm/h", { min: 0, suggestedMax: 20 }),
    percent: createScale("right", "Prozent %", { min: 0, max: 100 }),
    solar: createScale("right", "Solar W/m²", { min: 0, suggestedMax: 1000 }),
};

export class WeatherChart {
    private currentRange: RangeId = "24h";
    private selectedChartDate = new Date();
    private activeSeries = new Set<SeriesId>(defaultSeries);
    private chart: ChartInstance<"line", Array<{ x: number; y: number | null }>, string> | null = null;
    private cachedChartData: ChartPayload | null = null;
    private readonly windRose = new WindRoseChart();

    async loadChartData(): Promise<void> {
        try {
            const response = await fetch(
                `api.php?action=chart&range=${this.currentRange}&date=${formatDateParam(this.selectedChartDate)}`,
            );
            this.cachedChartData = (await response.json()) as ChartPayload;
            this.syncSeriesButtons();
            this.syncPresetButtons();
            this.syncChartDateControls();
            this.renderChart();
            this.windRose.render(this.cachedChartData);
        } catch (error) {
            console.error("Diagramm-Fehler", error);
        }
    }

    changeRange(range: RangeId, btn: HTMLElement): void {
        this.currentRange = range;
        this.syncRangeButtons(btn);
        void this.loadChartData();
    }

    shiftChartDate(direction: number): void {
        this.selectedChartDate = addDays(this.selectedChartDate, direction * this.getRangeDays());
        void this.loadChartData();
    }

    resetChartDate(): void {
        this.selectedChartDate = new Date();
        void this.loadChartData();
    }

    changeMode(mode: string, btn: HTMLElement): void {
        if (!this.isPresetId(mode)) return;

        this.activeSeries = new Set(chartPresets[mode]);
        this.syncPresetButtons();
        this.syncSeriesButtons();
        this.renderChart();
    }

    toggleSeries(seriesId: SeriesId, btn: HTMLElement): void {
        if (this.activeSeries.has(seriesId) && this.activeSeries.size > 1) {
            this.activeSeries.delete(seriesId);
        } else {
            this.activeSeries.add(seriesId);
        }

        const isActive = this.activeSeries.has(seriesId);
        btn.classList.toggle("active", isActive);
        btn.setAttribute("aria-pressed", String(isActive));
        this.syncPresetButtons();
        this.renderChart();
    }

    private renderChart(): void {
        if (!this.cachedChartData) return;
        const canvas = document.getElementById("mainChart");
        const ctx = canvas instanceof HTMLCanvasElement ? canvas.getContext("2d") : null;
        if (!ctx) return;

        this.chart?.destroy();
        const isMobile = window.matchMedia("(max-width: 700px)").matches;
        const selectedSeries = Array.from(this.activeSeries);
        const datasets = selectedSeries.map((seriesId) => ({
            ...chartSeries[seriesId],
            data: this.buildTimedData(chartSeries[seriesId].dataKey),
        }));

        this.chart = new Chart<"line", Array<{ x: number; y: number | null }>, string>(ctx, {
            type: "line",
            data: { labels: this.cachedChartData.labels, datasets },
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
                            title: (items: TooltipItem<"line">[]) => {
                                const xValue = items[0]?.parsed.x;
                                return typeof xValue === "number" ? this.formatTooltipTime(xValue) : "";
                            },
                        },
                    },
                },
                scales: this.buildScales(selectedSeries, isMobile),
            },
        });
    }

    private buildTimedData(dataKey: ChartDataKey): Array<{ x: number; y: number | null }> {
        if (!this.cachedChartData) return [];

        return this.cachedChartData[dataKey].map((value, index) => ({
            x: this.cachedChartData?.timestamps[index] ?? 0,
            y: value,
        }));
    }

    private getRangeDays(): number {
        if (this.currentRange === "7d") return 7;
        if (this.currentRange === "30d") return 30;
        return 1;
    }

    private syncChartDateControls(): void {
        const label = document.getElementById("chart-date-label");
        const nextButton = document.getElementById("chart-next-btn") as HTMLButtonElement | null;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selected = new Date(this.selectedChartDate);
        selected.setHours(0, 0, 0, 0);

        if (label) label.innerText = this.formatChartDateLabel(selected);
        if (nextButton) nextButton.disabled = selected >= today;
    }

    private formatChartDateLabel(selected: Date): string {
        const formatter = new Intl.DateTimeFormat("de-DE", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
        });

        if (this.currentRange === "24h") {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const yesterday = addDays(today, -1);

            if (selected.getTime() === today.getTime()) return `Heute, ${formatter.format(selected)}`;
            if (selected.getTime() === yesterday.getTime()) return `Gestern, ${formatter.format(selected)}`;
            return formatter.format(selected);
        }

        const startDate = addDays(selected, -(this.getRangeDays() - 1));
        return `${formatter.format(startDate)} bis ${formatter.format(selected)}`;
    }

    private formatTimeTick(value: number | string): string {
        if (this.currentRange === "30d") {
            return new Date(value).toLocaleDateString("de-DE", { day: "2-digit", month: "2-digit" });
        }

        return formatTimeOnly(value);
    }

    private formatTooltipTime(value: number): string {
        return new Date(value).toLocaleString("de-DE", {
            day: "2-digit",
            month: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    private buildScales(selectedSeries: SeriesId[], isMobile: boolean): Record<string, AppScaleOptions> {
        return selectedSeries.reduce<Record<string, AppScaleOptions>>(
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
                        callback: (value: string | number) => this.formatTimeTick(value),
                    },
                } as AppScaleOptions,
            },
        );
    }

    private syncSeriesButtons(): void {
        document.querySelectorAll<HTMLButtonElement>("#series-buttons .btn").forEach((btn) => {
            const isActive = this.activeSeries.has(btn.dataset.series as SeriesId);
            btn.classList.toggle("active", isActive);
            btn.setAttribute("aria-pressed", String(isActive));
        });
    }

    private syncPresetButtons(): void {
        const selected = Array.from(this.activeSeries).sort().join("|");
        document.querySelectorAll<HTMLButtonElement>("#mode-buttons .btn").forEach((btn) => {
            const mode = btn.dataset.mode;
            const preset = this.isPresetId(mode) ? chartPresets[mode].slice().sort().join("|") : "";
            const isActive = selected === preset;
            btn.classList.toggle("active", isActive);
            btn.setAttribute("aria-pressed", String(isActive));
        });
    }

    private syncRangeButtons(clickedBtn?: HTMLElement): void {
        document.querySelectorAll<HTMLButtonElement>("#range-buttons .btn").forEach((btn) => {
            const isActive = clickedBtn ? btn === clickedBtn : btn.dataset.range === this.currentRange;
            btn.classList.toggle("active", isActive);
            btn.setAttribute("aria-pressed", String(isActive));
        });
    }

    private isPresetId(value: string | undefined): value is PresetId {
        return value === "klima" || value === "feuchte" || value === "solar";
    }
}

function createLineSeries(
    label: string,
    dataKey: ChartDataKey,
    borderColor: string,
    yAxisID: AxisId,
    overrides: Partial<LineDataset> = {},
): LineDataset {
    return {
        label,
        dataKey,
        borderColor,
        yAxisID,
        borderWidth: 2,
        pointRadius: 0,
        fill: false,
        data: [],
        ...overrides,
    };
}

function createScale(position: "left" | "right", title: string, overrides: Partial<AppScaleOptions> = {}): AppScaleOptions {
    return {
        type: "linear",
        display: true,
        position,
        grid: position === "right" ? { drawOnChartArea: false } : undefined,
        title: { display: true, text: title },
        ...overrides,
    };
}

function getResponsiveScale(scale: AppScaleOptions, isMobile: boolean): AppScaleOptions {
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
