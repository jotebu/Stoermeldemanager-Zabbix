# Zabbix-Alarmmatrix-Importer

`zabbix_alarm_import.py` synchronisiert die von Excel erzeugte Alarmmatrix mit Zabbix 7.0.
Das Programm legt auf dem technischen Host für jede Alarm-ID genau ein Trapper-Item und einen
Trigger an beziehungsweise aktualisiert bestehende Objekte.

## Angelegte Objekte

- Item-Key `alarm.<ID>`, Typ **Zabbix trapper**, Werttyp **Numeric (unsigned)**
- erlaubter Sender standardmäßig `192.168.55.35` (IP-Symcon)
- Triggerausdruck `last(/Gebaeudetechnik/alarm.<ID>)=1`
- direkte Abbildung der Prioritäten 1 bis 5 auf die Zabbix-Severity 1 bis 5
- Tags `ManagedBy`, `AlarmID`, `Kategorie`, `Bereich`, `Quittierung`, `NotifyEmail`,
  `NotifyPush` und `EskalationMin`

Nicht mehr in der CSV vorhandene Objekte werden nicht gelöscht. Der Import ist damit bewusst
nicht destruktiv.

## Installation auf dem Alarmserver

```bash
sudo install -d -m 0750 /etc/stoermeldemanager-zabbix
sudo install -m 0755 AlarmServer/zabbix_alarm_import.py /usr/local/sbin/zabbix-alarm-import
sudo nano /etc/stoermeldemanager-zabbix/zabbix-api.token
sudo chmod 0600 /etc/stoermeldemanager-zabbix/zabbix-api.token
```

Das Token wird in Zabbix unter **Benutzer → API-Tokens** für einen Admin- oder Super-Admin-Benutzer
angelegt. In der Token-Datei steht ausschließlich das Token, ohne Anführungszeichen.

## Sicherer Ablauf

Zuerst wird immer der vollständige Vorschau-Lauf ausgeführt:

```bash
sudo /usr/local/sbin/zabbix-alarm-import \
  --csv /var/lib/stoermeldemanager-zabbix/alarmmatrix.csv
```

Erst wenn die angezeigten Zahlen stimmen, wird der Schreibvorgang mit der exakten Anzahl bestätigt:

```bash
sudo /usr/local/sbin/zabbix-alarm-import \
  --csv /var/lib/stoermeldemanager-zabbix/alarmmatrix.csv \
  --apply \
  --confirm-count 446
```

Ein späterer zweiter Vorschau-Lauf muss bei unveränderter CSV jeweils `0` Neuanlagen und
`0` Aktualisierungen melden.
