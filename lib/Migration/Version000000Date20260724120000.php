<?php

declare(strict_types=1);

namespace OCA\BigBlueButton\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add title (AI-generated, user-editable), participant roster, and an
 * email-notification marker to bbb_transcripts.
 */
class Version000000Date20260724120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbb_transcripts')) {
			return null;
		}

		$table = $schema->getTable('bbb_transcripts');

		if (!$table->hasColumn('title')) {
			$table->addColumn('title', Types::STRING, [
				'notnull' => false,
				'length' => 191,
			]);
		}
		if (!$table->hasColumn('title_locked')) {
			// true once a user edits the title, so reprocessing won't overwrite it
			$table->addColumn('title_locked', Types::BOOLEAN, [
				'notnull' => false,
				'default' => false,
			]);
		}
		if (!$table->hasColumn('participants')) {
			// JSON array of {name, extId, role, talkSeconds}
			$table->addColumn('participants', Types::TEXT, [
				'notnull' => false,
			]);
		}
		if (!$table->hasColumn('notified_at')) {
			// epoch seconds the minutes email was sent (null = never)
			$table->addColumn('notified_at', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
		}

		return $schema;
	}
}
