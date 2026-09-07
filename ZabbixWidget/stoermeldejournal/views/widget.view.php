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
		'active' => _('Störung'),
		'acknowledged' => _('Quittiert'),
		'resolved' => _('Behoben')
	];

	return (new CSpan($labels[$row['status_code']]))
		->addClass('smj-status')
		->addClass('smj-status-'.$row['status_code']);
};

$make_severity = static function (int $severity): CSpan {
	$labels = [
		0 => _('Not classified'),
		1 => _('Information'),
		2 => _('Warning'),
		3 => _('Average'),
		4 => _('High'),
		5 => _('Disaster')
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
	(new CSpan(_('Active').': '.$data['counts']['active']))->addClass('smj-counter smj-counter-active'),
	(new CSpan(_('Acknowledged').': '.$data['counts']['acknowledged']))
		->addClass('smj-counter smj-counter-acknowledged'),
	(new CSpan(_('Resolved').': '.$data['counts']['resolved']))->addClass('smj-counter smj-counter-resolved')
]))->addClass('smj-summary');

$table = (new CTableInfo())->setHeader([
	_('Alarm ID'),
	_('Severity'),
	_('Category'),
	_('Area'),
	_('Problem'),
	_('Occurred'),
	_('Acknowledged'),
	_('Acknowledged by'),
	_('Resolved'),
	_('Status'),
	_('Action')
]);

foreach ($data['rows'] as $row) {
	$ack_user = $display_text($row['ack_user']);
	if ($row['ack_message'] !== '') {
		$ack_user = (new CSpan($ack_user))->setAttribute('title', $row['ack_message']);
	}

	$action = '—';
	if ($row['status_code'] === 'active' && $data['allowed_acknowledge']) {
		$action = (new CLink('Quittieren'))
			->addClass('smj-ack-button')
			->setAttribute('data-eventid', $row['eventid'])
			->onClick(
				'acknowledgePopUp({eventids: [this.dataset.eventid], acknowledge_problem: '.
				ZBX_PROBLEM_UPDATE_ACKNOWLEDGE.'}, this);'
			);
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
