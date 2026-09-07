# Zabbix-Widget „Störmeldejournal“

Version 0.2.0 stellt Zabbix-Triggerereignisse in einer kompakten GLT-/BIS-ähnlichen Tabelle dar.

## Funktionen

- ein Ereignis pro Zeile
- Zeitpunkte für Kommen, erste Quittierung und Behebung
- quittierender Benutzer
- Zustände `Störung`, `Quittiert` und `Behoben`
- aktive Meldungen immer vor behobenen Meldungen
- Farblogik für Zustand und Priorität
- Filter für Hostgruppe, Zeitraum, Zustand und Zeilenanzahl
- Quittierung aktiver Störungen über den nativen Zabbix-Dialog
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

## Version 0.2.0

Berechtigte Benutzer können aktive Störungen direkt aus dem Widget quittieren. Der native Zabbix-Dialog
erlaubt dabei auch einen Kommentar; Benutzer und Zeitpunkt werden vollständig in Zabbix protokolliert.
