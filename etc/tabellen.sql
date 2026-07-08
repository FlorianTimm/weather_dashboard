CREATE DATABASE IF NOT EXISTS deine_wetter_db;

USE deine_wetter_db;

CREATE TABLE IF NOT EXISTS wetterdaten(
    id int AUTO_INCREMENT PRIMARY KEY,
    zeitstempel DATETIME NOT NULL,
    station_id varchar(50) NOT NULL,
    -- Luftdruck
    rbar DECIMAL(6, 2) COMMENT 'Relativer Luftdruck in hPa',
    abar DECIMAL(6, 2) COMMENT 'Absoluter Luftdruck in hPa',
    -- Innen
    intem DECIMAL(4, 1) COMMENT 'Innentemperatur in °C',
    inhum int COMMENT 'Innenluftfeuchtigkeit in %',
    -- Außen (7-in-1 Sensor)
    t1cn int COMMENT 'Verbindungsstatus Sensor 1',
    t1bat int COMMENT 'Batteriestatus Sensor 1',
    t1tem DECIMAL(4, 1) COMMENT 'Außentemperatur in °C',
    t1hum int COMMENT 'Außenluftfeuchtigkeit in %',
    t1feels DECIMAL(4, 1) COMMENT 'Gefühlte Temperatur in °C',
    t1chill DECIMAL(4, 1) COMMENT 'Windchill in °C',
    t1dew DECIMAL(4, 1) COMMENT 'Taupunkt in °C',
    -- Wind
    t1ws DECIMAL(5, 1) COMMENT 'Windgeschw. in m/s',
    t1ws10mav DECIMAL(5, 1) COMMENT 'Windgeschw. 10 Min Ø in m/s',
    t1wgust DECIMAL(5, 1) COMMENT 'Windböen in m/s',
    t1wdir int COMMENT 'Windrichtung in Grad',
    -- Regen
    t1rainra DECIMAL(6, 3) COMMENT 'Regenrate in mm/h',
    t1rainhr DECIMAL(6, 3) COMMENT 'Regen stündlich in mm',
    t1raindy DECIMAL(6, 3) COMMENT 'Regen täglich in mm',
    t1rainwy DECIMAL(6, 3) COMMENT 'Regen wöchentlich in mm',
    t1rainmth DECIMAL(6, 3) COMMENT 'Regen monatlich in mm',
    t1rainyr DECIMAL(6, 3) COMMENT 'Regen jährlich in mm',
    -- Sonne / UV
    t1uvi DECIMAL(4, 1) COMMENT 'UV Index',
    t1solrad DECIMAL(6, 2) COMMENT 'Lichtintensität in W/m2',
    -- System
    apiver varchar(10) COMMENT 'API Version'
);

DROP TABLE IF EXISTS wetter_tagesstatistik;

CREATE TABLE wetter_tagesstatistik(
    datum date PRIMARY KEY,
    -- Temperaturen Außen
    min_t1tem DECIMAL(4, 1) COMMENT 'Min Außentemperatur',
    max_t1tem DECIMAL(4, 1) COMMENT 'Max Außentemperatur',
    avg_t1tem DECIMAL(5, 2) COMMENT 'Durchschnitt Außentemperatur',
    min_t1tem_at DATETIME COMMENT 'Zeitpunkt Min Außentemperatur (UTC)',
    max_t1tem_at DATETIME COMMENT 'Zeitpunkt Max Außentemperatur (UTC)',
    min_t1feels DECIMAL(4, 1) COMMENT 'Min gefühlte Temp',
    max_t1feels DECIMAL(4, 1) COMMENT 'Max gefühlte Temp',
    avg_t1feels DECIMAL(5, 2) COMMENT 'Durchschnitt gefühlte Temp',
    min_t1feels_at DATETIME COMMENT 'Zeitpunkt Min gefühlte Temp (UTC)',
    max_t1feels_at DATETIME COMMENT 'Zeitpunkt Max gefühlte Temp (UTC)',
    min_t1dew DECIMAL(4, 1) COMMENT 'Min Taupunkt',
    max_t1dew DECIMAL(4, 1) COMMENT 'Max Taupunkt',
    avg_t1dew DECIMAL(5, 2) COMMENT 'Durchschnitt Taupunkt',
    min_t1dew_at DATETIME COMMENT 'Zeitpunkt Min Taupunkt (UTC)',
    max_t1dew_at DATETIME COMMENT 'Zeitpunkt Max Taupunkt (UTC)',
    -- Feuchtigkeit Außen
    min_t1hum int COMMENT 'Min Außenluftfeuchtigkeit',
    max_t1hum int COMMENT 'Max Außenluftfeuchtigkeit',
    avg_t1hum DECIMAL(5, 2) COMMENT 'Durchschnitt Außenluftfeuchtigkeit',
    min_t1hum_at DATETIME COMMENT 'Zeitpunkt Min Außenluftfeuchtigkeit (UTC)',
    max_t1hum_at DATETIME COMMENT 'Zeitpunkt Max Außenluftfeuchtigkeit (UTC)',
    -- Wind
    max_t1ws DECIMAL(5, 1) COMMENT 'Max Windgeschwindigkeit',
    max_t1ws_at DATETIME COMMENT 'Zeitpunkt Max Windgeschwindigkeit (UTC)',
    max_t1wgust DECIMAL(5, 1) COMMENT 'Stärkste Windböe',
    max_t1wgust_at DATETIME COMMENT 'Zeitpunkt stärkste Windböe (UTC)',
    -- Luftdruck
    min_rbar DECIMAL(6, 2) COMMENT 'Min relativer Luftdruck',
    max_rbar DECIMAL(6, 2) COMMENT 'Max relativer Luftdruck',
    avg_rbar DECIMAL(7, 3) COMMENT 'Durchschnitt relativer Luftdruck',
    min_rbar_at DATETIME COMMENT 'Zeitpunkt Min relativer Luftdruck (UTC)',
    max_rbar_at DATETIME COMMENT 'Zeitpunkt Max relativer Luftdruck (UTC)',
    -- Sonne & UV
    max_t1uvi DECIMAL(4, 1) COMMENT 'Max UV-Index',
    max_t1uvi_at DATETIME COMMENT 'Zeitpunkt Max UV-Index (UTC)',
    max_t1solrad DECIMAL(6, 2) COMMENT 'Max Lichtintensität',
    max_t1solrad_at DATETIME COMMENT 'Zeitpunkt Max Lichtintensität (UTC)',
    -- Regen
    regen_gesamt DECIMAL(6, 3) COMMENT 'Tagesniederschlag',
    max_t1rainra DECIMAL(6, 3) COMMENT 'Höchste Regenrate (Starkregen)',
    max_t1rainra_at DATETIME COMMENT 'Zeitpunkt höchste Regenrate (UTC)',
    -- Innen (optional, aber oft interessant)
    min_intem DECIMAL(4, 1) COMMENT 'Min Innentemperatur',
    max_intem DECIMAL(4, 1) COMMENT 'Max Innentemperatur',
    avg_intem DECIMAL(5, 2) COMMENT 'Durchschnitt Innentemperatur',
    min_intem_at DATETIME COMMENT 'Zeitpunkt Min Innentemperatur (UTC)',
    max_intem_at DATETIME COMMENT 'Zeitpunkt Max Innentemperatur (UTC)',
    min_inhum int COMMENT 'Min Innenluftfeuchtigkeit',
    max_inhum int COMMENT 'Max Innenluftfeuchtigkeit',
    avg_inhum DECIMAL(5, 2) COMMENT 'Durchschnitt Innenluftfeuchtigkeit',
    min_inhum_at DATETIME COMMENT 'Zeitpunkt Min Innenluftfeuchtigkeit (UTC)',
    max_inhum_at DATETIME COMMENT 'Zeitpunkt Max Innenluftfeuchtigkeit (UTC)'
);

