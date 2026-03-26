<?php

namespace OCA\BigBlueButton\Service;

use OCA\BigBlueButton\Db\Transcript;
use OCA\BigBlueButton\Db\TranscriptMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class TranscriptService {
	private TranscriptMapper $mapper;

	public function __construct(TranscriptMapper $mapper) {
		$this->mapper = $mapper;
	}

	public function findByRecordingId(string $recordingId): ?Transcript {
		try {
			return $this->mapper->findByRecordingId($recordingId);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * @param string[] $recordingIds
	 * @return array<string, Transcript> keyed by recording ID
	 */
	public function findByRecordingIds(array $recordingIds): array {
		return $this->mapper->findByRecordingIds($recordingIds);
	}

	public function createOrUpdate(
		string $recordingId,
		string $transcriptVtt,
		string $transcriptTxt,
		string $notesMd,
		string $language,
		string $status
	): Transcript {
		$existing = $this->findByRecordingId($recordingId);
		$now = time();

		if ($existing !== null) {
			if (!empty($transcriptVtt)) {
				$existing->setTranscriptVtt($transcriptVtt);
			}
			if (!empty($transcriptTxt)) {
				$existing->setTranscriptTxt($transcriptTxt);
			}
			if (!empty($notesMd)) {
				$existing->setNotesMd($notesMd);
			}
			if (!empty($language)) {
				$existing->setLanguage($language);
			}
			$existing->setStatus($status);
			$existing->setUpdatedAt($now);

			return $this->mapper->update($existing);
		}

		$transcript = new Transcript();
		$transcript->setRecordingId($recordingId);
		$transcript->setTranscriptVtt($transcriptVtt);
		$transcript->setTranscriptTxt($transcriptTxt);
		$transcript->setNotesMd($notesMd);
		$transcript->setLanguage($language);
		$transcript->setStatus($status);
		$transcript->setCreatedAt($now);
		$transcript->setUpdatedAt($now);

		return $this->mapper->insert($transcript);
	}
}
