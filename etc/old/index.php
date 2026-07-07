<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
	<link rel="manifest" href="manifest.json">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wetter- & Umwelt-Leitstand</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; }
        h1, h2 { color: #2c3e50; text-align: center; }
        h2 { margin-top: 40px; border-bottom: 2px solid #bdc3c7; padding-bottom: 10px; text-align: left; }
        .system-status { text-align: center; font-size: 0.9em; color: #7f8c8d; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-top: 4px solid #3498db; }
        .card.warning { border-top-color: #e74c3c; } .card.success { border-top-color: #2ecc71; } .card.neutral { border-top-color: #95a5a6; } .card.home { border-top-color: #f1c40f; }
        .card h3 { margin: 0 0 15px 0; font-size: 1.1em; color: #7f8c8d; text-align: center; }
        .value-display { text-align: center; margin-bottom: 15px; }
        .main-value { font-size: 2.4em; font-weight: bold; color: #2c3e50; }
        .sub-date { block: display; font-size: 0.4em; color: #7f8c8d; font-weight: normal; margin-top: 2px; }
        .data-list { border-top: 1px solid #edf2f7; padding-top: 10px; margin-top: 10px; }
        .data-row { display: flex; justify-content: space-between; font-size: 0.95em; margin-bottom: 6px; color: #4a5568; }
        .data-row .label { color: #718096; } .data-row .val { font-weight: 600; }
        .special-box { background: #fff; padding: 20px; border-radius: 10px; border-left: 8px solid #cbd5e1; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .special-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .special-title { font-size: 1.2em; font-weight: bold; color: #2c3e50; }
        .special-badge { padding: 6px 14px; border-radius: 20px; color: #fff; font-weight: bold; }
        .drone-section { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 30px; text-align: center; }
        .drone-status-bar { padding: 15px; border-radius: 8px; font-size: 1.2em; font-weight: bold; margin: 15px 0; }
        .chart-container { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 40px; }
        .btn-group { display: flex; justify-content: center; gap: 10px; margin-bottom: 15px; }
        .btn { background: #e2e8f0; border: none; padding: 8px 16px; font-weight: 600; border-radius: 6px; cursor: pointer; color: #4a5568; transition: background 0.2s; }
        .btn.active { background: #3498db; color: #fff; }
    </style>
</head>
<body>

<div class="container">
    <h1>🌤️ Wohnraum-, Wetter- & Umweltcenter</h1>
    <div class="system-status">Datenzeitstempel: <strong id="live-time">-</strong> | Status: <span style="color:#2ecc71; font-weight:bold;">● Live-Verbindung aktiv</span></div>

    <!-- HELIOS KWL LÜFTUNGSASSISTENT -->
    <div id="kwl-card" class="special-box">
        <div class="special-header">
            <div class="special-title">🏠 Helios ZEB EC Lüftungsassistent</div>
            <div id="kwl-badge" class="special-badge">-</div>
        </div>
        <p id="kwl-text" style="font-size: 1.05em; margin: 10px 0; font-weight:500;"></p>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin: 10px 0 0 0; gap: 10px;">
            <div style="background:#f8fafc; padding:8px; border-radius:6px; text-align:center;"><span style="color:#718096; font-size:0.85em;">Abs. Feuchte Innen</span><br><strong id="live-af-in">-</strong></div>
            <div style="background:#f8fafc; padding:8px; border-radius:6px; text-align:center;"><span style="color:#718096; font-size:0.85em;">Abs. Feuchte Außen</span><br><strong id="live-af-out">-</strong></div>
            <div style="background:#f8fafc; padding:8px; border-radius:6px; text-align:center;"><span style="color:#718096; font-size:0.85em;">Lüftungs-Effekt</span><br><strong id="live-kwl-trend">-</strong></div>
        </div>
    </div>

    <!-- AKUSTISCHES GARTEN-BAROMETER -->
    <div id="noise-card" class="special-box">
        <div class="special-header">
            <div class="special-title">🤫 Akustisches Garten-Barometer (Schallprognose A1 / A7)</div>
            <div id="noise-badge" class="special-badge">-</div>
        </div>
        <p id="noise-text" style="font-size: 1.05em; margin: 10px 0;"></p>
        <div id="noise-factors" style="font-size:0.85em; color:#718096;"></div>
    </div>

    <!-- LIVE CLUSTER -->
    <h2>Echtzeit-Klimadaten</h2>
    <div class="grid">
        <div class="card home">
            <h3>🏠 Innenraum (Soll: 20-22°C)</h3>
            <div class="value-display"><span id="live-t-in" class="main-value">-</span><span style="font-size:1.2em; font-weight:bold;"> °C</span></div>
            <div class="data-list">
                <div class="data-row"><span class="label">Relative Feuchte:</span><span id="live-rh-in" class="val">-</span></div>
                <div class="data-row"><span class="label">Zustand:</span><span id="live-in-status" class="val">-</span></div>
            </div>
        </div>
        <div class="card warning">
            <h3>🌡️ Außenklima</h3>
            <div class="value-display"><span id="live-t-out" class="main-value">-</span><span style="font-size:1.2em; font-weight:bold;"> °C</span></div>
            <div class="data-list">
                <div class="data-row"><span class="label">Relative Feuchte:</span><span id="live-rh-out" class="val">-</span></div>
                <div class="data-row"><span class="label">Windrichtung:</span><span id="live-wdir" class="val">-</span></div>
            </div>
        </div>
        <div class="card success">
            <h3>💧 Niederschlag</h3>
            <div class="value-display"><span id="live-rain" class="main-value">-</span><span style="font-size:1.2em; font-weight:bold;"> mm</span></div>
            <div class="data-list">
                <div class="data-row"><span class="label">Regenrate aktuell:</span><span id="live-rainrate" class="val">-</span></div>
            </div>
        </div>
        <div class="card neutral">
            <h3>💨 Windgeschwindigkeiten</h3>
            <div class="value-display"><span id="live-ws" class="main-value">-</span><span style="font-size:1.0em; font-weight:bold;"> km/h</span></div>
            <div class="data-list">
                <div class="data-row"><span class="label">Windstärke:</span><span id="live-bft" class="val">-</span></div>
            </div>
        </div>
    </div>

    <!-- HISTORISCHE EXTREME MIT DATUM -->
    <h2>🏆 Historische Allzeit-Rekorde (Wann war das?)</h2>
    <div class="grid">
        <div class="card warning"><h3>Heißester Tag</h3><div class="value-display"><span id="rec-max-t" class="main-value">- <div class="sub-date">-</div></span></div></div>
        <div class="card" style="border-top-color:#3498db;"><h3>Kältester Tag</h3><div class="value-display"><span id="rec-min-t" class="main-value">- <div class="sub-date">-</div></span></div></div>
        <div class="card"><h3>Stärkster Sturm</h3><div class="value-display"><span id="rec-max-w" class="main-value">- <div class="sub-date">-</div></span></div></div>
        <div class="card success"><h3>Meister Regen (Tag)</h3><div class="value-display"><span id="rec-max-r" class="main-value">- <div class="sub-date">-</div></span></div></div>
    </div>

    <!-- DJI FLUGCHECK -->
    <div class="drone-section">
        <h3 style="margin:0; color:#2c3e50;">🎮 DJI Mini 2 Flugwetter-Check</h3>
        <div id="drone-bar" class="drone-status-bar">-</div>
        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 0; gap:15px;" id="drone-details"></div>
    </div>
<!-- DIAGRAMME MIT KORRIGIERTEN BUTTON-GRUPPEN -->
    <h2>📉 Analyse & Verlaufsdiagramme</h2>
    <div class="btn-group" id="range-buttons">
        <button class="btn active" onclick="changeRange('24h', this)">24 Stunden</button>
        <button class="btn" onclick="changeRange('7d', this)">7 Tage</button>
        <button class="btn" onclick="changeRange('30d', this)">30 Tage</button>
    </div>
<div class="btn-group" id="mode-buttons">
        <button class="btn active" onclick="changeMode('klima', this)">Klima & Wind</button>
        <button class="btn" onclick="changeMode('feuchte', this)">Feuchtigkeits-Trend (Absolut/Relativ)</button>
        <button class="btn" onclick="changeMode('solar', this)">☀️ Solar & Bewölkung</button>
    </div>
    <div class="chart-container">
        <canvas id="mainChart"></canvas>
    </div>
</div>

<script>
    // --- NEU: SERVICE WORKER REGISTRIERUNG FÜR PWA ---
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('PWA ServiceWorker erfolgreich registriert!', reg.scope))
                .catch(err => console.log('ServiceWorker Fehlgeschlagen', err));
        });
    }

    let currentRange = '24h';
    let currentMode = 'klima';
    let chartInstance = null;
    let cachedChartData = null;

    function bftCalc(ms) {
        if (ms < 0.3) return 0; if (ms < 1.6) return 1; if (ms < 3.4) return 2; if (ms < 5.5) return 3;
        if (ms < 8.0) return 4; if (ms < 10.8) return 5; return '6+';
    }

    async function updateLiveDashboard() {
        try {
            const response = await fetch('api.php?action=live');
            const data = await response.json();
            
            document.getElementById('live-time').innerText = new Date(data.current.zeitstempel).toLocaleTimeString('de-DE');
            document.getElementById('live-t-in').innerText = parseFloat(data.current.intem).toLocaleString('de-DE', {minimumFractionDigits: 1});
            document.getElementById('live-t-in').style.color = (data.current.intem >= 20 && data.current.intem <= 22) ? '#2ecc71' : '#e67e22';
            document.getElementById('live-rh-in').innerText = data.current.inhum + " %";
            document.getElementById('live-t-out').innerText = parseFloat(data.current.t1tem).toLocaleString('de-DE', {minimumFractionDigits: 1});
            document.getElementById('live-rh-out').innerText = data.current.t1hum + " %";
            document.getElementById('live-wdir').innerText = data.current.t1wdir + "°";
            document.getElementById('live-rain').innerText = parseFloat(data.current.t1raindy).toLocaleString('de-DE', {minimumFractionDigits: 1});
            document.getElementById('live-rainrate').innerText = parseFloat(data.current.t1rainra).toLocaleString('de-DE', {minimumFractionDigits: 1}) + " mm/h";
            document.getElementById('live-ws').innerText = data.calculated.wind_speed_kmh.toLocaleString('de-DE');
            document.getElementById('live-bft').innerText = bftCalc(data.current.t1ws) + " Bft (" + parseFloat(data.current.t1ws).toLocaleString('de-DE') + " m/s)";
            
            document.getElementById('live-in-status').innerText = data.current.inhum < 40 ? 'Zu trocken 🌵' : (data.current.inhum > 55 ? 'Feucht 💧' : 'Optimal OK');
            document.getElementById('live-in-status').style.color = (data.current.inhum >= 40 && data.current.inhum <= 55) ? '#2ecc71' : '#e67e22';

            document.getElementById('rec-max-t').innerHTML = parseFloat(data.records.max_temp.val).toLocaleString('de-DE') + " °C <div class='sub-date'>am " + data.records.max_temp.date + "</div>";
            document.getElementById('rec-min-t').innerHTML = parseFloat(data.records.min_temp.val).toLocaleString('de-DE') + " °C <div class='sub-date'>am " + data.records.min_temp.date + "</div>";
            document.getElementById('rec-max-w').innerHTML = parseFloat(data.records.max_wind.val).toLocaleString('de-DE', {maximumFractionDigits:1}) + " km/h <div class='sub-date'>am " + data.records.max_wind.date + "</div>";
            document.getElementById('rec-max-r').innerHTML = parseFloat(data.records.max_rain.val).toLocaleString('de-DE') + " mm <div class='sub-date'>am " + data.records.max_rain.date + "</div>";

            renderKWL(data.current.intem, data.current.inhum, data.current.t1tem, data.calculated.af_in, data.calculated.af_out);
            renderNoise(data.calculated.laerm_index, data.current.t1wdir, data.calculated.wind_speed_kmh, data.calculated.laerm_index);
            renderDrone(data.current.t1wgust, data.current.t1ws10mav);

        } catch(e) { console.error("Live-Polling fehlgeschlagen", e); }
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

    function renderNoise(index, wdir, speed, traffic) {
        let status = "🟡 Spürbares Hintergrundrauschen", desc = "Deutliches Autobahnbrummen im Garten wahrnehmbar.", color = "#f1c40f";
        if (index < 30) { status = "🟢 Ruhige Oase"; desc = "Kaum Lärm. Der Wind drückt den Schall weg oder der Verkehr ruht."; color = "#2ecc71"; }
        else if (index > 60) { status = "🔴 Lautstärke-Maximum! (Autobahn dröhnt)"; desc = "Der Wind steht voll auf Südost und trägt den brutalen Rollschall direkt in deinen Garten."; color = "#e74c3c"; }
        
        document.getElementById('noise-card').style.borderLeftColor = color;
        document.getElementById('noise-badge').style.backgroundColor = color;
        document.getElementById('noise-badge').innerText = "Index: " + index + " %";
        document.getElementById('noise-text').innerHTML = "🔊 <strong>" + status + "</strong> — " + desc;
        document.getElementById('noise-factors').innerHTML = "<strong>Faktoren:</strong> Wind aus " + wdir + "° | Windkraft: " + speed.toLocaleString('de-DE') + " km/h";
    }

    function renderDrone(gust, avg) {
        let bar = document.getElementById('drone-bar');
        let gustKmh = gust * 3.6, avgKmh = avg * 3.6;
        if (gust < 6.0 && avg < 5.0) { bar.innerText = '🟢 Perfekt zum Fliegen. Kaum Wind.'; bar.style.backgroundColor='#c6f6d5'; bar.style.color='#22543d'; }
        else if (gust <= 8.5 && avg <= 7.0) { bar.innerText = '🟠 Risiko! Spürbarer Wind/Böen. Mini 2 kämpft.'; bar.style.backgroundColor='#feebc8'; bar.style.color='#744210'; }
        else { bar.innerText = '🔴 Flugverbot! Zu starke Böen/Wind für die Mini 2.'; bar.style.backgroundColor='#fed7d7'; bar.style.color='#742a2a'; }
        
        document.getElementById('drone-details').innerHTML = `
            <div style="text-align:left; padding:0 15px;"><strong>Wind aktuell:</strong> `+gustKmh.toFixed(1)+` km/h</div>
            <div style="text-align:left; padding:0 15px;"><strong>Ø Wind (10 Min):</strong> `+avgKmh.toFixed(1)+` km/h</div>
        `;
    }

    async function loadChartData() {
        try {
            const response = await fetch('api.php?action=chart&range=' + currentRange);
            cachedChartData = await response.json();
            renderChart();
        } catch(e) { console.error("Diagramm-Fehler", e); }
    }

    function renderChart() {
        if (!cachedChartData) return;
        const ctx = document.getElementById('mainChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();

        let datasets = [];
        let scales = { y: { type: 'linear', display: true, position: 'left' } };

        if (currentMode === 'klima') {
            datasets = [
                { label: 'Temperatur Außen (°C)', data: cachedChartData.temp, borderColor: '#e74c3c', backgroundColor: 'rgba(231, 76, 60, 0.05)', yAxisID: 'y', borderWidth: 2, pointRadius: 0, fill: true, tension: 0.3 },
                { label: 'Windböen (km/h)', data: cachedChartData.wind, borderColor: '#3498db', yAxisID: 'y1', borderWidth: 2, pointRadius: 0, fill: false, tension: 0.3 }
            ];
            scales.y1 = { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } };
            
        } else if (currentMode === 'feuchte') {
            datasets = [
                { label: 'Abs. Feuchte Innen (g/m³)', data: cachedChartData.af_in, borderColor: '#2c3e50', borderWidth: 2.5, pointRadius: 0, fill: false, tension: 0.2 },
                { label: 'Abs. Feuchte Außen (g/m³)', data: cachedChartData.af_out, borderColor: '#9b59b6', borderWidth: 2, pointRadius: 0, fill: false, tension: 0.2 },
                { label: 'Rel. Feuchte Innen (%)', data: cachedChartData.hum_in, borderColor: '#f1c40f', yAxisID: 'y1', borderWidth: 1.5, pointRadius: 0, fill: false, borderDash: [4,4] }
            ];
            scales.y1 = { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, min: 20, max: 100 };
            
        } else if (currentMode === 'solar') {
            // --- NEU: SOLAR & BEWÖLKUNGS DIAGRAMM ---
            datasets = [
                { label: 'Theoretische Einstrahlung (W/m²)', data: cachedChartData.solar_theo, borderColor: '#e67e22', borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false },
                { label: 'Gemessene Einstrahlung (W/m²)', data: cachedChartData.solar_meas, borderColor: '#f1c40f', backgroundColor: 'rgba(241, 196, 15, 0.15)', borderWidth: 2.5, pointRadius: 0, fill: true, tension: 0.2 },
                { label: 'Berechneter Bewölkungsgrad (%)', data: cachedChartData.cloudiness, borderColor: '#7f8c8d', yAxisID: 'y1', borderWidth: 2, pointRadius: 0, fill: false, spanGaps: false, tension: 0.2 }
            ];
            scales.y.title = { display: true, text: 'Einstrahlung (W/m²)' };
            scales.y1 = { type: 'linear', display: true, position: 'right', min: 0, max: 100, title: { display: true, text: 'Bewölkung (%)' }, grid: { drawOnChartArea: false } };
        }

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: { labels: cachedChartData.labels, datasets: datasets },
            options: { responsive: true, interaction: { mode: 'index', intersect: false }, scales: scales }
        });
    }

    // --- FIX: BUTTONS ENTFÄRBEN ÜBER GEZIELTE CONTAINER-IDS ---
    function changeRange(range, btn) {
        document.querySelectorAll('#range-buttons .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentRange = range;
        loadChartData();
    }

    function changeMode(mode, btn) {
        document.querySelectorAll('#mode-buttons .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentMode = mode;
        renderChart();
    }

    // INITIALISIERUNG & NEUER 60-SEKUNDEN-TAKT
    updateLiveDashboard();
    loadChartData();
    setInterval(updateLiveDashboard, 60000); // Exakt alle 60 Sekunden auffrischen
</script>
</body>
</html>