<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class EULA extends FHC_Controller
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads APIClientLib
		$this->load->library('extensions/FHC-Core-Turnitin/APIClientLib');
	}

	/**
	 *
	 */
	public function getLatestURL()
	{
		$language = $this->input->get('language');

		if (isEmptyString($language)) $language = 'de-DE';

		$response = $this->apiclientlib->call(
			'/eula/latest',
			APIClientLib::HTTP_GET_METHOD,
			array(
				'lang' => $language
			)
		);

		if ($this->apiclientlib->isSuccess())
		{
			if (isset($response->url))
			{
				$this->outputJsonSuccess($response->url);
			}
			else
			{
				$this->outputJsonError('Not a valid response, URL is missing');
			}
		}
		else
		{
			$this->outputJsonError($this->apiclientlib->getError());
		}
	}
}

