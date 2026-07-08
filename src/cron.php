<?php
require_once './inc/db.inc.php';

function upsertDailyStats(PDO $pdo, string $statDatum, string $startInUTC, string $endInUTC): bool
{
    $sql = "INSERT INTO wetter_tagesstatistik (
                datum,
                min_t1tem, max_t1tem, avg_t1tem, min_t1tem_at, max_t1tem_at,
                min_t1feels, max_t1feels, avg_t1feels, min_t1feels_at, max_t1feels_at,
                min_t1dew, max_t1dew, avg_t1dew, min_t1dew_at, max_t1dew_at,
                min_t1hum, max_t1hum, avg_t1hum, min_t1hum_at, max_t1hum_at,
                max_t1ws, max_t1ws_at,
                max_t1wgust, max_t1wgust_at,
                min_rbar, max_rbar, avg_rbar, min_rbar_at, max_rbar_at,
                max_t1uvi, max_t1uvi_at,
                max_t1solrad, max_t1solrad_at,
                regen_gesamt,
                max_t1rainra, max_t1rainra_at,
                min_intem, max_intem, avg_intem, min_intem_at, max_intem_at,
                min_inhum, max_inhum, avg_inhum, min_inhum_at, max_inhum_at
            )
            SELECT
                :stat_datum as datum,

                MIN(t1tem),
                MAX(t1tem),
                AVG(t1tem),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1tem IS NOT NULL ORDER BY wd2.t1tem ASC, wd2.zeitstempel ASC LIMIT 1),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1tem IS NOT NULL ORDER BY wd2.t1tem DESC, wd2.zeitstempel ASC LIMIT 1),

                MIN(t1feels),
                MAX(t1feels),
                AVG(t1feels),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1feels IS NOT NULL ORDER BY wd2.t1feels ASC, wd2.zeitstempel ASC LIMIT 1),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1feels IS NOT NULL ORDER BY wd2.t1feels DESC, wd2.zeitstempel ASC LIMIT 1),

                MIN(t1dew),
                MAX(t1dew),
                AVG(t1dew),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1dew IS NOT NULL ORDER BY wd2.t1dew ASC, wd2.zeitstempel ASC LIMIT 1),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1dew IS NOT NULL ORDER BY wd2.t1dew DESC, wd2.zeitstempel ASC LIMIT 1),

                MIN(t1hum),
                MAX(t1hum),
                AVG(t1hum),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1hum IS NOT NULL ORDER BY wd2.t1hum ASC, wd2.zeitstempel ASC LIMIT 1),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1hum IS NOT NULL ORDER BY wd2.t1hum DESC, wd2.zeitstempel ASC LIMIT 1),

                MAX(t1ws),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1ws IS NOT NULL ORDER BY wd2.t1ws DESC, wd2.zeitstempel ASC LIMIT 1),

                MAX(t1wgust),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1wgust IS NOT NULL ORDER BY wd2.t1wgust DESC, wd2.zeitstempel ASC LIMIT 1),

                MIN(rbar),
                MAX(rbar),
                AVG(rbar),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.rbar IS NOT NULL ORDER BY wd2.rbar ASC, wd2.zeitstempel ASC LIMIT 1),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.rbar IS NOT NULL ORDER BY wd2.rbar DESC, wd2.zeitstempel ASC LIMIT 1),

                MAX(t1uvi),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1uvi IS NOT NULL ORDER BY wd2.t1uvi DESC, wd2.zeitstempel ASC LIMIT 1),

                MAX(t1solrad),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1solrad IS NOT NULL ORDER BY wd2.t1solrad DESC, wd2.zeitstempel ASC LIMIT 1),

                MAX(t1raindy),

                MAX(t1rainra),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.t1rainra IS NOT NULL ORDER BY wd2.t1rainra DESC, wd2.zeitstempel ASC LIMIT 1),

                MIN(intem),
                MAX(intem),
                AVG(intem),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.intem IS NOT NULL ORDER BY wd2.intem ASC, wd2.zeitstempel ASC LIMIT 1),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.intem IS NOT NULL ORDER BY wd2.intem DESC, wd2.zeitstempel ASC LIMIT 1),

                MIN(inhum),
                MAX(inhum),
                AVG(inhum),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.inhum IS NOT NULL ORDER BY wd2.inhum ASC, wd2.zeitstempel ASC LIMIT 1),
                (SELECT wd2.zeitstempel FROM wetterdaten wd2 WHERE wd2.zeitstempel >= :start_utc AND wd2.zeitstempel <= :end_utc AND wd2.inhum IS NOT NULL ORDER BY wd2.inhum DESC, wd2.zeitstempel ASC LIMIT 1)

            FROM wetterdaten
            WHERE zeitstempel >= :start_utc AND zeitstempel <= :end_utc
            HAVING COUNT(*) > 0

            ON DUPLICATE KEY UPDATE
                min_t1tem = VALUES(min_t1tem), max_t1tem = VALUES(max_t1tem), avg_t1tem = VALUES(avg_t1tem),
                min_t1tem_at = VALUES(min_t1tem_at), max_t1tem_at = VALUES(max_t1tem_at),

                min_t1feels = VALUES(min_t1feels), max_t1feels = VALUES(max_t1feels), avg_t1feels = VALUES(avg_t1feels),
                min_t1feels_at = VALUES(min_t1feels_at), max_t1feels_at = VALUES(max_t1feels_at),

                min_t1dew = VALUES(min_t1dew), max_t1dew = VALUES(max_t1dew), avg_t1dew = VALUES(avg_t1dew),
                min_t1dew_at = VALUES(min_t1dew_at), max_t1dew_at = VALUES(max_t1dew_at),

                min_t1hum = VALUES(min_t1hum), max_t1hum = VALUES(max_t1hum), avg_t1hum = VALUES(avg_t1hum),
                min_t1hum_at = VALUES(min_t1hum_at), max_t1hum_at = VALUES(max_t1hum_at),

                max_t1ws = VALUES(max_t1ws), max_t1ws_at = VALUES(max_t1ws_at),
                max_t1wgust = VALUES(max_t1wgust), max_t1wgust_at = VALUES(max_t1wgust_at),

                min_rbar = VALUES(min_rbar), max_rbar = VALUES(max_rbar), avg_rbar = VALUES(avg_rbar),
                min_rbar_at = VALUES(min_rbar_at), max_rbar_at = VALUES(max_rbar_at),

                max_t1uvi = VALUES(max_t1uvi), max_t1uvi_at = VALUES(max_t1uvi_at),
                max_t1solrad = VALUES(max_t1solrad), max_t1solrad_at = VALUES(max_t1solrad_at),

                regen_gesamt = VALUES(regen_gesamt),
                max_t1rainra = VALUES(max_t1rainra), max_t1rainra_at = VALUES(max_t1rainra_at),

                min_intem = VALUES(min_intem), max_intem = VALUES(max_intem), avg_intem = VALUES(avg_intem),
                min_intem_at = VALUES(min_intem_at), max_intem_at = VALUES(max_intem_at),

                min_inhum = VALUES(min_inhum), max_inhum = VALUES(max_inhum), avg_inhum = VALUES(avg_inhum),
                min_inhum_at = VALUES(min_inhum_at), max_inhum_at = VALUES(max_inhum_at)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'stat_datum' => $statDatum,
        'start_utc' => $startInUTC,
        'end_utc' => $endInUTC
    ]);

    return $stmt->rowCount() > 0;
}

function recalculateAllDays(PDO $pdo, DateTimeZone $localZone, DateTimeZone $utcZone): int
{
    $range = $pdo->query("SELECT MIN(zeitstempel) AS min_ts, MAX(zeitstempel) AS max_ts FROM wetterdaten")
        ->fetch(PDO::FETCH_ASSOC);

    if (empty($range['min_ts']) || empty($range['max_ts'])) {
        return 0;
    }

    $startLocal = new DateTime($range['min_ts'], $utcZone);
    $endLocal = new DateTime($range['max_ts'], $utcZone);
    $startLocal->setTimezone($localZone)->setTime(0, 0, 0);
    $endLocal->setTimezone($localZone)->setTime(0, 0, 0);

    $count = 0;
    $cursor = clone $startLocal;
    while ($cursor <= $endLocal) {
        $dayStart = (clone $cursor)->setTime(0, 0, 0);
        $dayEnd = (clone $cursor)->setTime(23, 59, 59);

        $statDatum = $dayStart->format('Y-m-d');
        $startInUTC = (clone $dayStart)->setTimezone($utcZone)->format('Y-m-d H:i:s');
        $endInUTC = (clone $dayEnd)->setTimezone($utcZone)->format('Y-m-d H:i:s');

        if (upsertDailyStats($pdo, $statDatum, $startInUTC, $endInUTC)) {
            $count++;
        }

        $cursor->modify('+1 day');
    }

    return $count;
}

function shouldRecalculateAll(): bool
{
    $flag = $_GET['recalc_all'] ?? $_GET['recalc'] ?? null;

    if ($flag !== null) {
        return in_array(strtolower((string) $flag), ['1', 'true', 'all', 'yes', 'ja'], true);
    }

    if (PHP_SAPI === 'cli' && isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
        foreach ($_SERVER['argv'] as $arg) {
            if (in_array($arg, ['--recalc-all', '--all', 'recalc_all=1', 'recalc=all'], true)) {
                return true;
            }
        }
    }

    return false;
}

try {
    $pdo = getDBConnection();

    $recalculateAll = shouldRecalculateAll();

    // 1. Zeitgrenzen für "gestern" in Berliner Zeit definieren
    $localZone = new DateTimeZone('Europe/Berlin');
    $utcZone   = new DateTimeZone('UTC');

    if ($recalculateAll) {
        $updatedDays = recalculateAllDays($pdo, $localZone, $utcZone);
        echo "Komplette Neuberechnung abgeschlossen. Aktualisierte Tage: $updatedDays";
        exit;
    }

    // Start- und Endzeitpunkt für gestern lokal
    $dtStart = new DateTime('yesterday 00:00:00', $localZone);
    $dtEnd   = new DateTime('yesterday 23:59:59', $localZone);

    // Das Datum, das in die Statistik eingetragen wird (Format: Y-m-d)
    $statDatum = $dtStart->format('Y-m-d');

    // Diese Zeiten in UTC umrechnen für die WHERE-Klausel der DB
    $startInUTC = $dtStart->setTimezone($utcZone)->format('Y-m-d H:i:s');
    $endInUTC   = $dtEnd->setTimezone($utcZone)->format('Y-m-d H:i:s');

    upsertDailyStats($pdo, $statDatum, $startInUTC, $endInUTC);

    echo "Erweiterte Statistik fuer gestern ($statDatum) erfolgreich generiert!";
} catch (PDOException $e) {
    file_put_contents('cron_error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Fehler bei der Datenbankverbindung.";
}
