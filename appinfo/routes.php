<?php

return [
	'resources' => [
		'room' => ['url' => '/rooms'],
		'roomShare' => ['url' => '/roomShares'],
		'restriction' => ['url' => '/restrictions'],
	],
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'server#isRunning', 'url' => '/server/{roomUid}/isRunning', 'verb' => 'GET'],
		['name' => 'server#insertDocument', 'url' => '/server/{roomUid}/insertDocument', 'verb' => 'POST'],
		['name' => 'server#records', 'url' => '/server/{roomUid}/records', 'verb' => 'GET'],
		['name' => 'server#check', 'url' => '/server/check', 'verb' => 'POST'],
		['name' => 'server#version', 'url' => '/server/version', 'verb' => 'GET'],
		['name' => 'server#delete_record', 'url' => '/server/record/{recordId}', 'verb' => 'DELETE'],
		['name' => 'server#publish_record', 'url' => '/server/record/{recordId}/publish', 'verb' => 'POST'],
		['name' => 'server#persist_record', 'url' => '/server/record/{recordId}/persist', 'verb' => 'POST'],
		['name' => 'join#index', 'url' => '/b/{token}/{moderatorToken}', 'verb' => 'GET', 'defaults' => ['moderatorToken' => '']],
		['name' => 'restriction#user', 'url' => '/restrictions/user', 'verb' => 'GET'],
		['name' => 'hook#meetingEnded', 'url' => '/hook/ended/{token}/{mac}', 'verb' => 'GET'],
		['name' => 'hook#recordingReady', 'url' => '/hook/recording/{token}/{mac}', 'verb' => 'POST'],
		// Transcript endpoints
		['name' => 'transcript#batch', 'url' => '/api/transcript/batch', 'verb' => 'GET'],
		['name' => 'transcript#get', 'url' => '/api/transcript/{recordingId}', 'verb' => 'GET'],
		['name' => 'transcript#getText', 'url' => '/api/transcript/{recordingId}/text', 'verb' => 'GET'],
		['name' => 'transcript#content', 'url' => '/api/transcript/{recordingId}/{kind}', 'verb' => 'GET'],
		['name' => 'transcript#download', 'url' => '/api/transcript/{recordingId}/download/{kind}', 'verb' => 'GET'],
		['name' => 'transcript#updateTitle', 'url' => '/api/transcript/{recordingId}/title', 'verb' => 'PUT'],
		['name' => 'transcript#send', 'url' => '/api/transcript/{recordingId}/send', 'verb' => 'POST'],
		['name' => 'transcript#receive', 'url' => '/api/transcript/{recordingId}', 'verb' => 'POST'],
	]
];
