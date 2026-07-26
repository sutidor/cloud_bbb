import React from 'react';
import { Restriction } from '../Common/Api';
import EditableValue from '../Manager/EditableValue';
import EditableSelection from '../Common/EditableSelection';
import { AccessOptions } from '../Common/Translation';

type Props = {
	restriction: Restriction;
	updateRestriction: (restriction: Restriction) => Promise<void>;
	deleteRestriction: (id: number) => void;
}


const RestrictionRoom = (props: Props): JSX.Element => {
	const restriction = props.restriction;

	function updateRestriction(key: string, value: string | boolean | number | string[]) {
		return props.updateRestriction({
			...props.restriction,
			[key]: value,
		});
	}

	function deleteRow(ev: MouseEvent) {
		ev.preventDefault();
		const groupName = restriction.groupName || restriction.groupId;
		OC.dialogs.confirm(
			t('boss_meeting', 'Are you sure you want to delete the restrictions for group "{name}"? This operation cannot be undone.', { name: groupName }),
			t('boss_meeting', 'Delete restrictions for "{name}"?', { name: groupName}),
			confirmed => {
				if (confirmed) {
					props.deleteRestriction(restriction.id);
				}
			},
			true,
		);
	}

	function edit(field: string, type: 'text' | 'number' = 'text') {
		return <EditableValue field={field} value={restriction[field]} setValue={updateRestriction} type={type} options={{min: -1}} />;
	}

	return (
		<tr>
			<td className="name">{restriction.groupName || restriction.groupId || t('boss_meeting', 'All users')}</td>
			<td className="max-rooms bbb-shrink">
				{edit('maxRooms', 'number')}
			</td>

			<td>
				<EditableSelection
					field="roomTypes"
					values={restriction.roomTypes}
					options={AccessOptions}
					setValue={updateRestriction}
					invert={true}
					placeholder={t('boss_meeting', 'All')} />
			</td>

			<td className="max-participants bbb-shrink">
				{edit('maxParticipants', 'number')}
			</td>

			<td className="record bbb-shrink">
				<input
					id={'bbb-record-' + restriction.id}
					type="checkbox"
					className="checkbox"
					checked={restriction.allowRecording}
					onChange={(event) => updateRestriction('allowRecording', event.target.checked)} />
				<label htmlFor={'bbb-record-' + restriction.id}></label>
			</td>

			<td className="remove icon-col">
				<button disabled={!restriction.groupId} className="action-item" onClick={deleteRow as any} title={t('boss_meeting', 'Delete')}>
					<span className="icon icon-delete icon-visible"></span>
				</button>
			</td>
		</tr>
	);
};

export default RestrictionRoom;
