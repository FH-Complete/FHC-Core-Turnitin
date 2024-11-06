<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class Submissions extends Auth_Controller
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct(array(
			'getDetails' => 'admin:r'
		));

		// Loads APIClientLib
		$this->load->library('extensions/FHC-Core-Turnitin/APIClientLib');
	}

	/**
	 *
	 */
	public function getDetails()
	{
		$id = $this->input->get('id');

		$response = $this->apiclientlib->call(
			'/submissions/'.$id,
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
}

