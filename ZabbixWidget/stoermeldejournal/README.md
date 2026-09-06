# Störmeldejournal 0.1.1

Read-only dashboard widget for Zabbix 7.0.

Version 0.1.1 resolves acknowledgement user names through the Zabbix User API, as required by Zabbix 7.0.

It consolidates a trigger problem event, its first acknowledgement and its recovery into one row. Event tags named `AlarmID`, `Kategorie` and `Bereich` populate the engineering columns. If no host group is configured, the widget uses the group `Alarmmatrix`.

Install this complete directory as `/usr/share/zabbix/modules/stoermeldejournal`, scan the module directory in the Zabbix frontend and enable the module.
