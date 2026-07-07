import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { formatTimeOnly } from "./format.js";

const stationPosition = [53.4, 10.03];
const radarFrameInterval = 5 * 60 * 1000;
const radarFrameCount = 13;
const radarOpacity = 0.72;

export function initRainRadar() {
    const mapElement = document.getElementById("rainRadarMap");
    if (!mapElement) return;

    const controls = mapElement.parentElement?.querySelector(".radar-controls");
    const timeLabel = document.getElementById("radar-time-label");
    const playToggle = document.getElementById("radar-play-toggle");
    const frameSlider = document.getElementById("radar-time-slider");
    const loopIndicator = document.getElementById("radar-loop-indicator");
    const frameTimes = buildRadarFrameTimes();
    let activeFrame = frameTimes.length - 1;
    let isPlaying = true;
    let animationTimer = null;
    let loopFadeTimer = null;
    let loopNoticeTimer = null;

    const map = L.map(mapElement, {
        center: stationPosition,
        zoom: 9,
        scrollWheelZoom: false,
    });

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap-Mitwirkende",
        maxZoom: 18,
    }).addTo(map);

    const radarLayers = frameTimes.map((time, index) =>
        L.tileLayer.wms("https://maps.dwd.de/geoserver/dwd/ows", {
            layers: "Niederschlagsradar",
            format: "image/png",
            transparent: true,
            version: "1.3.0",
            time: time.toISOString(),
            opacity: index === activeFrame ? radarOpacity : 0,
            attribution: "&copy; Deutscher Wetterdienst",
        }),
    );

    radarLayers.forEach((layer) => layer.addTo(map));

    L.circleMarker(stationPosition, {
        radius: 6,
        color: "#ffffff",
        weight: 2,
        fillColor: "#e74c3c",
        fillOpacity: 1,
    })
        .addTo(map)
        .bindPopup("Wetterstation");

    setTimeout(() => map.invalidateSize(), 250);

    setupFrameSlider();
    setupPlayToggle();
    updateRadarFrame();
    startRadarAnimation();

    function setupFrameSlider() {
        if (!frameSlider) return;
        frameSlider.max = String(frameTimes.length - 1);
        frameSlider.value = String(activeFrame);
        frameSlider.addEventListener("input", () => {
            stopRadarAnimation();
            isPlaying = false;
            activeFrame = Number(frameSlider.value);
            syncPlayToggle();
            updateRadarFrame();
        });
    }

    function setupPlayToggle() {
        if (!playToggle) return;
        playToggle.addEventListener("click", () => {
            isPlaying = !isPlaying;
            syncPlayToggle();

            if (isPlaying) {
                startRadarAnimation();
            } else {
                stopRadarAnimation();
            }
        });
    }

    function startRadarAnimation() {
        stopRadarAnimation();
        animationTimer = setInterval(() => {
            const isLoopRestart = activeFrame === radarLayers.length - 1;
            activeFrame = (activeFrame + 1) % radarLayers.length;
            if (isLoopRestart) showLoopRestart();
            updateRadarFrame();
        }, 700);
    }

    function stopRadarAnimation() {
        if (animationTimer) clearInterval(animationTimer);
        animationTimer = null;
    }

    function updateRadarFrame() {
        radarLayers.forEach((layer, index) => {
            layer.setOpacity(index === activeFrame ? radarOpacity : 0);
        });

        if (frameSlider) frameSlider.value = String(activeFrame);
        if (timeLabel) timeLabel.innerText = `Radarzeit: ${formatTimeOnly(frameTimes[activeFrame])}`;
    }

    function syncPlayToggle() {
        if (!playToggle) return;
        playToggle.innerText = isPlaying ? "Pause" : "Abspielen";
        playToggle.classList.toggle("active", isPlaying);
    }

    function showLoopRestart() {
        restartClassAnimation(mapElement, "loop-restart-fade");
        if (loopFadeTimer) clearTimeout(loopFadeTimer);
        loopFadeTimer = setTimeout(() => {
            mapElement.classList.remove("loop-restart-fade");
        }, 1000);

        if (controls) restartClassAnimation(controls, "loop-restart");

        if (loopIndicator) {
            loopIndicator.innerText = "Loop startet neu";
            if (loopNoticeTimer) clearTimeout(loopNoticeTimer);
            loopNoticeTimer = setTimeout(() => {
                loopIndicator.innerText = "";
            }, 1200);
        }
    }
}

function buildRadarFrameTimes() {
    const latestFrame = new Date(Date.now() - 10 * 60 * 1000);
    latestFrame.setUTCSeconds(0, 0);
    latestFrame.setUTCMinutes(Math.floor(latestFrame.getUTCMinutes() / 5) * 5);

    return Array.from(
        { length: radarFrameCount },
        (_, index) => new Date(latestFrame.getTime() - (radarFrameCount - 1 - index) * radarFrameInterval),
    );
}

function restartClassAnimation(element, className) {
    element.classList.remove(className);
    void element.offsetWidth;
    element.classList.add(className);
}
