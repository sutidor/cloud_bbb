<?php

namespace OCA\BigBlueButton\Controller;

use OCA\BigBlueButton\Db\Transcript;
use OCA\BigBlueButton\Db\TranscriptMapper;
use OCA\BigBlueButton\BigBlueButton\API;
use OCA\BigBlueButton\Permission;
use OCA\BigBlueButton\Service\RoomService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class TranscriptController extends Controller {
	private TranscriptMapper $mapper;
	private API $server;
	private Permission $permission;
	private RoomService $roomService;
	private IConfig $config;
	private ?string $userId;

	public function __construct(
		string $appName,
		IRequest $request,
		TranscriptMapper $mapper,
		API $server,
		Permission $permission,
		RoomService $roomService,
		IConfig $config,
		?string $userId
	) {
		parent::__construct($appName, $request);
		$this->mapper = $mapper;
		$this->server = $server;
		$this->permission = $permission;
		$this->roomService = $roomService;
		$this->config = $config;
		$this->userId = $userId;
	}

	/**
	 * Get transcript metadata for a recording (called by the UI).
	 */
	#[NoAdminRequired]
	public function get(string $recordingId): DataResponse {
		if (!$this->userCanAccessRecording($recordingId)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$transcript = $this->mapper->findByRecordingId($recordingId);
			return new DataResponse($transcript);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Get the full transcript text (VTT or TXT) for a recording.
	 */
	#[NoAdminRequired]
	public function getText(string $recordingId, string $format = 'txt'): DataResponse {
		if (!$this->userCanAccessRecording($recordingId)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$transcript = $this->mapper->findByRecordingId($recordingId);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($format === 'vtt') {
			return new DataResponse(['content' => $transcript->getTranscriptVtt()]);
		}

		return new DataResponse(['content' => $transcript->getTranscriptTxt()]);
	}

	/**
	 * Batch-check transcript status for multiple recording IDs.
	 * Returns a map of recordingId => {status, language, updatedAt}.
	 */
	#[NoAdminRequired]
	public function batch(string $ids): DataResponse {
		$recordingIds = array_filter(explode(',', $ids));
		if (empty($recordingIds)) {
			return new DataResponse([]);
		}

		$transcripts = $this->mapper->findByRecordingIds($recordingIds);

		$result = [];
		foreach ($transcripts as $recId => $t) {
			$result[$recId] = [
				'status' => $t->getStatus(),
				'language' => $t->getLanguage(),
				'updatedAt' => $t->getUpdatedAt(),
			];
		}

		return new DataResponse($result);
	}

	/**
	 * Receive transcript data from the BBB post-processor.
	 * Authenticated via shared secret (no CSRF, no login required).
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function receive(string $recordingId): DataResponse {
		// Verify shared secret
		$secret = $this->config->getAppValue('bbb', 'transcript_secret', '');
		$authHeader = $this->request->getHeader('Authorization');

		if (empty($secret) || $authHeader !== 'Bearer ' . $secret) {
			return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		}

		$status = $this->request->getParam('status', 'processing');
		$transcriptVtt = $this->request->getParam('transcript_vtt', '');
		$transcriptTxt = $this->request->getParam('transcript_txt', '');
		$notesMd = $this->request->getParam('notes_md', '');
		$language = $this->request->getParam('language', '');
		$now = time();

		try {
			$transcript = $this->mapper->findByRecordingId($recordingId);
			// Update existing
			if (!empty($transcriptVtt)) {
				$transcript->setTranscriptVtt($transcriptVtt);
			}
			if (!empty($transcriptTxt)) {
				$transcript->setTranscriptTxt($transcriptTxt);
			}
			if (!empty($notesMd)) {
				$transcript->setNotesMd($notesMd);
			}
			if (!empty($language)) {
				$transcript->setLanguage($language);
			}
			$transcript->setStatus($status);
			$transcript->setUpdatedAt($now);
			$this->mapper->update($transcript);
		} catch (DoesNotExistException $e) {
			// Create new
			$transcript = new Transcript();
			$transcript->setRecordingId($recordingId);
			$transcript->setTranscriptVtt($transcriptVtt);
			$transcript->setTranscriptTxt($transcriptTxt);
			$transcript->setNotesMd($notesMd);
			$transcript->setLanguage($language);
			$transcript->setStatus($status);
			$transcript->setCreatedAt($now);
			$transcript->setUpdatedAt($now);
			$this->mapper->insert($transcript);
		}

		return new DataResponse(['status' => 'ok']);
	}

	/**
	 * Check if the current user can access the recording's room.
	 */
	private function userCanAccessRecording(string $recordingId): bool {
		if ($this->userId === null) {
			return false;
		}

		try {
			$record = $this->server->getRecording($recordingId);
			$room = $this->roomService->findByUid($record['meetingId']);

			if ($room === null) {
				return false;
			}

			return $this->permission->isUser($room, $this->userId);
		} catch (\Exception $e) {
			return false;
		}
	}
}
