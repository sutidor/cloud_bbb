<?php

declare(strict_types=1);

namespace OCA\BigBlueButton\BackgroundJob;

use OCA\BigBlueButton\BigBlueButton\API;
use OCA\BigBlueButton\Db\Transcript;
use OCA\BigBlueButton\Db\TranscriptMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Retention: once a day, delete published recordings older than
 * `retention.days` that are NOT marked persist=true.
 *
 * Modes (retention.mode):
 *   archive  delete the BBB recording (video) but KEEP the transcript + notes,
 *            flagged status=archived so they stay browsable in the plugin.
 *   full     delete the BBB recording AND the stored transcript.
 *
 * Dry-run (retention.dryRun, default on) logs what it would do and deletes
 * nothing. Controlled from the app's admin settings.
 */
class DeleteOldRecordingsJob extends TimedJob {
	private const SECONDS_PER_DAY = 86400;

	public function __construct(
		ITimeFactory $time,
		private API $api,
		private TranscriptMapper $transcriptMapper,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(self::SECONDS_PER_DAY);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		if (!$this->appConfig->getValueBool('boss_meeting', 'retention.enabled', false)) {
			return;
		}
		$retentionDays = $this->appConfig->getValueInt('boss_meeting', 'retention.days', 180);
		if ($retentionDays <= 0) {
			return;
		}
		$dryRun = $this->appConfig->getValueBool('boss_meeting', 'retention.dryRun', true);
		$mode = $this->appConfig->getValueString('boss_meeting', 'retention.mode', 'archive');
		$cutoff = time() - ($retentionDays * self::SECONDS_PER_DAY);

		try {
			$recordings = $this->api->getAllRecordings();
		} catch (\Throwable $e) {
			$this->logger->error('bbb retention: could not list recordings', ['exception' => $e]);
			return;
		}

		$acted = 0;
		$kept = 0;
		foreach ($recordings as $rec) {
			$startSec = (int)((int)$rec['startTime'] / 1000);
			if ($startSec === 0 || $startSec >= $cutoff) {
				continue; // within the retention window
			}
			$metas = $rec['metas'] ?? [];
			if (isset($metas['persist']) && (string)$metas['persist'] === 'true') {
				$kept++;
				continue;
			}

			$ageDays = (int)((time() - $startSec) / self::SECONDS_PER_DAY);
			if ($dryRun) {
				$this->logger->warning('bbb retention [dry-run]: WOULD ' . $mode . ' recording', [
					'recordingId' => $rec['id'], 'name' => $rec['name'] ?? '', 'ageDays' => $ageDays,
				]);
				$acted++;
				continue;
			}

			if ($this->deleteRecording($rec['id'], $mode)) {
				$this->logger->warning('bbb retention: ' . $mode . 'd recording', [
					'recordingId' => $rec['id'], 'name' => $rec['name'] ?? '',
				]);
				$acted++;
			}
		}

		$this->logger->warning('bbb retention: run complete', [
			'dryRun' => $dryRun, 'mode' => $mode, 'retentionDays' => $retentionDays,
			'actedOrWouldAct' => $acted, 'persistedKept' => $kept,
		]);
	}

	/**
	 * Delete a recording. In 'archive' mode the BBB media is removed but the
	 * transcript row is kept (status=archived). In 'full' mode the transcript
	 * row is removed too.
	 */
	private function deleteRecording(string $recordingId, string $mode): bool {
		try {
			$this->api->deleteRecording($recordingId);
		} catch (\Throwable $e) {
			$this->logger->warning('bbb retention: BBB delete failed', [
				'recordingId' => $recordingId, 'exception' => $e,
			]);
			return false;
		}

		try {
			$transcript = $this->transcriptMapper->findByRecordingId($recordingId);
			if ($mode === 'full') {
				$this->transcriptMapper->delete($transcript);
			} else {
				$transcript->setStatus(Transcript::STATUS_ARCHIVED);
				$transcript->setUpdatedAt(time());
				$this->transcriptMapper->update($transcript);
			}
		} catch (DoesNotExistException $e) {
			// no transcript to keep/remove — the media deletion is enough
		}
		return true;
	}
}
