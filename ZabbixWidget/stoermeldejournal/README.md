# Störmeldejournal 0.4.0

Read-only dashboard widget for Zabbix 7.0.

Version 0.2.1 adds acknowledgement directly from an active journal row. It opens the native Zabbix problem update dialog, explicitly presets acknowledgement and refreshes the widget immediately after a successful update. Resolved events remain distinguishable as acknowledged or unacknowledged.

Version 0.2.2 uses German labels throughout the widget and sorts all rows strictly by the original problem occurrence time, newest first.

Version 0.2.3 keeps the acknowledgement action available for resolved but still unacknowledged events, so a user can acknowledge them afterwards and add a comment. Existing acknowledgement comments are indicated on the status badge and shown completely on mouse-over.

Version 0.3.0 adds an interactive filter bar for status, priority, category, area and alarm IDs. Alarm IDs support individual values, inclusive ranges and exclusions, for example `1001,1010-1020,-1015`. Filter selections remain active while the widget refreshes automatically.

Version 0.4.0 removes the fixed history-days window and adds an exact-date filter plus independent from/to date filters. Date selections are sent to the widget controller, so old events are queried directly from the complete event history still retained by Zabbix. The explanatory alarm-ID hint below the filter bar was removed.

It consolidates a trigger problem event, its first acknowledgement and its recovery into one row. Event tags named `AlarmID`, `Kategorie` and `Bereich` populate the engineering columns. If no host group is configured, the widget uses the group `Alarmmatrix`.

Install this complete directory as `/usr/share/zabbix/modules/stoermeldejournal`, scan the module directory in the Zabbix frontend and enable the module.
