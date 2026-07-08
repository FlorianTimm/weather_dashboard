<?php
ini_set('display_errors', 0); // Schützt das JSON vor PHP-Warnungen
error_reporting(E_ALL);
require_once './inc/functions.inc.php';
header('Content-Type: application/json');

// Zeitzonen für die PHP-Konvertierung definieren
$utcZone = new DateTimeZone('UTC');
$localZone = new DateTimeZone('Europe/Berlin');

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'live';

if ($action === 'live') {
    // 1. Aktueller Datensatz holen (zeitstempel roh als UTC)
    $current = $pdo->query("SELECT id, zeitstempel, station_id, rbar, abar, intem, inhum, t1cn, t1bat, t1tem, t1hum, t1feels, t1chill, t1dew, t1ws, t1ws10mav, t1wgust, t1wdir, t1rainra, t1rainhr, t1raindy, t1rainwy, t1rainmth, t1rainyr, t1uvi, t1solrad, traffic_flow, traffic_noise, apiver FROM wetterdaten ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo json_encode(['error' => 'Keine Daten vorhanden']);
        exit;
    }

    // Zeitstempel mit PHP von UTC nach Europe/Berlin konvertieren
    if (!empty($current['zeitstempel'])) {
        $dt = new DateTime($current['zeitstempel'], $utcZone);
        $dt->setTimezone($localZone);
        $current['zeitstempel'] = $dt->format('Y-m-d H:i:s');
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
    $traffic_noise = isset($current['traffic_noise']) ? $current['traffic_noise'] : getHamburgTrafficLoad()[1];

    $wind_diff = abs($current['t1wdir'] - 135);
    if ($wind_diff > 180) $wind_diff = 360 - $wind_diff;
    $schall_leitung = max(0, 1 - ($wind_diff / 110));

    $w_speed_kmh = msToKmh($current['t1ws']);
    $wind_effect = ($w_speed_kmh < 4) ? 0.2 : (($w_speed_kmh > 28) ? 0.4 : 0.4 + ($w_speed_kmh / 35));
    $laerm_index = min(100, max(5, round($traffic_noise * $schall_leitung * $wind_effect * 1.8)));

    // 5. Live-Solarberechnung auf Basis des Messzeitstempels
    $theoSolarLive = getTheoreticalInsolation($current['zeitstempel']);
    $measuredSolarLive = $current['t1solrad'] ?? 0;
    $cloudinessLive = ($theoSolarLive > 20) ? min(100, max(0, round(100 * (1 - ($measuredSolarLive / $theoSolarLive)), 0))) : 0;

    // 6. JSON-Ausgabe
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
            'traffic_noise' => round($traffic_noise, 1),
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
    $dateParam = $_GET['date'] ?? date('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
        $dateParam = date('Y-m-d');
    }

    // Für die SQL-WHERE-Klausel müssen wir die lokalen Filter-Daten in UTC umrechnen, 
    // da die Datenbank in UTC läuft.
    $dtStart = new DateTime($dateParam . ' 00:00:00', $localZone);
    $dtEnd = new DateTime($dateParam . ' 23:59:59', $localZone);

    if ($range === '7d') {
        $dtStart->modify('-6 days');
        $startInUTC = $dtStart->setTimezone($utcZone)->format('Y-m-d H:i:s');
        $endInUTC = $dtEnd->setTimezone($utcZone)->format('Y-m-d H:i:s');

        // Wir gruppieren hier grob über den rohen UTC-String (Stunde), korrigieren die Anzeige aber im Loop
        $stmt = $pdo->query("SELECT DATE_FORMAT(zeitstempel, '%Y-%m-%d %H:00:00') as ts, AVG(t1tem) as temp, AVG(intem) as temp_in, AVG(t1dew) as dew_out, MAX(t1wgust) as wind, AVG(t1wdir) as wind_dir, MAX(t1rainra) as rain_rate, AVG(t1hum) as hum_out, AVG(inhum) as hum_in, AVG(t1solrad) as solar, AVG(traffic_flow) as traffic_flow, AVG(traffic_noise) as traffic_noise FROM wetterdaten WHERE zeitstempel >= '$startInUTC' AND zeitstempel <= '$endInUTC' GROUP BY ts ORDER BY ts ASC");
        $format = 'd.m. H:i';
    } elseif ($range === '30d') {
        $dtStart->modify('-29 days');
        $startInUTC = $dtStart->setTimezone($utcZone)->format('Y-m-d H:i:s');
        $endInUTC = $dtEnd->setTimezone($utcZone)->format('Y-m-d H:i:s');

        $stmt = $pdo->query("SELECT DATE_FORMAT(zeitstempel, '%Y-%m-%d %H:00:00') as ts, AVG(t1tem) as temp, AVG(intem) as temp_in, AVG(t1dew) as dew_out, MAX(t1wgust) as wind, AVG(t1wdir) as wind_dir, MAX(t1rainra) as rain_rate, AVG(t1hum) as hum_out, AVG(inhum) as hum_in, AVG(t1solrad) as solar, AVG(traffic_flow) as traffic_flow, AVG(traffic_noise) as traffic_noise FROM wetterdaten WHERE zeitstempel >= '$startInUTC' AND zeitstempel <= '$endInUTC' GROUP BY FLOOR(HOUR(zeitstempel)/4), DATE(zeitstempel) ORDER BY ts ASC");
        $format = 'd.m.';
    } else {
        $startInUTC = $dtStart->setTimezone($utcZone)->format('Y-m-d H:i:s');
        $endInUTC = $dtEnd->setTimezone($utcZone)->format('Y-m-d H:i:s');

        $stmt = $pdo->query("SELECT zeitstempel as ts, t1tem as temp, intem as temp_in, t1dew as dew_out, t1wgust as wind, t1wdir as wind_dir, t1rainra as rain_rate, t1hum as hum_out, inhum as hum_in, t1solrad as solar, traffic_flow, traffic_noise FROM wetterdaten WHERE zeitstempel >= '$startInUTC' AND zeitstempel <= '$endInUTC' ORDER BY ts ASC");
        $format = 'H:i';
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payload = ['labels' => [], 'timestamps' => [], 'temp' => [], 'temp_in' => [], 'dew_in' => [], 'dew_out' => [], 'wind' => [], 'wind_dir' => [], 'rain_rate' => [], 'hum_out' => [], 'hum_in' => [], 'solar_meas' => [], 'solar_theo' => [], 'cloudiness' => [], 'traffic_flow' => [], 'traffic_noise' => []];

    foreach ($rows as $r) {
        // Jedes Datum aus der DB (UTC) wird nun mittels PHP nach Berlin konvertiert
        $dtRow = new DateTime($r['ts'], $utcZone);
        $dtRow->setTimezone($localZone);

        $localTimeStr = $dtRow->format('Y-m-d H:i:s');
        $timestampMs = $dtRow->getTimestamp() * 1000;

        $dewIn = calculateDewPoint($r['temp_in'], $r['hum_in']);

        $payload['labels'][] = $dtRow->format($format);
        $payload['timestamps'][] = $timestampMs;
        $payload['temp'][] = $r['temp'] === null ? null : round($r['temp'], 1);
        $payload['temp_in'][] = $r['temp_in'] === null ? null : round($r['temp_in'], 1);
        $payload['dew_in'][] = $dewIn === null ? null : round($dewIn, 1);
        $payload['dew_out'][] = $r['dew_out'] === null ? null : round($r['dew_out'], 1);
        $payload['wind'][] = $r['wind'] === null ? null : round(msToKmh($r['wind']), 1);
        $payload['wind_dir'][] = $r['wind_dir'] === null ? null : round($r['wind_dir'], 0);
        $payload['rain_rate'][] = $r['rain_rate'] === null ? null : round($r['rain_rate'], 1);
        $payload['hum_out'][] = $r['hum_out'] === null ? null : round($r['hum_out'], 1);
        $payload['hum_in'][] = $r['hum_in'] === null ? null : round($r['hum_in'], 1);
        $payload['traffic_flow'][] = $r['traffic_flow'] === null ? null : round($r['traffic_flow'], 0);
        $payload['traffic_noise'][] = $r['traffic_noise'] === null ? null : round($r['traffic_noise'], 0);

        $measuredSolar = $r['solar'] === null ? null : round($r['solar'], 1);

        // Wichtig: getTheoreticalInsolation braucht die lokale Zeit
        $theoSolar = getTheoreticalInsolation($localTimeStr);

        $payload['solar_meas'][] = $measuredSolar;
        $payload['solar_theo'][] = round($theoSolar, 1);

        if ($theoSolar <= 1) {
            $payload['cloudiness'][] = null;
        } else {
            $cloud = 100 * (1 - ($measuredSolar / $theoSolar));
            $payload['cloudiness'][] = min(100, max(0, round($cloud, 0)));
        }
    }
    echo json_encode($payload);
    exit;
}
