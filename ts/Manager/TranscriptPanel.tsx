import React, { useEffect, useState } from 'react';
import { showSuccess, showError } from '@nextcloud/dialogs';
import { api, TranscriptContent } from '../Common/Api';

type Props = {
	recordingId: string;
}

type Tab = 'notes' | 'transcript';

const TranscriptPanel = ({ recordingId }: Props): JSX.Element => {
	const [tab, setTab] = useState<Tab>('notes');
	const [content, setContent] = useState<TranscriptContent | null>(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState<string | null>(null);
	const [sending, setSending] = useState(false);

	async function sendToParticipants() {
		setSending(true);
		try {
			const sent = await api.sendTranscriptEmail(recordingId);
			if (sent > 0) {
				showSuccess(n('bbb', 'Minutes sent to %n participant', 'Minutes sent to %n participants', sent));
			} else {
				showError(t('bbb', 'No participants with a Nextcloud account to email'));
			}
		} catch (err) {
			console.warn('Could not send minutes', err);
			showError(t('bbb', 'Could not send minutes'));
		} finally {
			setSending(false);
		}
	}

	useEffect(() => {
		loadContent(tab);
	}, [tab, recordingId]);

	async function loadContent(activeTab: Tab) {
		setLoading(true);
		setError(null);

		try {
			const result = await api.getTranscriptContent(recordingId, activeTab);
			setContent(result);
		} catch (err) {
			console.warn('Could not load content', err);
			setError(t('bbb', 'Could not load transcript'));
			setContent(null);
		} finally {
			setLoading(false);
		}
	}

	function renderMarkdown(md: string): JSX.Element {
		const lines = md.split('\n');
		const elements: JSX.Element[] = [];

		lines.forEach((line, i) => {
			if (line.startsWith('## ')) {
				elements.push(<h3 key={i}>{line.slice(3)}</h3>);
			} else if (line.startsWith('- ')) {
				elements.push(<li key={i}>{line.slice(2)}</li>);
			} else if (line.trim() === '') {
				elements.push(<br key={i} />);
			} else {
				elements.push(<p key={i}>{line}</p>);
			}
		});

		return <div>{elements}</div>;
	}

	if (loading) {
		return (
			<div className="bbb-transcript-panel">
				<span className="icon icon-loading-small icon-visible"></span>
				{t('bbb', 'Loading transcript...')}
			</div>
		);
	}

	if (error || !content) {
		return (
			<div className="bbb-transcript-panel bbb-transcript-error">
				{error || t('bbb', 'No transcript available.')}
			</div>
		);
	}

	return (
		<div className="bbb-transcript-panel">
			<div className="bbb-transcript-tabs">
				<button
					className={`bbb-transcript-tab ${tab === 'notes' ? 'active' : ''}`}
					onClick={() => setTab('notes')}>
					{t('bbb', 'Meeting Notes')}
				</button>
				<button
					className={`bbb-transcript-tab ${tab === 'transcript' ? 'active' : ''}`}
					onClick={() => setTab('transcript')}>
					{t('bbb', 'Transcript')}
				</button>
				<span className="bbb-transcript-meta">
					{content.language?.toUpperCase()}
				</span>
			</div>

			<div className="bbb-transcript-content">
				{tab === 'notes'
					? (content.content
						? renderMarkdown(content.content)
						: <p>{t('bbb', 'No meeting notes available.')}</p>)
					: <pre className="bbb-transcript-pre">{content.content}</pre>
				}
			</div>

			<div className="bbb-transcript-downloads">
				<a className="button" href={api.getTranscriptDownloadUrl(recordingId, 'notes_md')}>
					<span className="icon icon-download icon-visible"></span>
					{t('bbb', 'Notes (.md)')}
				</a>
				<a className="button" href={api.getTranscriptDownloadUrl(recordingId, 'transcript_txt')}>
					<span className="icon icon-download icon-visible"></span>
					{t('bbb', 'Transcript (.txt)')}
				</a>
				<a className="button" href={api.getTranscriptDownloadUrl(recordingId, 'transcript_vtt')}>
					<span className="icon icon-download icon-visible"></span>
					{t('bbb', 'Subtitles (.vtt)')}
				</a>
				<button className="button primary bbb-send-minutes" onClick={sendToParticipants} disabled={sending}>
					<span className={'icon icon-visible ' + (sending ? 'icon-loading-small' : 'icon-mail')}></span>
					{t('bbb', 'Send to participants')}
				</button>
			</div>
		</div>
	);
};

export default TranscriptPanel;
