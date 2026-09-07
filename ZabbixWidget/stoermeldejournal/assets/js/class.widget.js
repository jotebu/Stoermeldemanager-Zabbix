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

	setContents(response) {
		super.setContents(response);

		for (const button of this._contents.querySelectorAll('.smj-ack-button')) {
			button.addEventListener('click', event => {
				event.preventDefault();
				this.#openAcknowledgeDialog(button);
			});
		}
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
