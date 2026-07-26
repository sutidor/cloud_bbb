<?php

declare(strict_types=1);

namespace OCA\BigBlueButton\Migration;

use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * This app was renamed from the app id "bbb" to "boss_meeting" (to avoid a
 * collision with the upstream cloud_bbb app on the Nextcloud app store). The DB
 * tables kept their `bbb_*` names, but app config lives under the app id, so
 * copy every value from the old "bbb" app id to "boss_meeting" once. Idempotent:
 * never overwrites a value already set under the new id.
 */
class MigrateAppConfig implements IRepairStep {
	public function __construct(private IConfig $config) {
	}

	public function getName(): string {
		return 'Migrate cloud_bbb app config to boss_meeting';
	}

	public function run(IOutput $output): void {
		$oldKeys = $this->config->getAppKeys('bbb');
		if (empty($oldKeys)) {
			return;
		}
		$copied = 0;
		foreach ($oldKeys as $key) {
			// don't clobber a value already present under the new id
			if ($this->config->getAppValue('boss_meeting', $key, '__none__') !== '__none__') {
				continue;
			}
			$value = $this->config->getAppValue('bbb', $key, '');
			if ($value !== '') {
				$this->config->setAppValue('boss_meeting', $key, $value);
				$copied++;
			}
		}
		$output->info("Migrated {$copied} config value(s) from bbb to boss_meeting");
	}
}
