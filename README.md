# Alarmmatrix für IP-Symcon und Zabbix

[![IP-Symcon 9.0](https://img.shields.io/badge/IP--Symcon-9.0%2B-blue)](https://www.symcon.de/)
[![Zabbix 7.0](https://img.shields.io/badge/Zabbix-7.0-red)](https://www.zabbix.com/)

Version 0.1.1 implementiert den Laufzeitpfad der zentralen Alarmmatrix:

- Import der semikolongetrennten CSV mit `SchemaVersion=1`
- strikte Prüfung der 21 vereinbarten Spalten
- Prüfung der IP-Symcon-Variablen-IDs und Variablentypen
- Operatoren `EQ`, `NE`, `GT`, `GE`, `LT`, `LE`, `BETWEEN`, `OUTSIDE`
- Hysterese bei numerischen Grenzwerten
- deutsche Dezimalwerte mit Komma sowie Dezimalwerte mit Punkt
- Einschaltverzögerung für kommende Alarme; Rücksetzungen werden sofort gesendet
- Abonnement der Variablenänderungen über `RegisterMessage`/`MessageSink`
- Übertragung von `alarm.<ID> = 0/1` über das native Zabbix-Sender-Protokoll
- Wiederholung fehlgeschlagener Zustandswechsel
- sofortige Rückstellung nach erfolgreicher Alarmübertragung, unabhängig vom
  Wiederholungsintervall für Sendefehler
- Importbericht und Zustandsvariablen in IP-Symcon

Unter `ZabbixWidget/` befindet sich zusätzlich das eigenständige Zabbix-Frontend-Widget
**Störmeldejournal**. Version 0.2.1 zeigt Kommen, Quittierung und Behebung eines Alarms zusammengefasst
in einer GLT-/BIS-ähnlichen Tabellenzeile an. Berechtigte Benutzer können aktive Störungen über den nativen
Zabbix-Dialog direkt aus dem Journal quittieren. Installationshinweise stehen in `ZabbixWidget/README.md`.

## Standardkonfiguration

- CSV: `/var/lib/symcon/user/alarmserver/alarmmatrix.csv`
- Zabbix-Port: `10051`
- technischer Zabbix-Host: `Gebaeudetechnik`

Die IP-Adresse beziehungsweise der DNS-Name des Zabbix-Servers bleibt konfigurierbar, weil der Alarmserver noch nicht an seinem endgültigen Standort betrieben wird.

Unter `examples/` liegt eine Beispieldatei. Vor dem Import muss dort die
Platzhalter-ID `12345` durch die ID einer vorhandenen booleschen Symcon-Variable
ersetzt werden.

## CSV-Kopfzeile

```text
SchemaVersion;ID;Aktiv;IPS_VariableID;Vergleich;Grenzwert1;Grenzwert2;Hysterese;Kurztext;Langtext;Kategorie;Bereich;Prioritaet;Quittierung;Email;Push;Verzoegerung_s;Eskalation_min;Aktivierungsgruppe;Kommentar;Zabbix_Key
```

## Sicherheit

Der Button `Alle aktuellen Zustände senden` sollte erst verwendet werden, wenn die passenden Trapper-Items in Zabbix existieren. Der Verbindungstest verwendet das bereits eingerichtete Test-Item `alarm.1001` und sendet den Wert `0`.

Die automatische Zabbix-API-Synchronisierung für Items, Trigger und Tags folgt in Version 0.2, nachdem der Laufzeitpfad auf dem echten Symcon-System geprüft wurde.

## Installation über IP-Symcon

1. In der IP-Symcon-Verwaltungskonsole den **Module Control** öffnen.
2. **Modul hinzufügen** auswählen.
3. Diese Repository-URL eintragen:

   ```text
   https://github.com/jotebu/Stoermeldemanager-Zabbix
   ```

4. Danach eine Instanz **Alarmmatrix / Zabbix** anlegen.

Für den ersten Verbindungstest werden als technischer Host
`Gebaeudetechnik`, Port `10051` und das bereits vorhandene Trapper-Item
`alarm.1001` verwendet.
