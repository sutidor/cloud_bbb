<?php

declare(strict_types=1);

namespace OCA\BigBlueButton\BackgroundJob;

use OCA\BigBlueButton\BigBlueButton\API;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Retention: once a day, delete published recordings older than
 * `retention_days` that are NOT marked persist=true. Runs in dry-run mode by
 * default (logs what it WOULD delete, deletes nothing) until an admin sets
 * `retention_dry_run` to false.
 *
 * Config (occ config:app:set bbb <key> --value ...):
 *   retention_days      integer, default 0  (0 = disabled)
 *   retention_dry_run   '1' (default) or '0'
 */
class DeleteOldRecordingsJob extends TimedJob {
	private const SECONDS_PER_DAY = 86400;

	public function __construct(
		ITimeFactory $time,
		private API $api,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(self::SECONDS_PER_DAY);
		// Don't block anything else; fine to run a bit late.
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$retentionDays = $this->appConfig->getValueInt('bbb', 'retention_days', 0);
		if ($retentionDays <= 0) {
			return; // disabled
		}
		$dryRun = $this->appConfig->getValueString('bbb', 'retention_dry_run', '1') !== '0';
		$cutoff = time() - ($retentionDays * self::SECONDS_PER_DAY);

		try {
			$recordings = $this->api->getAllRecordings();
		} catch (\Throwable $e) {
			$this->logger->error('bbb retention: could not list recordings', ['exception' => $e]);
			return;
		}

		$deleted = 0;
		$kept = 0;
		foreach ($recordings as $rec) {
			// startTime is epoch milliseconds (string) from BBB
			$startSec = (int)((int)$rec['startTime'] / 1000);
			if ($startSec === 0 || $startSec >= $cutoff) {
				continue; // newer than the retention window
			}
			$metas = $rec['metas'] ?? [];
			$persist = isset($metas['persist']) && (string)$metas['persist'] === 'true';
			if ($persist) {
				$kept++;
				continue;
			}

			if ($dryRun) {
				$this->logger->warning('bbb retention [dry-run]: WOULD delete recording', [
					'recordingId' => $rec['id'],
					'name' => $rec['name'] ?? '',
					'ageDays' => (int)((time() - $startSec) / self::SECONDS_PER_DAY),
				]);
				$deleted++;
				continue;
			}

			try {
				if ($this->api->deleteRecording($rec['id'])) {
					$this->logger->warning('bbb retention: DELETED recording', ['recordingId' => $rec['id'], 'name' => $rec['name'] ?? '']);
					$deleted++;
				}
			} catch (\Throwable $e) {
				$this->logger->warning('bbb retention: delete failed', [
					'recordingId' => $rec['id'],
					'exception' => $e,
				]);
			}
		}

		$this->logger->warning('bbb retention: run complete', [
			'dryRun' => $dryRun,
			'retentionDays' => $retentionDays,
			'deletedOrWouldDelete' => $deleted,
			'persistedKept' => $kept,
		]);
	}
}
