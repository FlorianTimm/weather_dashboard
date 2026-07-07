# Weather Dashboard

Dieses Projekt verarbeitet Messdaten einer **Bresser 7-in-1 Wetterstation** ueber das Gateway **El Nino** und zeigt sie in einem webbasierten Dashboard an.

Der aktuelle Stand ist ein Arbeitsstand aus einem Gemini-Chat. Das Projekt ist noch nicht fertig und soll nun manuell weiterentwickelt, bereinigt und produktionsreif gemacht werden.

## Ziel

Das Gateway sendet ungefaehr einmal pro Minute Wetterdaten per HTTP-Request an `src/data/upload.php`. Die eingehenden Messwerte werden in einer MariaDB gespeichert. Das Dashboard liest diese Daten ueber `src/api.php` aus und visualisiert aktuelle Werte, Diagramme und berechnete Kennzahlen.

Zusaetzlich erzeugt `src/cron.php` einmal taeglich Tagesauswertungen fuer den jeweils vergangenen Tag.

## Funktionen im aktuellen Stand

- Empfang von Wetterstationsdaten per GET-Request an `data/upload.php`
- Authentifizierung des Gateways ueber Stations-ID und Passwort
- Speicherung der Rohdaten in der Tabelle `wetterdaten`
- Tagesstatistik in der Tabelle `wetter_tagesstatistik`
- JSON-API fuer Live-Daten und Diagrammdaten
- Dashboard mit aktuellen Wetterwerten, Rekorden und Verlaufsdiagrammen
- Berechnung zusaetzlicher Werte wie absolute Feuchte, Wind in km/h und theoretische Sonneneinstrahlung
- Einbindung einer Verkehrslage-Abfrage fuer einen lokalen Verkehrs-/Laermindex
- PWA-Grundstruktur mit Manifest und Service Worker

## Projektstruktur

```text
src/
	index.htm              Dashboard
	style.css              Styles fuer das Dashboard
	script.js              Frontend-Logik und Chart-Aktualisierung
	api.php                JSON-API fuer Live- und Chart-Daten
	data/upload.php        Endpunkt fuer das El-Nino-Gateway
	cron.php               Taegliche Tagesauswertung
	db.inc.php             Datenbankverbindung
	functions.inc.php      Hilfsfunktionen und Berechnungen
	config.example.inc.php Beispielkonfiguration
	config.inc.php         Lokale Konfiguration, nicht veroeffentlichen
	manifest.json          PWA-Manifest
	sw.js                  Service Worker

etc/
	tabellen.sql           SQL-Schema fuer MariaDB
	old/                   Alte Zwischenstaende
```

## Voraussetzungen

- Webserver mit PHP
- MariaDB oder MySQL
- PHP-PDO mit MySQL-Treiber
- Bresser 7-in-1 Wetterstation
- El-Nino-Gateway mit konfigurierbarem Upload-Endpunkt
- Cron oder ein anderer Scheduler fuer die taegliche Auswertung

## Installation

1. Dateien aus `src/` auf den Webserver kopieren.
2. MariaDB-Datenbank anlegen.
3. SQL-Schema aus `etc/tabellen.sql` importieren.
4. `src/config.example.inc.php` nach `src/config.inc.php` kopieren.
5. Datenbankzugang und Wetterstations-Zugangsdaten in `src/config.inc.php` eintragen.

Beispiel:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'weather_user');
define('DB_PASS', 'secret');
define('DB_NAME', 'weather');

define('WEATHER_STATION_ID', 'station123');
define('WEATHER_STATION_PASSWORD', 'secret456');
```

`config.inc.php` enthaelt lokale Zugangsdaten und sollte nicht in ein oeffentliches Repository uebernommen werden.

## Gateway-Konfiguration

Das El-Nino-Gateway muss so konfiguriert werden, dass es die Daten an den Upload-Endpunkt sendet:

```text
https://example.org/weather/data/upload.php
```

Der Request muss mindestens die in `config.inc.php` gesetzten Zugangsdaten enthalten:

```text
wsid=station123&wspw=secret456
```

Weitere Parameter werden, sofern vorhanden, in `wetterdaten` gespeichert. Dazu gehoeren unter anderem:

- `datetime` fuer den Zeitstempel der Messung
- `rbar`, `abar` fuer relativen und absoluten Luftdruck
- `intem`, `inhum` fuer Innenwerte
- `t1tem`, `t1hum`, `t1feels`, `t1chill`, `t1dew` fuer Aussenwerte
- `t1ws`, `t1ws10mav`, `t1wgust`, `t1wdir` fuer Wind
- `t1rainra`, `t1rainhr`, `t1raindy`, `t1rainwy`, `t1rainmth`, `t1rainyr` fuer Regen
- `t1uvi`, `t1solrad` fuer UV-Index und Solarstrahlung
- `apiver` fuer die API-Version

Bei erfolgreicher Speicherung antwortet `upload.php` mit `Success`. Bei falschen Zugangsdaten wird `401 Unauthorized` zurueckgegeben.

## Datenbank

Das Schema liegt in `etc/tabellen.sql`.

Die wichtigsten Tabellen sind:

- `wetterdaten`: Rohdaten der Wetterstation, ein Datensatz pro Upload
- `wetter_tagesstatistik`: Tagesaggregate fuer Minimum, Maximum, Regen, Wind, Luftdruck und weitere Kennzahlen

Hinweis: Der aktuelle Code speichert auch `traffic_flow` in `wetterdaten`. Das SQL-Schema sollte beim Weiterentwickeln mit dem Code abgeglichen werden, falls die Spalte in der lokalen Datenbank noch fehlt.

## Cronjob

`src/cron.php` erzeugt die Tagesstatistik fuer den vergangenen Tag. Der Job sollte einmal taeglich nach Mitternacht laufen, zum Beispiel um 00:10 Uhr.

Beispiel:

```cron
10 0 * * * /usr/bin/php /pfad/zum/projekt/src/cron.php
```

Die Statistik wird per `ON DUPLICATE KEY UPDATE` aktualisiert. Dadurch kann der Cronjob erneut ausgefuehrt werden, ohne doppelte Tageszeilen zu erzeugen.

## API

`src/api.php` stellt JSON-Daten fuer das Dashboard bereit.

Live-Daten:

```text
api.php?action=live
```

Diagrammdaten:

```text
api.php?action=chart&range=24h
api.php?action=chart&range=7d
api.php?action=chart&range=30d
```

## Dashboard

Das Dashboard wird ueber `src/index.htm` aufgerufen. Es aktualisiert die Live-Anzeige alle 60 Sekunden und laedt Diagrammdaten ueber die API nach.

Der aktuelle Stand enthaelt unter anderem:

- Live-Werte fuer Innen- und Aussenklima
- Wind-, Regen-, Solar- und Bewoelkungsanzeigen
- Rekordwerte aus den Tagesstatistiken
- Empfehlungen fuer Lueftung anhand absoluter Feuchte
- Lokaler Verkehrs-/Laermindex
- Verlaufsdiagramme fuer 24 Stunden, 7 Tage und 30 Tage

## Entwicklungsstand und offene Punkte

Das Projekt ist ausdruecklich noch nicht fertig. Bekannte Punkte fuer die weitere manuelle Bearbeitung:

- Code und SQL-Schema abgleichen, insbesondere zusaetzliche Spalten wie `traffic_flow`
- Include-Pfade pruefen und vereinheitlichen
- Fehlerbehandlung und Logging fuer Produktion ueberarbeiten
- Eingehende Gateway-Daten robuster validieren und typisieren
- Sicherheitskonzept pruefen, insbesondere fuer oeffentlich erreichbare Endpunkte
- Dashboard-Texte, Darstellung und Responsiveness weiter verbessern
- Tests oder einfache Pruefscripte fuer Upload, API und Cron ergaenzen
- Alte Dateien unter `etc/old/` pruefen und bei Bedarf entfernen

## Sicherheitshinweise

- `config.inc.php` enthaelt Zugangsdaten und darf nicht veroeffentlicht werden.
- Der Upload-Endpunkt sollte nur die erwarteten Gateway-Zugangsdaten akzeptieren.
- PHP-Fehlerausgaben sollten im Produktivbetrieb deaktiviert und stattdessen geloggt werden.
- Bei oeffentlichem Betrieb sollte zusaetzlich ueber HTTPS, Rate Limiting und serverseitige Zugriffsbeschraenkungen nachgedacht werden.

## Lizenz

Eine Lizenzdatei ist im Repository vorhanden. Details stehen in `LICENCE`.
