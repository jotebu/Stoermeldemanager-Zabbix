class CWidgetStoermeldejournal extends CWidget {

	onInitialize() {
		this._filter_state = {
			status: '__all__',
			priority: '__all__',
			category: '__all__',
			area: '__all__',
			alarm_id: ''
		};
		this._show_lines = 50;
	}

	onStart() {
		this._events = {
			...this._events,

			acknowledgeCreated: (event, response) => {
				clearMessages();
				addMessage(makeMessageBox('good', [], response.success.title));

				if (this._state === WIDGET_STATE_ACTIVE) {
					this._startUpdating();
				}
			}
		};
	}

	onActivate() {
		$.subscribe('acknowledge.create', this._events.acknowledgeCreated);
	}

	onDeactivate() {
		$.unsubscribe('acknowledge.create', this._events.acknowledgeCreated);
	}

	setContents(response) {
		this._show_lines = Math.max(1, Number(response.show_lines) || 50);
		super.setContents(response);
		this.#initializeFilters();

		for (const button of this._contents.querySelectorAll('.smj-ack-button')) {
			button.addEventListener('click', event => {
				event.preventDefault();
				this.#openAcknowledgeDialog(button);
			});
		}
	}

	#initializeFilters() {
		const controls = {
			status: this._contents.querySelector('#smj-filter-status'),
			priority: this._contents.querySelector('#smj-filter-priority'),
			category: this._contents.querySelector('#smj-filter-category'),
			area: this._contents.querySelector('#smj-filter-area'),
			alarm_id: this._contents.querySelector('#smj-filter-alarm-id')
		};

		for (const [name, control] of Object.entries(controls)) {
			if (control === null) {
				continue;
			}

			control.value = this._filter_state[name];
			control.addEventListener(name === 'alarm_id' ? 'input' : 'change', () => {
				this._filter_state[name] = control.value;
				this.#applyFilters();
			});
		}

		const reset_button = this._contents.querySelector('#smj-filter-reset');
		if (reset_button !== null) {
			reset_button.addEventListener('click', () => {
				this._filter_state = {
					status: '__all__',
					priority: '__all__',
					category: '__all__',
					area: '__all__',
					alarm_id: ''
				};

				for (const [name, control] of Object.entries(controls)) {
					if (control !== null) {
						control.value = this._filter_state[name];
					}
				}

				this.#applyFilters();
			});
		}

		this.#applyFilters();
	}

	#applyFilters() {
		const rows = [...this._contents.querySelectorAll('.smj-row')];
		const alarm_filter = this.#parseAlarmIdFilter(this._filter_state.alarm_id);
		const alarm_input = this._contents.querySelector('#smj-filter-alarm-id');

		if (alarm_input !== null) {
			alarm_input.classList.toggle('smj-filter-invalid', !alarm_filter.valid);
			alarm_input.title = alarm_filter.valid
				? 'Einzelne IDs und Bereiche mit Komma trennen; Minus schließt IDs aus.'
				: `Ungültige Alarm-ID-Eingabe: ${alarm_filter.invalid_token}`;
		}

		const counts = {
			active: 0,
			acknowledged: 0,
			resolved_unacknowledged: 0,
			resolved_acknowledged: 0
		};
		let matching_rows = 0;

		for (const row of rows) {
			const matches =
				(this._filter_state.status === '__all__'
					|| row.dataset.smjStatus === this._filter_state.status)
				&& (this._filter_state.priority === '__all__'
					|| row.dataset.smjPriority === this._filter_state.priority)
				&& (this._filter_state.category === '__all__'
					|| row.dataset.smjCategory === this._filter_state.category)
				&& (this._filter_state.area === '__all__'
					|| row.dataset.smjArea === this._filter_state.area)
				&& (!alarm_filter.valid
					|| this.#matchesAlarmId(row.dataset.smjAlarmId, alarm_filter.rules));

			if (matches) {
				counts[row.dataset.smjStatus]++;
				matching_rows++;
			}

			row.classList.toggle('smj-filter-hidden', !matches || matching_rows > this._show_lines);
		}

		const labels = {
			active: 'Aktiv unquittiert',
			acknowledged: 'Aktiv quittiert',
			resolved_unacknowledged: 'Behoben unquittiert',
			resolved_acknowledged: 'Behoben quittiert'
		};

		for (const counter of this._contents.querySelectorAll('[data-smj-counter]')) {
			const status = counter.dataset.smjCounter;
			counter.textContent = `${labels[status]}: ${counts[status]}`;
		}

		const empty_message = this._contents.querySelector('#smj-filter-empty');
		if (empty_message !== null) {
			empty_message.classList.toggle('smj-filter-empty-visible', matching_rows === 0);
		}
	}

	#parseAlarmIdFilter(expression) {
		const value = expression.trim();
		if (value === '') {
			return {valid: true, rules: [], invalid_token: ''};
		}

		const tokens = value.split(/[\s,;]+/).filter(token => token !== '');
		const rules = [];

		for (const token of tokens) {
			const match = token.match(/^([+-]?)(\d+)(?:-(\d+))?$/);
			if (match === null) {
				return {valid: false, rules: [], invalid_token: token};
			}

			const first = Number(match[2]);
			const second = match[3] === undefined ? first : Number(match[3]);
			rules.push({
				include: match[1] !== '-',
				from: Math.min(first, second),
				to: Math.max(first, second)
			});
		}

		return {valid: true, rules, invalid_token: ''};
	}

	#matchesAlarmId(alarm_id, rules) {
		if (rules.length === 0) {
			return true;
		}

		const value = Number(alarm_id);
		const include_rules = rules.filter(rule => rule.include);
		if (!Number.isInteger(value)) {
			return include_rules.length === 0;
		}

		const included = include_rules.length === 0
			|| include_rules.some(rule => value >= rule.from && value <= rule.to);
		const excluded = rules
			.filter(rule => !rule.include)
			.some(rule => value >= rule.from && value <= rule.to);

		return included && !excluded;
	}

	#openAcknowledgeDialog(button) {
		const acknowledge_action = Number(button.dataset.ackAction);
		const overlay = acknowledgePopUp({
			eventids: [button.dataset.eventid],
			acknowledge_problem: acknowledge_action
		}, button);

		overlay.xhr.then(() => {
			const checkbox = overlay.$dialogue[0].querySelector('[name="acknowledge_problem"]');

			if (checkbox !== null && !checkbox.checked) {
				checkbox.checked = true;
				checkbox.dispatchEvent(new Event('change', {bubbles: true}));
			}
		});
	}
}
