class CWidgetStoermeldejournal extends CWidget {

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
}
