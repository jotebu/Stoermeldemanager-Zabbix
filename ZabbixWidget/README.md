# Zabbix-Widget „Störmeldejournal“

Version 0.4.0 stellt Zabbix-Triggerereignisse in einer kompakten GLT-/BIS-ähnlichen Tabelle dar.

## Funktionen

- ein Ereignis pro Zeile
- Zeitpunkte für Kommen, erste Quittierung und Behebung
- quittierender Benutzer
- getrennte Zustände für aktive/behobene sowie quittierte/unquittierte Meldungen
- Farblogik für Zustand und Priorität
- Filter für Hostgruppe und Zeilenanzahl
- Quittierung aktiver Störungen über den nativen Zabbix-Dialog
- nachträgliche Quittierung bereits behobener, bislang unquittierter Störungen einschließlich Kommentar
- Anzeige des Quittierkommentars als Tooltip am Status, gekennzeichnet durch ein Informationszeichen
- direkt bedienbare Filter für Status, Priorität, Kategorie, Bereich, Alarm-ID und Datum
- Sortierung ausschließlich nach dem ursprünglichen Eingang der Störung, neueste Meldung zuerst
- vollständig deutsche Beschriftung des Widgets
- Standard-Hostgruppe `Alarmmatrix`, falls im Widget keine Gruppe ausgewählt wurde
- Zugriff ausschließlich über die internen Zabbix-APIs und damit innerhalb der Rechte des angemeldeten Benutzers

Die Spalten Alarm-ID, Kategorie und Bereich werden aus den Ereignis-Tags `AlarmID`, `Kategorie` und `Bereich` gelesen. Solange diese Tags noch nicht von der Alarmmatrix-Synchronisierung angelegt wurden, bleiben die betreffenden Felder leer. Die Alarm-ID wird zusätzlich aus einer mindestens vierstelligen Zahl im Ereignisnamen abgeleitet.

## Installation auf Debian/Raspberry Pi OS

Das Verzeichnis `stoermeldejournal` muss vollständig in das Modulverzeichnis des Zabbix-Frontends kopiert werden:

```bash
sudo cp -a stoermeldejournal /usr/share/zabbix/modules/
sudo chown -R root:root /usr/share/zabbix/modules/stoermeldejournal
sudo find /usr/share/zabbix/modules/stoermeldejournal -type d -exec chmod 755 {} +
sudo find /usr/share/zabbix/modules/stoermeldejournal -type f -exec chmod 644 {} +
```

Danach in Zabbix:

1. `Administration -> Allgemein -> Module` öffnen.
2. `Verzeichnis einlesen` anklicken.
3. Das Modul `Störmeldejournal` aktivieren.
4. Im gewünschten Dashboard ein neues Widget vom Typ `Störmeldejournal` hinzufügen.

## Version 0.4.0

Berechtigte Benutzer können aktive Störungen direkt aus dem Widget quittieren. Der native Zabbix-Dialog
erlaubt dabei auch einen Kommentar; Benutzer und Zeitpunkt werden vollständig in Zabbix protokolliert.
Behobene Ereignisse werden getrennt als `Behoben – unquittiert` oder `Behoben – quittiert` dargestellt.
Bei `Behoben – unquittiert` bleibt die Aktion `Quittieren` verfügbar.
Vorhandene Quittierkommentare werden durch `ⓘ` am Status gekennzeichnet und beim Überfahren mit der Maus angezeigt.

Die Alarm-ID-Eingabe unterstützt Einzelwerte (`1001`), mehrere Werte (`1001,1005`), Bereiche
(`1000-1099`) und Ausschlüsse (`1000-1099,-1050-1060`). Die Filter bleiben bei automatischen
Widget-Aktualisierungen erhalten. Die eingestellte Zeilenbegrenzung wird erst nach dem Filtern angewendet.

Die frühere feste Begrenzung `Verlauf in Tagen` entfällt. Ohne Datumsfilter zeigt das Journal die neuesten
Ereignisse aus der gesamten noch in Zabbix gespeicherten Historie. Über `Datum` wird genau ein Kalendertag
gesucht. Alternativ grenzen `Von` und `Bis` einen beliebigen Zeitraum ein; beide Grenzen können auch einzeln
verwendet werden. Die Datumswerte werden an den Server übertragen, damit auch mehrere Jahre alte Ereignisse
gezielt aus Zabbix geladen werden können.
