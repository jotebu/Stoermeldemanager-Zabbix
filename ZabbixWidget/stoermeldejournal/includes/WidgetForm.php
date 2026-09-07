<?php declare(strict_types = 0);

namespace Modules\Stoermeldejournal\Includes;

use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectGroup;
use Zabbix\Widgets\Fields\CWidgetFieldSelect;

class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(new CWidgetFieldMultiSelectGroup('groupids', 'Hostgruppen'))
			->addField(
				(new CWidgetFieldIntegerBox('history_days', 'Verlauf in Tagen', 1, 3650))->setDefault(30)
			)
			->addField(
				(new CWidgetFieldSelect('status_filter', 'Status', [
					0 => 'Alle Meldungen',
					1 => 'Aktive Meldungen',
					2 => 'Behobene Meldungen'
				]))->setDefault(0)
			)
			->addField(
				(new CWidgetFieldIntegerBox('show_lines', 'Angezeigte Zeilen', 10, 1000))->setDefault(50)
			);
	}
}
