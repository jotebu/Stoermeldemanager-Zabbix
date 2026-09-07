# Störmeldemanager für IP-Symcon und Zabbix

[![IP-Symcon 9.0](https://img.shields.io/badge/IP--Symcon-9.0%2B-blue)](https://www.symcon.de/)
[![Zabbix 7.0](https://img.shields.io/badge/Zabbix-7.0-red)](https://www.zabbix.com/)

Dieses Repository enthält zwei getrennt versionierte Komponenten:

| Komponente | Aktuelle Version | Aufgabe |
|---|---:|---|
| **AlarmMatrix für IP-Symcon** | 0.1.1 | CSV einlesen, Alarmbedingungen auswerten und Zustände an Zabbix senden |
| **Zabbix-Widget Störmeldejournal** | 0.4.0 | Störungen anzeigen, filtern, quittieren und als Journal auswerten |

Die vorgesehene Architektur lautet:

```text
Excel → CSV → IP-Symcon AlarmMatrix → Zabbix → Störmeldejournal / E-Mail / ntfy
```

## AlarmMatrix für IP-Symcon

### Funktionen

- Import einer semikolongetrennten CSV mit `SchemaVersion=1`
- strikte Prüfung der 21 vereinbarten Spalten
- Prüfung der IP-Symcon-Variablen-IDs und Variablentypen
- Operatoren `EQ`, `NE`, `GT`, `GE`, `LT`, `LE`, `BETWEEN` und `OUTSIDE`
- Hysterese bei numerischen Grenzwerten
- Unterstützung deutscher Dezimalwerte mit Komma und internationaler Werte mit Punkt
- Einschaltverzögerung für kommende Alarme
- sofortige Übertragung von Rückstellungen
- Abonnement der Variablenänderungen über `RegisterMessage` und `MessageSink`
- Übertragung von `alarm.<ID> = 0/1` über das native Zabbix-Sender-Protokoll
- Wiederholung fehlgeschlagener Übertragungen
- Importbericht und Zustandsvariablen in IP-Symcon
- manueller Verbindungstest zum Zabbix-Server
- erneutes Senden aller aktuellen Alarmzustände

### Installation in IP-Symcon

1. In der IP-Symcon-Verwaltungskonsole **Module Control** öffnen.
2. **Modul hinzufügen** auswählen.
3. Folgende Repository-URL eintragen:

   ```text
   https://github.com/jotebu/Stoermeldemanager-Zabbix
   ```

4. Danach eine Instanz vom Typ **Alarmmatrix / Zabbix** anlegen.
5. Zabbix-Server, Port, technischen Hostnamen und CSV-Datei konfigurieren.
6. Zunächst **Verbindung testen** verwenden.
7. Erst wenn die zugehörigen Trapper-Items in Zabbix existieren, die Alarmmatrix importieren und alle aktuellen Zustände senden.

### Standardkonfiguration

| Einstellung | Standardwert |
|---|---|
| CSV-Datei | `/var/lib/symcon/user/alarmserver/alarmmatrix.csv` |
| Zabbix-Port | `10051` |
| Technischer Zabbix-Host | `Gebaeudetechnik` |
| Schlüssel | `alarm.<ID>` |

Die IP-Adresse beziehungsweise der DNS-Name des Zabbix-Servers ist konfigurierbar. Unter `examples/`
liegt eine Beispieldatei. Vor dem Import muss die Platzhalter-ID `12345` durch die ID einer vorhandenen
IP-Symcon-Variable ersetzt werden.

### CSV-Schema

```text
SchemaVersion;ID;Aktiv;IPS_VariableID;Vergleich;Grenzwert1;Grenzwert2;Hysterese;Kurztext;Langtext;Kategorie;Bereich;Prioritaet;Quittierung;Email;Push;Verzoegerung_s;Eskalation_min;Aktivierungsgruppe;Kommentar;Zabbix_Key
```

Die CSV ist die zentrale Engineering-Datei. IP-Symcon entscheidet anhand der Variablenwerte und Regeln,
ob eine Störung vorliegt. Zur Laufzeit werden nur der technische Host, der Schlüssel und der Zustand `0`
oder `1` an das entsprechende Zabbix-Trapper-Item übertragen.

### Sicherheit und aktueller Entwicklungsstand

Der Button **Alle aktuellen Zustände senden** sollte erst verwendet werden, wenn die passenden
Trapper-Items in Zabbix existieren. Der Verbindungstest verwendet das Test-Item `alarm.1001` und sendet
den Wert `0`.

Die automatische Zabbix-API-Synchronisierung für Items, Trigger und Tags ist für eine spätere Version
vorgesehen. Der aktuelle Stand 0.1.1 deckt den geprüften Laufzeitpfad IP-Symcon → Zabbix ab.

## Versionsverlauf AlarmMatrix

### Version 0.1.1

- Rückstellungen werden nach einem Wechsel auf `false` sofort an Zabbix übertragen.
- Das Wiederholungsintervall für fehlgeschlagene Sendungen verzögert eine erfolgreiche Rückstellung nicht mehr.
- Damit verschwindet eine behobene Störung ohne die zuvor beobachtete Wartezeit von etwa 30 Sekunden.

### Version 0.1.0

- Erste lauffähige Version des IP-Symcon-Moduls.
- CSV-Import mit `SchemaVersion=1` und Prüfung aller 21 Spalten.
- Prüfung der referenzierten IP-Symcon-Variablen und Datentypen.
- Alarmvergleich mit `EQ`, `NE`, `GT`, `GE`, `LT`, `LE`, `BETWEEN` und `OUTSIDE`.
- Unterstützung von Hysterese, Einschaltverzögerung und Aktivierungsgruppen.
- Ereignisgesteuerte Auswertung über `RegisterMessage` und `MessageSink`.
- Native Übertragung an Zabbix-Trapper-Items über das Zabbix-Sender-Protokoll.
- Wiederholungslogik für fehlgeschlagene Übertragungen.
- Verbindungstest, Importbericht und interne Statusvariablen.

## Zabbix-Widget Störmeldejournal

Das Widget befindet sich unter `ZabbixWidget/stoermeldejournal`. Es fasst Eingang, Quittierung und
Behebung eines Alarms in einer GLT-/BIS-ähnlichen Tabellenzeile zusammen. Berechtigte Benutzer können
Störungen direkt über den nativen Zabbix-Dialog quittieren.

Die vollständige Funktionsbeschreibung, Installationsanleitung und der eigene Versionsverlauf stehen in
[`ZabbixWidget/README.md`](ZabbixWidget/README.md).
