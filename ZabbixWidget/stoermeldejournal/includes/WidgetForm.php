<?php declare(strict_types = 0);

namespace Modules\Stoermeldejournal\Includes;

use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectGroup;
use Zabbix\Widgets\Fields\CWidgetFieldSelect;

class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(new CWidgetFieldMultiSelectGroup('groupids', _('Host groups')))
			->addField(
				(new CWidgetFieldIntegerBox('history_days', _('History in days'), 1, 3650))->setDefault(30)
			)
			->addField(
				(new CWidgetFieldSelect('status_filter', _('Status'), [
					0 => _('All'),
					1 => _('Active'),
					2 => _('Resolved')
				]))->setDefault(0)
			)
			->addField(
				(new CWidgetFieldIntegerBox('show_lines', _('Show lines'), 10, 1000))->setDefault(50)
			);
	}
}
