<?php
require_once './inc/db.inc.php';

try {
    $pdo = getDBConnection();

    // 1. Zeitgrenzen für "gestern" in Berliner Zeit definieren
    $localZone = new DateTimeZone('Europe/Berlin');
    $utcZone   = new DateTimeZone('UTC');

    // Start- und Endzeitpunkt für gestern lokal
    $dtStart = new DateTime('yesterday 00:00:00', $localZone);
    $dtEnd   = new DateTime('yesterday 23:59:59', $localZone);

    // Das Datum, das in die Statistik eingetragen wird (Format: Y-m-d)
    $statDatum = $dtStart->format('Y-m-d');

    // Diese Zeiten in UTC umrechnen für die WHERE-Klausel der DB
    $startInUTC = $dtStart->setTimezone($utcZone)->format('Y-m-d H:i:s');
    $endInUTC   = $dtEnd->setTimezone($utcZone)->format('Y-m-d H:i:s');

    // 2. SQL-Query mit Platzhaltern vorbereiten
    $sql = "INSERT INTO wetter_tagesstatistik (
                datum, 
                min_t1tem, max_t1tem, min_t1feels, max_t1feels, min_t1dew, max_t1dew,
                min_t1hum, max_t1hum, 
                max_t1ws, max_t1wgust, 
                min_rbar, max_rbar, 
                max_t1uvi, max_t1solrad, 
                regen_gesamt, max_t1rainra, 
                min_intem, max_intem, min_inhum, max_inhum
            )
            SELECT 
                :stat_datum as datum,
                
                MIN(t1tem), MAX(t1tem), 
                MIN(t1feels), MAX(t1feels), 
                MIN(t1dew), MAX(t1dew),
                
                MIN(t1hum), MAX(t1hum),
                
                MAX(t1ws), MAX(t1wgust),
                
                MIN(rbar), MAX(rbar),
                
                MAX(t1uvi), MAX(t1solrad),
                
                MAX(t1raindy), MAX(t1rainra),
                
                MIN(intem), MAX(intem), 
                MIN(inhum), MAX(inhum)
                
            FROM wetterdaten
            WHERE zeitstempel >= :start_utc AND zeitstempel <= :end_utc
            
            ON DUPLICATE KEY UPDATE 
                min_t1tem = VALUES(min_t1tem), max_t1tem = VALUES(max_t1tem),
                min_t1feels = VALUES(min_t1feels), max_t1feels = VALUES(max_t1feels),
                min_t1dew = VALUES(min_t1dew), max_t1dew = VALUES(max_t1dew),
                min_t1hum = VALUES(min_t1hum), max_t1hum = VALUES(max_t1hum),
                max_t1ws = VALUES(max_t1ws), max_t1wgust = VALUES(max_t1wgust),
                min_rbar = VALUES(min_rbar), max_rbar = VALUES(max_rbar),
                max_t1uvi = VALUES(max_t1uvi), max_t1solrad = VALUES(max_t1solrad),
                regen_gesamt = VALUES(regen_gesamt), max_t1rainra = VALUES(max_t1rainra),
                min_intem = VALUES(min_intem), max_intem = VALUES(max_intem),
                min_inhum = VALUES(min_inhum), max_inhum = VALUES(max_inhum)";

    $stmt = $pdo->prepare($sql);
    
    // 3. Parameter binden und ausführen
    $stmt->execute([
        'stat_datum' => $statDatum,
        'start_utc'  => $startInUTC,
        'end_utc'    => $endInUTC
    ]);

    echo "Erweiterte Statistik fuer gestern ($statDatum) erfolgreich generiert!";
} catch (PDOException $e) {
    file_put_contents('cron_error.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Fehler bei der Datenbankverbindung.";
}