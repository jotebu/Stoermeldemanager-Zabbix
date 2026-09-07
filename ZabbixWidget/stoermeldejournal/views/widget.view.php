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

$summary = (new CDiv([
	(new CSpan('Aktiv unquittiert: '.$data['counts']['active']))->addClass('smj-counter smj-counter-active'),
	(new CSpan('Aktiv quittiert: '.$data['counts']['acknowledged']))
		->addClass('smj-counter smj-counter-acknowledged'),
	(new CSpan('Behoben unquittiert: '.$data['counts']['resolved_unacknowledged']))
		->addClass('smj-counter smj-counter-resolved-unacknowledged'),
	(new CSpan('Behoben quittiert: '.$data['counts']['resolved_acknowledged']))
		->addClass('smj-counter smj-counter-resolved-acknowledged')
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
	);
}

(new CWidgetView($data))
	->addItem($summary)
	->addItem((new CDiv($table))->addClass('smj-table-wrap'))
	->show();
