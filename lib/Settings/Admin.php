<?php

namespace OCA\BigBlueButton\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	/**
	 * Admin constructor.
	 *
	 * @param IAppConfig $config
	 */
	public function __construct(private IAppConfig $config) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$parameters = [
			'api.url' => $this->config->getValueString('boss_meeting', 'api.url'),
			'api.secret' => $this->config->getValueString('boss_meeting', 'api.secret'),
			'app.navigation' => $this->config->getValueBool('boss_meeting', 'app.navigation') ? 'checked' : '',
			'join.theme' => $this->config->getValueBool('boss_meeting', 'join.theme') ? 'checked' : '',
			'app.shortener' => $this->config->getValueString('boss_meeting', 'app.shortener'),
			'join.mediaCheck' => $this->config->getValueBool('boss_meeting', 'join.mediaCheck', true) ? 'checked' : '',
			'retention.enabled' => $this->config->getValueBool('boss_meeting', 'retention.enabled', false) ? 'checked' : '',
			'retention.days' => $this->config->getValueInt('boss_meeting', 'retention.days', 180),
			'retention.mode' => $this->config->getValueString('boss_meeting', 'retention.mode', 'archive'),
			'retention.dryRun' => $this->config->getValueBool('boss_meeting', 'retention.dryRun', true) ? 'checked' : '',
		];

		return new TemplateResponse('boss_meeting', 'admin', $parameters);
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'additional';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 */
	public function getPriority() {
		return 50;
	}
}
