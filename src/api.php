<?php
ini_set('display_errors', 0); // Schützt das JSON vor PHP-Warnungen
error_reporting(E_ALL);
require_once './inc/functions.inc.php';
header('Content-Type: application/json');

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'live';

if ($action === 'live') {
    // 1. Aktueller Datensatz holen
    $current = $pdo->query("SELECT * FROM wetterdaten ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['error' => 'Keine Daten vorhanden']);
        exit;
    }

    // 2. Allzeit-Rekorde holen
    $recMaxTemp = $pdo->query("SELECT datum, max_t1tem as val FROM wetter_tagesstatistik WHERE max_t1tem IS NOT NULL ORDER BY max_t1tem DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $recMinTemp = $pdo->query("SELECT datum, min_t1tem as val FROM wetter_tagesstatistik WHERE min_t1tem IS NOT NULL ORDER BY min_t1tem ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $recMaxWind = $pdo->query("SELECT datum, max_t1wgust as val FROM wetter_tagesstatistik WHERE max_t1wgust IS NOT NULL ORDER BY max_t1wgust DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $recMaxRain = $pdo->query("SELECT datum, regen_gesamt as val FROM wetter_tagesstatistik WHERE regen_gesamt IS NOT NULL ORDER BY regen_gesamt DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // 3. Absolute Feuchten berechnen (für Lüftungsanzeige)
    $af_in = calculateAbsoluteHumidity($current['intem'], $current['inhum']);
    $af_out = calculateAbsoluteHumidity($current['t1tem'], $current['t1hum']);

    // 4. Verkehrsfluss & Lärmindex (Autobahnindex)
    $traffic_flow = isset($current['traffic_flow']) ? $current['traffic_flow'] : getHamburgTrafficLoad();

    $wind_diff = abs($current['t1wdir'] - 135);
    if ($wind_diff > 180) $wind_diff = 360 - $wind_diff;
    $schall_leitung = max(0, 1 - ($wind_diff / 110));

    $w_speed_kmh = msToKmh($current['t1ws']);
    $wind_effect = ($w_speed_kmh < 4) ? 0.2 : (($w_speed_kmh > 28) ? 0.4 : 0.4 + ($w_speed_kmh / 35));
    $laerm_index = min(100, max(5, round($traffic_flow * $schall_leitung * $wind_effect * 1.8)));

    // 5. Live-Solarberechnung (Korrigerter Datums-String!)
    $theoSolarLive = getTheoreticalInsolation(date('Y-m-d H:i:s'));
    $measuredSolarLive = $current['t1solrad'] ?? 0;
    $cloudinessLive = ($theoSolarLive > 20) ? min(100, max(0, round(100 * (1 - ($measuredSolarLive / $theoSolarLive)), 0))) : 0;

    // 6. JSON-Ausgabe (Exakt so strukturiert, wie script.js es erwartet)
    echo json_encode([
        'current' => $current,
        'records' => [
            'max_temp' => ['val' => $recMaxTemp['val'] ?? 0, 'date' => isset($recMaxTemp['datum']) ? date('d.m.Y', strtotime($recMaxTemp['datum'])) : '-'],
            'min_temp' => ['val' => $recMinTemp['val'] ?? 0, 'date' => isset($recMinTemp['datum']) ? date('d.m.Y', strtotime($recMinTemp['datum'])) : '-'],
            'max_wind' => ['val' => msToKmh($recMaxWind['val'] ?? 0), 'date' => isset($recMaxWind['datum']) ? date('d.m.Y', strtotime($recMaxWind['datum'])) : '-'],
            'max_rain' => ['val' => $recMaxRain['val'] ?? 0, 'date' => isset($recMaxRain['datum']) ? date('d.m.Y', strtotime($recMaxRain['datum'])) : '-']
        ],
        'calculated' => [
            'af_in' => round($af_in, 2),
            'af_out' => round($af_out, 2),
            'traffic_flow' => round($traffic_flow, 1),
            'schall_leitung' => round($schall_leitung * 100, 0),
            'laerm_index' => $laerm_index,
            'wind_speed_kmh' => round($w_speed_kmh, 1),
            'solar_theo' => round($theoSolarLive, 0),
            'cloudiness' => $cloudinessLive
        ]
    ]);
    exit;
}

if ($action === 'chart') {
    $range = $_GET['range'] ?? '24h';

    if ($range === '7d') {
        $stmt = $pdo->query("SELECT DATE_FORMAT(zeitstempel, '%Y-%m-%d %H:00:00') as ts, AVG(t1tem) as temp, MAX(t1wgust) as wind, AVG(t1hum) as hum_out, AVG(inhum) as hum_in, AVG(intem) as temp_in, AVG(t1solrad) as solar, AVG(traffic_flow) as traffic FROM wetterdaten WHERE zeitstempel >= NOW() - INTERVAL 7 DAY GROUP BY ts ORDER BY ts ASC");
        $format = 'd.m. H:i';
    } elseif ($range === '30d') {
        $stmt = $pdo->query("SELECT DATE_FORMAT(zeitstempel, '%Y-%m-%d %H:00:00') as ts, AVG(t1tem) as temp, MAX(t1wgust) as wind, AVG(t1hum) as hum_out, AVG(inhum) as hum_in, AVG(intem) as temp_in, AVG(t1solrad) as solar, AVG(traffic_flow) as traffic FROM wetterdaten WHERE zeitstempel >= NOW() - INTERVAL 30 DAY GROUP BY FLOOR(HOUR(zeitstempel)/4), DATE(zeitstempel) ORDER BY ts ASC");
        $format = 'd.m.';
    } else {
        $stmt = $pdo->query("SELECT zeitstempel as ts, t1tem as temp, t1wgust as wind, t1hum as hum_out, inhum as hum_in, intem as temp_in, t1solrad as solar, traffic_flow as traffic FROM wetterdaten WHERE zeitstempel >= NOW() - INTERVAL 1 DAY ORDER BY ts ASC");
        $format = 'H:i';
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payload = ['labels' => [], 'temp' => [], 'wind' => [], 'hum_out' => [], 'hum_in' => [], 'af_in' => [], 'af_out' => [], 'solar_meas' => [], 'solar_theo' => [], 'cloudiness' => [], 'traffic' => []];

    foreach ($rows as $r) {
        $dateTimeStr = $r['ts'];
        $payload['labels'][] = date($format, strtotime($dateTimeStr));
        $payload['temp'][] = round($r['temp'], 1);
        $payload['wind'][] = round(msToKmh($r['wind']), 1);
        $payload['hum_out'][] = round($r['hum_out'], 1);
        $payload['hum_in'][] = round($r['hum_in'], 1);
        $payload['traffic'][] = round($r['traffic'] ?? 50, 0);
        $payload['af_in'][] = round(calculateAbsoluteHumidity($r['temp_in'], $r['hum_in']), 2);
        $payload['af_out'][] = round(calculateAbsoluteHumidity($r['temp'], $r['hum_out']), 2);

        $measuredSolar = round($r['solar'] ?? 0, 1);
        $theoSolar = getTheoreticalInsolation($dateTimeStr);

        $payload['solar_meas'][] = $measuredSolar;
        $payload['solar_theo'][] = round($theoSolar, 1);

        if ($theoSolar <= 20) {
            $payload['cloudiness'][] = 0;
        } else {
            $cloud = 100 * (1 - ($measuredSolar / $theoSolar));
            $payload['cloudiness'][] = min(100, max(0, round($cloud, 0)));
        }
    }
    echo json_encode($payload);
    exit;
}
