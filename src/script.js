import Chart from "chart.js/auto";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

document.addEventListener("DOMContentLoaded", () => {
    // --- PWA SERVICE WORKER REGISTRIERUNG ---
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker
            .register("sw.js")
            .then((reg) => console.log("PWA ServiceWorker aktiv:", reg.scope))
            .catch((err) => console.error("ServiceWorker fehlgeschlagen:", err));
    }

    // Globale Steuervariablen
    let currentRange = "24h";
    let selectedChartDate = new Date();
    let activeSeries = new Set([
        "temp",
        "rain_rate",
        "dew_out",
        "solar_meas"
    ]);
    let chartInstance = null;
    let windRoseInstance = null;
    let cachedChartData = null;

    const windSectorLabels = ["N", "NO", "O", "SO", "S", "SW", "W", "NW"];
    const stationPosition = [53.4, 10.03];

    const chartPresets = {
        klima: ["temp", "temp_in", "wind", "rain_rate", "traffic"],
        feuchte: ["dew_in", "dew_out", "hum_in"],
        solar: ["solar_theo", "solar_meas", "cloudiness"],
    };

    const chartSeries = {
        temp: {
            label: "Temperatur Außen (°C)",
            dataKey: "temp",
            borderColor: "#e74c3c",
            backgroundColor: "rgba(231, 76, 60, 0.05)",
            yAxisID: "temperature",
            borderWidth: 2,
            pointRadius: 0,
            fill: true,
            tension: 0.3,
        },
        temp_in: {
            label: "Temperatur Innen (°C)",
            dataKey: "temp_in",
            borderColor: "#d35400",
            yAxisID: "temperature",
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
            tension: 0.3,
        },
        wind: {
            label: "Windböen (km/h)",
            dataKey: "wind",
            borderColor: "#3498db",
            yAxisID: "wind",
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
            tension: 0.3,
        },
        rain_rate: {
            label: "Regenrate (mm/h)",
            dataKey: "rain_rate",
            borderColor: "#003ada",
            backgroundColor: "rgba(26, 64, 188, 0.32)",
            yAxisID: "rain",
            borderWidth: 2,
            pointRadius: 0,
            fill: true,
            tension: 0.15,
        },
        traffic: {
            label: "Autobahn-Verkehrsfluss (%)",
            dataKey: "traffic",
            borderColor: "#2ecc71",
            yAxisID: "percent",
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
            borderDash: [2, 2],
            tension: 0.2,
        },
        dew_in: {
            label: "Taupunkt Innen (°C)",
            dataKey: "dew_in",
            borderColor: "#2c3e50",
            yAxisID: "temperature",
            borderWidth: 2.5,
            pointRadius: 0,
            fill: false,
            tension: 0.2,
        },
        dew_out: {
            label: "Taupunkt Außen (°C)",
            dataKey: "dew_out",
            borderColor: "#9b59b6",
            yAxisID: "temperature",
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
            tension: 0.2,
        },
        hum_in: {
            label: "Rel. Feuchte Innen (%)",
            dataKey: "hum_in",
            borderColor: "#f1c40f",
            yAxisID: "percent",
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
            borderDash: [4, 4],
        },
        solar_theo: {
            label: "Theoretische Einstrahlung (W/m²)",
            dataKey: "solar_theo",
            borderColor: "#e67e22",
            yAxisID: "solar",
            borderDash: [6, 4],
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
        },
        solar_meas: {
            label: "Gemessene Einstrahlung (W/m²)",
            dataKey: "solar_meas",
            borderColor: "#f1c40f",
            backgroundColor: "rgba(241, 196, 15, 0.15)",
            yAxisID: "solar",
            borderWidth: 2.5,
            pointRadius: 0,
            fill: true,
            tension: 0.2,
        },
        cloudiness: {
            label: "Berechneter Bewölkungsgrad (%)",
            dataKey: "cloudiness",
            borderColor: "#7f8c8d",
            yAxisID: "percent",
            borderWidth: 2,
            pointRadius: 0,
            fill: false,
            tension: 0.2,
        },
    };

    const chartScales = {
        temperature: {
            type: "linear",
            display: true,
            position: "left",
            title: { display: true, text: "Temperatur °C" },
        },
        wind: {
            type: "linear",
            display: true,
            position: "right",
            min: 0,
            suggestedMax: 70,
            grid: { drawOnChartArea: false },
            title: { display: true, text: "Wind km/h" },
        },
        rain: {
            type: "linear",
            display: true,
            position: "right",
            grid: { drawOnChartArea: false },
            min: 0,
            suggestedMax: 20,
            title: { display: true, text: "Regen mm/h" },
        },
        percent: {
            type: "linear",
            display: true,
            position: "right",
            grid: { drawOnChartArea: false },
            min: 0,
            max: 100,
            title: { display: true, text: "Prozent %" },
        },
        solar: {
            type: "linear",
            display: true,
            position: "right",
            grid: { drawOnChartArea: false },
            min: 0,
            suggestedMax: 1000,
            title: { display: true, text: "Solar W/m²" },
        },
    };

    function bftCalc(ms) {
        if (ms < 0.3) return 0;
        if (ms < 1.6) return 1;
        if (ms < 3.4) return 2;
        if (ms < 5.5) return 3;
        if (ms < 8.0) return 4;
        if (ms < 10.8) return 5;
        return "6+";
    }

    async function updateLiveDashboard() {
        try {
            const response = await fetch("api.php?action=live");
            const data = await response.json();

            // Echtzeit-Klimadaten befüllen
            document.getElementById("live-time").innerText = new Date(
                data.current.zeitstempel,
            ).toLocaleTimeString("de-DE");
            document.getElementById("live-t-in").innerText = parseFloat(
                data.current.intem,
            ).toLocaleString("de-DE", { minimumFractionDigits: 1 });
            document.getElementById("live-t-in").style.color =
                data.current.intem >= 20 && data.current.intem <= 22
                    ? "#2ecc71"
                    : "#e67e22";
            document.getElementById("live-rh-in").innerText =
                data.current.inhum + " %";
            document.getElementById("live-t-out").innerText = parseFloat(
                data.current.t1tem,
            ).toLocaleString("de-DE", { minimumFractionDigits: 1 });
            document.getElementById("live-rh-out").innerText =
                data.current.t1hum + " %";
            document.getElementById("live-wdir").innerText =
                data.current.t1wdir + "°";
            document.getElementById("live-rain").innerText = parseFloat(
                data.current.t1raindy,
            ).toLocaleString("de-DE", { minimumFractionDigits: 1 });
            document.getElementById("live-rainrate").innerText =
                parseFloat(data.current.t1rainra).toLocaleString("de-DE", {
                    minimumFractionDigits: 1,
                }) + " mm/h";
            document.getElementById("live-ws").innerText =
                data.calculated.wind_speed_kmh.toLocaleString("de-DE");
            document.getElementById("live-bft").innerText =
                bftCalc(data.current.t1ws) +
                " Bft (" +
                parseFloat(data.current.t1ws).toLocaleString("de-DE") +
                " m/s)";

            document.getElementById("live-in-status").innerText =
                data.current.inhum < 40
                    ? "Zu trocken 🌵"
                    : data.current.inhum > 55
                        ? "Feucht 💧"
                        : "Optimal OK";
            document.getElementById("live-in-status").style.color =
                data.current.inhum >= 40 && data.current.inhum <= 55
                    ? "#2ecc71"
                    : "#e67e22";

            // Ergänzung innerhalb der updateLiveDashboard() Funktion im JavaScript:
            document.getElementById("live-solar").innerText =
                parseFloat(data.current.t1solrad).toLocaleString("de-DE") +
                " W/m² (Soll: " +
                data.calculated.solar_theo +
                ")";
            document.getElementById("live-cloud").innerText =
                data.calculated.cloudiness + " %";

            // Allzeit-Historie rendern
            document.getElementById("rec-max-t").innerHTML =
                parseFloat(data.records.max_temp.val).toLocaleString("de-DE") +
                " °C <div class='sub-date'>am " +
                data.records.max_temp.date +
                "</div>";
            document.getElementById("rec-min-t").innerHTML =
                parseFloat(data.records.min_temp.val).toLocaleString("de-DE") +
                " °C <div class='sub-date'>am " +
                data.records.min_temp.date +
                "</div>";
            document.getElementById("rec-max-w").innerHTML =
                parseFloat(data.records.max_wind.val).toLocaleString("de-DE", {
                    maximumFractionDigits: 1,
                }) +
                " km/h <div class='sub-date'>am " +
                data.records.max_wind.date +
                "</div>";
            document.getElementById("rec-max-r").innerHTML =
                parseFloat(data.records.max_rain.val).toLocaleString("de-DE") +
                " mm <div class='sub-date'>am " +
                data.records.max_rain.date +
                "</div>";

            // Sub-Systeme triggern
            renderKWL(
                data.current.intem,
                data.current.inhum,
                data.current.t1tem,
                data.calculated.af_in,
                data.calculated.af_out,
            );
            renderNoise(
                data.calculated.laerm_index,
                data.current.t1wdir,
                data.calculated.wind_speed_kmh,
                data.calculated.traffic_flow,
                data.calculated.schall_leitung,
            );
            renderDrone(data.current.t1wgust, data.current.t1ws10mav);
        } catch (e) {
            console.error("Live-Polling fehlgeschlagen", e);
        }
    }

    function renderKWL(tin, rhin, tout, afin, afout) {
        let stufe = 2,
            color = "#2cb67d",
            text = "Normalbetrieb. Das Raumklima bewegt sich im gewünschten Bereich.";
        if (tout > 23.5 || (tout > tin && afout > afin)) {
            stufe = 1;
            color = "#e67e22";
            text =
                "Stufe 1 (Mindestlüftung). Draußen zu warm/schwül. Keine Hitze reinholen!";
        } else if (tout < tin && tin > 22.0 && afout < afin) {
            stufe = 3;
            color = "#3498db";
            text =
                "Stufe 3 (Intensivlüftung). Perfekt zum Abkühlen und Entfeuchten des Hauses!";
        } else if (tout < 8.0 && (rhin < 42 || afin < 7.5 || tin < 20.0)) {
            stufe = 1;
            color = "#9b59b6";
            text =
                "Stufe 1 (Frost-/Trockenschutz). Raumluft zu trocken (<42%) oder zu kalt. Lüftung minimieren!";
        } else if (rhin > 58.0 && afout < afin) {
            stufe = 3;
            color = "#e74c3c";
            text =
                "Stufe 3 (Intensivlüftung). Hohe Feuchte im Haus. Jetzt schnell ablüften!";
        }

        document.getElementById("kwl-card").style.borderLeftColor = color;
        document.getElementById("kwl-badge").style.backgroundColor = color;
        document.getElementById("kwl-badge").innerText =
            "Empfehlung: Stufe " + stufe;
        document.getElementById("kwl-text").innerText = "👉 " + text;
        document.getElementById("live-af-in").innerText =
            afin.toLocaleString("de-DE") + " g/m³";
        document.getElementById("live-af-out").innerText =
            afout.toLocaleString("de-DE") + " g/m³";
        document.getElementById("live-kwl-trend").innerText =
            afout < afin ? "⬇️ Entfeuchtend" : "⬆️ Befeuchtend";
        document.getElementById("live-kwl-trend").style.color =
            afout < afin ? "#2ecc71" : "#e67e22";
    }

    // --- REAKTIVIERT: DAS AKUSTISCHE MODELL MIT FLUSSKOEFFIZIENT ---
    function renderNoise(index, wdir, speed, trafficFlow, schallLeitung) {
        let status = "Norddeutsche Gelassenheit",
            desc = "Kaum merkliches Rauschen der Autobahnen.",
            color = "#2ecc71";
        if (index >= 30 && index <= 60) {
            ((status = "Spürbares Hintergrundrauschen"),
                (desc = "Klassisches Autobahnbrummen im Garten wahrnehmbar."),
                (color = "#f1c40f"));
        } else if (index > 60) {
            ((status = "Lautstärke-Maximum!"),
                (desc =
                    "Der Wind steht ungünstig und trägt den Rollschall direkt zu dir."),
                (color = "#e74c3c"));
        }

        document.getElementById("noise-card").style.borderLeftColor = color;
        document.getElementById("noise-badge").style.backgroundColor = color;
        document.getElementById("noise-badge").innerText =
            "Lärm-Index: " + index + " %";
        document.getElementById("noise-text").innerHTML =
            "🔊 <strong>" + status + "</strong> — " + desc;
        document.getElementById("noise-factors").innerHTML = `
            <strong>Analysedaten:</strong> 
            Verkehrsfluss-Koeffizient: <strong>${trafficFlow}%</strong> (Höher = Freier/Lauter) | 
            Schall-Ausbreitung: <strong>${schallLeitung}%</strong> (Wind-Vektor bei ${wdir}° / ${speed} km/h)
        `;
    }

    function renderDrone(gust, avg) {
        let bar = document.getElementById("drone-bar");
        let gustKmh = gust * 3.6,
            avgKmh = avg * 3.6;
        if (gust < 6.0 && avg < 5.0) {
            bar.innerText = "🟢 Perfekt zum Fliegen. Kaum Wind.";
            bar.style.backgroundColor = "#c6f6d5";
            bar.style.color = "#22543d";
        } else if (gust <= 8.5 && avg <= 7.0) {
            bar.innerText = "🟠 Risiko! Spürbarer Wind/Böen. Mini 2 kämpft.";
            bar.style.backgroundColor = "#feebc8";
            bar.style.color = "#744210";
        } else {
            bar.innerText = "🔴 Flugverbot! Zu starke Böen/Wind für die Mini 2.";
            bar.style.backgroundColor = "#fed7d7";
            bar.style.color = "#742a2a";
        }

        document.getElementById("drone-details").innerHTML = `
            <div style="text-align:left; padding:0 15px;"><strong>Wind aktuell:</strong> ${gustKmh.toFixed(1)} km/h</div>
            <div style="text-align:left; padding:0 15px;"><strong>Ø Wind (10 Min):</strong> ${avgKmh.toFixed(1)} km/h</div>
        `;
    }

    async function loadChartData() {
        try {
            const response = await fetch(
                `api.php?action=chart&range=${currentRange}&date=${formatDateParam(selectedChartDate)}`,
            );
            cachedChartData = await response.json();
            syncChartDateControls();
            renderChart();
            renderWindRose();
        } catch (e) {
            console.error("Diagramm-Fehler", e);
        }
    }

    function formatDateParam(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    }

    function addDays(date, days) {
        const nextDate = new Date(date);
        nextDate.setDate(nextDate.getDate() + days);
        return nextDate;
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

        if (label) {
            label.innerText = formatChartDateLabel(selected);
        }

        if (nextButton) {
            nextButton.disabled = selected >= today;
        }
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

            if (selected.getTime() === today.getTime())
                return `Heute, ${formatter.format(selected)}`;
            if (selected.getTime() === yesterday.getTime())
                return `Gestern, ${formatter.format(selected)}`;
            return formatter.format(selected);
        }

        const startDate = addDays(selected, -(getRangeDays() - 1));
        return `${formatter.format(startDate)} bis ${formatter.format(selected)}`;
    }

    function renderChart() {
        if (!cachedChartData) return;
        const ctx = document.getElementById("mainChart").getContext("2d");
        if (chartInstance) chartInstance.destroy();

        const isMobile = window.matchMedia("(max-width: 700px)").matches;
        const selectedSeries = Array.from(activeSeries);
        const datasets = selectedSeries.map((seriesId) => ({
            ...chartSeries[seriesId],
            data: buildTimedData(chartSeries[seriesId].dataKey),
        }));
        const scales = selectedSeries.reduce(
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

        chartInstance = new Chart(ctx, {
            type: "line",
            data: { labels: cachedChartData.labels, datasets: datasets },
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
                            title: (items) => items.length ? formatTooltipTime(items[0].parsed.x) : "",
                        },
                    },
                },
                scales: scales,
            },
        });
    }

    function buildTimedData(dataKey) {
        return cachedChartData[dataKey].map((value, index) => ({
            x: cachedChartData.timestamps[index],
            y: value,
        }));
    }

    function formatTimeTick(value) {
        const date = new Date(value);
        if (currentRange === "30d") {
            return date.toLocaleDateString("de-DE", { day: "2-digit", month: "2-digit" });
        }

        return date.toLocaleTimeString("de-DE", { hour: "2-digit", minute: "2-digit" });
    }

    function formatTooltipTime(value) {
        const date = new Date(value);
        return date.toLocaleString("de-DE", {
            day: "2-digit",
            month: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    function getResponsiveScale(scale, isMobile) {
        return {
            ...scale,
            title: scale.title
                ? { ...scale.title, display: scale.title.display && !isMobile }
                : scale.title,
            ticks: {
                ...scale.ticks,
                font: { size: isMobile ? 10 : 12 },
                maxTicksLimit: isMobile ? 6 : 10,
            },
        };
    }

    function renderWindRose() {
        if (!cachedChartData || !cachedChartData.wind_dir) return;

        const isMobile = window.matchMedia("(max-width: 700px)").matches;

        const sectorTotals = new Array(windSectorLabels.length).fill(0);
        const sectorCounts = new Array(windSectorLabels.length).fill(0);

        cachedChartData.wind_dir.forEach((direction, index) => {
            const windSpeed = Number(cachedChartData.wind[index]);
            const degrees = Number(direction);
            if (!Number.isFinite(windSpeed) || !Number.isFinite(degrees)) return;

            const sectorIndex =
                Math.round((((degrees % 360) + 360) % 360) / 45) %
                windSectorLabels.length;
            sectorTotals[sectorIndex] += windSpeed;
            sectorCounts[sectorIndex] += 1;
        });

        const sectorAverages = sectorTotals.map((total, index) =>
            sectorCounts[index]
                ? Number((total / sectorCounts[index]).toFixed(1))
                : 0,
        );
        const dominantIndex = sectorAverages.indexOf(Math.max(...sectorAverages));
        const validCount = sectorCounts.reduce((sum, count) => sum + count, 0);
        const summary = document.getElementById("wind-rose-summary");

        if (summary) {
            summary.innerText = validCount
                ? `Dominante Windrichtung: ${windSectorLabels[dominantIndex]} mit Ø ${sectorAverages[dominantIndex].toLocaleString("de-DE")} km/h Böen.`
                : "Für diesen Zeitraum liegen keine Windrichtungsdaten vor.";
        }

        const ctx = document.getElementById("windRoseChart").getContext("2d");
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
                                `${context.label}: ${context.raw.toLocaleString("de-DE")} km/h Ø Böen (${sectorCounts[context.dataIndex]} Messpunkte)`,
                        },
                    },
                },
                scales: {
                    r: { beginAtZero: true, ticks: { backdropColor: "transparent" } },
                },
            },
        });
    }

    function initRainRadar() {
        const mapElement = document.getElementById("rainRadarMap");
        if (!mapElement) return;

        const map = L.map(mapElement, {
            center: stationPosition,
            zoom: 9,
            scrollWheelZoom: false,
        });

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; OpenStreetMap-Mitwirkende",
            maxZoom: 18,
        }).addTo(map);

        L.tileLayer
            .wms("https://maps.dwd.de/geoserver/dwd/ows", {
                layers: "Niederschlagsradar",
                format: "image/png",
                transparent: true,
                version: "1.3.0",
                opacity: 0.72,
                attribution: "&copy; Deutscher Wetterdienst",
            })
            .addTo(map);

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
    }

    // --- BUTTON-FIX: EXKLUSIVE CONTAINER-IDS GEGEN DAS DAUER-BLAU ---
    window.changeRange = function (range, btn) {
        document
            .querySelectorAll("#range-buttons .btn")
            .forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        currentRange = range;
        loadChartData();
    };

    window.shiftChartDate = function (direction) {
        selectedChartDate = addDays(selectedChartDate, direction * getRangeDays());
        loadChartData();
    };

    window.resetChartDate = function () {
        selectedChartDate = new Date();
        loadChartData();
    };

    window.changeMode = function (mode, btn) {
        document
            .querySelectorAll("#mode-buttons .btn")
            .forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        activeSeries = new Set(chartPresets[mode]);
        syncSeriesButtons();
        renderChart();
    };

    window.toggleSeries = function (seriesId, btn) {
        if (activeSeries.has(seriesId) && activeSeries.size > 1) {
            activeSeries.delete(seriesId);
        } else {
            activeSeries.add(seriesId);
        }

        btn.classList.toggle("active", activeSeries.has(seriesId));
        syncPresetButtons();
        renderChart();
    };

    function syncSeriesButtons() {
        document.querySelectorAll("#series-buttons .btn").forEach((btn) => {
            btn.classList.toggle("active", activeSeries.has(btn.dataset.series));
        });
    }

    function syncPresetButtons() {
        const selected = Array.from(activeSeries).sort().join("|");
        document.querySelectorAll("#mode-buttons .btn").forEach((btn) => {
            const preset = chartPresets[btn.dataset.mode].slice().sort().join("|");
            btn.classList.toggle("active", selected === preset);
        });
    }

    // Initialisierungs-Lauf
    initRainRadar();
    updateLiveDashboard();
    loadChartData();
    setInterval(updateLiveDashboard, 60000); // Exakt alle 60 Sekunden auffrischen
});
