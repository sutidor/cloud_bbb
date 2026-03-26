<?php

namespace OCA\BigBlueButton\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getRecordingId()
 * @method string getTranscriptVtt()
 * @method string getTranscriptTxt()
 * @method string getNotesMd()
 * @method string getLanguage()
 * @method string getStatus()
 * @method int getCreatedAt()
 * @method int getUpdatedAt()
 * @method void setRecordingId(string $id)
 * @method void setTranscriptVtt(string $vtt)
 * @method void setTranscriptTxt(string $txt)
 * @method void setNotesMd(string $md)
 * @method void setLanguage(string $lang)
 * @method void setStatus(string $status)
 * @method void setCreatedAt(int $ts)
 * @method void setUpdatedAt(int $ts)
 */
class Transcript extends Entity implements JsonSerializable {
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_COMPLETE = 'complete';
	public const STATUS_PARTIAL = 'partial';
	public const STATUS_FAILED = 'failed';

	public $recordingId;
	public $transcriptVtt;
	public $transcriptTxt;
	public $notesMd;
	public $language;
	public $status;
	public $createdAt;
	public $updatedAt;

	public function __construct() {
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'recordingId' => $this->recordingId,
			'language' => $this->language,
			'status' => $this->status,
			'hasTranscript' => !empty($this->transcriptTxt),
			'hasNotes' => !empty($this->notesMd),
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
