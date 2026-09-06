<?php

declare(strict_types=1);

class AlarmMatrix extends IPSModule
{
    private const SCHEMA_VERSION = 1;
    private const VM_UPDATE_MESSAGE = 10603;

    private const STATUS_ACTIVE = 102;
    private const STATUS_NO_MATRIX = 201;
    private const STATUS_MATRIX_ERROR = 202;
    private const STATUS_SENDER_ERROR = 203;

    private const CSV_COLUMNS = [
        'SchemaVersion',
        'ID',
        'Aktiv',
        'IPS_VariableID',
        'Vergleich',
        'Grenzwert1',
        'Grenzwert2',
        'Hysterese',
        'Kurztext',
        'Langtext',
        'Kategorie',
        'Bereich',
        'Prioritaet',
        'Quittierung',
        'Email',
        'Push',
        'Verzoegerung_s',
        'Eskalation_min',
        'Aktivierungsgruppe',
        'Kommentar',
        'Zabbix_Key'
    ];

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('CSVPath', '/var/lib/symcon/user/alarmserver/alarmmatrix.csv');
        $this->RegisterPropertyString('ZabbixServer', '');
        $this->RegisterPropertyInteger('ZabbixPort', 10051);
        $this->RegisterPropertyString('ZabbixHost', 'Gebaeudetechnik');
        $this->RegisterPropertyInteger('ConnectTimeoutMs', 3000);
        $this->RegisterPropertyInteger('RetryIntervalSec', 30);

        $this->RegisterAttributeString('AlarmMatrix', '[]');
        $this->RegisterAttributeString('AlarmStates', '{}');
        $this->RegisterAttributeString('RegisteredVariables', '[]');
        $this->RegisterAttributeString('ImportReport', 'Noch keine Alarmmatrix importiert.');
        $this->RegisterAttributeString('LastSenderResponse', '');

        $this->RegisterVariableString('MatrixStatus', 'Status Alarmmatrix', '', 10);
        $this->RegisterVariableInteger('LoadedAlarms', 'Geladene Meldungen', '', 20);
        $this->RegisterVariableInteger('ActiveAlarms', 'Aktive Definitionen', '', 30);
        $this->RegisterVariableInteger('LastImport', 'Letzter Import', '~UnixTimestamp', 40);
        $this->RegisterVariableInteger('LastTransmission', 'Letzte Übertragung', '~UnixTimestamp', 50);

        $this->RegisterTimer('ProcessPending', 1000, 'AZA_ProcessPending($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->restoreVariableSubscriptions();

        if (count($this->getMatrix()) === 0) {
            $this->SetStatus(self::STATUS_NO_MATRIX);
            $this->SetValue('MatrixStatus', 'Keine Alarmmatrix geladen');
            return;
        }

        $this->SetStatus(self::STATUS_ACTIVE);
    }

    public function GetConfigurationForm(): string
    {
        $report = $this->ReadAttributeString('ImportReport');
        $lastResponse = $this->ReadAttributeString('LastSenderResponse');

        $form = [
            'elements' => [
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'CSVPath',
                    'caption' => 'Pfad zur Alarmmatrix-CSV'
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'ZabbixServer',
                    'caption' => 'Zabbix-Server (IP oder DNS-Name)'
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'ZabbixPort',
                    'caption' => 'Zabbix-Trapper-Port',
                    'minimum' => 1,
                    'maximum' => 65535
                ],
                [
                    'type' => 'ValidationTextBox',
                    'name' => 'ZabbixHost',
                    'caption' => 'Technischer Zabbix-Hostname'
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'ConnectTimeoutMs',
                    'caption' => 'Verbindungs-Timeout in Millisekunden',
                    'minimum' => 500,
                    'maximum' => 30000
                ],
                [
                    'type' => 'NumberSpinner',
                    'name' => 'RetryIntervalSec',
                    'caption' => 'Wiederholungsintervall bei Sendefehlern',
                    'minimum' => 5,
                    'maximum' => 3600
                ]
            ],
            'actions' => [
                [
                    'type' => 'Button',
                    'caption' => 'CSV laden und prüfen',
                    'onClick' => 'echo AZA_ImportCSV($id);'
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Zabbix-Verbindung testen',
                    'onClick' => 'echo AZA_TestSender($id);'
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Alle aktuellen Zustände senden',
                    'onClick' => 'echo AZA_SendAllStates($id);'
                ],
                [
                    'type' => 'Label',
                    'caption' => "Importbericht:\n" . $report
                ],
                [
                    'type' => 'Label',
                    'caption' => "Letzte Zabbix-Antwort:\n" . ($lastResponse !== '' ? $lastResponse : 'Noch keine Übertragung.')
                ]
            ],
            'status' => [
                ['code' => self::STATUS_NO_MATRIX, 'icon' => 'inactive', 'caption' => 'Keine Alarmmatrix geladen'],
                ['code' => self::STATUS_MATRIX_ERROR, 'icon' => 'error', 'caption' => 'Alarmmatrix fehlerhaft'],
                ['code' => self::STATUS_SENDER_ERROR, 'icon' => 'error', 'caption' => 'Zabbix-Sendefehler']
            ]
        ];

        return json_encode($form, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ((int) $Message !== self::VM_UPDATE_MESSAGE) {
            return;
        }

        $this->processVariable((int) $SenderID);
    }

    public function ImportCSV(): string
    {
        $path = trim($this->ReadPropertyString('CSVPath'));

        try {
            $rows = $this->readCsv($path);
            $matrix = $this->validateRows($rows);
            $this->applyMatrix($matrix);

            $active = count(array_filter($matrix, static fn(array $alarm): bool => $alarm['active']));
            $report = sprintf(
                "CSV-Import erfolgreich\n%d Definitionen geladen\n%d Definitionen aktiv\n%d Definitionen deaktiviert",
                count($matrix),
                $active,
                count($matrix) - $active
            );

            $this->WriteAttributeString('ImportReport', $report);
            $this->SetValue('MatrixStatus', 'Import erfolgreich');
            $this->SetValue('LoadedAlarms', count($matrix));
            $this->SetValue('ActiveAlarms', $active);
            $this->SetValue('LastImport', time());
            $this->SetStatus(self::STATUS_ACTIVE);

            return 'MESSAGE:' . $report;
        } catch (Throwable $exception) {
            $report = 'CSV-Import fehlgeschlagen: ' . $exception->getMessage();
            $this->WriteAttributeString('ImportReport', $report);
            $this->SetValue('MatrixStatus', $report);
            $this->SetStatus(self::STATUS_MATRIX_ERROR);
            $this->LogMessage($report, KL_ERROR);

            return $report;
        }
    }

    public function TestSender(): string
    {
        try {
            $response = $this->sendValue('alarm.1001', 0);
            $message = 'Zabbix-Verbindung erfolgreich: ' . $response;
            $this->SetStatus(count($this->getMatrix()) > 0 ? self::STATUS_ACTIVE : self::STATUS_NO_MATRIX);

            return 'MESSAGE:' . $message;
        } catch (Throwable $exception) {
            $message = 'Zabbix-Verbindung fehlgeschlagen: ' . $exception->getMessage();
            $this->WriteAttributeString('LastSenderResponse', $message);
            $this->SetStatus(self::STATUS_SENDER_ERROR);
            $this->LogMessage($message, KL_ERROR);

            return $message;
        }
    }

    public function SendAllStates(): string
    {
        $matrix = $this->getMatrix();

        if (count($matrix) === 0) {
            return 'Keine Alarmmatrix geladen.';
        }

        $states = $this->getStates();
        $sent = 0;
        $errors = [];

        foreach ($matrix as $alarm) {
            if (!$alarm['active']) {
                continue;
            }

            try {
                $value = GetValue($alarm['variableId']);
                $current = $this->evaluateAlarm($alarm, $value, false);
                $this->sendValue($alarm['key'], $current ? 1 : 0);

                $states[(string) $alarm['id']] = [
                    'stable' => $current,
                    'pendingSince' => 0,
                    'lastAttempt' => time(),
                    'lastSent' => time()
                ];
                $sent++;
            } catch (Throwable $exception) {
                $errors[] = sprintf('%d: %s', $alarm['id'], $exception->getMessage());
            }
        }

        $this->setStates($states);

        if (count($errors) > 0) {
            $message = sprintf('%d Zustände gesendet; Fehler: %s', $sent, implode(' | ', $errors));
            $this->SetStatus(self::STATUS_SENDER_ERROR);
            return $message;
        }

        $this->SetStatus(self::STATUS_ACTIVE);
        return sprintf('MESSAGE:%d aktuelle Zustände erfolgreich gesendet.', $sent);
    }

    public function ProcessPending(): void
    {
        $matrix = $this->getMatrix();
        $states = $this->getStates();
        $now = time();

        foreach ($matrix as $alarm) {
            if (!$alarm['active']) {
                continue;
            }

            $id = (string) $alarm['id'];
            $state = $states[$id] ?? $this->initialState($alarm);

            // Im Normalbetrieb kommen Zustandsänderungen über MessageSink. Der
            // Timer arbeitet nur verzögerte oder zuvor fehlgeschlagene Wechsel ab.
            if ((int) $state['pendingSince'] === 0
                && (int) $state['lastAttempt'] <= (int) $state['lastSent']) {
                continue;
            }

            $value = GetValue($alarm['variableId']);
            $desired = $this->evaluateAlarm($alarm, $value, (bool) $state['stable']);

            if (!$desired) {
                $state['pendingSince'] = 0;

                if ((bool) $state['stable']) {
                    $state = $this->attemptTransition($alarm, $state, false, $now);
                }
            } elseif ((bool) $state['stable']) {
                $state['pendingSince'] = 0;
            } else {
                if ((int) $state['pendingSince'] === 0) {
                    $state['pendingSince'] = $now;
                }

                if (($now - (int) $state['pendingSince']) >= $alarm['delay']) {
                    $state = $this->attemptTransition($alarm, $state, true, $now);
                }
            }

            $states[$id] = $state;
        }

        $this->setStates($states);
    }

    private function processVariable(int $variableId): void
    {
        $matrix = $this->getMatrix();
        $states = $this->getStates();
        $now = time();

        foreach ($matrix as $alarm) {
            if (!$alarm['active'] || $alarm['variableId'] !== $variableId) {
                continue;
            }

            $id = (string) $alarm['id'];
            $state = $states[$id] ?? $this->initialState($alarm);
            $value = GetValue($variableId);
            $desired = $this->evaluateAlarm($alarm, $value, (bool) $state['stable']);

            if (!$desired) {
                $state['pendingSince'] = 0;

                if ((bool) $state['stable']) {
                    $state = $this->attemptTransition($alarm, $state, false, $now);
                }
            } elseif (!(bool) $state['stable']) {
                if ($alarm['delay'] === 0) {
                    $state = $this->attemptTransition($alarm, $state, true, $now);
                } elseif ((int) $state['pendingSince'] === 0) {
                    $state['pendingSince'] = $now;
                }
            }

            $states[$id] = $state;
        }

        $this->setStates($states);
    }

    private function attemptTransition(array $alarm, array $state, bool $desired, int $now): array
    {
        $retry = max(5, $this->ReadPropertyInteger('RetryIntervalSec'));

        // Nur einen zuvor fehlgeschlagenen Sendeversuch verzögern. Nach einer
        // erfolgreichen Übertragung müssen Gegenrichtungen (insbesondere die
        // Rückstellung eines Alarms) ohne Retry-Wartezeit gesendet werden.
        $lastAttemptFailed = (int) $state['lastAttempt'] > (int) $state['lastSent'];

        if ($lastAttemptFailed && ($now - (int) $state['lastAttempt']) < $retry) {
            return $state;
        }

        $state['lastAttempt'] = $now;

        try {
            $this->sendValue($alarm['key'], $desired ? 1 : 0);
            $state['stable'] = $desired;
            $state['pendingSince'] = 0;
            $state['lastSent'] = $now;
            $this->SetStatus(self::STATUS_ACTIVE);
        } catch (Throwable $exception) {
            $message = sprintf('Alarm %d konnte nicht gesendet werden: %s', $alarm['id'], $exception->getMessage());
            $this->WriteAttributeString('LastSenderResponse', $message);
            $this->SetStatus(self::STATUS_SENDER_ERROR);
            $this->LogMessage($message, KL_ERROR);
        }

        return $state;
    }

    private function sendValue(string $key, int $value): string
    {
        $server = trim($this->ReadPropertyString('ZabbixServer'));
        $port = $this->ReadPropertyInteger('ZabbixPort');
        $host = trim($this->ReadPropertyString('ZabbixHost'));
        $timeoutMs = max(500, $this->ReadPropertyInteger('ConnectTimeoutMs'));

        if ($server === '') {
            throw new RuntimeException('Zabbix-Server ist nicht konfiguriert.');
        }

        if ($host === '') {
            throw new RuntimeException('Technischer Zabbix-Hostname ist leer.');
        }

        $payload = json_encode([
            'request' => 'sender data',
            'data' => [[
                'host' => $host,
                'key' => $key,
                'value' => (string) $value,
                'clock' => time(),
                'ns' => 0
            ]]
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $packet = "ZBXD\x01" . pack('V2', strlen($payload), 0) . $payload;
        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $server, $port),
            $errorNumber,
            $errorMessage,
            $timeoutMs / 1000,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            throw new RuntimeException(sprintf('TCP-Verbindung fehlgeschlagen (%d): %s', $errorNumber, $errorMessage));
        }

        try {
            stream_set_timeout($socket, intdiv($timeoutMs, 1000), ($timeoutMs % 1000) * 1000);
            $this->writeAll($socket, $packet);
            $header = $this->readExact($socket, 13);

            if (substr($header, 0, 5) !== "ZBXD\x01") {
                throw new RuntimeException('Ungültiger Zabbix-Antwortheader.');
            }

            $length = unpack('Vlow/Vhigh', substr($header, 5, 8));

            if ($length === false || $length['high'] !== 0 || $length['low'] < 1 || $length['low'] > 1048576) {
                throw new RuntimeException('Ungültige Länge der Zabbix-Antwort.');
            }

            $responseJson = $this->readExact($socket, $length['low']);
            $response = json_decode($responseJson, true, 512, JSON_THROW_ON_ERROR);

            if (($response['response'] ?? '') !== 'success') {
                throw new RuntimeException('Zabbix meldet keinen Erfolg: ' . $responseJson);
            }

            $info = (string) ($response['info'] ?? 'success');

            if (preg_match('/failed:\s*([1-9][0-9]*)/i', $info) === 1) {
                throw new RuntimeException('Zabbix hat den Wert abgewiesen: ' . $info);
            }

            $this->WriteAttributeString('LastSenderResponse', $info);
            $this->SetValue('LastTransmission', time());

            return $info;
        } finally {
            fclose($socket);
        }
    }

    private function writeAll($socket, string $data): void
    {
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $written = fwrite($socket, substr($data, $offset));

            if ($written === false || $written === 0) {
                throw new RuntimeException('Zabbix-Paket konnte nicht vollständig gesendet werden.');
            }

            $offset += $written;
        }
    }

    private function readExact($socket, int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                $metadata = stream_get_meta_data($socket);
                $reason = ($metadata['timed_out'] ?? false) ? 'Zeitüberschreitung' : 'Verbindung beendet';
                throw new RuntimeException('Zabbix-Antwort unvollständig: ' . $reason);
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function readCsv(string $path): array
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('CSV-Datei nicht gefunden oder nicht lesbar: ' . $path);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('CSV-Datei konnte nicht geöffnet werden.');
        }

        try {
            $header = fgetcsv($handle, 0, ';', '"', '');

            if ($header === false) {
                throw new RuntimeException('CSV-Datei ist leer.');
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map(static fn($value): string => trim((string) $value), $header);

            if ($header !== self::CSV_COLUMNS) {
                throw new RuntimeException(
                    'CSV-Spalten entsprechen nicht SchemaVersion 1. Erwartet: ' . implode(';', self::CSV_COLUMNS)
                );
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                $line++;

                if (count(array_filter($values, static fn($value): bool => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if (count($values) !== count(self::CSV_COLUMNS)) {
                    throw new RuntimeException(sprintf('Zeile %d hat %d statt %d Spalten.', $line, count($values), count(self::CSV_COLUMNS)));
                }

                $rows[] = ['line' => $line, 'values' => array_combine(self::CSV_COLUMNS, $values)];
            }

            if (count($rows) === 0) {
                throw new RuntimeException('CSV-Datei enthält keine Alarmdefinitionen.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function validateRows(array $rows): array
    {
        $matrix = [];
        $ids = [];
        $keys = [];
        $errors = [];
        $operators = ['EQ', 'NE', 'GT', 'GE', 'LT', 'LE', 'BETWEEN', 'OUTSIDE'];

        foreach ($rows as $row) {
            $line = $row['line'];
            $data = array_map(static fn($value): string => trim((string) $value), $row['values']);
            $rowErrors = [];

            if ($data['SchemaVersion'] !== (string) self::SCHEMA_VERSION) {
                $rowErrors[] = 'SchemaVersion muss 1 sein';
            }

            $id = filter_var($data['ID'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $rowErrors[] = 'ID ungültig';
                $id = 0;
            } elseif (isset($ids[$id])) {
                $rowErrors[] = 'ID doppelt';
            }

            if (!in_array($data['Aktiv'], ['0', '1'], true)) {
                $rowErrors[] = 'Aktiv muss 0 oder 1 sein';
            }

            $variableId = filter_var($data['IPS_VariableID'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($variableId === false || !IPS_VariableExists((int) $variableId)) {
                $rowErrors[] = 'IPS_VariableID existiert nicht';
                $variableId = 0;
            }

            $operator = strtoupper($data['Vergleich']);
            if (!in_array($operator, $operators, true)) {
                $rowErrors[] = 'Vergleich ungültig';
            }

            if ($data['Grenzwert1'] === '') {
                $rowErrors[] = 'Grenzwert1 fehlt';
            }

            if (in_array($operator, ['GT', 'GE', 'LT', 'LE', 'BETWEEN', 'OUTSIDE'], true)
                && !$this->isNumericText($data['Grenzwert1'])) {
                $rowErrors[] = 'Grenzwert1 muss numerisch sein';
            }

            if (in_array($operator, ['BETWEEN', 'OUTSIDE'], true)) {
                if (!$this->isNumericText($data['Grenzwert2'])
                    || $this->toFloat($data['Grenzwert2']) <= $this->toFloat($data['Grenzwert1'])) {
                    $rowErrors[] = 'Grenzwert2 muss größer als Grenzwert1 sein';
                }
            }

            if ($data['Hysterese'] !== ''
                && (!$this->isNumericText($data['Hysterese']) || $this->toFloat($data['Hysterese']) < 0)) {
                $rowErrors[] = 'Hysterese ungültig';
            }

            if ($data['Kurztext'] === '' || mb_strlen($data['Kurztext']) > 120) {
                $rowErrors[] = 'Kurztext fehlt oder ist zu lang';
            }

            $priority = filter_var($data['Prioritaet'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
            if ($priority === false) {
                $rowErrors[] = 'Prioritaet muss zwischen 1 und 5 liegen';
                $priority = 0;
            }

            foreach (['Quittierung', 'Email', 'Push'] as $booleanColumn) {
                if (!in_array($data[$booleanColumn], ['0', '1'], true)) {
                    $rowErrors[] = $booleanColumn . ' muss 0 oder 1 sein';
                }
            }

            $delay = filter_var($data['Verzoegerung_s'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($delay === false) {
                $rowErrors[] = 'Verzoegerung_s ungültig';
                $delay = 0;
            }

            $escalation = filter_var($data['Eskalation_min'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($escalation === false) {
                $rowErrors[] = 'Eskalation_min ungültig';
                $escalation = 0;
            }

            $expectedKey = 'alarm.' . $id;
            if ($data['Zabbix_Key'] !== $expectedKey) {
                $rowErrors[] = 'Zabbix_Key muss ' . $expectedKey . ' sein';
            } elseif (isset($keys[$data['Zabbix_Key']])) {
                $rowErrors[] = 'Zabbix_Key doppelt';
            }

            if (count($rowErrors) > 0) {
                $errors[] = sprintf('Zeile %d: %s', $line, implode(', ', $rowErrors));
                continue;
            }

            $ids[$id] = true;
            $keys[$data['Zabbix_Key']] = true;
            $variable = IPS_GetVariable((int) $variableId);

            if (in_array($operator, ['GT', 'GE', 'LT', 'LE', 'BETWEEN', 'OUTSIDE'], true)
                && !in_array($variable['VariableType'], [1, 2], true)) {
                $errors[] = sprintf('Zeile %d: numerischer Vergleich benötigt Integer- oder Float-Variable', $line);
                continue;
            }

            $matrix[] = [
                'id' => (int) $id,
                'active' => $data['Aktiv'] === '1',
                'variableId' => (int) $variableId,
                'variableType' => (int) $variable['VariableType'],
                'operator' => $operator,
                'threshold1' => $this->convertThreshold($data['Grenzwert1'], (int) $variable['VariableType']),
                'threshold2' => $data['Grenzwert2'] === '' ? null : $this->convertThreshold($data['Grenzwert2'], (int) $variable['VariableType']),
                'hysteresis' => $data['Hysterese'] === '' ? 0.0 : $this->toFloat($data['Hysterese']),
                'shortText' => $data['Kurztext'],
                'longText' => $data['Langtext'],
                'category' => $data['Kategorie'],
                'area' => $data['Bereich'],
                'priority' => (int) $priority,
                'ackRequired' => $data['Quittierung'] === '1',
                'email' => $data['Email'] === '1',
                'push' => $data['Push'] === '1',
                'delay' => (int) $delay,
                'escalationMinutes' => (int) $escalation,
                'activationGroup' => $data['Aktivierungsgruppe'],
                'comment' => $data['Kommentar'],
                'key' => $data['Zabbix_Key']
            ];
        }

        if (count($errors) > 0) {
            throw new RuntimeException(implode(' | ', $errors));
        }

        return $matrix;
    }

    private function convertThreshold(string $value, int $variableType)
    {
        return match ($variableType) {
            0 => in_array(strtolower($value), ['1', 'true', 'ja'], true),
            1 => (int) $value,
            2 => $this->toFloat($value),
            default => $value
        };
    }

    private function isNumericText(string $value): bool
    {
        return is_numeric(str_replace(',', '.', trim($value)));
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '.', trim($value));
    }

    private function applyMatrix(array $matrix): void
    {
        $oldVariables = json_decode($this->ReadAttributeString('RegisteredVariables'), true) ?: [];

        foreach ($oldVariables as $variableId) {
            $this->UnregisterMessage((int) $variableId, self::VM_UPDATE_MESSAGE);
            $this->UnregisterReference((int) $variableId);
        }

        $newVariables = [];
        foreach ($matrix as $alarm) {
            if (!$alarm['active']) {
                continue;
            }

            $newVariables[$alarm['variableId']] = true;
        }

        foreach (array_keys($newVariables) as $variableId) {
            $this->RegisterReference((int) $variableId);
            $this->RegisterMessage((int) $variableId, self::VM_UPDATE_MESSAGE);
        }

        $states = $this->getStates();
        $newStates = [];

        foreach ($matrix as $alarm) {
            if (!$alarm['active']) {
                continue;
            }

            $id = (string) $alarm['id'];
            $newStates[$id] = $states[$id] ?? $this->initialState($alarm);
        }

        $this->WriteAttributeString('AlarmMatrix', json_encode($matrix, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->WriteAttributeString('AlarmStates', json_encode($newStates, JSON_THROW_ON_ERROR));
        $this->WriteAttributeString('RegisteredVariables', json_encode(array_map('intval', array_keys($newVariables)), JSON_THROW_ON_ERROR));
    }

    private function restoreVariableSubscriptions(): void
    {
        $variables = json_decode($this->ReadAttributeString('RegisteredVariables'), true) ?: [];

        foreach ($variables as $variableId) {
            if (IPS_VariableExists((int) $variableId)) {
                $this->RegisterReference((int) $variableId);
                $this->RegisterMessage((int) $variableId, self::VM_UPDATE_MESSAGE);
            }
        }
    }

    private function initialState(array $alarm): array
    {
        $current = false;

        if (IPS_VariableExists($alarm['variableId'])) {
            $current = $this->evaluateAlarm($alarm, GetValue($alarm['variableId']), false);
        }

        return [
            'stable' => $current,
            'pendingSince' => 0,
            'lastAttempt' => 0,
            'lastSent' => 0
        ];
    }

    private function evaluateAlarm(array $alarm, $value, bool $wasActive): bool
    {
        $operator = $alarm['operator'];
        $threshold1 = $alarm['threshold1'];
        $threshold2 = $alarm['threshold2'];
        $hysteresis = (float) $alarm['hysteresis'];

        if (!$wasActive || $hysteresis <= 0 || !is_numeric($value)) {
            return match ($operator) {
                'EQ' => $value == $threshold1,
                'NE' => $value != $threshold1,
                'GT' => (float) $value > (float) $threshold1,
                'GE' => (float) $value >= (float) $threshold1,
                'LT' => (float) $value < (float) $threshold1,
                'LE' => (float) $value <= (float) $threshold1,
                'BETWEEN' => (float) $value >= (float) $threshold1 && (float) $value <= (float) $threshold2,
                'OUTSIDE' => (float) $value < (float) $threshold1 || (float) $value > (float) $threshold2,
                default => false
            };
        }

        return match ($operator) {
            'GT', 'GE' => (float) $value > ((float) $threshold1 - $hysteresis),
            'LT', 'LE' => (float) $value < ((float) $threshold1 + $hysteresis),
            'BETWEEN' => (float) $value >= ((float) $threshold1 - $hysteresis)
                && (float) $value <= ((float) $threshold2 + $hysteresis),
            'OUTSIDE' => (float) $value < ((float) $threshold1 + $hysteresis)
                || (float) $value > ((float) $threshold2 - $hysteresis),
            default => match ($operator) {
                'EQ' => $value == $threshold1,
                'NE' => $value != $threshold1,
                default => false
            }
        };
    }

    private function getMatrix(): array
    {
        return json_decode($this->ReadAttributeString('AlarmMatrix'), true) ?: [];
    }

    private function getStates(): array
    {
        return json_decode($this->ReadAttributeString('AlarmStates'), true) ?: [];
    }

    private function setStates(array $states): void
    {
        $this->WriteAttributeString('AlarmStates', json_encode($states, JSON_THROW_ON_ERROR));
    }
}
