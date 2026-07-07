<?php
require_once 'config.inc.php';

// --- DATENBANKVERBINDUNG ---
function getDBConnection()
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Datenbankfehler: ' . $e->getMessage()]));
    }
}
