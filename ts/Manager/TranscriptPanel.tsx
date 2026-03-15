import React, { useEffect, useState } from 'react';
import { api, TranscriptDetail } from '../Common/Api';

type Props = {
	recordingId: string;
}

type Tab = 'notes' | 'transcript';

const TranscriptPanel = ({ recordingId }: Props): JSX.Element => {
	const [tab, setTab] = useState<Tab>('notes');
	const [detail, setDetail] = useState<TranscriptDetail | null>(null);
	const [transcriptText, setTranscriptText] = useState<string | null>(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState<string | null>(null);

	useEffect(() => {
		setLoading(true);
		setError(null);

		api.getTranscript(recordingId).then(data => {
			setDetail(data);
			setLoading(false);
		}).catch(err => {
			console.warn('Could not load transcript', err);
			setError(t('bbb', 'Could not load transcript'));
			setLoading(false);
		});
	}, [recordingId]);

	useEffect(() => {
		if (tab !== 'transcript' || transcriptText !== null) {
			return;
		}

		api.getTranscriptText(recordingId).then(text => {
			setTranscriptText(text);
		}).catch(err => {
			console.warn('Could not load transcript text', err);
			setTranscriptText(t('bbb', 'Could not load transcript text.'));
		});
	}, [tab, recordingId, transcriptText]);

	if (loading) {
		return (
			<div className="bbb-transcript-panel">
				<span className="icon icon-loading-small icon-visible"></span>
				{t('bbb', 'Loading transcript...')}
			</div>
		);
	}

	if (error || !detail) {
		return (
			<div className="bbb-transcript-panel bbb-transcript-error">
				{error || t('bbb', 'No transcript available.')}
			</div>
		);
	}

	function downloadFile(content: string, filename: string, mime: string) {
		const blob = new Blob([content], { type: mime });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = filename;
		a.click();
		URL.revokeObjectURL(url);
	}

	async function handleDownload(format: 'txt' | 'vtt' | 'md') {
		if (format === 'md' && detail) {
			downloadFile(detail.notesMd, `meeting-notes-${recordingId}.md`, 'text/markdown');
			return;
		}

		try {
			const text = await api.getTranscriptText(recordingId, format === 'vtt' ? 'vtt' : 'txt');
			const mime = format === 'vtt' ? 'text/vtt' : 'text/plain';
			downloadFile(text, `transcript-${recordingId}.${format}`, mime);
		} catch (err) {
			console.warn('Download failed', err);
		}
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
					{detail.language?.toUpperCase()}
					{detail.status === 'partial' && ` — ${t('bbb', 'Notes generation failed, transcript only')}`}
				</span>
			</div>

			<div className="bbb-transcript-content">
				{tab === 'notes' && (
					<div className="bbb-transcript-notes">
						{detail.notesMd
							? <pre className="bbb-transcript-pre">{detail.notesMd}</pre>
							: <p>{t('bbb', 'No meeting notes available.')}</p>
						}
					</div>
				)}

				{tab === 'transcript' && (
					<div className="bbb-transcript-text">
						{transcriptText === null
							? <span className="icon icon-loading-small icon-visible"></span>
							: <pre className="bbb-transcript-pre">{transcriptText}</pre>
						}
					</div>
				)}
			</div>

			<div className="bbb-transcript-downloads">
				{detail.notesMd && (
					<button className="button" onClick={() => handleDownload('md')}>
						<span className="icon icon-download icon-visible"></span>
						{t('bbb', 'Notes (.md)')}
					</button>
				)}
				<button className="button" onClick={() => handleDownload('txt')}>
					<span className="icon icon-download icon-visible"></span>
					{t('bbb', 'Transcript (.txt)')}
				</button>
				<button className="button" onClick={() => handleDownload('vtt')}>
					<span className="icon icon-download icon-visible"></span>
					{t('bbb', 'Subtitles (.vtt)')}
				</button>
			</div>
		</div>
	);
};

export default TranscriptPanel;
