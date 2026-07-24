<?php

namespace OCA\BigBlueButton\Controller;

use OCA\BigBlueButton\BigBlueButton\API;
use OCA\BigBlueButton\Db\Transcript;
use OCA\BigBlueButton\Db\TranscriptMapper;
use OCA\BigBlueButton\Permission;
use OCA\BigBlueButton\Service\RoomService;
use OCA\BigBlueButton\Service\TranscriptMailService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IRequest;

class TranscriptController extends Controller {
	private const BATCH_MAX_IDS = 100;

	private TranscriptMapper $mapper;
	private API $server;
	private Permission $permission;
	private RoomService $roomService;
	private IAppConfig $appConfig;
	private TranscriptMailService $mailService;
	private ?string $userId;

	public function __construct(
		string $appName,
		IRequest $request,
		TranscriptMapper $mapper,
		API $server,
		Permission $permission,
		RoomService $roomService,
		IAppConfig $appConfig,
		TranscriptMailService $mailService,
		?string $userId
	) {
		parent::__construct($appName, $request);
		$this->mapper = $mapper;
		$this->server = $server;
		$this->permission = $permission;
		$this->roomService = $roomService;
		$this->appConfig = $appConfig;
		$this->mailService = $mailService;
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
	 * Get full transcript or notes content for display.
	 */
	#[NoAdminRequired]
	public function content(string $recordingId, string $kind): DataResponse {
		if (!$this->userCanAccessRecording($recordingId)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$transcript = $this->mapper->findByRecordingId($recordingId);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		switch ($kind) {
			case 'transcript':
				return new DataResponse([
					'content' => $transcript->getTranscriptTxt(),
					'language' => $transcript->getLanguage(),
				]);
			case 'notes':
				return new DataResponse([
					'content' => $transcript->getNotesMd(),
					'language' => $transcript->getLanguage(),
				]);
			case 'vtt':
				return new DataResponse([
					'content' => $transcript->getTranscriptVtt(),
					'language' => $transcript->getLanguage(),
				]);
			default:
				return new DataResponse(['error' => 'invalid kind'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Download transcript or notes as a file.
	 */
	#[NoAdminRequired]
	public function download(string $recordingId, string $kind): DataDownloadResponse|DataResponse {
		if (!$this->userCanAccessRecording($recordingId)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$transcript = $this->mapper->findByRecordingId($recordingId);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		switch ($kind) {
			case 'transcript_vtt':
				return new DataDownloadResponse(
					$transcript->getTranscriptVtt() ?? '',
					'transcript.vtt',
					'text/vtt'
				);
			case 'transcript_txt':
				return new DataDownloadResponse(
					$transcript->getTranscriptTxt() ?? '',
					'transcript.txt',
					'text/plain'
				);
			case 'notes_md':
				return new DataDownloadResponse(
					$transcript->getNotesMd() ?? '',
					'notes.md',
					'text/markdown'
				);
			default:
				return new DataResponse(['error' => 'invalid kind'], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Batch-check transcript status for multiple recording IDs.
	 * Returns a map of recordingId => {status, language, updatedAt}.
	 */
	#[NoAdminRequired]
	public function batch(string $ids): DataResponse {
		$recordingIds = array_filter(explode(',', $ids));
		if (empty($recordingIds) || count($recordingIds) > self::BATCH_MAX_IDS) {
			return new DataResponse([]);
		}

		// Only report on recordings the user is allowed to see
		$recordingIds = array_values(array_filter(
			$recordingIds,
			fn (string $id): bool => $this->userCanAccessRecording($id)
		));
		if (empty($recordingIds)) {
			return new DataResponse([]);
		}

		$transcripts = $this->mapper->findByRecordingIds($recordingIds);

		$result = [];
		foreach ($transcripts as $recId => $t) {
			$result[$recId] = [
				'status' => $t->getStatus(),
				'language' => $t->getLanguage(),
				'title' => $t->getTitle(),
				'participants' => $t->getParticipantList(),
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
	#[BruteForceProtection(action: 'bbbTranscriptReceive')]
	public function receive(string $recordingId): DataResponse {
		// Verify shared secret (constant-time comparison)
		$secret = $this->appConfig->getValueString('bbb', 'transcript_secret', '');
		$authHeader = (string)$this->request->getHeader('Authorization');

		if ($secret === '' || !hash_equals('Bearer ' . $secret, $authHeader)) {
			$response = new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'bbbTranscriptReceive']);
			return $response;
		}

		$status = $this->request->getParam('status', 'processing');
		if (!is_string($status) || !in_array($status, [
			Transcript::STATUS_PROCESSING,
			Transcript::STATUS_COMPLETE,
			Transcript::STATUS_PARTIAL,
			Transcript::STATUS_FAILED,
		], true)) {
			return new DataResponse(['error' => 'invalid status'], Http::STATUS_BAD_REQUEST);
		}
		$transcriptVtt = $this->request->getParam('transcript_vtt', '');
		$transcriptTxt = $this->request->getParam('transcript_txt', '');
		$notesMd = $this->request->getParam('notes_md', '');
		$language = $this->request->getParam('language', '');
		$title = (string)$this->request->getParam('title', '');
		$participants = $this->request->getParam('participants', '');
		$sendEmail = $this->request->getParam('send_email', '0') === '1';
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
			// Don't overwrite a title the user has manually edited
			if ($title !== '' && !$transcript->getTitleLocked()) {
				$transcript->setTitle($title);
			}
			if (is_string($participants) && $participants !== '') {
				$transcript->setParticipants($participants);
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
			$transcript->setTitle($title);
			$transcript->setTitleLocked(false);
			$transcript->setParticipants(is_string($participants) ? $participants : '');
			$transcript->setStatus($status);
			$transcript->setCreatedAt($now);
			$transcript->setUpdatedAt($now);
			$this->mapper->insert($transcript);
		}

		// Auto-email minutes once, only when the pipeline says it's within the
		// same-day window (backfill/reprocessing sets send_email=0).
		if ($sendEmail
			&& $status === Transcript::STATUS_COMPLETE
			&& empty($transcript->getNotifiedAt())) {
			$this->mailService->notifyParticipants($transcript, $this->resolveRoomUid($recordingId));
			$transcript->setNotifiedAt($now);
			$this->mapper->update($transcript);
		}

		return new DataResponse(['status' => 'ok']);
	}

	/**
	 * Update the meeting title (user edit). Locks it against future
	 * pipeline overwrites.
	 */
	#[NoAdminRequired]
	public function updateTitle(string $recordingId): DataResponse {
		if (!$this->userCanAccessRecording($recordingId)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$title = (string)$this->request->getParam('title', '');
		$title = trim($title);
		if ($title === '' || mb_strlen($title) > 191) {
			return new DataResponse(['error' => 'invalid title'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$transcript = $this->mapper->findByRecordingId($recordingId);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		$transcript->setTitle($title);
		$transcript->setTitleLocked(true);
		$transcript->setUpdatedAt(time());
		$this->mapper->update($transcript);

		return new DataResponse(['title' => $title]);
	}

	/**
	 * Manually email the minutes to the meeting's participants (the
	 * "Send to participants" button). Bypasses the same-day auto-gate.
	 */
	#[NoAdminRequired]
	public function send(string $recordingId): DataResponse {
		if (!$this->userCanAccessRecording($recordingId)) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		try {
			$transcript = $this->mapper->findByRecordingId($recordingId);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($transcript->getStatus() !== Transcript::STATUS_COMPLETE) {
			return new DataResponse(['error' => 'not ready'], Http::STATUS_BAD_REQUEST);
		}

		$sent = $this->mailService->notifyParticipants($transcript, $this->resolveRoomUid($recordingId));
		$transcript->setNotifiedAt(time());
		$this->mapper->update($transcript);

		return new DataResponse(['sent' => $sent]);
	}

	/** Resolve a recording to its room uid (for email deep-links), or null. */
	private function resolveRoomUid(string $recordingId): ?string {
		try {
			$record = $this->server->getRecording($recordingId);
			$room = $this->roomService->findByUid($record['meetingId']);
			return $room?->uid;
		} catch (\Exception $e) {
			return null;
		}
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
