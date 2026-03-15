<?php

declare(strict_types=1);

namespace OCA\BigBlueButton\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create bbb_transcripts table for storing recording transcripts and AI notes.
 */
class Version000000Date20260315120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbb_transcripts')) {
			$table = $schema->createTable('bbb_transcripts');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('recording_id', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('transcript_vtt', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('transcript_txt', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('notes_md', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('language', Types::STRING, [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 20,
				'default' => 'processing',
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['recording_id'], 'bbb_transcript_rec_idx');
		}

		return $schema;
	}
}
