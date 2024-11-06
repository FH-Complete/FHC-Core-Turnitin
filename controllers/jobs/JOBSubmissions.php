<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to manage the sent submissions to turnitin
 */
class JOBSubmissions extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads ManageSubmissionsLib
		$this->load->library('extensions/FHC-Core-Turnitin/ManageSubmissionsLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Read from the sync table all the CREATED submissions and checks on turnitin side if they are completed,
	 * if yes then the sync table is updated
	 */
	public function updateStatus()
	{
		$this->logInfo('Start updating the submissions statuses');

		$updateStatus = $this->managesubmissionslib->updateStatus();

		if (isError($updateStatus)) $this->logError(getError($updateStatus));

		$this->logInfo('End updating the submissions statuses');
	}
}

