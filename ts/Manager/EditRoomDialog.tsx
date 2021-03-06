import React, { useState, useEffect } from 'react';
import { Access, Room, Permission, RoomShare, api, Restriction } from '../Common/Api';
import Dialog from './Dialog';
import ShareWith from './ShareWith';
import { SubmitInput } from './SubmitInput';
import { AccessOptions } from '../Common/Translation';

const descriptions: { [key: string]: string } = {
	name: t('bbb', 'Descriptive name of this room.'),
	welcome: t('bbb', 'This message is shown to all users in the chat area after they joined.'),
	maxParticipants: t('bbb', 'Sets a limit on the number of participants for this room. Zero means there is no limit.'),
	recording: t('bbb', 'If enabled, the moderator is able to start the recording.'),
	access: t('bbb', 'Public: Everyone knowing the link is able to join. Password: Guests have to provide a password. Waiting room: A moderator has to accept every guest before they can join. Internal: Only Nextcloud users can join.'),
	moderator: t('bbb', 'A moderator is able to manage all participants in a meeting including kicking, muting or selecting a presenter. Users with the role moderator are also able to close a meeting or change the default settings.'),
	requireModerator: t('bbb', 'If enabled, normal users have to wait until a moderator is in the room.'),
};

type Props = {
	room: Room;
	restriction?: Restriction;
	updateProperty: (key: string, value: string | boolean | number) => Promise<void>;
	open: boolean;
	setOpen: (open: boolean) => void;
}

const EditRoomDialog: React.FC<Props> = ({ room, restriction, updateProperty, open, setOpen }) => {
	const [shares, setShares] = useState<RoomShare[]>();

	const maxParticipantsLimit = (restriction?.maxParticipants || 0) < 0 ? undefined : restriction?.maxParticipants;
	const minParticipantsLimit = (restriction?.maxParticipants || -1) < 1 ? 0 : 1;

	useEffect(() => {
		if (!open) {
			return;
		}

		api.getRoomShares(room.id).then(roomShares => {
			console.log(room.name, roomShares);
			setShares(roomShares);
		}).catch(err => {
			console.warn('Could not load room shares.', err);

			setShares([]);
		});
	}, [room.id, open]);

	useEffect(() => {
		if (!shares) {
			return;
		}

		updateProperty('shared', shares.filter(share => share.permission === Permission.Admin).length > 0);
	}, [shares]);

	function inputElement(label: string, field: string, type: 'text' | 'number' = 'text') {
		return (
			<div className="bbb-form-element">
				<label htmlFor={`bbb-${field}`}>
					<h3>{label}</h3>
				</label>

				<SubmitInput initialValue={room[field]} type={type} name={field} onSubmitValue={value => updateProperty(field, value)} min={minParticipantsLimit} max={maxParticipantsLimit} />
				{descriptions[field] && <em>{descriptions[field]}</em>}
			</div>
		);
	}

	function selectElement(label: string, field: string, value: string, options: { [key: string]: string }, onChange: (value: string) => void) {
		return (
			<div className="bbb-form-element">
				<label htmlFor={`bbb-${field}`}>
					<h3>{label}</h3>
				</label>

				<select name={field} value={value} onChange={(event) => onChange(event.target.value)}>
					{Object.keys(options).map(key => {
						const label = options[key];

						return <option key={key} value={key}>{label}</option>;
					})}
				</select>
				{(value === Access.Password && room.password) && <input type="text" readOnly={true} value={room.password} />}
				{descriptions[field] && <em>{descriptions[field]}</em>}
			</div>
		);
	}

	const accessOptions = {...AccessOptions};
	for(const roomType of restriction?.roomTypes || []) {
		if (roomType !== room.access) {
			delete accessOptions[roomType];
		}
	}

	return (
		<Dialog open={open} onClose={() => setOpen(false)} title={t('bbb', 'Edit "{room}"', { room: room.name })}>
			{inputElement(t('bbb', 'Name'), 'name')}
			{inputElement(t('bbb', 'Welcome'), 'welcome')}
			{inputElement(t('bbb', 'Participant limit'), 'maxParticipants', 'number')}

			{selectElement(t('bbb', 'Access'), 'access', room.access, accessOptions, (value) => {
				console.log('access', value);
				updateProperty('access', value);
			})}

			{room.access === Access.InternalRestricted && <div className="bbb-form-element bbb-form-shareWith">
				<ShareWith permission={Permission.User} room={room} shares={shares} setShares={setShares} />
			</div>}

			<div className="bbb-form-element">
				<label htmlFor={'bbb-moderator'}>
					<h3>Moderator</h3>
				</label>

				{!room.everyoneIsModerator && <ShareWith permission={Permission.Moderator} room={room} shares={shares} setShares={setShares} />}

				<div className="bbb-mt-1">
					<input id={`bbb-everyoneIsModerator-${room.id}`}
						type="checkbox"
						className="checkbox"
						checked={room.everyoneIsModerator}
						onChange={(event) => updateProperty('everyoneIsModerator', event.target.checked)} />
					<label htmlFor={`bbb-everyoneIsModerator-${room.id}`}>{t('bbb', 'Every participant is moderator')}</label>
				</div>
				<em>{descriptions.moderator}</em>
			</div>

			<h3>{t('bbb', 'Miscellaneous')}</h3>
			<div>
				<div>
					<input id={`bbb-record-${room.id}`}
						type="checkbox"
						className="checkbox"
						checked={room.record}
						disabled={!restriction?.allowRecording}
						onChange={(event) => updateProperty('record', event.target.checked)} />
					<label htmlFor={`bbb-record-${room.id}`}>{t('bbb', 'Recording')}</label>
				</div>
				<p><em>{descriptions.recording}</em></p>
			</div>
			<div>
				<div>
					<input id={`bbb-requireModerator-${room.id}`}
						type="checkbox"
						className="checkbox"
						checked={room.requireModerator}
						onChange={(event) => updateProperty('requireModerator', event.target.checked)} />
					<label htmlFor={`bbb-requireModerator-${room.id}`}>{t('bbb', 'Require moderator to start room')}</label>
				</div>
				<p><em>{descriptions.requireModerator}</em></p>
			</div>
		</Dialog>
	);
};

export default EditRoomDialog;
