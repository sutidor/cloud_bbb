import React, { useState } from 'react';
import { CopyToClipboard } from 'react-copy-to-clipboard';
import { showError } from '@nextcloud/dialogs';
import { api, Recording, TranscriptStatus } from '../Common/Api';
import TranscriptPanel from './TranscriptPanel';

type Props = {
    recording: Recording;
	isAdmin : boolean;
	transcriptStatus?: TranscriptStatus;
    deleteRecording: (recording: Recording) => void;
    storeRecording: (recording: Recording) => void;
	publishRecording: (recording: Recording, publish: boolean) => void;
}

// Material "description" glyph — a document with text lines. Reads as
// "meeting minutes", and unlike a pencil doesn't imply an edit action.
const NotesIcon = (): JSX.Element => (
	<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
		<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z" />
	</svg>
);

function formatTalk(seconds: number): string {
	if (!seconds) {
		return '';
	}
	const m = Math.floor(seconds / 60);
	const s = Math.round(seconds % 60);
	return m ? `${m}m ${s}s` : `${s}s`;
}

const RecordingRow = ({recording, isAdmin, transcriptStatus, deleteRecording, storeRecording, publishRecording}: Props): JSX.Element => {
	const [showTranscript, setShowTranscript] = useState(false);
	const [title, setTitle] = useState(transcriptStatus?.title || '');
	const [editingTitle, setEditingTitle] = useState(false);
	const [titleDraft, setTitleDraft] = useState('');

	const participants = transcriptStatus?.participants || [];

	function checkPublished(recording: Recording, onChange: (value: boolean) => void) {
		return (
			<div>
				<input id={'bbb-record-state-' + recording.id}
					type="checkbox"
					className="checkbox"
					checked={recording.state === 'published'}
					onChange={(event) =>  onChange(event.target.checked)} />
				<label htmlFor={'bbb-record-state-' + recording.id}>{t('bbb', 'Published')}</label>
			</div>
		);
	}

	async function saveTitle() {
		const next = titleDraft.trim();
		setEditingTitle(false);
		if (!next || next === title) {
			return;
		}
		try {
			const saved = await api.updateTranscriptTitle(recording.id, next);
			setTitle(saved);
		} catch (err) {
			console.warn('Could not save title', err);
			showError(t('bbb', 'Could not save title'));
		}
	}

	function titleCell(): JSX.Element {
		// Only meetings with a transcript can carry a title
		if (!transcriptStatus) {
			return <span className="bbb-recording-title bbb-recording-title--muted">{recording.name}</span>;
		}
		if (editingTitle) {
			return (
				<form className="bbb-title-edit" onSubmit={(e) => { e.preventDefault(); saveTitle(); }}>
					<input type="text" autoFocus maxLength={191}
						value={titleDraft}
						onChange={(e) => setTitleDraft(e.target.value)}
						onBlur={saveTitle}
						aria-label={t('bbb', 'Meeting title')} />
				</form>
			);
		}
		return (
			<button className="bbb-recording-title" title={t('bbb', 'Click to rename')}
				onClick={() => { setTitleDraft(title); setEditingTitle(true); }}>
				{title || <span className="bbb-recording-title--muted">{t('bbb', 'Untitled meeting')}</span>}
			</button>
		);
	}

	function participantsCell(): JSX.Element {
		const count = recording.participants;
		if (!participants.length) {
			return <span>{n('bbb', '%n participant', '%n participants', count)}</span>;
		}
		return (
			<div className="bbb-participants">
				<span className="bbb-participants-count">
					{n('bbb', '%n participant', '%n participants', count)}
				</span>
				<div className="bbb-participants-list" role="tooltip">
					<ul>
						{participants.map((p, i) => (
							<li key={i}>
								<span className="bbb-participant-name">{p.name}</span>
								{formatTalk(p.talkSeconds) &&
									<span className="bbb-participant-talk">{formatTalk(p.talkSeconds)}</span>}
							</li>
						))}
					</ul>
				</div>
			</div>
		);
	}

	function transcriptIcon() {
		if (!transcriptStatus) {
			return null;
		}

		const { status } = transcriptStatus;

		if (status === 'processing') {
			return (
				<span className="icon icon-loading-small icon-visible" title={t('bbb', 'Transcribing...')}></span>
			);
		}

		if (status === 'failed') {
			return (
				<span className="icon icon-error icon-visible" title={t('bbb', 'Transcription failed')}></span>
			);
		}

		// complete or partial
		return (
			<button
				className={'action-item bbb-notes-toggle' + (showTranscript ? ' active' : '')}
				onClick={() => setShowTranscript(!showTranscript)}
				title={t('bbb', 'Meeting notes')}>
				<NotesIcon />
			</button>
		);
	}

	return (
		<>
			<tr key={recording.id}>
				<td className="start icon-col">
					<a href={recording.url} className="action-item" target="_blank" rel="noopener noreferrer" title={t('bbb', 'Open recording')}>
						<span className="icon icon-external icon-visible"></span>
					</a>
				</td>
				<td className="share icon-col">
					<CopyToClipboard text={recording.url} options={{format:'text/plain'}}>
						<button className="action-item copy-to-clipboard" title={t('bbb', 'Copy to clipboard')}>
							<span className="icon icon-clippy icon-visible" ></span>
						</button>
					</CopyToClipboard>
				</td>
				<td className="icon-col">
					<button className="action-item" onClick={() => storeRecording(recording)} title={t('bbb', 'Save as file')}>
						<span className="icon icon-add-shortcut icon-visible"></span>
					</button>
				</td>
				<td className="bbb-title-col">
					{titleCell()}
				</td>
				<td>
					{(new Date(recording.startTime)).toLocaleString()}
				</td>
				<td>
					{recording.length === 0 ? '< 1 min' : (recording.length + ' min')}
				</td>
				<td>
					{participantsCell()}
				</td>
				<td>
					{recording.type}
				</td>
				<td className="icon-col">
					{transcriptIcon()}
				</td>
				<td>
					{isAdmin && checkPublished(recording, (checked) => {
						publishRecording(recording, checked);
					})}
				</td>
				<td className="remove icon-col">
					{isAdmin &&
						<button className="action-item" onClick={() => deleteRecording(recording)} title={t('bbb', 'Delete')}>
							<span className="icon icon-delete icon-visible"></span>
						</button>
					}
				</td>
			</tr>
			{showTranscript && (
				<tr className="transcript-row">
					<td colSpan={11}>
						<TranscriptPanel recordingId={recording.id} />
					</td>
				</tr>
			)}
		</>
	);
};

export default RecordingRow;
