CREATE DATABASE IF NOT EXISTS deine_wetter_db;
USE deine_wetter_db;

CREATE TABLE IF NOT EXISTS wetterdaten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zeitstempel DATETIME NOT NULL,
    station_id VARCHAR(50) NOT NULL,
    
    -- Luftdruck
    rbar DECIMAL(6,2) COMMENT 'Relativer Luftdruck in hPa',
    abar DECIMAL(6,2) COMMENT 'Absoluter Luftdruck in hPa',
    
    -- Innen
    intem DECIMAL(4,1) COMMENT 'Innentemperatur in °C',
    inhum INT COMMENT 'Innenluftfeuchtigkeit in %',
    
    -- Außen (7-in-1 Sensor)
    t1cn INT COMMENT 'Verbindungsstatus Sensor 1',
    t1bat INT COMMENT 'Batteriestatus Sensor 1',
    t1tem DECIMAL(4,1) COMMENT 'Außentemperatur in °C',
    t1hum INT COMMENT 'Außenluftfeuchtigkeit in %',
    t1feels DECIMAL(4,1) COMMENT 'Gefühlte Temperatur in °C',
    t1chill DECIMAL(4,1) COMMENT 'Windchill in °C',
    t1dew DECIMAL(4,1) COMMENT 'Taupunkt in °C',
    
    -- Wind
    t1ws DECIMAL(5,1) COMMENT 'Windgeschw. in m/s',
    t1ws10mav DECIMAL(5,1) COMMENT 'Windgeschw. 10 Min Ø in m/s',
    t1wgust DECIMAL(5,1) COMMENT 'Windböen in m/s',
    t1wdir INT COMMENT 'Windrichtung in Grad',
    
    -- Regen
    t1rainra DECIMAL(6,3) COMMENT 'Regenrate in mm/h',
    t1rainhr DECIMAL(6,3) COMMENT 'Regen stündlich in mm',
    t1raindy DECIMAL(6,3) COMMENT 'Regen täglich in mm',
    t1rainwy DECIMAL(6,3) COMMENT 'Regen wöchentlich in mm',
    t1rainmth DECIMAL(6,3) COMMENT 'Regen monatlich in mm',
    t1rainyr DECIMAL(6,3) COMMENT 'Regen jährlich in mm',
    
    -- Sonne / UV
    t1uvi DECIMAL(4,1) COMMENT 'UV Index',
    t1solrad DECIMAL(6,2) COMMENT 'Lichtintensität in W/m2',
    
    -- System
    apiver VARCHAR(10) COMMENT 'API Version'
);


DROP TABLE IF EXISTS wetter_tagesstatistik;

CREATE TABLE wetter_tagesstatistik (
    datum DATE PRIMARY KEY,
    
    -- Temperaturen Außen
    min_t1tem DECIMAL(4,1) COMMENT 'Min Außentemperatur',
    max_t1tem DECIMAL(4,1) COMMENT 'Max Außentemperatur',
    min_t1feels DECIMAL(4,1) COMMENT 'Min gefühlte Temp',
    max_t1feels DECIMAL(4,1) COMMENT 'Max gefühlte Temp',
    min_t1dew DECIMAL(4,1) COMMENT 'Min Taupunkt',
    max_t1dew DECIMAL(4,1) COMMENT 'Max Taupunkt',
    
    -- Feuchtigkeit Außen
    min_t1hum INT COMMENT 'Min Außenluftfeuchtigkeit',
    max_t1hum INT COMMENT 'Max Außenluftfeuchtigkeit',
    
    -- Wind
    max_t1ws DECIMAL(5,1) COMMENT 'Max Windgeschwindigkeit',
    max_t1wgust DECIMAL(5,1) COMMENT 'Stärkste Windböe',
    
    -- Luftdruck
    min_rbar DECIMAL(6,2) COMMENT 'Min relativer Luftdruck',
    max_rbar DECIMAL(6,2) COMMENT 'Max relativer Luftdruck',
    
    -- Sonne & UV
    max_t1uvi DECIMAL(4,1) COMMENT 'Max UV-Index',
    max_t1solrad DECIMAL(6,2) COMMENT 'Max Lichtintensität',
    
    -- Regen
    regen_gesamt DECIMAL(6,3) COMMENT 'Tagesniederschlag',
    max_t1rainra DECIMAL(6,3) COMMENT 'Höchste Regenrate (Starkregen)',
    
    -- Innen (optional, aber oft interessant)
    min_intem DECIMAL(4,1) COMMENT 'Min Innentemperatur',
    max_intem DECIMAL(4,1) COMMENT 'Max Innentemperatur',
    min_inhum INT COMMENT 'Min Innenluftfeuchtigkeit',
    max_inhum INT COMMENT 'Max Innenluftfeuchtigkeit'
);