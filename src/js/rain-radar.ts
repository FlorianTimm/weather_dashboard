import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { byId } from "./dom";
import { formatTimeOnly } from "./format";

const stationPosition: L.LatLngTuple = [53.4, 10.03];
const radarFrameInterval = 5 * 60 * 1000;
const radarFrameCount = 13;
const radarOpacity = 0.72;

interface TimedWmsOptions extends L.WMSOptions {
    time: string;
}

export class RainRadar {
    private readonly mapElement = byId<HTMLDivElement>("rainRadarMap");
    private readonly controls = this.mapElement?.parentElement?.querySelector<HTMLElement>(".radar-controls") ?? null;
    private readonly timeLabel = byId<HTMLSpanElement>("radar-time-label");
    private readonly playToggle = byId<HTMLButtonElement>("radar-play-toggle");
    private readonly frameSlider = byId<HTMLInputElement>("radar-time-slider");
    private readonly loopIndicator = byId<HTMLSpanElement>("radar-loop-indicator");
    private readonly frameTimes = this.buildRadarFrameTimes();
    private activeFrame = this.frameTimes.length - 1;
    private isPlaying = true;
    private map: L.Map | null = null;
    private radarLayers: L.TileLayer.WMS[] = [];
    private animationTimer: number | null = null;
    private loopFadeTimer: number | null = null;
    private loopNoticeTimer: number | null = null;

    init(): void {
        if (!this.mapElement) return;

        this.map = L.map(this.mapElement, {
            center: stationPosition,
            zoom: 9,
            scrollWheelZoom: false,
        });

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; OpenStreetMap-Mitwirkende",
            maxZoom: 18,
        }).addTo(this.map);

        this.radarLayers = this.frameTimes.map((time, index) => {
            const options: TimedWmsOptions = {
                layers: "Niederschlagsradar",
                format: "image/png",
                transparent: true,
                version: "1.3.0",
                time: time.toISOString(),
                opacity: index === this.activeFrame ? radarOpacity : 0,
                attribution: "&copy; Deutscher Wetterdienst",
            };

            return L.tileLayer.wms("https://maps.dwd.de/geoserver/dwd/ows", options);
        });

        this.radarLayers.forEach((layer) => {
            if (this.map) layer.addTo(this.map);
        });
        this.addStationMarker();

        window.setTimeout(() => this.map?.invalidateSize(), 250);

        this.setupFrameSlider();
        this.setupPlayToggle();
        this.updateRadarFrame();
        this.startRadarAnimation();
    }

    private addStationMarker(): void {
        if (!this.map) return;

        L.circleMarker(stationPosition, {
            radius: 6,
            color: "#ffffff",
            weight: 2,
            fillColor: "#e74c3c",
            fillOpacity: 1,
        })
            .addTo(this.map)
            .bindPopup("Wetterstation");
    }

    private setupFrameSlider(): void {
        if (!this.frameSlider) return;

        this.frameSlider.max = String(this.frameTimes.length - 1);
        this.frameSlider.value = String(this.activeFrame);
        this.frameSlider.addEventListener("input", () => {
            this.stopRadarAnimation();
            this.isPlaying = false;
            this.activeFrame = Number(this.frameSlider?.value ?? 0);
            this.syncPlayToggle();
            this.updateRadarFrame();
        });
    }

    private setupPlayToggle(): void {
        if (!this.playToggle) return;

        this.playToggle.addEventListener("click", () => {
            this.isPlaying = !this.isPlaying;
            this.syncPlayToggle();

            if (this.isPlaying) {
                this.startRadarAnimation();
            } else {
                this.stopRadarAnimation();
            }
        });
    }

    private startRadarAnimation(): void {
        this.stopRadarAnimation();
        this.animationTimer = window.setInterval(() => {
            const isLoopRestart = this.activeFrame === this.radarLayers.length - 1;
            this.activeFrame = (this.activeFrame + 1) % this.radarLayers.length;
            if (isLoopRestart) this.showLoopRestart();
            this.updateRadarFrame();
        }, 700);
    }

    private stopRadarAnimation(): void {
        if (this.animationTimer) window.clearInterval(this.animationTimer);
        this.animationTimer = null;
    }

    private updateRadarFrame(): void {
        this.radarLayers.forEach((layer, index) => {
            layer.setOpacity(index === this.activeFrame ? radarOpacity : 0);
        });

        if (this.frameSlider) this.frameSlider.value = String(this.activeFrame);
        if (this.timeLabel) this.timeLabel.innerText = `Radarzeit: ${formatTimeOnly(this.frameTimes[this.activeFrame])}`;
    }

    private syncPlayToggle(): void {
        if (!this.playToggle) return;

        this.playToggle.innerText = this.isPlaying ? "Pause" : "Abspielen";
        this.playToggle.classList.toggle("active", this.isPlaying);
    }

    private showLoopRestart(): void {
        if (!this.mapElement) return;

        this.restartClassAnimation(this.mapElement, "loop-restart-fade");
        if (this.loopFadeTimer) window.clearTimeout(this.loopFadeTimer);
        this.loopFadeTimer = window.setTimeout(() => {
            this.mapElement?.classList.remove("loop-restart-fade");
        }, 1000);

        if (this.controls) this.restartClassAnimation(this.controls, "loop-restart");

        if (this.loopIndicator) {
            this.loopIndicator.innerText = "Loop startet neu";
            if (this.loopNoticeTimer) window.clearTimeout(this.loopNoticeTimer);
            this.loopNoticeTimer = window.setTimeout(() => {
                if (this.loopIndicator) this.loopIndicator.innerText = "";
            }, 1200);
        }
    }

    private buildRadarFrameTimes(): Date[] {
        const latestFrame = new Date(Date.now() - 10 * 60 * 1000);
        latestFrame.setUTCSeconds(0, 0);
        latestFrame.setUTCMinutes(Math.floor(latestFrame.getUTCMinutes() / 5) * 5);

        return Array.from(
            { length: radarFrameCount },
            (_, index) => new Date(latestFrame.getTime() - (radarFrameCount - 1 - index) * radarFrameInterval),
        );
    }

    private restartClassAnimation(element: HTMLElement, className: string): void {
        element.classList.remove(className);
        void element.offsetWidth;
        element.classList.add(className);
    }
}
