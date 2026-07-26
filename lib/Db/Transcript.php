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
 * @method string getTitle()
 * @method bool getTitleLocked()
 * @method string getParticipants()
 * @method int getNotifiedAt()
 * @method int getCreatedAt()
 * @method int getUpdatedAt()
 * @method void setRecordingId(string $id)
 * @method void setTranscriptVtt(string $vtt)
 * @method void setTranscriptTxt(string $txt)
 * @method void setNotesMd(string $md)
 * @method void setLanguage(string $lang)
 * @method void setStatus(string $status)
 * @method void setTitle(string $title)
 * @method void setTitleLocked(bool $locked)
 * @method void setParticipants(string $json)
 * @method void setNotifiedAt(int $ts)
 * @method void setCreatedAt(int $ts)
 * @method void setUpdatedAt(int $ts)
 */
class Transcript extends Entity implements JsonSerializable {
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_COMPLETE = 'complete';
	public const STATUS_PARTIAL = 'partial';
	public const STATUS_FAILED = 'failed';
	// Kept after the BBB recording (video) was deleted by retention "archive" mode
	public const STATUS_ARCHIVED = 'archived';
	// Row exists only to hold a user-set title (recording never transcribed)
	public const STATUS_NONE = 'none';

	public $recordingId;
	public $transcriptVtt;
	public $transcriptTxt;
	public $notesMd;
	public $language;
	public $status;
	public $title;
	public $titleLocked;
	public $participants;
	public $notifiedAt;
	public $createdAt;
	public $updatedAt;

	public function __construct() {
		$this->addType('titleLocked', 'boolean');
		$this->addType('notifiedAt', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	/** @return array participant roster (decoded from the stored JSON) */
	public function getParticipantList(): array {
		if (empty($this->participants)) {
			return [];
		}
		$decoded = json_decode($this->participants, true);
		return is_array($decoded) ? $decoded : [];
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'recordingId' => $this->recordingId,
			'language' => $this->language,
			'status' => $this->status,
			'title' => $this->title,
			'participants' => $this->getParticipantList(),
			'hasTranscript' => !empty($this->transcriptTxt) || !empty($this->transcriptVtt),
			'hasNotes' => !empty($this->notesMd),
			'notifiedAt' => $this->notifiedAt,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
