<?php
/**
 * Copyright (C) 2024 fhcomplete.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('BASEPATH')) exit('No direct script access allowed');

use \DB_Model as DB_Model;

/**
 * Library that contains the logic to work similarity jobs
 */
class ManageSimilaritiesLib
{
	private $_ci; // Code igniter instance

	// REST API response values
	const SUBMISSION_COMPLETE = 'COMPLETE';
	const SUBMISSION_SENT = 'SENT';
	const SUBMISSION_DONE = 'DONE';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads APIClientLib
		$this->_ci->load->library('extensions/FHC-Core-Turnitin/APIClientLib');

		// Loads TTISync_model
		$this->_ci->load->model('extensions/FHC-Core-Turnitin/TTISync_model', 'TTISyncModel');

		// Loads the LogLib with the needed parameters to log correctly from this library
		$this->_ci->load->library(
			'LogLib',
			array(
				'classIndex' => 3,
				'functionIndex' => 3,
				'lineIndex' => 2,
				'dbLogType' => 'job', // required
				'dbExecuteUser' => 'Cronjob system',
				'requestId' => 'JOB',
				'requestDataFormatter' => function($data) {
					return json_encode($data);
				}
			),
			'LogLibTII'
		);
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Generate new similarity
	 */
	public function generate()
	{
		$dbModel = new DB_Model();

		// Get all the created submission stored into the database
		$dbResults = $dbModel->execReadOnlyQuery(
			'SELECT stii.submission_id,
				stii.paabgabe_id,
				stii.projektarbeit_id
			   FROM '.$this->_ci->TTISyncModel->getDbTable().' stii
			  WHERE stii.status = ?
			',
			array(
				self::SUBMISSION_COMPLETE
			)
		);

		// If error occurred while retrieving new users from database then return the error
		if (isError($dbResults)) return $dbResults;

		// If data has been found
		if (hasData($dbResults))
		{
			// For each db record
                        foreach (getData($dbResults) as $dbRecord)
                        {
				// Get submission info using the submission_id from database
				$rSubmission = $this->_ci->apiclientlib->call(
					'/submissions/'.$dbRecord->submission_id,
					APIClientLib::HTTP_GET_METHOD
				);

				// Error handling
				if ($this->_ci->apiclientlib->isError()) return error($this->_ci->apiclientlib->getError(), $this->_ci->apiclientlib->getErrorCode());

				// If the status is not COMPLETE then...
				if (!isset($rSubmission->status)
					|| (isset($rSubmission->status) && $rSubmission->status != self::SUBMISSION_COMPLETE))
				{
					continue; // with the next one
				}
				// otherwise...

				// Generate similarity
				$requestBody = new stdClass();
				// indexing_settings
				$requestBody->indexing_settings = new stdClass();
				$requestBody->indexing_settings->add_to_index = true;
				// generation_settings
				$requestBody->generation_settings = new stdClass();
				$requestBody->generation_settings->search_repositories = array(
					'INTERNET', 'PUBLICATION', 'SUBMITTED_WORK', 'CROSSREF', 'CROSSREF_POSTED_CONTENT'
				);
				$requestBody->generation_settings->auto_exclude_self_matching_scope = 'ALL';
				$requestBody->generation_settings->priority = false;
				// view_settings
				$requestBody->view_settings = new stdClass();
				$requestBody->view_settings->exclude_quotes = true;
				$requestBody->view_settings->exclude_bibliography = true;
				$requestBody->view_settings->exclude_citations = true;
				$requestBody->view_settings->exclude_abstract = true;
				$requestBody->view_settings->exclude_methods = true;
				$requestBody->view_settings->exclude_small_matches = 4;
				$requestBody->view_settings->exclude_internet = false;
				$requestBody->view_settings->exclude_publications = false;
				$requestBody->view_settings->exclude_crossref = false;
				$requestBody->view_settings->exclude_crossref_posted_content = false;
				$requestBody->view_settings->exclude_submitted_works = false;
				$requestBody->view_settings->exclude_custom_sections = true;
				$requestBody->view_settings->exclude_preprints = true;

				$rSimilarity = $this->_ci->apiclientlib->call(
					'/submissions/'.$dbRecord->submission_id.'/similarity',
					APIClientLib::HTTP_PUT_METHOD,
					$requestBody
				);

				// Error handling
				if ($this->_ci->apiclientlib->isError()) return error($this->_ci->apiclientlib->getError(), $this->_ci->apiclientlib->getErrorCode());

				// Update sync table
				$syncResult = $this->_ci->TTISyncModel->update(
					array(
						'paabgabe_id' => $dbRecord->paabgabe_id,
						'projektarbeit_id' => $dbRecord->projektarbeit_id,
						'submission_id' => $rSubmission->id
					),
					array(
						'status' => self::SUBMISSION_SENT
					)
				);
			}
		}
		else
		{
			return success('No submissions with status: '.self::SUBMISSION_COMPLETE);
		}

		// Everything is fine!
		return success('All the similarities generated');
	}

	/**
	 * Gets all the SENT submissions from the sync table and check on the Turnitin side if the status of the related similatiry is COMPLETE,
	 * in case the sync table is updated
	 */
	public function updateStatus()
	{
		$dbModel = new DB_Model();

		$dbResults = $dbModel->execReadOnlyQuery(
			'SELECT stii.submission_id,
				stii.paabgabe_id,
				stii.projektarbeit_id
			   FROM '.$this->_ci->TTISyncModel->getDbTable().' stii
			  WHERE stii.status = ?
			',
			array(
				self::SUBMISSION_SENT
			)
		);

		// If error occurred then return the error
		if (isError($dbResults)) return $dbResults;

		// If data has been found
		if (hasData($dbResults))
		{
			// For each db record
			foreach (getData($dbResults) as $dbRecord)
			{
				// Check the status of the submission
				$rSubmission = $this->_ci->apiclientlib->call(
					'/submissions/'.$dbRecord->submission_id.'/similarity',
					APIClientLib::HTTP_GET_METHOD
				);

				// Error handling
				if ($this->_ci->apiclientlib->isError()) return error($this->_ci->apiclientlib->getError(), $this->_ci->apiclientlib->getErrorCode());
				if (!isset($rSubmission->status))
				{
					return error('The submission response is wrong: status is missing');
				}

				// If the status of this submission is COMPLETE then update the DB
				if ($rSubmission->status == self::SUBMISSION_COMPLETE)
				{
					// Update DB
					$syncResult = $this->_ci->TTISyncModel->update(
						array(
							'paabgabe_id' => $dbRecord->paabgabe_id,
							'projektarbeit_id' => $dbRecord->projektarbeit_id,
							'submission_id' => $dbRecord->submission_id
						),
						array(
							'status' => self::SUBMISSION_DONE
						)
					);

					// If error occurred then return it
					if (isError($syncResult)) return $syncResult;
				}
			}
		}
		else
		{
			return success('There are no submission with status: '.self::SUBMISSION_SENT);
		}

		return success('Submissions updated to status: '.self::SUBMISSION_DONE);
	}
}

