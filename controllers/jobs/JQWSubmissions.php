<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job queue workers to send submissions to turnitin
 */
class JQWSubmissions extends JQW_Controller
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
	 * Reads jobs from the database and sends submissions to turnitin
	 */
	public function create()
	{
		$this->logInfo('Start sending submissions to turnitin: create');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs(ManageSubmissionsLib::JOB_TYPE_TII_NEW_UPLOADED_DOCUMENT);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), ManageSubmissionsLib::JOB_TYPE_TII_NEW_UPLOADED_DOCUMENT);
		}
		elseif (hasData($lastJobs)) // if there jobs to work
		{
			// Update jobs start time
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);
			$updateResult = $this->updateJobsQueue(ManageSubmissionsLib::JOB_TYPE_TII_NEW_UPLOADED_DOCUMENT, getData($lastJobs));

			// If an error occurred then log it
			if (isError($updateResult))
			{
				$this->logError(getError($updateResult));
			}
			else // works the jobs
			{
				// Get all the jobs in the queue
				$submissionsResult = $this->managesubmissionslib->create($this->_paabgabeJobsToArray(getData($lastJobs)));

				// Log the result
				if (isError($submissionsResult))
				{
					// Save all the errors
					$errors = getError($submissionsResult);

					// If it is NOT an array...
					if (isEmptyArray($errors))
					{
						// ...then convert it to an array
						$errors = array($errors);
					}
					// otherwise it is already an array

					// For each error found
					foreach ($errors as $error)
					{
						$this->logError(getCode($submissionsResult).': '.$error);
					}
				}
				else
				{
					$this->logInfo(getData($submissionsResult));
				}

				// Update jobs properties values
				$this->updateJobs(
					getData($lastJobs), // Jobs to be updated
					array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
					array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
				);
				$this->updateJobsQueue(ManageSubmissionsLib::JOB_TYPE_TII_NEW_UPLOADED_DOCUMENT, getData($lastJobs));
			}
		}

		$this->logInfo('End sending submissions to turnitin: create');
	}

	/**
	 * Gets a list of jobs as parameter and returns a merged array of person ids
	 */
	private function _paabgabeJobsToArray($jobs, $jobsAmount = 99999)
	{
		$jobsCounter = 0;
		$returnArray = array();
	
		// If no jobs then return an empty array
		if (count($jobs) == 0) return $returnArray;
	
		// For each job
		foreach ($jobs as $job)
		{
			// Decode the json input
			$decodedInput = json_decode($job->input);
	
			// If decoding was fine
			if ($decodedInput != null)
			{
				// For each element in the array
				foreach ($decodedInput as $el)
				{
					$returnArray[] = $el->paabgabe_id; //
				}
			}
	
			$jobsCounter++; // jobs counter
	
			if ($jobsCounter >= $jobsAmount) break; // if the required amount is reached then exit
		}
	
		return $returnArray;
	}
}

