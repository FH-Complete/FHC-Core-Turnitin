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
			'getDetails' => 'admin:r',
			'delete' => 'admin:r'
		));

		// Loads TTISync_model
		$this->load->model('extensions/FHC-Core-Turnitin/TTISync_model', 'TTISyncModel');

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

	/**
	 *
	 */
	public function delete()
	{
		$id = $this->input->get('id');
		$hard = $this->input->get('hard');

		$response = $this->apiclientlib->call(
			'/submissions/'.$id,
			APIClientLib::HTTP_DELETE_METHOD,
			array(
				'hard' => $hard
			)
		);

		if ($this->apiclientlib->isSuccess())
		{
			// Update DB
			$syncResult = $this->TTISyncModel->update(
				array(
					'submission_id' => $id
				),
				array(
					'status' => strtolower($hard) == 'true' ? 'DELETED' : 'SOFT'
				)
			);

			$this->outputJsonSuccess($response);
		}
		else
		{
			$this->outputJsonError($this->apiclientlib->getError());
		}
	}
}

