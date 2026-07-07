<?php
require_once 'db.inc.php';

// --- HILFSFUNKTIONEN ---
function msToKmh($ms)
{
    return $ms * 3.6;
}

function msToBft($ms)
{
    if ($ms < 0.3) return 0;
    if ($ms < 1.6) return 1;
    if ($ms < 3.4) return 2;
    if ($ms < 5.5) return 3;
    if ($ms < 8.0) return 4;
    if ($ms < 10.8) return 5;
    if ($ms < 13.9) return 6;
    if ($ms < 17.2) return 7;
    if ($ms < 20.8) return 8;
    if ($ms < 24.5) return 9;
    if ($ms < 28.5) return 10;
    if ($ms < 32.7) return 11;
    return 12;
}

// Berechnung der absoluten Feuchte in g/m³ (Magnus-Formel)
function calculateAbsoluteHumidity($temp, $rh)
{
    $es = 6.112 * exp((17.67 * $temp) / ($temp + 243.5)); // Sättigungsdampfdruck in hPa
    $e = $es * ($rh / 100); // Tatsächlicher Dampfdruck in hPa
    return (216.7 * $e) / ($temp + 273.15); // Absolute Feuchte in g/m³
}

try {
    $pdo = getDBConnection();

    // 1. Aktuelles Wetter
    $stmtCurrent = $pdo->query("SELECT * FROM wetterdaten ORDER BY id DESC LIMIT 1");
    $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    // 2. Tages-Statistiken (Live von heute)
    $stmtStats = $pdo->query("
        SELECT 
            MIN(t1tem) as min_temp, MAX(t1tem) as max_temp,
            MAX(t1wgust) as max_wind,
            MAX(t1raindy) as regen_heute,
            MAX(t1rainra) as max_regenrate,
            MIN(rbar) as min_druck, MAX(rbar) as max_druck,
            MAX(t1uvi) as max_uv
        FROM wetterdaten 
        WHERE DATE(zeitstempel) = CURDATE()
    ");
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // 3. Allzeit-Rekorde
    $stmtRecords = $pdo->query("
        SELECT MAX(max_t1tem) as rec_max_temp, MIN(min_t1tem) as rec_min_temp,
               MAX(max_t1wgust) as rec_max_wind, MAX(regen_gesamt) as rec_max_regen
        FROM wetter_tagesstatistik
    ");
    $records = $stmtRecords->fetch(PDO::FETCH_ASSOC);

    // 4. Daten für das Diagramm (die letzten 24 Stunden)
    $stmtChart = $pdo->query("SELECT zeitstempel, t1tem, t1wgust FROM wetterdaten WHERE zeitstempel >= NOW() - INTERVAL 1 DAY ORDER BY zeitstempel ASC");
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $temperatures = [];
    $windgustsKmh = [];
    foreach ($chartData as $row) {
        $labels[] = date('H:i', strtotime($row['zeitstempel']));
        $temperatures[] = $row['t1tem'];
        $windgustsKmh[] = round(msToKmh($row['t1wgust']), 1);
    }
} catch (PDOException $e) {
    die("Datenbankfehler: " . $e->getMessage());
}

// --- LOGIK FÜR DIE HELIOS KWL STEUERUNG ---
$t_in   = $current['intem'];
$rh_in  = $current['inhum'];
$t_out  = $current['t1tem'];
$rh_out = $current['t1hum'];

$af_in  = calculateAbsoluteHumidity($t_in, $rh_in);
$af_out = calculateAbsoluteHumidity($t_out, $rh_out);

$kwl_stufe = 2;
$kwl_empfehlung = "Normalbetrieb. Das Raumklima bewegt sich im gewünschten Bereich.";
$kwl_color = "#2cb67d"; // Grün

// REGEL 1: Sommerliche Hitze / Schwüle (Draußen zu warm ODER Außenluft feuchter als drinnen)
if ($t_out > 23.5 || ($t_out > $t_in && $af_out > $af_in)) {
    $kwl_stufe = 1;
    $kwl_empfehlung = "Stufe 1 (Mindestlüftung). Draußen ist es zu warm oder zu schwül. Hol dir keine Hitze ins Haus!";
    $kwl_color = "#e67e22"; // Orange
}
// REGEL 2: Sommer-Nachtkühlung (Draußen kühler als drinnen, es ist drinnen über 22°C und draußen trocken)
elseif ($t_out < $t_in && $t_in > 22.0 && $af_out < $af_in) {
    $kwl_stufe = 3;
    $kwl_empfehlung = "Stufe 3 (Intensivlüftung). Perfekte Bedingungen zum Abkühlen und Entfeuchten des Hauses!";
    $kwl_color = "#3498db"; // Blau
}
// REGEL 3: Winter / Trockenschutz (Draußen kalt und drinnen droht Trockenheit oder Auskühlung)
elseif ($t_out < 8.0 && ($rh_in < 42 || $af_in < 7.5 || $t_in < 20.0)) {
    $kwl_stufe = 1;
    $kwl_empfehlung = "Stufe 1 (Frost-/Trockenschutz). Luft ist zu trocken (<42%) oder Raumtemperatur sinkt. Lüftung minimieren!";
    $kwl_color = "#9b59b6"; // Lila
}
// REGEL 4: Feuchtespitzen im Haus (z.B. nach Duschen/Kochen, Feuchte innen über 58% und draußen ist es trockener)
elseif ($rh_in > 58.0 && $af_out < $af_in) {
    $kwl_stufe = 3;
    $kwl_empfehlung = "Stufe 3 (Intensivlüftung). Erhöhte Luftfeuchtigkeit im Wohnraum. Draußen ist es trockener – Feuchtigkeit jetzt schnell ablüften.";
    $kwl_color = "#e74c3c"; // Rot
}

// --- LOGIK FÜR DEN DJI MINI 2 CHECK ---
$currentGustMS = $current['t1wgust'] ?? 0;
$currentAvgMS = $current['t1ws10mav'] ?? 0;
if ($currentGustMS < 6.0 && $currentAvgMS < 5.0) {
    $droneStatus = '🟢 Perfekt zum Fliegen. Kaum Wind.';
    $droneClass = 'drone-ok';
} elseif ($currentGustMS <= 8.5 && $currentAvgMS <= 7.0) {
    $droneStatus = '🟠 Risiko! Spürbarer Wind/Böen. Mini 2 muss hart arbeiten.';
    $droneClass = 'drone-warn';
} else {
    $droneStatus = '🔴 Flugverbot! Zu starke Böen/Wind für die Mini 2.';
    $droneClass = 'drone-danger';
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wetter & KWL Leitstand</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1,
        h2 {
            color: #2c3e50;
        }

        h2 {
            margin-top: 40px;
            border-bottom: 2px solid #bdc3c7;
            padding-bottom: 10px;
        }

        .system-status {
            text-align: center;
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 5px;
            background: #e2e8f0;
            font-weight: bold;
        }

        .status-badge.ok {
            background: #c6f6d5;
            color: #22543d;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-top: 4px solid #3498db;
        }

        .card.warning {
            border-top-color: #e74c3c;
        }

        .card.success {
            border-top-color: #2ecc71;
        }

        .card.neutral {
            border-top-color: #95a5a6;
        }

        .card.home {
            border-top-color: #f1c40f;
        }

        .card h3 {
            margin: 0 0 15px 0;
            font-size: 1.1em;
            color: #7f8c8d;
            text-align: center;
        }

        .card .value-display {
            text-align: center;
            margin-bottom: 15px;
        }

        .card .main-value {
            font-size: 2.4em;
            font-weight: bold;
            color: #2c3e50;
        }

        .data-list {
            border-top: 1px solid #edf2f7;
            padding-top: 10px;
            margin-top: 10px;
        }

        .data-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95em;
            margin-bottom: 6px;
            color: #4a5568;
        }

        .data-row span.label {
            color: #718096;
        }

        .data-row span.val {
            font-weight: 600;
        }

        /* KWL & Drohnen Spezial-Styles */
        .kwl-box {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            border-left: 8px solid;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .kwl-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .kwl-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
        }

        .kwl-badge {
            padding: 8px 16px;
            border-radius: 20px;
            color: #fff;
            font-weight: bold;
            font-size: 1.1em;
        }

        .drone-section {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-top: 30px;
            text-align: center;
        }

        .drone-status-bar {
            padding: 15px;
            border-radius: 8px;
            font-size: 1.2em;
            font-weight: bold;
            margin: 15px 0;
        }

        .drone-ok {
            background-color: #c6f6d5;
            color: #22543d;
        }

        .drone-warn {
            background-color: #feebc8;
            color: #744210;
        }

        .drone-danger {
            background-color: #fed7d7;
            color: #742a2a;
        }

        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1 style="text-align:center;">🌤️ Wohnraum- & Wetterleitstand</h1>

        <div class="system-status">
            Daten von: <strong><?= date('d.m.Y H:i:s', strtotime($current['zeitstempel'])) ?> Uhr</strong> |
            Sensor-Funk: <span class="status-badge ok"><?= $current['t1cn'] ? 'Verbunden' : 'Kein Signal' ?></span> |
            Batterie: <span class="status-badge ok"><?= $current['t1bat'] ? 'OK' : 'LEER' ?></span>
        </div>

        <!-- NEU: DYNAMISCHE HELIOS KWL EMPFEHLUNG -->
        <div class="kwl-box" style="border-left-color: <?= $kwl_color ?>;">
            <div class="kwl-header">
                <div class="kwl-title">🏠 Helios ZEB EC Lüftungsempfehlung</div>
                <div class="kwl-badge" style="background-color: <?= $kwl_color ?>;">Empfehlung: Stufe <?= $kwl_stufe ?></div>
            </div>
            <p style="font-size: 1.1em; margin: 15px 0; color: #2d3748; font-weight: 500;">
                👉 <?= $kwl_empfehlung ?>
            </p>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 0; margin-top: 15px; gap: 10px;">
                <div style="background:#f8fafc; padding:10px; border-radius:6px; text-align:center;">
                    <span style="color:#718096; font-size:0.9em;">Absolute Feuchte Innen</span><br>
                    <strong style="font-size:1.3em; color:#2d3748;"><?= number_format($af_in, 2, ',', '.') ?> g/m³</strong>
                </div>
                <div style="background:#f8fafc; padding:10px; border-radius:6px; text-align:center;">
                    <span style="color:#718096; font-size:0.9em;">Absolute Feuchte Außen</span><br>
                    <strong style="font-size:1.3em; color:#2d3748;"><?= number_format($af_out, 2, ',', '.') ?> g/m³</strong>
                </div>
                <div style="background:#f8fafc; padding:10px; border-radius:6px; text-align:center;">
                    <span style="color:#718096; font-size:0.9em;">Lüftungs-Tendenz</span><br>
                    <strong style="font-size:1.1em; color:<?= ($af_out < $af_in) ? '#2ecc71' : '#e67e22' ?>;">
                        <?= ($af_out < $af_in) ? '⬇️ Entfeuchtend (Trockner)' : '⬆️ Befeuchtend (Feuchter)' ?>
                    </strong>
                </div>
            </div>
        </div>

        <!-- LIVE-DATEN BLÖCKE -->
        <h2>Live-Messwerte</h2>
        <div class="grid">

            <!-- INNENKLIMA -->
            <div class="card home">
                <h3>🏠 Innenraum (Soll: 20-22°C)</h3>
                <div class="value-display">
                    <span class="main-value" style="color: <?= ($t_in >= 20 && $t_in <= 22) ? '#2ecc71' : '#e67e22' ?>;"><?= number_format($t_in, 1, ',', '.') ?></span><span style="font-size:1.2em; font-weight:bold;"> °C</span>
                </div>
                <div class="data-list">
                    <div class="data-row"><span class="label">Luftfeuchtigkeit Innen:</span><span class="val"><?= $rh_in ?> %</span></div>
                    <div class="data-row"><span class="label">Absolute Feuchte:</span><span class="val"><?= number_format($af_in, 1, ',', '.') ?> g/m³</span></div>
                    <div class="data-row"><span class="label">Status:</span><span class="val" style="color: <?= ($rh_in >= 40 && $rh_in <= 55) ? '#2ecc71' : '#e67e22' ?>;"><?= ($rh_in < 40) ? 'Zu trocken 🌵' : (($rh_in > 55) ? 'Feucht 💧' : 'Optimal OK  V') ?></span></div>
                </div>
            </div>

            <!-- TEMPERATUR AUSSEN -->
            <div class="card warning">
                <h3>🌡️ Außenklima</h3>
                <div class="value-display">
                    <span class="main-value"><?= number_format($current['t1tem'], 1, ',', '.') ?></span><span style="font-size:1.2em; font-weight:bold;"> °C</span>
                </div>
                <div class="data-list">
                    <div class="data-row"><span class="label">Luftfeuchtigkeit Außen:</span><span class="val"><?= $rh_out ?> %</span></div>
                    <div class="data-row"><span class="label">Absolute Feuchte:</span><span class="val"><?= number_format($af_out, 1, ',', '.') ?> g/m³</span></div>
                    <div class="data-row"><span class="label">Taupunkt / Windchill:</span><span class="val"><?= number_format($current['t1dew'], 1, ',', '.') ?>°C / <?= number_format($current['t1chill'], 1, ',', '.') ?>°C</span></div>
                </div>
            </div>

            <!-- REGEN -->
            <div class="card success">
                <h3>💧 Niederschlag</h3>
                <div class="value-display">
                    <span class="main-value"><?= number_format($current['t1raindy'], 1, ',', '.') ?></span><span style="font-size:1.2em; font-weight:bold;"> mm</span>
                </div>
                <div class="data-list">
                    <div class="data-row"><span class="label">Regenrate aktuell:</span><span class="val"><?= number_format($current['t1rainra'], 1, ',', '.') ?> mm/h</span></div>
                    <div class="data-row"><span class="label">Laufender Monat:</span><span class="val"><?= number_format($current['t1rainmth'], 1, ',', '.') ?> mm</span></div>
                    <div class="data-row"><span class="label">Laufendes Jahr:</span><span class="val"><?= number_format($current['t1rainyr'], 1, ',', '.') ?> mm</span></div>
                </div>
            </div>

            <!-- ATMOSPHÄRE -->
            <div class="card neutral">
                <h3>☀️ Sonne & Luftdruck</h3>
                <div class="value-display">
                    <span class="main-value"><?= number_format($current['rbar'], 1, ',', '.') ?></span><span style="font-size:1.0em; font-weight:bold;"> hPa</span>
                </div>
                <div class="data-list">
                    <div class="data-row"><span class="label">Absoluter Druck (abar):</span><span class="val"><?= number_format($current['abar'], 1, ',', '.') ?> hPa</span></div>
                    <div class="data-row"><span class="label">Sonnenstrahlung:</span><span class="val"><?= number_format($current['t1solrad'], 1, ',', '.') ?> W/m²</span></div>
                    <div class="data-row"><span class="label">UV-Index:</span><span class="val"><?= number_format($current['t1uvi'], 1, ',', '.') ?></span></div>
                </div>
            </div>
        </div>

        <!-- HEUTIGE EXTREME -->
        <h2>📊 Heute gemessene Extremwerte</h2>
        <div class="grid">
            <div class="card neutral">
                <h3>Temperatur Min / Max</h3>
                <div class="value-display"><span class="main-value" style="font-size: 1.8em;"><span style="color: #3498db;"><?= number_format($stats['min_temp'], 1, ',', '.') ?></span> / <span style="color: #e74c3c;"><?= number_format($stats['max_temp'], 1, ',', '.') ?></span> °C</span></div>
            </div>
            <div class="card neutral">
                <h3>Stärkste Böe heute</h3>
                <div class="value-display"><span class="main-value" style="font-size: 1.8em;"><?= number_format(msToKmh($stats['max_wind']), 1, ',', '.') ?> km/h</span></div>
                <div style="text-align:center; font-size:0.9em; color:#7f8c8d;"><?= number_format($stats['max_wind'], 1, ',', '.') ?> m/s | <strong><?= msToBft($stats['max_wind']) ?> Bft</strong></div>
            </div>
            <div class="card neutral">
                <h3>Max. Regenrate & UV</h3>
                <div class="data-list" style="border:none; margin-top:5px;">
                    <div class="data-row"><span class="label">Max. Regenrate:</span><span class="val"><?= number_format($stats['max_regenrate'], 1, ',', '.') ?> mm/h</span></div>
                    <div class="data-row"><span class="label">Max. UV-Index:</span><span class="val"><?= number_format($stats['max_uv'], 1, ',', '.') ?></span></div>
                </div>
            </div>
        </div>

        <!-- NEU PLATZIERT: DROHNEN FLUGWETTER-CHECK -->
        <div class="drone-section">
            <h3 style="margin:0; color:#2c3e50;">🎮 DJI Mini 2 Flugwetter-Check</h3>
            <div class="drone-status-bar <?= $droneClass ?>"><?= $droneStatus ?></div>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 0; gap:15px;">
                <div style="text-align:left; padding: 0 15px;">
                    <strong>Wind aktuell:</strong> <?= number_format(msToKmh($current['t1ws']), 1, ',', '.') ?> km/h (<?= number_format($current['t1ws'], 1, ',', '.') ?> m/s | <?= msToBft($current['t1ws']) ?> Bft)
                </div>
                <div style="text-align:left; padding: 0 15px;">
                    <strong>Ø Wind (10 Min):</strong> <?= number_format(msToKmh($current['t1ws10mav']), 1, ',', '.') ?> km/h (<?= number_format($current['t1ws10mav'], 1, ',', '.') ?> m/s)
                </div>
                <div style="text-align:left; padding: 0 15px;">
                    <strong>Spitzenböen:</strong> <?= number_format(msToKmh($current['t1wgust']), 1, ',', '.') ?> km/h (<?= number_format($current['t1wgust'], 1, ',', '.') ?> m/s)
                </div>
            </div>
        </div>

        <!-- ALLZEIT-REKORDE -->
        <h2>🏆 Historische Allzeit-Rekorde</h2>
        <?php if ($records['rec_max_temp'] !== null): ?>
            <div class="grid">
                <div class="card warning">
                    <h3>Heißester Tag</h3>
                    <div class="value-display"><span class="main-value" style="font-size: 1.8em;"><?= number_format($records['rec_max_temp'], 1, ',', '.') ?> °C</span></div>
                </div>
                <div class="card" style="border-top-color: #3498db;">
                    <h3>Kältester Tag</h3>
                    <div class="value-display"><span class="main-value" style="font-size: 1.8em;"><?= number_format($records['rec_min_temp'], 1, ',', '.') ?> °C</span></div>
                </div>
                <div class="card">
                    <h3>Stärkster Sturm</h3>
                    <div class="value-display"><span class="main-value" style="font-size: 1.8em;"><?= number_format(msToKmh($records['rec_max_wind']), 1, ',', '.') ?> km/h</span></div>
                </div>
                <div class="card success">
                    <h3>Meister Regen (Tag)</h3>
                    <div class="value-display"><span class="main-value" style="font-size: 1.8em;"><?= number_format($records['rec_max_regen'], 1, ',', '.') ?> mm</span></div>
                </div>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #7f8c8d;"><i>Historische Rekorde sind nach dem ersten nächtlichen Cronjob verfügbar.</i></p>
        <?php endif; ?>

        <!-- DIAGRAMME -->
        <h2>📉 Verlauf (Letzte 24 Stunden)</h2>
        <div class="chart-container">
            <canvas id="weatherChart"></canvas>
        </div>
    </div>

    <script>
        const labels = <?= json_encode($labels) ?>;
        const tempPoints = <?= json_encode($temperatures) ?>;
        const windPointsKmh = <?= json_encode($windgustsKmh) ?>;

        const ctx = document.getElementById('weatherChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                        label: 'Temperatur (°C)',
                        data: tempPoints,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        yAxisID: 'y',
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Windböen (km/h)',
                        data: windPointsKmh,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        yAxisID: 'y1',
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: false,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Temperatur (°C)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Windgeschw. (km/h)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>

</body>

</html>