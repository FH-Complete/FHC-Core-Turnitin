<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class Similarity extends Auth_Controller
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct(array(
			'getReportResults' => 'admin:r',
			'getReportURL' => 'admin:r'
		));

		// Loads APIClientLib
		$this->load->library('extensions/FHC-Core-Turnitin/APIClientLib');
	}

	/**
	 *
	 */
	public function getReportResults()
	{
		$id = $this->input->get('id');

		$response = $this->apiclientlib->call(
			'/submissions/'.$id.'/similarity',
			APIClientLib::HTTP_GET_METHOD
		);

		if ($this->apiclientlib->isSuccess())
		{
			$this->outputJsonSuccess($response);
		}
		else
		{
			$this->outputJsonError($this->apiclientlib->getError());
		}
	}

	/**
	 *
	 */
	public function getReportURL()
	{
		$id = $this->input->get('id');

		$viewURLParameters = new stdClass();
		// TODO

		$response = $this->apiclientlib->call(
			'/submissions/'.$id.'/viewer-url',
			APIClientLib::HTTP_POST_METHOD,
			$viewURLParameters
		);

		if ($this->apiclientlib->isSuccess())
		{
			$this->outputJsonSuccess($response);
		}
		else
		{
			$this->outputJsonError($this->apiclientlib->getError());
		}
	}
}

