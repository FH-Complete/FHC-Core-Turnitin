<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Schedules new jobs for turnitin
 */
class JQMScheduler extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads JQMSchedulerLib
		$this->load->library('extensions/FHC-Core-Turnitin/JQMSchedulerLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Checks for new uploaded documents from the Abgabetool and create a new job with the related id
	 */
	public function newUploadedDocuments()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-Turnitin->newUploadedDocuments');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->newUploadedDocuments();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Add the new job to the jobs queue
				$addNewJobResult = $this->addNewJobsToQueue(
					JQMSchedulerLib::JOB_TYPE_TII_NEW_UPLOADED_DOCUMENT, // job type
					$this->generateJobs( // generate the structure of the new job
						JobsQueueLib::STATUS_NEW,
						getData($jobInputResult)
					)
				);

				// If error occurred return it
				if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-Turnitin->newUploadedDocuments');
	}
}

