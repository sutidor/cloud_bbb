<?php

namespace OCA\BigBlueButton;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IL10N;

class TemplateProvider {

	/**
	 * Admin constructor.
	 *
	 */
	public function __construct(private IAppConfig $config, private IL10N $l) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getManager(): TemplateResponse {
		$warning = '';

		if (empty($this->config->getValueString('boss_meeting', 'api.url')) || empty($this->config->getValueString('boss_meeting', 'api.secret'))) {
			$warning = $this->l->t('API URL or secret not configured. Please contact your administrator.');
		}

		return new TemplateResponse('boss_meeting', 'manager', [
			'warning' => $warning,
			'shortener' => $this->config->getValueString('boss_meeting', 'app.shortener', ''),
		]);
	}
}
