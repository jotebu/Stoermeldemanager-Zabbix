<?php declare(strict_types = 0);

namespace Modules\Stoermeldejournal\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use CRoleHelper;
use Throwable;

/** Supplies a consolidated COMING -> ACKNOWLEDGED -> RESOLVED journal. */
class WidgetView extends CControllerDashboardWidgetView {

	private const ACKNOWLEDGE_ACTION = 2;
	private const API_LIMIT = 1000;

	protected function doAction(): void {
		try {
			$groupids = $this->resolveGroupIds($this->fields_values['groupids'] ?? []);
			$history_days = max(1, min(3650, (int) ($this->fields_values['history_days'] ?? 30)));
			$show_lines = max(10, min(1000, (int) ($this->fields_values['show_lines'] ?? 50)));
			$events = $this->loadHistory($groupids, time() - ($history_days * 86400));
			$events = $this->mergeActiveProblems($events, $this->loadActiveProblems($groupids));
			$recovery_clocks = $this->loadRecoveryClocks($events);
			$users = $this->loadUsers($events);

			$rows = [];
			foreach ($events as $event) {
				$rows[] = $this->makeRow($event, $recovery_clocks, $users);
			}

			usort($rows, static function (array $left, array $right): int {
				$clock_compare = $right['clock'] <=> $left['clock'];
				if ($clock_compare !== 0) {
					return $clock_compare;
				}

				return (int) $right['eventid'] <=> (int) $left['eventid'];
			});

			$counts = [
				'active' => 0,
				'acknowledged' => 0,
				'resolved_unacknowledged' => 0,
				'resolved_acknowledged' => 0
			];
			foreach ($rows as $row) {
				$counts[$row['status_code']]++;
			}

			$this->setResponse(new CControllerResponseData([
				'name' => $this->getInput('name', $this->widget->getDefaultName()),
				'rows' => $rows,
				'counts' => $counts,
				'show_lines' => $show_lines,
				'allowed_acknowledge' => $this->checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
				'error' => null,
				'user' => ['debug_mode' => $this->getDebugMode()]
			]));
		}
		catch (Throwable $exception) {
			$this->setResponse(new CControllerResponseData([
				'name' => $this->getInput('name', $this->widget->getDefaultName()),
				'rows' => [],
				'counts' => [
					'active' => 0,
					'acknowledged' => 0,
					'resolved_unacknowledged' => 0,
					'resolved_acknowledged' => 0
				],
				'show_lines' => $show_lines ?? 50,
				'allowed_acknowledge' => false,
				'error' => $exception->getMessage(),
				'user' => ['debug_mode' => $this->getDebugMode()]
			]));
		}
	}

	private function resolveGroupIds(array $configured_groupids): array {
		$groupids = array_values(array_filter(array_map('strval', $configured_groupids)));
		if ($groupids !== []) {
			return $groupids;
		}

		$groups = API::HostGroup()->get([
			'output' => ['groupid'],
			'filter' => ['name' => 'Alarmmatrix']
		]);

		if ($groups === []) {
			throw new \RuntimeException(
				'Die Hostgruppe "Alarmmatrix" wurde nicht gefunden. Bitte im Widget eine Hostgruppe auswählen.'
			);
		}

		return array_column($groups, 'groupid');
	}

	private function loadHistory(array $groupids, int $time_from): array {
		$events = API::Event()->get([
			'output' => ['eventid', 'objectid', 'clock', 'name', 'severity', 'acknowledged', 'r_eventid'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'value' => TRIGGER_VALUE_TRUE,
			'groupids' => $groupids,
			'time_from' => $time_from,
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action'],
			'selectTags' => ['tag', 'value'],
			'sortfield' => ['clock', 'eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => self::API_LIMIT
		]);

		$result = [];
		foreach ($events as $event) {
			$result[(string) $event['eventid']] = $event;
		}

		return $result;
	}

	private function loadActiveProblems(array $groupids): array {
		return API::Problem()->get([
			'output' => ['eventid', 'objectid', 'clock', 'name', 'severity', 'acknowledged', 'r_eventid'],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'groupids' => $groupids,
			'recent' => false,
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action'],
			'selectTags' => ['tag', 'value'],
			'limit' => self::API_LIMIT
		]);
	}

	private function mergeActiveProblems(array $events, array $active_problems): array {
		foreach ($active_problems as $problem) {
			$events[(string) $problem['eventid']] = $problem;
		}

		return $events;
	}

	private function loadRecoveryClocks(array $events): array {
		$recovery_eventids = [];
		foreach ($events as $event) {
			if (!empty($event['r_eventid']) && (string) $event['r_eventid'] !== '0') {
				$recovery_eventids[] = (string) $event['r_eventid'];
			}
		}

		if ($recovery_eventids === []) {
			return [];
		}

		$recovery_events = API::Event()->get([
			'output' => ['eventid', 'clock'],
			'eventids' => array_values(array_unique($recovery_eventids)),
			'preservekeys' => true
		]);

		$clocks = [];
		foreach ($recovery_events as $eventid => $event) {
			$clocks[(string) $eventid] = (int) $event['clock'];
		}

		return $clocks;
	}

	private function loadUsers(array $events): array {
		$userids = [];
		foreach ($events as $event) {
			foreach ($event['acknowledges'] ?? [] as $acknowledgement) {
				if (!empty($acknowledgement['userid'])) {
					$userids[] = (string) $acknowledgement['userid'];
				}
			}
		}

		if ($userids === []) {
			return [];
		}

		try {
			$api_users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_values(array_unique($userids))
			]);
		}
		catch (Throwable $exception) {
			return [];
		}

		$users = [];
		foreach ($api_users as $user) {
			$display_name = trim(((string) ($user['name'] ?? '')).' '.((string) ($user['surname'] ?? '')));
			if ($display_name === '') {
				$display_name = (string) ($user['username'] ?? '');
			}

			$users[(string) $user['userid']] = $display_name;
		}

		return $users;
	}

	private function makeRow(array $event, array $recovery_clocks, array $users): array {
		$tags = [];
		foreach ($event['tags'] ?? [] as $tag) {
			$tags[mb_strtolower(trim((string) $tag['tag']))] = trim((string) $tag['value']);
		}

		$acknowledgement = $this->firstAcknowledgement($event['acknowledges'] ?? [], $users);
		$r_eventid = (string) ($event['r_eventid'] ?? '0');
		$resolved_clock = $r_eventid !== '0' ? ($recovery_clocks[$r_eventid] ?? null) : null;
		$was_acknowledged = $acknowledgement !== null;

		if ($resolved_clock !== null) {
			$status_code = $was_acknowledged ? 'resolved_acknowledged' : 'resolved_unacknowledged';
		}
		elseif ($was_acknowledged) {
			$status_code = 'acknowledged';
		}
		else {
			$status_code = 'active';
		}

		$name = (string) ($event['name'] ?? '');
		$alarm_id = $this->tagValue($tags, ['alarmid', 'alarm-id', 'id']);
		if ($alarm_id === '' && preg_match('/\b(\d{4,})\b/u', $name, $match) === 1) {
			$alarm_id = $match[1];
		}

		return [
			'eventid' => (string) $event['eventid'],
			'alarm_id' => $alarm_id,
			'category' => $this->tagValue($tags, ['kategorie', 'category']),
			'area' => $this->tagValue($tags, ['bereich', 'area']),
			'name' => $name,
			'severity' => (int) ($event['severity'] ?? 0),
			'clock' => (int) $event['clock'],
			'ack_clock' => $acknowledgement['clock'] ?? null,
			'ack_user' => $acknowledgement['user'] ?? '',
			'ack_message' => $acknowledgement['message'] ?? '',
			'resolved_clock' => $resolved_clock,
			'status_code' => $status_code
		];
	}

	private function firstAcknowledgement(array $updates, array $users): ?array {
		$acknowledgements = [];
		foreach ($updates as $update) {
			if (((int) ($update['action'] ?? 0) & self::ACKNOWLEDGE_ACTION) === 0) {
				continue;
			}

			$userid = (string) ($update['userid'] ?? '');
			$display_name = $users[$userid] ?? ($userid !== '' ? 'Benutzer #'.$userid : '');

			$acknowledgements[] = [
				'clock' => (int) $update['clock'],
				'user' => $display_name,
				'message' => (string) ($update['message'] ?? '')
			];
		}

		if ($acknowledgements === []) {
			return null;
		}

		usort($acknowledgements, static fn(array $a, array $b): int => $a['clock'] <=> $b['clock']);

		return $acknowledgements[0];
	}

	private function tagValue(array $tags, array $names): string {
		foreach ($names as $name) {
			if (array_key_exists($name, $tags)) {
				return $tags[$name];
			}
		}

		return '';
	}
}
