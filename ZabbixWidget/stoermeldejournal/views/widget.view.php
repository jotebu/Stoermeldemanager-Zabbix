<?php declare(strict_types = 0);

/** @var CView $this */
/** @var array $data */

$format_time = static function (?int $clock): string {
	return $clock === null ? '—' : zbx_date2str(DATE_TIME_FORMAT_SECONDS, $clock);
};

$display_text = static function (string $value): string {
	return $value === '' ? '—' : $value;
};

$make_status = static function (array $row): CSpan {
	$labels = [
		'active' => 'Störung – unquittiert',
		'acknowledged' => 'Störung – quittiert',
		'resolved_unacknowledged' => 'Behoben – unquittiert',
		'resolved_acknowledged' => 'Behoben – quittiert'
	];

	$status = (new CSpan($labels[$row['status_code']]))
		->addClass('smj-status')
		->addClass('smj-status-'.$row['status_code']);

	if ($row['ack_message'] !== '') {
		$status
			->addClass('smj-status-has-comment')
			->setAttribute('title', 'Quittierkommentar: '.$row['ack_message']);
	}

	return $status;
};

$make_severity = static function (int $severity): CSpan {
	$labels = [
		0 => 'Unklassifiziert',
		1 => 'Information',
		2 => 'Warnung',
		3 => 'Mittel',
		4 => 'Hoch',
		5 => 'Kritisch'
	];

	return (new CSpan($labels[$severity] ?? (string) $severity))
		->addClass('smj-severity')
		->addClass('smj-severity-'.$severity);
};

if ($data['error'] !== null) {
	$table = new CTableInfo();
	$table->setNoDataMessage($data['error']);

	(new CWidgetView($data))->addItem($table)->show();
	return;
}

$make_options = static function (array $rows, string $field, string $all_label): array {
	$values = [];
	foreach ($rows as $row) {
		$value = trim((string) $row[$field]);
		if ($value !== '') {
			$values[$value] = $value;
		}
	}

	$values = array_values($values);
	usort($values, 'strnatcasecmp');

	$options = ['__all__' => $all_label];
	foreach ($values as $value) {
		$options[$value] = $value;
	}

	return $options;
};

$make_select_filter = static function (string $id, string $label, array $options): CDiv {
	return (new CDiv([
		(new CSpan($label))->addClass('smj-filter-label'),
		(new CSelect($id))
			->setId($id)
			->setValue('__all__')
			->setWidthAuto()
			->addOptions(CSelect::createOptionsFromArray($options))
	]))->addClass('smj-filter-field');
};

$make_date_filter = static function (string $id, string $name, string $label): CDiv {
	return (new CDiv([
		(new CSpan($label))->addClass('smj-filter-label'),
		(new CTextBox($name, ''))
			->setId($id)
			->setAttribute('type', 'date')
	]))->addClass('smj-filter-field smj-filter-field-date');
};

$filter_bar = (new CDiv([
	$make_select_filter('smj-filter-status', 'Status', [
		'__all__' => 'Alle Zustände',
		'active' => 'Störung – unquittiert',
		'acknowledged' => 'Störung – quittiert',
		'resolved_unacknowledged' => 'Behoben – unquittiert',
		'resolved_acknowledged' => 'Behoben – quittiert'
	]),
	$make_select_filter('smj-filter-priority', 'Priorität', [
		'__all__' => 'Alle Prioritäten',
		'5' => 'Kritisch',
		'4' => 'Hoch',
		'3' => 'Mittel',
		'2' => 'Warnung',
		'1' => 'Information',
		'0' => 'Unklassifiziert'
	]),
	$make_select_filter(
		'smj-filter-category',
		'Kategorie',
		$make_options($data['rows'], 'category', 'Alle Kategorien')
	),
	$make_select_filter(
		'smj-filter-area',
		'Bereich',
		$make_options($data['rows'], 'area', 'Alle Bereiche')
	),
	(new CDiv([
		(new CSpan('Alarm-ID'))->addClass('smj-filter-label'),
		(new CTextBox('smj_filter_alarm_id', ''))
			->setId('smj-filter-alarm-id')
			->setAttribute('placeholder', 'z. B. 1001, 1010-1020, -1015')
			->setAttribute('title', 'Einzelne IDs und Bereiche mit Komma trennen; Minus schließt IDs aus.')
	]))->addClass('smj-filter-field smj-filter-field-alarm-id'),
	$make_date_filter('smj-filter-date-exact', 'smj_filter_date_exact', 'Datum'),
	$make_date_filter('smj-filter-date-from', 'smj_filter_date_from', 'Von'),
	$make_date_filter('smj-filter-date-to', 'smj_filter_date_to', 'Bis'),
	(new CButton('smj_filter_reset', 'Filter zurücksetzen'))
		->setId('smj-filter-reset')
		->addClass('smj-filter-reset')
]))->addClass('smj-filter-bar');

$filter_empty = (new CDiv('Keine Meldung entspricht den gewählten Filtern.'))
	->setId('smj-filter-empty')
	->addClass('smj-filter-empty');

$summary = (new CDiv([
	(new CSpan('Aktiv unquittiert: '.$data['counts']['active']))
		->addClass('smj-counter smj-counter-active')
		->setAttribute('data-smj-counter', 'active'),
	(new CSpan('Aktiv quittiert: '.$data['counts']['acknowledged']))
		->addClass('smj-counter smj-counter-acknowledged')
		->setAttribute('data-smj-counter', 'acknowledged'),
	(new CSpan('Behoben unquittiert: '.$data['counts']['resolved_unacknowledged']))
		->addClass('smj-counter smj-counter-resolved-unacknowledged')
		->setAttribute('data-smj-counter', 'resolved_unacknowledged'),
	(new CSpan('Behoben quittiert: '.$data['counts']['resolved_acknowledged']))
		->addClass('smj-counter smj-counter-resolved-acknowledged')
		->setAttribute('data-smj-counter', 'resolved_acknowledged')
]))->addClass('smj-summary');

$table = (new CTableInfo())->setHeader([
	'Alarm-ID',
	'Priorität',
	'Kategorie',
	'Bereich',
	'Meldung',
	'Eingang',
	'Quittiert am',
	'Quittiert durch',
	'Behoben am',
	'Status',
	'Aktion'
]);

foreach ($data['rows'] as $row) {
	$ack_user = $display_text($row['ack_user']);
	if ($row['ack_message'] !== '') {
		$ack_user = (new CSpan($ack_user))->setAttribute('title', $row['ack_message']);
	}

	$action = '—';
	if (in_array($row['status_code'], ['active', 'resolved_unacknowledged'], true)
			&& $data['allowed_acknowledge']) {
		$action = (new CLink('Quittieren'))
			->addClass('smj-ack-button')
			->setAttribute('data-eventid', $row['eventid'])
			->setAttribute('data-ack-action', ZBX_PROBLEM_UPDATE_ACKNOWLEDGE);
	}

	$table->addRow(
		(new CRow([
			$display_text($row['alarm_id']),
			$make_severity($row['severity']),
			$display_text($row['category']),
			$display_text($row['area']),
			$row['name'],
			$format_time($row['clock']),
			$format_time($row['ack_clock']),
			$ack_user,
			$format_time($row['resolved_clock']),
			$make_status($row),
			$action
		]))
			->addClass('smj-row')
			->addClass('smj-row-'.$row['status_code'])
			->setAttribute('data-smj-status', $row['status_code'])
			->setAttribute('data-smj-priority', (string) $row['severity'])
			->setAttribute('data-smj-category', $row['category'])
			->setAttribute('data-smj-area', $row['area'])
			->setAttribute('data-smj-alarm-id', $row['alarm_id'])
			->setAttribute('data-smj-date', zbx_date2str('Y-m-d', $row['clock']))
	);
}

(new CWidgetView($data))
	->addItem($filter_bar)
	->addItem($summary)
	->addItem($filter_empty)
	->addItem((new CDiv($table))->addClass('smj-table-wrap'))
	->setVar('show_lines', $data['show_lines'])
	->show();
