<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to manage the turnitin similaties
 */
class JOBSimilarities extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads ManageSimilaritiesLib
		$this->load->library('extensions/FHC-Core-Turnitin/ManageSimilaritiesLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Read from the sync table all the COMPLETE submissions and start the turnitin similarities generation
	 */
	public function generate()
	{
		$this->logInfo('Start similarities generation');

		$generateResult = $this->managesimilaritieslib->generate();

		if (isError($generateResult)) $this->logError(getError($generateResult));

		$this->logInfo('End similarities generation');
	}

	/**
	 * Read from the sync table all the SENT submissions and check on turnitin side if they are COMPLETE,
	 * if yes then the sync table is updated
	 */
	public function updateStatus()
	{
		$this->logInfo('Start updating similaties statuses');

		$updateStatus = $this->managesimilaritieslib->updateStatus();

		if (isError($updateStatus)) $this->logError(getError($updateStatus));

		$this->logInfo('End updating similaties statuses');
	}
}

