<?php
// 1. Log-Datei definieren
$logFile = 'wslink_log.txt';

// Die WSLink API sendet Daten als GET-Request
$incomingData = $_GET;

// Zeitstempel des Servers für das Log
$serverTime = date('Y-m-d H:i:s');

// Prüfen, ob überhaupt Daten gesendet wurden und ID/Passwort vorhanden sind
// Der Parameter "wsid" ist die Device ID und "wspw" das Device Password
if (!isset($incomingData['wsid']) || !isset($incomingData['wspw'])) {
    // Wenn Daten fehlen, senden wir den Fehlercode 401 (Incorrect device id or device password)[cite: 1]
    http_response_code(401);
    die("Error 401: Unauthorized");
}

// Zeitstempel der Station übernehmen (falls gesendet), sonst Serverzeit nutzen
// Datetime kommt im Format YYYY-MM-DD hh:mm:ss[cite: 1]
$deviceTime = $incomingData['datetime'] ?? $serverTime;

// --- TEIL 1: ALS TEXT SPEICHERN ---
$logContent = "=== WSLink Daten vom $serverTime (Geräte-Zeit: $deviceTime) ===\n";
foreach ($incomingData as $key => $value) {
    $logContent .= htmlspecialchars($key) . ": " . htmlspecialchars($value) . "\n";
}
$logContent .= "========================================================\n\n";

// In die Textdatei schreiben
file_put_contents($logFile, $logContent, FILE_APPEND);


// --- TEIL 2: MARIADB / MYSQL SPEICHERUNG (AKTUELL AUSKOMMENTIERT) ---
/*
$db_host = 'localhost';
$db_user = 'dein_datenbank_user';
$db_pass = 'dein_passwort';
$db_name = 'deine_wetter_db';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL-Statement für einen 7-in-1 Sensor (Sensor Type 1)[cite: 1]
    // Passe die Spaltennamen an deine Datenbank an.
    $sql = "INSERT INTO wetterdaten (
                zeitstempel, station_id, 
                temp_innen, temp_aussen, 
                feuchtigkeit_aussen, luftdruck_relativ, 
                windgeschwindigkeit, windrichtung, windboeen,
                regen_taeglich, uv_index, lichtintensitaet
            ) VALUES (
                :zeitstempel, :station_id, 
                :intem, :t1tem, 
                :t1hum, :rbar, 
                :t1ws, :t1wdir, :t1wgust,
                :t1raindy, :t1uvi, :t1solrad
            )";
            
    $stmt = $pdo->prepare($sql);
    
    // Daten mappen. Ein großer Vorteil: Die Einheiten sind bereits metrisch!
    $stmt->execute([
        ':zeitstempel'         => $deviceTime,
        ':station_id'          => $incomingData['wsid'],
        ':intem'               => $incomingData['intem'] ?? null,    // Innentemperatur in °C[cite: 1]
        ':t1tem'               => $incomingData['t1tem'] ?? null,    // Außentemperatur in °C[cite: 1]
        ':t1hum'               => $incomingData['t1hum'] ?? null,    // Außenluftfeuchtigkeit in %[cite: 1]
        ':rbar'                => $incomingData['rbar'] ?? null,     // Relativer Luftdruck in hPa[cite: 1]
        ':t1ws'                => $incomingData['t1ws'] ?? null,     // Windgeschwindigkeit in m/s[cite: 1]
        ':t1wdir'              => $incomingData['t1wdir'] ?? null,   // Windrichtung in Grad[cite: 1]
        ':t1wgust'             => $incomingData['t1wgust'] ?? null,  // Windböen in m/s[cite: 1]
        ':t1raindy'            => $incomingData['t1raindy'] ?? null, // Täglicher Niederschlag in mm[cite: 1]
        ':t1uvi'               => $incomingData['t1uvi'] ?? null,    // UV-Index[cite: 1]
        ':t1solrad'            => $incomingData['t1solrad'] ?? null  // Lichtintensität in W/m²[cite: 1]
    ]);
    
} catch (PDOException $e) {
    file_put_contents($logFile, "DB-Fehler am $serverTime: " . $e->getMessage() . "\n", FILE_APPEND);
}
*/

// --- TEIL 3: ERFOLGSMELDUNG FÜR DIE WETTERSTATION ---
// Wenn alles durchgelaufen ist, den HTTP-Statuscode 200 (Success) zurückgeben[cite: 1]
http_response_code(200);
echo "Success";
?>