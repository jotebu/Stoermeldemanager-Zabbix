# Störmeldejournal 0.4.0

Frontend-Widget für Zabbix 7.0 zur kompakten Darstellung von Störungen aus der IP-Symcon-Alarmmatrix.
Problemereignis, erste Quittierung und Behebung werden in einer gemeinsamen Journalzeile dargestellt.

Die vollständige Funktionsbeschreibung, Installations- und Aktualisierungsanleitung, Bedienung,
Fehlerdiagnose sowie der lückenlose Versionsverlauf stehen in
[`../README.md`](../README.md).

## Kurzinstallation

```bash
sudo mkdir -p /usr/share/zabbix/modules/stoermeldejournal

sudo cp -a \
  ZabbixWidget/stoermeldejournal/. \
  /usr/share/zabbix/modules/stoermeldejournal/

sudo chown -R root:root \
  /usr/share/zabbix/modules/stoermeldejournal
```

Anschließend in Zabbix **Administration → Allgemein → Module → Verzeichnis einlesen** wählen, das Modul
**Störmeldejournal** aktivieren und es über **Dashboard bearbeiten → Widget hinzufügen** in das gewünschte
Dashboard einsetzen.

## Versionen

- **0.4.0:** Datums- und Von-/Bis-Filter, serverseitige Langzeitabfrage, feste Tagesgrenze entfernt
- **0.3.0:** interaktive Filter für Status, Priorität, Kategorie, Bereich und Alarm-ID
- **0.2.3:** nachträgliche Quittierung behobener Ereignisse und Kommentar als Mouse-over
- **0.2.2:** deutsche Oberfläche und feste Sortierung nach dem ursprünglichen Eingang
- **0.2.1:** vier eindeutige Zustände und korrigierte Quittierungs-/Behebungslogik
- **0.2.0:** nativer Zabbix-Quittierdialog mit Kommentar
- **0.1.1:** kompatible Benutzerauflösung für Quittierungen unter Zabbix 7.0
- **0.1.0:** erste lesende Journalansicht mit zusammengefassten Ereignissen
