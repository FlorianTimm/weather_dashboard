<?php
require_once("db.inc.php");

// --- MATHEMATISCHE HILFSFUNKTIONEN ---
function msToKmh($ms)
{
    return $ms * 3.6;
}

function msToBft($ms)
{
    if ($ms < 0.3)
        return 0;
    if ($ms < 1.6)
        return 1;
    if ($ms < 3.4)
        return 2;
    if ($ms < 5.5)
        return 3;
    if ($ms < 8.0)
        return 4;
    if ($ms < 10.8)
        return 5;
    if ($ms < 13.9)
        return 6;
    if ($ms < 17.2)
        return 7;
    if ($ms < 20.8)
        return 8;
    if ($ms < 24.5)
        return 9;
    if ($ms < 28.5)
        return 10;
    if ($ms < 32.7)
        return 11;
    return 12;
}

function calculateAbsoluteHumidity($temp, $rh)
{
    $es = 6.112 * exp((17.67 * $temp) / ($temp + 243.5));
    $e = $es * ($rh / 100);
    return (216.7 * $e) / ($temp + 273.15);
}

// --- LIVE-VERKEHRSLAGE LGV HAMBURG ---
function getHamburgTrafficLoad()
{
    $url = "https://geodienste.hamburg.de/wfs_hh_verkehrslage";
    $defaultIndex = 50; // Fallback

    // Dein funktionierender XML-Payload für den POST-Request
    $xmlPayload = '<?xml version="1.0" encoding="UTF-8"?>
    <wfs:GetFeature outputFormat="text/csv" xmlns:ows="http://www.opengis.net/ows"
        xmlns:gml="http://www.opengis.net/gml"
        xmlns:ogc="http://www.opengis.net/ogc"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xmlns:wfs="http://www.opengis.net/wfs"
        xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd">
        <wfs:Query typeName="de.hh.up:verkehrslage">
            <ogc:Filter>
                <ogc:And>
                    <ogc:PropertyIsEqualTo>
                        <ogc:PropertyName>de.hh.up:strassenklasse</ogc:PropertyName>
                        <ogc:Literal>Hauptverkehrsstraße</ogc:Literal>
                    </ogc:PropertyIsEqualTo>
                    <ogc:BBOX>
                        <ogc:PropertyName>de.hh.up:geom</ogc:PropertyName>
                        <gml:Envelope srsName="EPSG:4326">
                            <gml:lowerCorner>10.00631 53.38783</gml:lowerCorner>
                            <gml:upperCorner>10.03944 53.41526</gml:upperCorner>
                        </gml:Envelope>
                    </ogc:BBOX>
                </ogc:And>
            </ogc:Filter>
        </wfs:Query>
    </wfs:GetFeature>';

    // HTTP-Context für den POST-Request einrichten
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/xml\r\n" .
                "User-Agent: WetterstationLeitstand/4.0\r\n",
            'content' => $xmlPayload,
            'timeout' => 3
        ]
    ]);

    $csvContent = @file_get_contents($url, false, $ctx);

    $zaeh = substr_count($csvContent, ';zäh;');
    $fliessend = substr_count($csvContent, ';fliessend;');
    $gestaut = substr_count($csvContent, ';gestaut;');
    $dicht = substr_count($csvContent, ';dicht;');


    $total = $zaeh + $fliessend + $gestaut + $dicht;
    if ($total > 0) {
        $trafficIndex = ($zaeh * 60. + $fliessend * 80. + $gestaut * 0. + $dicht * 100.) / $total;
        return round($trafficIndex);
    }

    return $defaultIndex;
}

function getTheoreticalInsolation($timestamp)
{
    $dt = new DateTime($timestamp);

    // 1. Tag des Jahres (1-365) und Stunde berechnen
    $dayOfYear = (int) $dt->format('z') + 1;
    $hour = (int) $dt->format('G') + ((int) $dt->format('i') / 60);

    // 2. Deklination der Sonne (Winkel zur Äquatorebene)
    $declination = 23.45 * sin(deg2rad((360 / 365) * ($dayOfYear - 80)));

    // 3. Stundenwinkel (12 Uhr Mittag = 0°, pro Stunde 15°)
    $hourAngle = 15 * ($hour - 12);

    // 4. Breitengrad für deine Region (Seevetal / Hamburg ca. 53.4)
    $latitude = 53.4;

    // 5. Sonnenhöhenwinkel (Solar Elevation Angle) berechnen
    $sinElevation = sin(deg2rad($latitude)) * sin(deg2rad($declination)) +
        cos(deg2rad($latitude)) * cos(deg2rad($declination)) * cos(deg2rad($hourAngle));

    $elevation = rad2deg(asin($sinElevation));

    // Wenn die Sonne unter dem Horizont steht -> 0 W/m²
    if ($elevation <= 0) {
        return 0;
    }

    // 6. Clear-Sky-Insolation nach vereinfachtem Haurwitz-Modell
    // Berücksichtigt die Abschwächung durch die Erdatmosphäre
    $solarConstant = 1361; // Solarkonstante außerhalb der Atmosphäre
    $theoretical = $solarConstant * sin(deg2rad($elevation)) * exp(-0.13 / sin(deg2rad($elevation)));

    return max(0, round($theoretical, 1));
}
