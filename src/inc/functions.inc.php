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

function calculateDewPoint($temp, $rh)
{
    if ($temp === null || $rh === null || $rh <= 0) {
        return null;
    }

    $a = 17.62;
    $b = 243.12;
    $gamma = (($a * $temp) / ($b + $temp)) + log($rh / 100);
    return ($b * $gamma) / ($a - $gamma);
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
        $noiseIndex = ($zaeh * 60. + $fliessend * 80. + $gestaut * 0. + $dicht * 100.) / $total;
        $trafficIndex = ($zaeh * 60. + $fliessend * 0. + $gestaut * 100. + $dicht * 40.) / $total;
        return [round($trafficIndex), round($noiseIndex)];
    }

    return [null, null]; // Fallback, falls keine Daten verfügbar sind
}

function getTheoreticalInsolation($timestamp)
{
    $timezone = new DateTimeZone('Europe/Berlin');
    $dt = new DateTime($timestamp, $timezone);
    $dt->setTimezone($timezone);

    $latitude = 53.4;
    $longitude = 10.03;
    $dayOfYear = (int) $dt->format('z') + 1;
    $hour = (int) $dt->format('G');
    $minute = (int) $dt->format('i');
    $second = (int) $dt->format('s');
    $timezoneOffsetHours = $timezone->getOffset($dt) / 3600;

    $decimalHour = $hour + ($minute / 60) + ($second / 3600);
    $gamma = (2 * M_PI / 365) * ($dayOfYear - 1 + (($decimalHour - 12) / 24));

    $equationOfTime = 229.18 * (
        0.000075
        + 0.001868 * cos($gamma)
        - 0.032077 * sin($gamma)
        - 0.014615 * cos(2 * $gamma)
        - 0.040849 * sin(2 * $gamma)
    );

    $declination = 0.006918
        - 0.399912 * cos($gamma)
        + 0.070257 * sin($gamma)
        - 0.006758 * cos(2 * $gamma)
        + 0.000907 * sin(2 * $gamma)
        - 0.002697 * cos(3 * $gamma)
        + 0.00148 * sin(3 * $gamma);

    $timeOffset = $equationOfTime + (4 * $longitude) - (60 * $timezoneOffsetHours);
    $trueSolarTime = fmod(($decimalHour * 60) + $timeOffset, 1440);
    if ($trueSolarTime < 0) {
        $trueSolarTime += 1440;
    }

    $hourAngle = ($trueSolarTime / 4) - 180;
    if ($hourAngle < -180) {
        $hourAngle += 360;
    }

    $cosZenith = sin(deg2rad($latitude)) * sin($declination)
        + cos(deg2rad($latitude)) * cos($declination) * cos(deg2rad($hourAngle));
    $cosZenith = max(-1, min(1, $cosZenith));

    if ($cosZenith <= 0) {
        return 0;
    }

    $theoretical = 1098 * $cosZenith * exp(-0.059 / $cosZenith);

    return max(0, round($theoretical, 1));
}
