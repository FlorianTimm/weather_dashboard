<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../functions.inc.php';

$pdo = getDBConnection();

$incomingData = $_GET;
$serverTime = date('Y-m-d H:i:s');

// Authentifizierung prüfen
if (
    !isset($incomingData['wsid']) || !isset($incomingData['wspw']) ||
    $incomingData['wsid'] !== $expected_wsid || $incomingData['wspw'] !== $expected_wspw
) {
    http_response_code(401);
    die("Error 401: Unauthorized");
}

// Zeitstempel bestimmen
$deviceTime = $incomingData['datetime'] ?? $serverTime;

try {
    // Datenbankverbindung aufbauen
    $pdo = getDBConnection();

    // SQL Insert Statement
    $sql = "INSERT INTO wetterdaten (
                zeitstempel, station_id, 
                rbar, abar, intem, inhum, 
                t1cn, t1bat, t1tem, t1hum, t1feels, t1chill, t1dew, 
                t1ws, t1ws10mav, t1wgust, t1wdir, 
                t1rainra, t1rainhr, t1raindy, t1rainwy, t1rainmth, t1rainyr, 
                t1uvi, t1solrad, apiver
            ) VALUES (
                :zeitstempel, :station_id, 
                :rbar, :abar, :intem, :inhum, 
                :t1cn, :t1bat, :t1tem, :t1hum, :t1feels, :t1chill, :t1dew, 
                :t1ws, :t1ws10mav, :t1wgust, :t1wdir, 
                :t1rainra, :t1rainhr, :t1raindy, :t1rainwy, :t1rainmth, :t1rainyr, 
                :t1uvi, :t1solrad, :apiver
            )";

    $stmt = $pdo->prepare($sql);

    // Daten binden
    $stmt->execute([
        ':zeitstempel' => $deviceTime,
        ':station_id'  => $incomingData['wsid'],
        ':rbar'        => $incomingData['rbar'] ?? null,
        ':abar'        => $incomingData['abar'] ?? null,
        ':intem'       => $incomingData['intem'] ?? null,
        ':inhum'       => $incomingData['inhum'] ?? null,
        ':t1cn'        => $incomingData['t1cn'] ?? null,
        ':t1bat'       => $incomingData['t1bat'] ?? null,
        ':t1tem'       => $incomingData['t1tem'] ?? null,
        ':t1hum'       => $incomingData['t1hum'] ?? null,
        ':t1feels'     => $incomingData['t1feels'] ?? null,
        ':t1chill'     => $incomingData['t1chill'] ?? null,
        ':t1dew'       => $incomingData['t1dew'] ?? null,
        ':t1ws'        => $incomingData['t1ws'] ?? null,
        ':t1ws10mav'   => $incomingData['t1ws10mav'] ?? null,
        ':t1wgust'     => $incomingData['t1wgust'] ?? null,
        ':t1wdir'      => $incomingData['t1wdir'] ?? null,
        ':t1rainra'    => $incomingData['t1rainra'] ?? null,
        ':t1rainhr'    => $incomingData['t1rainhr'] ?? null,
        ':t1raindy'    => $incomingData['t1raindy'] ?? null,
        ':t1rainwy'    => $incomingData['t1rainwy'] ?? null,
        ':t1rainmth'   => $incomingData['t1rainmth'] ?? null,
        ':t1rainyr'    => $incomingData['t1rainyr'] ?? null,
        ':t1uvi'       => $incomingData['t1uvi'] ?? null,
        ':t1solrad'    => $incomingData['t1solrad'] ?? null,
        ':apiver'      => $incomingData['apiver'] ?? null
    ]);

    // Erfolgsmeldung für die Wetterstation
    http_response_code(200);
    echo "Success";
} catch (PDOException $e) {
    // Im Fehlerfall Log-Datei schreiben
    $errorMsg = "DB-Fehler am $serverTime: " . $e->getMessage() . "\n";
    file_put_contents('wslink_error.log', $errorMsg, FILE_APPEND);

    // 500er Fehler an Station senden
    http_response_code(500);
    echo "Database Error";
}
