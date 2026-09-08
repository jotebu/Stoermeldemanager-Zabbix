#!/usr/bin/env python3
"""Synchronisiert die Alarmmatrix mit Zabbix 7.0 über die JSON-RPC-API.

Ohne --apply arbeitet das Programm ausschließlich lesend. Ein schreibender Lauf
erfordert zusätzlich --confirm-count mit der exakten Anzahl der ausgewählten
CSV-Zeilen.
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import sys
import urllib.error
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable


CSV_COLUMNS = [
    "SchemaVersion",
    "ID",
    "Aktiv",
    "IPS_VariableID",
    "Vergleich",
    "Grenzwert1",
    "Grenzwert2",
    "Hysterese",
    "Kurztext",
    "Langtext",
    "Kategorie",
    "Bereich",
    "Prioritaet",
    "Quittierung",
    "Email",
    "Push",
    "Verzoegerung_s",
    "Eskalation_min",
    "Aktivierungsgruppe",
    "Kommentar",
    "Zabbix_Key",
]

MANAGED_BY = "Stoermeldemanager-Zabbix"
ITEM_TYPE_TRAPPER = 2
VALUE_TYPE_UNSIGNED = 3
STATUS_ENABLED = 0
STATUS_DISABLED = 1


class ImportError(RuntimeError):
    pass


@dataclass(frozen=True)
class Alarm:
    alarm_id: int
    active: bool
    variable_id: int
    short_text: str
    long_text: str
    category: str
    area: str
    priority: int
    ack_required: bool
    email: bool
    push: bool
    escalation_minutes: int
    comment: str
    key: str


class ZabbixApi:
    def __init__(self, url: str, token: str, timeout: int = 30) -> None:
        self.url = url
        self.token = token
        self.timeout = timeout
        self.request_id = 0

    def call(self, method: str, params: Any) -> Any:
        self.request_id += 1
        payload = json.dumps(
            {
                "jsonrpc": "2.0",
                "method": method,
                "params": params,
                "id": self.request_id,
            },
            ensure_ascii=False,
        ).encode("utf-8")
        request = urllib.request.Request(
            self.url,
            data=payload,
            headers={
                "Authorization": f"Bearer {self.token}",
                "Content-Type": "application/json-rpc",
            },
            method="POST",
        )

        try:
            with urllib.request.urlopen(request, timeout=self.timeout) as response:
                result = json.loads(response.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", errors="replace")
            raise ImportError(f"HTTP-Fehler {exc.code}: {detail}") from exc
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as exc:
            raise ImportError(f"Zabbix-API nicht erreichbar: {exc}") from exc

        if "error" in result:
            error = result["error"]
            raise ImportError(
                f"Zabbix-API {method}: {error.get('message', 'Fehler')} – "
                f"{error.get('data', '')}"
            )
        return result.get("result")


def parse_bool(value: str, field: str, line: int) -> bool:
    if value not in {"0", "1"}:
        raise ImportError(f"Zeile {line}: {field} muss 0 oder 1 sein")
    return value == "1"


def parse_int(value: str, field: str, line: int, minimum: int, maximum: int | None = None) -> int:
    try:
        number = int(value)
    except ValueError as exc:
        raise ImportError(f"Zeile {line}: {field} ist keine ganze Zahl") from exc
    if number < minimum or (maximum is not None and number > maximum):
        suffix = f" bis {maximum}" if maximum is not None else ""
        raise ImportError(f"Zeile {line}: {field} muss zwischen {minimum}{suffix} liegen")
    return number


def read_matrix(path: Path) -> list[Alarm]:
    if not path.is_file():
        raise ImportError(f"CSV-Datei nicht gefunden: {path}")

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle, delimiter=";", quotechar='"')
        if reader.fieldnames != CSV_COLUMNS:
            raise ImportError(
                "CSV-Spalten entsprechen nicht SchemaVersion 1. Erwartet: "
                + ";".join(CSV_COLUMNS)
            )

        alarms: list[Alarm] = []
        ids: set[int] = set()
        keys: set[str] = set()

        for line, raw in enumerate(reader, start=2):
            row = {key: (value or "").strip() for key, value in raw.items()}
            if not any(row.values()):
                continue
            if row["SchemaVersion"] != "1":
                raise ImportError(f"Zeile {line}: SchemaVersion muss 1 sein")

            alarm_id = parse_int(row["ID"], "ID", line, 1)
            if alarm_id in ids:
                raise ImportError(f"Zeile {line}: Alarm-ID {alarm_id} ist doppelt")
            ids.add(alarm_id)

            key = row["Zabbix_Key"]
            if key != f"alarm.{alarm_id}":
                raise ImportError(f"Zeile {line}: Zabbix_Key muss alarm.{alarm_id} sein")
            if key in keys:
                raise ImportError(f"Zeile {line}: Zabbix-Key {key} ist doppelt")
            keys.add(key)

            short_text = row["Kurztext"]
            if not short_text or len(short_text) > 120:
                raise ImportError(f"Zeile {line}: Kurztext fehlt oder ist länger als 120 Zeichen")

            if row["Vergleich"] not in {"EQ", "NE", "GT", "GE", "LT", "LE", "BETWEEN", "OUTSIDE"}:
                raise ImportError(f"Zeile {line}: Vergleich ist ungültig")
            if row["Grenzwert1"] == "":
                raise ImportError(f"Zeile {line}: Grenzwert1 fehlt")

            alarms.append(
                Alarm(
                    alarm_id=alarm_id,
                    active=parse_bool(row["Aktiv"], "Aktiv", line),
                    variable_id=parse_int(row["IPS_VariableID"], "IPS_VariableID", line, 1),
                    short_text=short_text,
                    long_text=row["Langtext"],
                    category=row["Kategorie"],
                    area=row["Bereich"],
                    priority=parse_int(row["Prioritaet"], "Prioritaet", line, 1, 5),
                    ack_required=parse_bool(row["Quittierung"], "Quittierung", line),
                    email=parse_bool(row["Email"], "Email", line),
                    push=parse_bool(row["Push"], "Push", line),
                    escalation_minutes=parse_int(row["Eskalation_min"], "Eskalation_min", line, 0),
                    comment=row["Kommentar"],
                    key=key,
                )
            )

    if not alarms:
        raise ImportError("CSV-Datei enthält keine Alarmdefinitionen")
    return alarms


def chunks(values: list[dict[str, Any]], size: int = 100) -> Iterable[list[dict[str, Any]]]:
    for start in range(0, len(values), size):
        yield values[start : start + size]


def tags_for(alarm: Alarm) -> list[dict[str, str]]:
    return [
        {"tag": "ManagedBy", "value": MANAGED_BY},
        {"tag": "AlarmID", "value": str(alarm.alarm_id)},
        {"tag": "Kategorie", "value": alarm.category},
        {"tag": "Bereich", "value": alarm.area},
        {"tag": "Quittierung", "value": "1" if alarm.ack_required else "0"},
        {"tag": "NotifyEmail", "value": "1" if alarm.email else "0"},
        {"tag": "NotifyPush", "value": "1" if alarm.push else "0"},
        {"tag": "EskalationMin", "value": str(alarm.escalation_minutes)},
    ]


def item_description(alarm: Alarm) -> str:
    parts = [
        f"Alarm-ID: {alarm.alarm_id}",
        f"IP-Symcon Variable-ID: {alarm.variable_id}",
    ]
    if alarm.long_text:
        parts.append(alarm.long_text)
    if alarm.comment:
        parts.append(f"Engineering-Hinweis: {alarm.comment}")
    return "\n".join(parts)


def desired_item(alarm: Alarm, host_id: str, allowed_hosts: str) -> dict[str, Any]:
    return {
        "name": f"Alarm {alarm.alarm_id}: {alarm.short_text}",
        "key_": alarm.key,
        "hostid": host_id,
        "type": ITEM_TYPE_TRAPPER,
        "value_type": VALUE_TYPE_UNSIGNED,
        "delay": "0",
        "history": "90d",
        "trends": "1095d",
        "status": STATUS_ENABLED if alarm.active else STATUS_DISABLED,
        "trapper_hosts": allowed_hosts,
        "description": item_description(alarm),
        "tags": tags_for(alarm),
    }


def desired_trigger(alarm: Alarm, host: str) -> dict[str, Any]:
    return {
        "description": f"Alarm {alarm.alarm_id}: {alarm.short_text}",
        "expression": f"last(/{host}/{alarm.key})=1",
        "priority": alarm.priority,
        "status": STATUS_ENABLED if alarm.active else STATUS_DISABLED,
        "manual_close": 0,
        "comments": item_description(alarm),
        "tags": tags_for(alarm),
    }


def normalize_tags(tags: list[dict[str, Any]]) -> list[tuple[str, str]]:
    return sorted((str(tag.get("tag", "")), str(tag.get("value", ""))) for tag in tags)


def different(existing: dict[str, Any], desired: dict[str, Any], fields: list[str]) -> bool:
    for field in fields:
        if field == "tags":
            if normalize_tags(existing.get("tags", [])) != normalize_tags(desired.get("tags", [])):
                return True
        elif str(existing.get(field, "")) != str(desired.get(field, "")):
            return True
    return False


def get_host(api: ZabbixApi, host: str) -> dict[str, Any]:
    result = api.call(
        "host.get",
        {
            "output": ["hostid", "host", "name", "status"],
            "filter": {"host": [host]},
        },
    )
    if len(result) != 1:
        raise ImportError(f"Technischer Host {host!r} wurde nicht eindeutig gefunden")
    return result[0]


def get_items(api: ZabbixApi, host_id: str) -> dict[str, dict[str, Any]]:
    items = api.call(
        "item.get",
        {
            "output": [
                "itemid", "name", "key_", "type", "value_type", "delay", "history",
                "trends", "status", "trapper_hosts", "description",
            ],
            "hostids": [host_id],
            "selectTags": ["tag", "value"],
        },
    )
    result: dict[str, dict[str, Any]] = {}
    duplicates: set[str] = set()
    for item in items:
        key = str(item["key_"])
        if key in result:
            duplicates.add(key)
        result[key] = item
    if duplicates:
        raise ImportError("Doppelte Item-Keys auf dem Host: " + ", ".join(sorted(duplicates)))
    return result


def get_triggers(api: ZabbixApi, host_id: str) -> tuple[dict[str, dict[str, Any]], dict[str, list[dict[str, Any]]]]:
    triggers = api.call(
        "trigger.get",
        {
            "output": ["triggerid", "description", "expression", "priority", "status", "manual_close", "comments"],
            "hostids": [host_id],
            "selectTags": ["tag", "value"],
            "selectItems": ["itemid", "key_"],
        },
    )
    by_alarm_id: dict[str, dict[str, Any]] = {}
    by_item_key: dict[str, list[dict[str, Any]]] = {}
    for trigger in triggers:
        tag_values = {str(tag.get("tag")): str(tag.get("value")) for tag in trigger.get("tags", [])}
        alarm_id = tag_values.get("AlarmID")
        if tag_values.get("ManagedBy") == MANAGED_BY and alarm_id:
            if alarm_id in by_alarm_id:
                raise ImportError(f"Mehrere verwaltete Trigger für Alarm-ID {alarm_id}")
            by_alarm_id[alarm_id] = trigger
        for item in trigger.get("items", []):
            key = str(item.get("key_", ""))
            if key:
                by_item_key.setdefault(key, []).append(trigger)
    return by_alarm_id, by_item_key


def plan_sync(
    alarms: list[Alarm],
    host: dict[str, Any],
    allowed_hosts: str,
    items: dict[str, dict[str, Any]],
    triggers_by_alarm: dict[str, dict[str, Any]],
    triggers_by_key: dict[str, list[dict[str, Any]]],
) -> dict[str, Any]:
    item_fields = [
        "name", "type", "value_type", "delay", "history", "trends", "status",
        "trapper_hosts", "description", "tags",
    ]
    trigger_fields = ["description", "expression", "priority", "status", "manual_close", "comments", "tags"]
    item_create: list[dict[str, Any]] = []
    item_update: list[dict[str, Any]] = []
    item_unchanged = 0
    trigger_create: list[dict[str, Any]] = []
    trigger_update: list[dict[str, Any]] = []
    trigger_unchanged = 0

    for alarm in alarms:
        desired = desired_item(alarm, str(host["hostid"]), allowed_hosts)
        existing = items.get(alarm.key)
        if existing is None:
            item_create.append(desired)
        elif different(existing, desired, item_fields):
            update = dict(desired)
            update.pop("hostid", None)
            update.pop("key_", None)
            update["itemid"] = str(existing["itemid"])
            item_update.append(update)
        else:
            item_unchanged += 1

        desired_t = desired_trigger(alarm, str(host["host"]))
        existing_t = triggers_by_alarm.get(str(alarm.alarm_id))
        if existing_t is None:
            candidates = triggers_by_key.get(alarm.key, [])
            if len(candidates) > 1:
                raise ImportError(f"Mehrere bestehende Trigger verwenden {alarm.key}")
            existing_t = candidates[0] if candidates else None

        if existing_t is None:
            trigger_create.append(desired_t)
        elif different(existing_t, desired_t, trigger_fields):
            update_t = dict(desired_t)
            update_t["triggerid"] = str(existing_t["triggerid"])
            trigger_update.append(update_t)
        else:
            trigger_unchanged += 1

    return {
        "item_create": item_create,
        "item_update": item_update,
        "item_unchanged": item_unchanged,
        "trigger_create": trigger_create,
        "trigger_update": trigger_update,
        "trigger_unchanged": trigger_unchanged,
    }


def apply_batches(api: ZabbixApi, method: str, values: list[dict[str, Any]]) -> None:
    for batch in chunks(values):
        api.call(method, batch)


def print_summary(mode: str, api_version: str, host: dict[str, Any], alarms: list[Alarm], plan: dict[str, Any]) -> None:
    active = sum(1 for alarm in alarms if alarm.active)
    print("=" * 64)
    print("Zabbix-Alarmmatrix-Synchronisation")
    print("=" * 64)
    print(f"Modus:                 {mode}")
    print(f"Zabbix-Version:        {api_version}")
    print(f"Technischer Host:      {host['host']} (ID {host['hostid']})")
    print(f"Alarmdefinitionen:     {len(alarms)}")
    print(f"Davon aktiv:           {active}")
    print(f"Items neu:             {len(plan['item_create'])}")
    print(f"Items aktualisieren:   {len(plan['item_update'])}")
    print(f"Items unverändert:     {plan['item_unchanged']}")
    print(f"Trigger neu:           {len(plan['trigger_create'])}")
    print(f"Trigger aktualisieren: {len(plan['trigger_update'])}")
    print(f"Trigger unverändert:   {plan['trigger_unchanged']}")


def read_token(path: Path | None) -> str:
    token = os.environ.get("ZABBIX_API_TOKEN", "").strip()
    if token:
        return token
    if path is not None and path.is_file():
        token = path.read_text(encoding="utf-8").strip()
    if not token:
        raise ImportError("API-Token fehlt (ZABBIX_API_TOKEN oder --token-file)")
    return token


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--csv", type=Path, required=True, help="Alarmmatrix-CSV im SchemaVersion-1-Format")
    parser.add_argument("--url", default="http://127.0.0.1:8080/api_jsonrpc.php", help="Zabbix-API-URL")
    parser.add_argument("--host", default="Gebaeudetechnik", help="Technischer Zabbix-Hostname")
    parser.add_argument("--allowed-hosts", default="192.168.55.35", help="Erlaubte Absender für Trapper-Items")
    parser.add_argument("--token-file", type=Path, default=Path("/etc/stoermeldemanager-zabbix/zabbix-api.token"))
    parser.add_argument("--timeout", type=int, default=30)
    parser.add_argument("--limit", type=int, default=0, help="Nur die ersten N CSV-Zeilen berücksichtigen")
    parser.add_argument("--apply", action="store_true", help="Änderungen tatsächlich schreiben")
    parser.add_argument("--confirm-count", type=int, help="Sicherheitsbestätigung: erwartete Zahl ausgewählter Zeilen")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    try:
        alarms = read_matrix(args.csv)
        if args.limit < 0:
            raise ImportError("--limit darf nicht negativ sein")
        if args.limit:
            alarms = alarms[: args.limit]

        if args.apply and args.confirm_count != len(alarms):
            raise ImportError(
                f"Schreibender Lauf verweigert: --confirm-count muss exakt {len(alarms)} sein"
            )

        api = ZabbixApi(args.url, read_token(args.token_file), args.timeout)
        api_version = str(api.call("apiinfo.version", {}))
        if not api_version.startswith("7.0."):
            raise ImportError(f"Freigegeben ist Zabbix 7.0.x, gefunden wurde {api_version}")

        host = get_host(api, args.host)
        items = get_items(api, str(host["hostid"]))
        triggers_by_alarm, triggers_by_key = get_triggers(api, str(host["hostid"]))
        plan = plan_sync(alarms, host, args.allowed_hosts, items, triggers_by_alarm, triggers_by_key)
        print_summary("APPLY" if args.apply else "PREVIEW", api_version, host, alarms, plan)

        if not args.apply:
            print("\nKeine Änderungen vorgenommen. Für den Import --apply und --confirm-count verwenden.")
            return 0

        apply_batches(api, "item.create", plan["item_create"])
        apply_batches(api, "item.update", plan["item_update"])
        apply_batches(api, "trigger.create", plan["trigger_create"])
        apply_batches(api, "trigger.update", plan["trigger_update"])

        print("\nSynchronisation erfolgreich abgeschlossen.")
        return 0
    except ImportError as exc:
        print(f"FEHLER: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
