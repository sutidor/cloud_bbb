import axios from '@nextcloud/axios';

export enum ShareType {
	User = 0, // OC.Share.SHARE_TYPE_USER
	Group = 1, // OC.Share.SHARE_TYPE_GROUP
	Circle = 7, // OC.Share.SHARE_TYPE_CIRCLE
}

export enum Permission { Admin, Moderator, User }

export enum Access {
	Public = 'public',
	Password = 'password',
	WaitingRoom = 'waiting_room',
	WaitingRoomAll = 'waiting_room_all',
	Internal = 'internal',
	InternalRestricted = 'internal_restricted',
}

export interface Restriction {
	id: number;
	groupId: string;
	groupName: string;
	maxRooms: number;
	roomTypes: string[];
	maxParticipants: number;
	allowRecording: boolean;
}

export interface Room {
	id: number;
	uid: string;
	userId: string;
	name: string;
	welcome: string;
	maxParticipants: number;
	record: boolean;
	access: Access;
	password?: string;
	everyoneIsModerator: boolean;
	requireModerator: boolean;
	shared: boolean;
	permission: Permission;
	moderatorToken: string;
	listenOnly: boolean,
	mediaCheck: boolean,
	cleanLayout: boolean,
	joinMuted: boolean,
	running: boolean,
}

export interface RoomShare {
	id: number;
	roomId: number;
	shareType: ShareType;
	shareWith: string;
	shareWithDisplayName?: string;
	permission: Permission;
}

export type Recording = {
	id: string;
	name: string;
	published: boolean;
	state: 'processing' | 'processed' | 'published' | 'unpublished' | 'deleted';
	startTime: number;
	participants: number;
	type: string;
	length: number;
	url: string;
	meta: any;
}

export type Participant = {
	name: string;
	extId: string;
	role: string;
	talkSeconds: number;
}

export type TranscriptStatus = {
	status: 'processing' | 'complete' | 'partial' | 'failed';
	language: string;
	title: string;
	participants: Participant[];
	updatedAt: number;
}

export type TranscriptDetail = {
	id: number;
	recordingId: string;
	language: string;
	status: string;
	title: string;
	participants: Participant[];
	hasTranscript: boolean;
	hasNotes: boolean;
	notifiedAt: number | null;
	createdAt: number;
	updatedAt: number;
}

export type TranscriptContent = {
	content: string;
	language: string;
}

export interface ShareWithOption {
	label: string;
	value: {
		shareType: ShareType;
		shareWith: string;
	};
}

export interface ShareWith {
	users: ShareWithOption[];
	groups: ShareWithOption[];
	circles: ShareWithOption[];
	exact: {
		users: ShareWithOption[];
		groups: ShareWithOption[];
		circles: ShareWithOption[];
	}
}

class Api {
	public getUrl(endpoint: string): string {
		return OC.generateUrl(`apps/bbb/${endpoint}`);
	}

	public async getRestriction(): Promise<Restriction> {
		const response = await axios.get(this.getUrl('restrictions/user'));

		return response.data;
	}

	public async getRestrictions(): Promise<Restriction[]> {
		const response = await axios.get(this.getUrl('restrictions'));

		return response.data;
	}

	public async createRestriction(groupId: string) {
		const response = await axios.post(this.getUrl('restrictions'), {
			groupId,
		});

		return response.data;
	}

	public async updateRestriction(restriction: Restriction) {
		if (!restriction.id) {
			const newRestriction = await this.createRestriction(
				restriction.groupId,
			);

			restriction.id = newRestriction.id;
		}

		const response = await axios.put(this.getUrl(`restrictions/${restriction.id}`), restriction);

		return response.data;
	}

	public async deleteRestriction(id: number) {
		const response = await axios.delete(this.getUrl(`restrictions/${id}`));

		return response.data;
	}

	public async isRunning(uid: string): Promise<boolean> {
		const response = await axios.get(this.getUrl(`server/${uid}/isRunning`));

		return response.data;
	}

	public async insertDocument(uid: string, url: string, filename: string): Promise<boolean> {
		const response = await axios.post(this.getUrl(`server/${uid}/insertDocument`), { url, filename });

		return response.data;
	}

	public getRoomUrl(room: Room, forModerator = false) {
		const shortener = document.getElementById('bbb-root')?.getAttribute('data-shortener') || '';
		const token = (forModerator && room.moderatorToken) ? `${room.uid}/${room.moderatorToken}` : room.uid;

		if (shortener) {
			return shortener
				.replace(/\{user\}/g, room.userId)
				.replace(/\{token\}/g, token);
		}

		return window.location.origin + api.getUrl(`b/${token}`);
	}

	public async getRooms(): Promise<Room[]> {
		const response = await axios.get(this.getUrl('rooms'));

		return response.data;
	}

	public async createRoom(name: string, access: Access = Access.Public, maxParticipants = 0): Promise<Room> {
		const response = await axios.post(this.getUrl('rooms'), {
			name,
			welcome: '',
			maxParticipants,
			record: false,
			access,
		});

		return response.data as Room;
	}

	public async updateRoom(room: Room) {
		const response = await axios.put(this.getUrl(`rooms/${room.id}`), room);

		return response.data;
	}

	public async deleteRoom(id: number) {
		const response = await axios.delete(this.getUrl(`rooms/${id}`));

		return response.data;
	}

	public async getRecordings(uid: string) {
		const response = await axios.get(this.getUrl(`server/${uid}/records`));

		return response.data;
	}

	public async deleteRecording(id: string) {
		const response = await axios.delete(this.getUrl(`server/record/${id}`));

		return response.data;
	}

	public async publishRecording(id: string, publish: boolean) {
		const response = await axios.post(this.getUrl(`server/record/${id}/publish`), {
			published: publish,
		});

		return response.data;
	}

	public async storeRecording(recording: Recording, path: string) {
		const startDate = new Date(recording.startTime);
		const filename = `${encodeURIComponent(recording.name + ' ' + startDate.toISOString().replace(/:/g, '-').substr(0,19))}.url`;
		const url = OC.linkToRemote(`dav/files/${OC.currentUser}${path}/${filename}`);

		await axios.put(url, `[InternetShortcut]\nURL=${recording.url}`);

		return filename;
	}

	public async storeRoom(room: Room, path: string) {
		const filename = `${encodeURIComponent(room.name)}.url`;
		const url = OC.linkToRemote(`dav/files/${OC.currentUser}${path}/${filename}`);

		await axios.put(url, `[InternetShortcut]\nURL=${this.getRoomUrl(room)}`);

		return filename;
	}

	public async checkServer(url: string, secret: string): Promise<'success' | 'invalid-url' | 'invalid:secret'> {
		const response = await axios.post(this.getUrl('server/check'), {
			url,
			secret,
		});

		return response.data;
	}

	public async getRoomShares(roomId: number): Promise<RoomShare[]> {
		const response = await axios.get(this.getUrl('roomShares'), {
			params: {
				id: roomId,
			},
		});

		return response.data;
	}

	public async createRoomShare(roomId: number, shareType: ShareType, shareWith: string, permission: Permission): Promise<RoomShare> {
		const response = await axios.post(this.getUrl('roomShares'), {
			roomId,
			shareType,
			shareWith,
			permission,
		});

		return response.data;
	}

	public async deleteRoomShare(id: number) {
		const response = await axios.delete(this.getUrl(`roomShares/${id}`));

		return response.data;
	}

	public async getRecommendedShareWith(shareType: ShareType[] = [OC.Share.SHARE_TYPE_USER, OC.Share.SHARE_TYPE_GROUP]): Promise<ShareWith> {
		const url = OC.linkToOCS('apps/files_sharing/api/v1', 1) + 'sharees_recommended';
		const response = await axios.get(url, {
			params: {
				shareType,
				itemType: 'room',
				format: 'json',
			},
		});

		return {
			users: [],
			groups: [],
			circles: [],
			exact: {
				users: response.data.ocs.data.exact.users,
				groups: response.data.ocs.data.exact.groups,
				circles: response.data.ocs.data.exact.circles || [],
			},
		};
	}

	public async getTranscriptStatuses(recordingIds: string[]): Promise<Record<string, TranscriptStatus>> {
		if (recordingIds.length === 0) {
			return {};
		}
		const response = await axios.get(this.getUrl('api/transcript/batch'), {
			params: { ids: recordingIds.join(',') },
		});
		return response.data;
	}

	public async getTranscript(recordingId: string): Promise<TranscriptDetail> {
		const response = await axios.get(this.getUrl(`api/transcript/${recordingId}`));
		return response.data;
	}

	public async getTranscriptText(recordingId: string, format: 'txt' | 'vtt' = 'txt'): Promise<string> {
		const response = await axios.get(this.getUrl(`api/transcript/${recordingId}/text`), {
			params: { format },
		});
		return response.data.content;
	}

	public async getTranscriptContent(recordingId: string, kind: 'transcript' | 'notes' | 'vtt'): Promise<TranscriptContent> {
		const response = await axios.get(this.getUrl(`api/transcript/${recordingId}/${kind}`));
		return response.data;
	}

	public getTranscriptDownloadUrl(recordingId: string, kind: 'transcript_vtt' | 'transcript_txt' | 'notes_md'): string {
		return this.getUrl(`api/transcript/${recordingId}/download/${kind}`);
	}

	public async updateTranscriptTitle(recordingId: string, title: string): Promise<string> {
		const response = await axios.put(this.getUrl(`api/transcript/${recordingId}/title`), { title });
		return response.data.title;
	}

	public async sendTranscriptEmail(recordingId: string): Promise<number> {
		const response = await axios.post(this.getUrl(`api/transcript/${recordingId}/send`), {});
		return response.data.sent;
	}

	public async searchShareWith(search = '', shareType: ShareType[] = [OC.Share.SHARE_TYPE_USER, OC.Share.SHARE_TYPE_GROUP]): Promise<ShareWith> {
		const url = OC.linkToOCS('apps/files_sharing/api/v1', 1) + 'sharees';
		const response = await axios.get(url, {
			params: {
				search,
				shareType,
				itemType: 'room',
				format: 'json',
				lookup: false,
			},
		});

		const data = response.data.ocs.data;

		return {
			users: data.users,
			groups: data.groups,
			circles: data.circles || [],
			exact: {
				users: data.exact.users,
				groups: data.exact.groups,
				circles: data.exact.circles || [],
			},
		};
	}
}

export const api = new Api();
