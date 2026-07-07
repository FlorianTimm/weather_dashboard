import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
    // --- PWA SERVICE WORKER REGISTRIERUNG ---
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('PWA ServiceWorker aktiv:', reg.scope))
            .catch(err => console.error('ServiceWorker fehlgeschlagen:', err));
    }

    // Globale Steuervariablen
    let currentRange = '24h';
    let activeSeries = new Set(['temp', 'wind', 'traffic']);
    let chartInstance = null;
    let windRoseInstance = null;
    let cachedChartData = null;

    const windSectorLabels = ['N', 'NO', 'O', 'SO', 'S', 'SW', 'W', 'NW'];

    const chartPresets = {
        klima: ['temp', 'wind', 'traffic'],
        feuchte: ['af_in', 'af_out', 'hum_in'],
        solar: ['solar_theo', 'solar_meas', 'cloudiness'],
    };

    const chartSeries = {
        temp: { label: 'Temperatur Außen (°C)', dataKey: 'temp', borderColor: '#e74c3c', backgroundColor: 'rgba(231, 76, 60, 0.05)', yAxisID: 'temperature', borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3 },
        wind: { label: 'Windböen (km/h)', dataKey: 'wind', borderColor: '#3498db', yAxisID: 'wind', borderWidth: 2, pointRadius: 0, fill: false, tension: 0.3 },
        traffic: { label: 'Autobahn-Verkehrsfluss (%)', dataKey: 'traffic', borderColor: '#2ecc71', yAxisID: 'percent', borderWidth: 1.5, pointRadius: 0, fill: false, borderDash: [2, 2], tension: 0.2 },
        af_in: { label: 'Abs. Feuchte Innen (g/m³)', dataKey: 'af_in', borderColor: '#2c3e50', yAxisID: 'humidityAbs', borderWidth: 2.5, pointRadius: 0, fill: false, tension: 0.2 },
        af_out: { label: 'Abs. Feuchte Außen (g/m³)', dataKey: 'af_out', borderColor: '#9b59b6', yAxisID: 'humidityAbs', borderWidth: 2, pointRadius: 0, fill: false, tension: 0.2 },
        hum_in: { label: 'Rel. Feuchte Innen (%)', dataKey: 'hum_in', borderColor: '#f1c40f', yAxisID: 'percent', borderWidth: 1.5, pointRadius: 0, fill: false, borderDash: [4, 4] },
        solar_theo: { label: 'Theoretische Einstrahlung (W/m²)', dataKey: 'solar_theo', borderColor: '#e67e22', yAxisID: 'solar', borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false },
        solar_meas: { label: 'Gemessene Einstrahlung (W/m²)', dataKey: 'solar_meas', borderColor: '#f1c40f', backgroundColor: 'rgba(241, 196, 15, 0.15)', yAxisID: 'solar', borderWidth: 2.5, pointRadius: 0, fill: true, tension: 0.2 },
        cloudiness: { label: 'Berechneter Bewölkungsgrad (%)', dataKey: 'cloudiness', borderColor: '#7f8c8d', yAxisID: 'percent', borderWidth: 2, pointRadius: 0, fill: false, tension: 0.2 },
    };

    const chartScales = {
        temperature: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Temperatur °C' } },
        wind: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Wind km/h' } },
        percent: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, min: 0, max: 100, title: { display: true, text: 'Prozent %' } },
        humidityAbs: { type: 'linear', display: true, position: 'left', grid: { drawOnChartArea: false }, title: { display: true, text: 'Abs. Feuchte g/m³' } },
        solar: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, min: 0, title: { display: true, text: 'Solar W/m²' } },
    };

    function bftCalc(ms) {
        if (ms < 0.3) return 0; if (ms < 1.6) return 1; if (ms < 3.4) return 2; if (ms < 5.5) return 3;
        if (ms < 8.0) return 4; if (ms < 10.8) return 5; return '6+';
    }

    async function updateLiveDashboard() {
        try {
            const response = await fetch('api.php?action=live');
            const data = await response.json();

            // Echtzeit-Klimadaten befüllen
            document.getElementById('live-time').innerText = new Date(data.current.zeitstempel).toLocaleTimeString('de-DE');
            document.getElementById('live-t-in').innerText = parseFloat(data.current.intem).toLocaleString('de-DE', { minimumFractionDigits: 1 });
            document.getElementById('live-t-in').style.color = (data.current.intem >= 20 && data.current.intem <= 22) ? '#2ecc71' : '#e67e22';
            document.getElementById('live-rh-in').innerText = data.current.inhum + " %";
            document.getElementById('live-t-out').innerText = parseFloat(data.current.t1tem).toLocaleString('de-DE', { minimumFractionDigits: 1 });
            document.getElementById('live-rh-out').innerText = data.current.t1hum + " %";
            document.getElementById('live-wdir').innerText = data.current.t1wdir + "°";
            document.getElementById('live-rain').innerText = parseFloat(data.current.t1raindy).toLocaleString('de-DE', { minimumFractionDigits: 1 });
            document.getElementById('live-rainrate').innerText = parseFloat(data.current.t1rainra).toLocaleString('de-DE', { minimumFractionDigits: 1 }) + " mm/h";
            document.getElementById('live-ws').innerText = data.calculated.wind_speed_kmh.toLocaleString('de-DE');
            document.getElementById('live-bft').innerText = bftCalc(data.current.t1ws) + " Bft (" + parseFloat(data.current.t1ws).toLocaleString('de-DE') + " m/s)";

            document.getElementById('live-in-status').innerText = data.current.inhum < 40 ? 'Zu trocken 🌵' : (data.current.inhum > 55 ? 'Feucht 💧' : 'Optimal OK');
            document.getElementById('live-in-status').style.color = (data.current.inhum >= 40 && data.current.inhum <= 55) ? '#2ecc71' : '#e67e22';

            // Ergänzung innerhalb der updateLiveDashboard() Funktion im JavaScript:
            document.getElementById('live-solar').innerText = parseFloat(data.current.t1solrad).toLocaleString('de-DE') + " W/m² (Soll: " + data.calculated.solar_theo + ")";
            document.getElementById('live-cloud').innerText = data.calculated.cloudiness + " %";

            // Allzeit-Historie rendern
            document.getElementById('rec-max-t').innerHTML = parseFloat(data.records.max_temp.val).toLocaleString('de-DE') + " °C <div class='sub-date'>am " + data.records.max_temp.date + "</div>";
            document.getElementById('rec-min-t').innerHTML = parseFloat(data.records.min_temp.val).toLocaleString('de-DE') + " °C <div class='sub-date'>am " + data.records.min_temp.date + "</div>";
            document.getElementById('rec-max-w').innerHTML = parseFloat(data.records.max_wind.val).toLocaleString('de-DE', { maximumFractionDigits: 1 }) + " km/h <div class='sub-date'>am " + data.records.max_wind.date + "</div>";
            document.getElementById('rec-max-r').innerHTML = parseFloat(data.records.max_rain.val).toLocaleString('de-DE') + " mm <div class='sub-date'>am " + data.records.max_rain.date + "</div>";

            // Sub-Systeme triggern
            renderKWL(data.current.intem, data.current.inhum, data.current.t1tem, data.calculated.af_in, data.calculated.af_out);
            renderNoise(data.calculated.laerm_index, data.current.t1wdir, data.calculated.wind_speed_kmh, data.calculated.traffic_flow, data.calculated.schall_leitung);
            renderDrone(data.current.t1wgust, data.current.t1ws10mav);

        } catch (e) { console.error("Live-Polling fehlgeschlagen", e); }
    }

    function renderKWL(tin, rhin, tout, afin, afout) {
        let stufe = 2, color = "#2cb67d", text = "Normalbetrieb. Das Raumklima bewegt sich im gewünschten Bereich.";
        if (tout > 23.5 || (tout > tin && afout > afin)) { stufe = 1; color = "#e67e22"; text = "Stufe 1 (Mindestlüftung). Draußen zu warm/schwül. Keine Hitze reinholen!"; }
        else if (tout < tin && tin > 22.0 && afout < afin) { stufe = 3; color = "#3498db"; text = "Stufe 3 (Intensivlüftung). Perfekt zum Abkühlen und Entfeuchten des Hauses!"; }
        else if (tout < 8.0 && (rhin < 42 || afin < 7.5 || tin < 20.0)) { stufe = 1; color = "#9b59b6"; text = "Stufe 1 (Frost-/Trockenschutz). Raumluft zu trocken (<42%) oder zu kalt. Lüftung minimieren!"; }
        else if (rhin > 58.0 && afout < afin) { stufe = 3; color = "#e74c3c"; text = "Stufe 3 (Intensivlüftung). Hohe Feuchte im Haus. Jetzt schnell ablüften!"; }

        document.getElementById('kwl-card').style.borderLeftColor = color;
        document.getElementById('kwl-badge').style.backgroundColor = color;
        document.getElementById('kwl-badge').innerText = "Empfehlung: Stufe " + stufe;
        document.getElementById('kwl-text').innerText = "👉 " + text;
        document.getElementById('live-af-in').innerText = afin.toLocaleString('de-DE') + " g/m³";
        document.getElementById('live-af-out').innerText = afout.toLocaleString('de-DE') + " g/m³";
        document.getElementById('live-kwl-trend').innerText = (afout < afin) ? "⬇️ Entfeuchtend" : "⬆️ Befeuchtend";
        document.getElementById('live-kwl-trend').style.color = (afout < afin) ? "#2ecc71" : "#e67e22";
    }

    // --- REAKTIVIERT: DAS AKUSTISCHE MODELL MIT FLUSSKOEFFIZIENT ---
    function renderNoise(index, wdir, speed, trafficFlow, schallLeitung) {
        let status = "Norddeutsche Gelassenheit", desc = "Kaum merkliches Rauschen der Autobahnen.", color = "#2ecc71";
        if (index >= 30 && index <= 60) { status = "Spürbares Hintergrundrauschen", desc = "Klassisches Autobahnbrummen im Garten wahrnehmbar.", color = "#f1c40f"; }
        else if (index > 60) { status = "Lautstärke-Maximum!", desc = "Der Wind steht ungünstig und trägt den Rollschall direkt zu dir.", color = "#e74c3c"; }

        document.getElementById('noise-card').style.borderLeftColor = color;
        document.getElementById('noise-badge').style.backgroundColor = color;
        document.getElementById('noise-badge').innerText = "Lärm-Index: " + index + " %";
        document.getElementById('noise-text').innerHTML = "🔊 <strong>" + status + "</strong> — " + desc;
        document.getElementById('noise-factors').innerHTML = `
            <strong>Analysedaten:</strong> 
            Verkehrsfluss-Koeffizient: <strong>${trafficFlow}%</strong> (Höher = Freier/Lauter) | 
            Schall-Ausbreitung: <strong>${schallLeitung}%</strong> (Wind-Vektor bei ${wdir}° / ${speed} km/h)
        `;
    }

    function renderDrone(gust, avg) {
        let bar = document.getElementById('drone-bar');
        let gustKmh = gust * 3.6, avgKmh = avg * 3.6;
        if (gust < 6.0 && avg < 5.0) { bar.innerText = '🟢 Perfekt zum Fliegen. Kaum Wind.'; bar.style.backgroundColor = '#c6f6d5'; bar.style.color = '#22543d'; }
        else if (gust <= 8.5 && avg <= 7.0) { bar.innerText = '🟠 Risiko! Spürbarer Wind/Böen. Mini 2 kämpft.'; bar.style.backgroundColor = '#feebc8'; bar.style.color = '#744210'; }
        else { bar.innerText = '🔴 Flugverbot! Zu starke Böen/Wind für die Mini 2.'; bar.style.backgroundColor = '#fed7d7'; bar.style.color = '#742a2a'; }

        document.getElementById('drone-details').innerHTML = `
            <div style="text-align:left; padding:0 15px;"><strong>Wind aktuell:</strong> ${gustKmh.toFixed(1)} km/h</div>
            <div style="text-align:left; padding:0 15px;"><strong>Ø Wind (10 Min):</strong> ${avgKmh.toFixed(1)} km/h</div>
        `;
    }

    async function loadChartData() {
        try {
            const response = await fetch(`api.php?action=chart&range=${currentRange}`);
            cachedChartData = await response.json();
            renderChart();
            renderWindRose();
        } catch (e) { console.error("Diagramm-Fehler", e); }
    }

    function renderChart() {
        if (!cachedChartData) return;
        const ctx = document.getElementById('mainChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();

        const selectedSeries = Array.from(activeSeries);
        const datasets = selectedSeries.map(seriesId => ({
            ...chartSeries[seriesId],
            data: cachedChartData[chartSeries[seriesId].dataKey],
        }));
        const scales = selectedSeries.reduce((usedScales, seriesId) => {
            const axisId = chartSeries[seriesId].yAxisID;
            usedScales[axisId] = chartScales[axisId];
            return usedScales;
        }, {});

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: { labels: cachedChartData.labels, datasets: datasets },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: scales }
        });
    }

    function renderWindRose() {
        if (!cachedChartData || !cachedChartData.wind_dir) return;

        const sectorTotals = new Array(windSectorLabels.length).fill(0);
        const sectorCounts = new Array(windSectorLabels.length).fill(0);

        cachedChartData.wind_dir.forEach((direction, index) => {
            const windSpeed = Number(cachedChartData.wind[index]);
            const degrees = Number(direction);
            if (!Number.isFinite(windSpeed) || !Number.isFinite(degrees)) return;

            const sectorIndex = Math.round((((degrees % 360) + 360) % 360) / 45) % windSectorLabels.length;
            sectorTotals[sectorIndex] += windSpeed;
            sectorCounts[sectorIndex] += 1;
        });

        const sectorAverages = sectorTotals.map((total, index) => sectorCounts[index] ? Number((total / sectorCounts[index]).toFixed(1)) : 0);
        const dominantIndex = sectorAverages.indexOf(Math.max(...sectorAverages));
        const validCount = sectorCounts.reduce((sum, count) => sum + count, 0);
        const summary = document.getElementById('wind-rose-summary');

        if (summary) {
            summary.innerText = validCount
                ? `Dominante Windrichtung: ${windSectorLabels[dominantIndex]} mit Ø ${sectorAverages[dominantIndex].toLocaleString('de-DE')} km/h Böen.`
                : 'Für diesen Zeitraum liegen keine Windrichtungsdaten vor.';
        }

        const ctx = document.getElementById('windRoseChart').getContext('2d');
        if (windRoseInstance) windRoseInstance.destroy();

        windRoseInstance = new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels: windSectorLabels,
                datasets: [{
                    label: 'Ø Windböen (km/h)',
                    data: sectorAverages,
                    backgroundColor: ['#2c3e50', '#1f77b4', '#3498db', '#2ecc71', '#f1c40f', '#e67e22', '#e74c3c', '#9b59b6'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: context => `${context.label}: ${context.raw.toLocaleString('de-DE')} km/h Ø Böen (${sectorCounts[context.dataIndex]} Messpunkte)`,
                        }
                    }
                },
                scales: {
                    r: { beginAtZero: true, ticks: { backdropColor: 'transparent' } }
                }
            }
        });
    }

    // --- BUTTON-FIX: EXKLUSIVE CONTAINER-IDS GEGEN DAS DAUER-BLAU ---
    window.changeRange = function (range, btn) {
        document.querySelectorAll('#range-buttons .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentRange = range;
        loadChartData();
    }

    window.changeMode = function (mode, btn) {
        document.querySelectorAll('#mode-buttons .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeSeries = new Set(chartPresets[mode]);
        syncSeriesButtons();
        renderChart();
    }

    window.toggleSeries = function (seriesId, btn) {
        if (activeSeries.has(seriesId) && activeSeries.size > 1) {
            activeSeries.delete(seriesId);
        } else {
            activeSeries.add(seriesId);
        }

        btn.classList.toggle('active', activeSeries.has(seriesId));
        syncPresetButtons();
        renderChart();
    }

    function syncSeriesButtons() {
        document.querySelectorAll('#series-buttons .btn').forEach(btn => {
            btn.classList.toggle('active', activeSeries.has(btn.dataset.series));
        });
    }

    function syncPresetButtons() {
        const selected = Array.from(activeSeries).sort().join('|');
        document.querySelectorAll('#mode-buttons .btn').forEach(btn => {
            const preset = chartPresets[btn.dataset.mode].slice().sort().join('|');
            btn.classList.toggle('active', selected === preset);
        });
    }

    // Initialisierungs-Lauf
    updateLiveDashboard();
    loadChartData();
    setInterval(updateLiveDashboard, 60000); // Exakt alle 60 Sekunden auffrischen
});