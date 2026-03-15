<?php

namespace OCA\BigBlueButton\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Transcript>
 */
class TranscriptMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbb_transcripts', Transcript::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByRecordingId(string $recordingId): Transcript {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('recording_id', $qb->createNamedParameter($recordingId)));

		return $this->findEntity($qb);
	}

	/**
	 * Find transcripts for multiple recording IDs at once.
	 *
	 * @param string[] $recordingIds
	 * @return array<string, Transcript> keyed by recording ID
	 */
	public function findByRecordingIds(array $recordingIds): array {
		if (empty($recordingIds)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->in(
				'recording_id',
				$qb->createNamedParameter($recordingIds, IQueryBuilder::PARAM_STR_ARRAY)
			));

		$entities = $this->findEntities($qb);

		$result = [];
		foreach ($entities as $entity) {
			$result[$entity->getRecordingId()] = $entity;
		}

		return $result;
	}
}
