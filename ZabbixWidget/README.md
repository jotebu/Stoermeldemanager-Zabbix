# Zabbix-Widget „Störmeldejournal“

Aktuelle Version: **0.4.0**  
Unterstützte Zabbix-Version: **7.0**

Das Widget stellt Zabbix-Triggerereignisse in einer kompakten GLT-/BIS-ähnlichen Tabelle dar. Eingang,
erste Quittierung und Behebung werden dauerhaft in derselben Journalzeile zusammengeführt.

## Funktionen

- eine Störung mit ihrem gesamten Statusverlauf pro Zeile
- Zeitpunkte für Eingang, erste Quittierung und Behebung
- Anzeige des quittierenden Zabbix-Benutzers
- vier getrennte Zustände:
  - `Störung – unquittiert`
  - `Störung – quittiert`
  - `Behoben – unquittiert`
  - `Behoben – quittiert`
- Quittierung aktiver Störungen über den nativen Zabbix-Dialog
- nachträgliche Quittierung bereits behobener, bislang unquittierter Störungen
- Quittierkommentar als Mouse-over-Text am Statussymbol `ⓘ`
- Farblogik für Zustand und Priorität
- Sortierung nach dem ursprünglichen Eingang der Störung, neueste Meldung zuerst
- Filter nach Status, Priorität, Kategorie, Bereich, Alarm-ID und Datum
- Alarm-ID-Filter mit Einzelwerten, Listen, Bereichen und Ausschlüssen
- exakte Suche nach einem Kalendertag
- Suche innerhalb eines Von-/Bis-Zeitraums
- gezielte serverseitige Abfrage älterer Ereignisse
- automatische Ermittlung der Kategorie- und Bereichsauswahl aus den geladenen Ereignissen
- Beibehaltung der Filter bei automatischen Widget-Aktualisierungen
- deutsche Beschriftung
- Zugriff über die internen Zabbix-APIs und damit im Berechtigungskontext des angemeldeten Benutzers
- automatische Verwendung der Hostgruppe `Alarmmatrix`, wenn keine andere Gruppe ausgewählt wurde

## Benötigte Ereignisdaten

Die Engineering-Spalten werden aus folgenden Zabbix-Ereignis-Tags gelesen:

| Spalte im Widget | Bevorzugter Tag | Ebenfalls erkannt |
|---|---|---|
| Alarm-ID | `AlarmID` | `Alarm-ID`, `ID` |
| Kategorie | `Kategorie` | `Category` |
| Bereich | `Bereich` | `Area` |

Fehlt der Tag für die Alarm-ID, versucht das Widget zusätzlich, eine mindestens vierstellige Zahl aus dem
Ereignisnamen zu übernehmen. Fehlende Kategorie- oder Bereichstags bleiben leer.

## Voraussetzungen

- installiertes und lauffähiges Zabbix-Frontend 7.0
- Schreibzugriff per `sudo` auf `/usr/share/zabbix/modules`
- Zabbix-Hostgruppe `Alarmmatrix` oder eine andere im Widget auswählbare Hostgruppe
- Triggerereignisse der Symcon-Alarmmatrix
- Leseberechtigung des Zabbix-Benutzers für die betreffende Hostgruppe
- Rollenrecht zum Quittieren von Problemen, falls der Button **Quittieren** verwendet werden soll
- `git` für Installation und Aktualisierung
- `jq` nur für die optionale Manifestprüfung

## Erstinstallation auf Debian/Raspberry Pi OS

### 1. Repository herunterladen

```bash
cd /tmp
git clone \
  https://github.com/jotebu/Stoermeldemanager-Zabbix.git

cd /tmp/Stoermeldemanager-Zabbix
```

### 2. Widget in das Zabbix-Modulverzeichnis kopieren

```bash
sudo mkdir -p \
  /usr/share/zabbix/modules/stoermeldejournal

sudo cp -a \
  ZabbixWidget/stoermeldejournal/. \
  /usr/share/zabbix/modules/stoermeldejournal/

sudo chown -R root:root \
  /usr/share/zabbix/modules/stoermeldejournal

sudo find /usr/share/zabbix/modules/stoermeldejournal \
  -type d -exec chmod 755 {} +

sudo find /usr/share/zabbix/modules/stoermeldejournal \
  -type f -exec chmod 644 {} +
```

### 3. Dateien prüfen

```bash
find /usr/share/zabbix/modules/stoermeldejournal \
  -type f -name '*.php' \
  -exec php -l {} \;

jq . \
  /usr/share/zabbix/modules/stoermeldejournal/manifest.json
```

Alle PHP-Dateien müssen `No syntax errors detected` melden. Im Manifest muss die erwartete
Widget-Version stehen.

### 4. Modul in Zabbix erkennen und aktivieren

1. In Zabbix **Administration → Allgemein → Module** öffnen.
2. **Verzeichnis einlesen** anklicken.
3. In der Modulliste **Störmeldejournal** auswählen.
4. Den Modulstatus auf **Aktiviert** setzen und speichern.
5. Falls die Oberfläche noch alte Dateien verwendet, den Browser mit `Strg + F5` vollständig neu laden.

Ein Neustart des Zabbix-Servers ist normalerweise nicht erforderlich, da es sich um ein Frontend-Modul
handelt.

### 5. Widget zum Dashboard hinzufügen

1. Das gewünschte Zabbix-Dashboard öffnen.
2. **Dashboard bearbeiten** wählen.
3. **Widget hinzufügen** anklicken.
4. Als Typ **Störmeldejournal** auswählen.
5. Optional die gewünschte **Hostgruppe** festlegen. Ohne Auswahl wird `Alarmmatrix` verwendet.
6. Unter **Angezeigte Zeilen** die maximale Anzahl sichtbarer Journalzeilen einstellen.
7. **Hinzufügen** beziehungsweise **Übernehmen** wählen.
8. Anschließend das gesamte Dashboard **speichern**.

Danach kann das Widget unmittelbar über die Filterleiste bedient werden.

## Bedienung der Filter

### Alarm-ID

| Eingabe | Wirkung |
|---|---|
| `1001` | nur Alarm 1001 |
| `1001,1005,+1010` | mehrere einzelne Alarme |
| `1000-1099` | alle Alarm-IDs von 1000 bis 1099 |
| `1000-1099,-1050` | Bereich 1000–1099 ohne Alarm 1050 |
| `1000-1099,-1050-1060` | Bereich 1000–1099 ohne 1050–1060 |

### Datum

- **Datum** sucht exakt einen Kalendertag und deaktiviert währenddessen die Felder **Von** und **Bis**.
- **Von** und **Bis** suchen innerhalb eines einschließlich beider Grenztage definierten Zeitraums.
- Nur **Von** zeigt Ereignisse ab diesem Datum.
- Nur **Bis** zeigt Ereignisse bis zu diesem Datum.
- **Filter zurücksetzen** leert sämtliche Filter gemeinsam.

Datumswerte werden an den Widget-Controller übertragen. Dadurch kann Zabbix gezielt mehrere Jahre alte
Ereignisse laden, ohne bei jeder Aktualisierung die gesamte Ereignistabelle an den Browser zu senden.

## Aufbewahrung der Ereignisse

Das Widget kann nur Ereignisse anzeigen, die noch in der Zabbix-Datenbank vorhanden sind. Die gewünschte
Langzeitaufbewahrung muss deshalb zusätzlich unter **Administration → Bereinigung/Housekeeping → Ereignisse
und Alarme** eingestellt werden. Für diesen Alarmserver ist beispielsweise eine Aufbewahrung von zehn Jahren
sinnvoll. Die maximale von Zabbix unterstützte Aufbewahrungsdauer beträgt 25 Jahre.

Ohne Datumsfilter lädt das Widget die neuesten Ereignisse aus der verfügbaren Historie. Eine gezielte
Datums- oder Zeitraumssuche wird serverseitig auf den gewählten Zeitraum begrenzt.

## Widget aktualisieren

In einem bereits vorhandenen Checkout:

```bash
cd /tmp/Stoermeldemanager-Zabbix
git pull --ff-only

sudo cp -a \
  ZabbixWidget/stoermeldejournal/. \
  /usr/share/zabbix/modules/stoermeldejournal/

find /usr/share/zabbix/modules/stoermeldejournal \
  -type f -name '*.php' \
  -exec php -l {} \;

jq -r '.version' \
  /usr/share/zabbix/modules/stoermeldejournal/manifest.json
```

Danach in Zabbix unter **Administration → Allgemein → Module** erneut **Verzeichnis einlesen** wählen und
die Seite mit `Strg + F5` aktualisieren.

## Fehlerdiagnose

### Modul wird nicht gefunden

- Verzeichnis prüfen: `/usr/share/zabbix/modules/stoermeldejournal`
- Manifest prüfen: `/usr/share/zabbix/modules/stoermeldejournal/manifest.json`
- sicherstellen, dass sich die Dateien direkt in diesem Verzeichnis und nicht in einem zusätzlichen Unterordner befinden
- Dateirechte und Eigentümer prüfen
- in Zabbix nochmals **Verzeichnis einlesen** ausführen

### Widget zeigt keine Ereignisse

- ausgewählte Hostgruppe prüfen
- sicherstellen, dass Triggerereignisse für diese Hostgruppe existieren
- Zabbix-Benutzerrechte für die Hostgruppe prüfen
- gesetzte Filter über **Filter zurücksetzen** entfernen
- bei alten Ereignissen die Zabbix-Aufbewahrungsdauer kontrollieren

### Quittieren wird nicht angeboten

- prüfen, ob das Ereignis bereits quittiert wurde
- Zabbix-Rollenrecht **Probleme quittieren** kontrollieren
- Leseberechtigung für die Hostgruppe kontrollieren

## Versionsverlauf Zabbix-Widget

### Version 0.4.0

- feste Einstellung **Verlauf in Tagen** entfernt
- Filter **Datum** für genau einen Kalendertag ergänzt
- unabhängige Filter **Von** und **Bis** ergänzt
- Datumsbereiche werden serverseitig an die Zabbix-Ereignisabfrage übergeben
- dadurch gezielte Suche nach mehreren Jahre alten Ereignissen ermöglicht
- bisheriger Erklärungstext unter dem Alarm-ID-Filter entfernt

### Version 0.3.0

- interaktive Filterleiste für Status, Priorität, Kategorie, Bereich und Alarm-ID ergänzt
- Kategorien und Bereiche werden dynamisch aus den geladenen Ereignissen erzeugt
- Alarm-ID-Listen, inklusive Bereiche sowie positive und negative Regeln unterstützt
- Filterzustand bleibt während automatischer Widget-Aktualisierungen erhalten
- Zeilenbegrenzung wird erst nach dem Filtern angewendet
- statischen Statusfilter aus den Widget-Einstellungen entfernt

### Version 0.2.3

- Quittierbutton bleibt bei `Behoben – unquittiert` verfügbar
- bereits behobene Störungen können nachträglich quittiert und kommentiert werden
- Quittierkommentar wird mit `ⓘ` gekennzeichnet
- vollständiger Kommentar wird beim Überfahren des Status mit der Maus angezeigt

### Version 0.2.2

- sämtliche sichtbaren Beschriftungen auf Deutsch umgestellt
- feste Sortierung nach dem ursprünglichen Eingang der Störung eingeführt
- neueste Störung wird unabhängig vom späteren Quittier- oder Behebungszeitpunkt zuerst angezeigt

### Version 0.2.1

- vier eindeutige Zustände für aktiv/behoben und quittiert/unquittiert eingeführt
- Zeit und Benutzer der ersten echten Quittierung werden getrennt von der Behebung ausgewertet
- behobene, aber niemals quittierte Ereignisse werden nicht mehr automatisch als quittiert dargestellt
- Vorauswahl der Quittierung im nativen Zabbix-Dialog stabilisiert
- Farbzuordnung der vier Zustände korrigiert

### Version 0.2.0

- nativen Zabbix-Quittierdialog in das Journal integriert
- Quittierbutton für aktive, unquittierte Störungen ergänzt
- Eingabe eines Kommentars beim Quittieren ermöglicht
- Widget wird nach einer erfolgreichen Quittierung automatisch aktualisiert

### Version 0.1.1

- Abfrage der Quittierungsdaten an die Zabbix-7.0-API angepasst
- ungültige Benutzerfelder aus `selectAcknowledges` entfernt
- Benutzernamen werden anhand der Benutzer-ID separat über die Zabbix-API aufgelöst
- dadurch leere beziehungsweise fehlerhafte Journalabfragen behoben

### Version 0.1.0

- erste lauffähige, rein lesende Version des Störmeldejournals
- Zusammenführung von Problemereignis, Quittierung und Behebung in einer Tabellenzeile
- Anzeige von Alarm-ID, Priorität, Kategorie, Bereich, Meldung und Zeitpunkten
- Ereignis-Tags `AlarmID`, `Kategorie` und `Bereich` ausgewertet
- Standard-Hostgruppe `Alarmmatrix` eingeführt
- erste Status- und Prioritätsfarben ergänzt
