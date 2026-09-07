# Störmeldejournal 0.2.3

Read-only dashboard widget for Zabbix 7.0.

Version 0.2.1 adds acknowledgement directly from an active journal row. It opens the native Zabbix problem update dialog, explicitly presets acknowledgement and refreshes the widget immediately after a successful update. Resolved events remain distinguishable as acknowledged or unacknowledged.

Version 0.2.2 uses German labels throughout the widget and sorts all rows strictly by the original problem occurrence time, newest first.

Version 0.2.3 keeps the acknowledgement action available for resolved but still unacknowledged events, so a user can acknowledge them afterwards and add a comment. Existing acknowledgement comments are indicated on the status badge and shown completely on mouse-over.

It consolidates a trigger problem event, its first acknowledgement and its recovery into one row. Event tags named `AlarmID`, `Kategorie` and `Bereich` populate the engineering columns. If no host group is configured, the widget uses the group `Alarmmatrix`.

Install this complete directory as `/usr/share/zabbix/modules/stoermeldejournal`, scan the module directory in the Zabbix frontend and enable the module.
