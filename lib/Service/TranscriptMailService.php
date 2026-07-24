<?php

declare(strict_types=1);

namespace OCA\BigBlueButton\Service;

use OCA\BigBlueButton\Db\Transcript;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

/**
 * Emails AI meeting minutes to the meeting's participants who have a matching
 * Nextcloud account. Attendees are matched by their BBB externalUserId, which
 * for logged-in users equals their Nextcloud user id; guests have no match and
 * are skipped.
 */
class TranscriptMailService {
	public function __construct(
		private IMailer $mailer,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param string|null $roomUid deep-link the "Open in Nextcloud" button to
	 *                             this room (anchor), if known
	 * @return int number of recipients the minutes were sent to
	 */
	public function notifyParticipants(Transcript $transcript, ?string $roomUid = null): int {
		$recipients = $this->resolveRecipients($transcript);
		if (empty($recipients)) {
			$this->logger->info('bbb transcript mail: no matching Nextcloud recipients', [
				'recordingId' => $transcript->getRecordingId(),
			]);
			return 0;
		}

		$heading = $this->buildHeading($transcript);
		$link = $this->urlGenerator->linkToRouteAbsolute('bbb.page.index');
		if ($roomUid !== null && $roomUid !== '') {
			// deep-link: the app expands + scrolls to this room on load
			$link .= '#room-' . rawurlencode($roomUid);
		}
		$notesHtml = $this->markdownToHtml((string)$transcript->getNotesMd());
		$plain = (string)$transcript->getNotesMd();

		$sent = 0;
		foreach ($recipients as $email => $name) {
			try {
				$message = $this->mailer->createMessage();
				$message->setSubject($heading);
				$message->setTo([$email => $name]);

				$template = $this->mailer->createEMailTemplate('bbb.TranscriptMinutes', [
					'title' => $heading,
				]);
				$template->setSubject($heading);
				$template->addHeader();
				$template->addHeading($heading);
				$template->addBodyText($notesHtml, $plain);
				$template->addBodyButton($this->l10n->t('Open in Nextcloud'), $link);
				$template->addFooter();

				$message->useTemplate($template);
				$this->mailer->send($message);
				$sent++;
			} catch (\Throwable $e) {
				$this->logger->warning('bbb transcript mail: send failed', [
					'email' => $email,
					'exception' => $e,
				]);
			}
		}

		$this->logger->info('bbb transcript mail: sent minutes', [
			'recordingId' => $transcript->getRecordingId(),
			'recipients' => $sent,
		]);
		return $sent;
	}

	/**
	 * Subject + heading: "📃 Meeting Minutes: <slug> <date>" (EN) /
	 * "📃 Meeting Protokoll: <slug> <date>" (DE), date in the meeting's locale.
	 */
	private function buildHeading(Transcript $transcript): string {
		$de = $transcript->getLanguage() === 'de';
		$word = $de ? 'Protokoll' : 'Minutes';
		$slug = $transcript->getTitle() ?: ($de ? 'Besprechung' : 'Meeting');

		// Meeting time is the epoch-ms suffix of the recording id; fall back to
		// the row's created_at.
		$parts = explode('-', $transcript->getRecordingId());
		$suffix = end($parts);
		$ts = ctype_digit((string)$suffix)
			? (int)((int)$suffix / 1000)
			: (int)$transcript->getCreatedAt();
		$date = $de ? date('d.m.Y', $ts) : date('M j, Y', $ts);

		return sprintf('📃 Meeting %s: %s %s', $word, $slug, $date);
	}

	/**
	 * @return array<string,string> email => display name, de-duplicated
	 */
	private function resolveRecipients(Transcript $transcript): array {
		$recipients = [];
		foreach ($transcript->getParticipantList() as $participant) {
			$extId = trim((string)($participant['extId'] ?? ''));
			if ($extId === '') {
				continue;
			}
			$user = $this->userManager->get($extId);
			if ($user === null) {
				continue;
			}
			$email = $user->getEMailAddress();
			if (empty($email)) {
				continue;
			}
			$recipients[$email] = $user->getDisplayName();
		}
		return $recipients;
	}

	/**
	 * Minimal, safe Markdown → HTML for the notes body. Handles the headings and
	 * bullet lists our notes prompt produces; everything else becomes escaped
	 * paragraphs. No raw HTML from the model is ever emitted.
	 */
	private function markdownToHtml(string $md): string {
		$html = '';
		$inList = false;
		foreach (explode("\n", $md) as $line) {
			$line = rtrim($line);
			if (strpos($line, '## ') === 0) {
				if ($inList) {
					$html .= '</ul>';
					$inList = false;
				}
				$html .= '<h3>' . htmlspecialchars(substr($line, 3), ENT_QUOTES) . '</h3>';
			} elseif (strpos($line, '- ') === 0) {
				if (!$inList) {
					$html .= '<ul>';
					$inList = true;
				}
				$html .= '<li>' . htmlspecialchars(substr($line, 2), ENT_QUOTES) . '</li>';
			} elseif (trim($line) === '') {
				if ($inList) {
					$html .= '</ul>';
					$inList = false;
				}
			} else {
				if ($inList) {
					$html .= '</ul>';
					$inList = false;
				}
				$html .= '<p>' . htmlspecialchars($line, ENT_QUOTES) . '</p>';
			}
		}
		if ($inList) {
			$html .= '</ul>';
		}
		return $html;
	}
}
