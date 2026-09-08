import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path


MODULE_PATH = Path(__file__).parents[1] / "AlarmServer" / "zabbix_alarm_import.py"
SPEC = importlib.util.spec_from_file_location("zabbix_alarm_import", MODULE_PATH)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class ImporterTests(unittest.TestCase):
    def test_example_csv_is_valid(self):
        matrix = MODULE.read_matrix(Path(__file__).parents[1] / "examples" / "alarmmatrix.example.csv")
        self.assertEqual(1, len(matrix))
        self.assertEqual(1001, matrix[0].alarm_id)
        self.assertEqual("alarm.1001", matrix[0].key)

    def test_plan_creates_missing_objects(self):
        alarm = MODULE.read_matrix(Path(__file__).parents[1] / "examples" / "alarmmatrix.example.csv")[0]
        host = {"hostid": "42", "host": "Gebaeudetechnik", "name": "Gebäudetechnik"}
        plan = MODULE.plan_sync([alarm], host, "192.168.55.35", {}, {}, {})
        self.assertEqual(1, len(plan["item_create"]))
        self.assertEqual(1, len(plan["trigger_create"]))
        self.assertEqual("last(/Gebaeudetechnik/alarm.1001)=1", plan["trigger_create"][0]["expression"])

    def test_plan_recognizes_unchanged_objects(self):
        alarm = MODULE.read_matrix(Path(__file__).parents[1] / "examples" / "alarmmatrix.example.csv")[0]
        host = {"hostid": "42", "host": "Gebaeudetechnik", "name": "Gebäudetechnik"}
        item = MODULE.desired_item(alarm, "42", "192.168.55.35")
        item["itemid"] = "10001"
        trigger = MODULE.desired_trigger(alarm, "Gebaeudetechnik")
        trigger["triggerid"] = "20001"
        trigger["items"] = [{"itemid": "10001", "key_": "alarm.1001"}]
        plan = MODULE.plan_sync(
            [alarm], host, "192.168.55.35", {"alarm.1001": item}, {"1001": trigger}, {"alarm.1001": [trigger]}
        )
        self.assertEqual(1, plan["item_unchanged"])
        self.assertEqual(1, plan["trigger_unchanged"])

    def test_duplicate_alarm_id_is_rejected(self):
        source = (Path(__file__).parents[1] / "examples" / "alarmmatrix.example.csv").read_text(encoding="utf-8")
        duplicate = source + source.splitlines()[1] + "\n"
        with tempfile.NamedTemporaryFile("w", encoding="utf-8", suffix=".csv", delete=False) as handle:
            handle.write(duplicate)
            temp_path = Path(handle.name)
        try:
            with self.assertRaises(MODULE.ImportError):
                MODULE.read_matrix(temp_path)
        finally:
            temp_path.unlink(missing_ok=True)


if __name__ == "__main__":
    unittest.main()
