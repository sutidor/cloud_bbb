import { Access, Permission } from './Api';
import parse from 'html-react-parser';
import DOMPurify from 'dompurify';

export const AccessOptions = {
	[Access.Public]: t('boss_meeting', 'Public'),
	[Access.Password]: t('boss_meeting', 'Internal + Password protection for guests'),
	[Access.WaitingRoom]: t('boss_meeting', 'Internal + Waiting room for guests'),
	[Access.WaitingRoomAll]: t('boss_meeting', 'Waiting room for all users'),
	[Access.Internal]: t('boss_meeting', 'Internal'),
	[Access.InternalRestricted]: t('boss_meeting', 'Internal restricted'),
};

export const PermissionsOptions = {
	[Permission.Admin]: t('boss_meeting', 'admin'),
	[Permission.Moderator]: t('boss_meeting', 'moderator'),
	[Permission.User]: t('boss_meeting', 'user'),
};

export function html_sanitize_and_parse(str: string): string {
	return parse(DOMPurify.sanitize(str, { USE_PROFILES: { html: true } })) as string;
}
