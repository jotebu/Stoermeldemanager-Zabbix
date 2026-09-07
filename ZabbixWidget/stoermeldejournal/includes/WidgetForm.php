<?php declare(strict_types = 0);

namespace Modules\Stoermeldejournal\Includes;

use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectGroup;

class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		return $this
			->addField(new CWidgetFieldMultiSelectGroup('groupids', 'Hostgruppen'))
			->addField(
				(new CWidgetFieldIntegerBox('show_lines', 'Angezeigte Zeilen', 10, 1000))->setDefault(50)
			);
	}
}
