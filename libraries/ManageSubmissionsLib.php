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
 * Library that contains the logic to work submissions jobs
 */
class ManageSubmissionsLib
{
	private $_ci; // Code igniter instance

	// Config entries
	const TURNITIN_OWNER = 'turnitin_owner';
	const TURNITIN_SUBMITTER = 'turnitin_submitter';

	// Job types
	const JOB_TYPE_TII_NEW_UPLOADED_DOCUMENT = 'TTINewUploadedDocument';

	// REST API response values
	const SUBMISSION_CREATED = 'CREATED';
	const SUBMISSION_COMPLETE = 'COMPLETE';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads global config
		$this->_ci->load->config('extensions/FHC-Core-Turnitin/global');

		// Loads TTISync_model
		$this->_ci->load->model('extensions/FHC-Core-Turnitin/TTISync_model', 'TTISyncModel');

		// Loads APIClientLib
		$this->_ci->load->library('extensions/FHC-Core-Turnitin/APIClientLib');

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
	 * Creates new submissions
	 */
	public function create($paabgabe_ids)
	{
		// If the given array is empty
		if (isEmptyArray($paabgabe_ids)) return success('No submissions to be created');

		$dbModel = new DB_Model();

		// Get all the couples paabgabe_id student_uid from database related to the given paabgabe_ids, that have not been synced yet
		// Gets the student name, surname and email to be sent to TII
		$dbResults = $dbModel->execReadOnlyQuery(
			'SELECT pg.paabgabe_id,
				pg.projektarbeit_id,
				pa.student_uid,
				pa.titel,
				p.nachname,
				p.vorname,
				k.kontakt
			   FROM campus.tbl_paabgabe pg
			   JOIN lehre.tbl_projektarbeit pa USING (projektarbeit_id)
			   JOIN public.tbl_student s USING (student_uid)
			   JOIN public.tbl_prestudent ps USING (prestudent_id)
			   JOIN public.tbl_person p USING (person_id)
		      LEFT JOIN (
				SELECT kontakt,
					person_id
				  FROM public.tbl_kontakt
				 WHERE kontakttyp = \'email\'
				   AND zustellung = TRUE
			) k USING (person_id)
			  WHERE pg.paabgabe_id IN ?
			    AND NOT EXISTS (
				SELECT 1
				  FROM '.$this->_ci->TTISyncModel->getDbTable().' stii
				 WHERE stii.paabgabe_id = pg.paabgabe_id
				   AND stii.projektarbeit_id = pg.projektarbeit_id
			)',
			array(
				$paabgabe_ids
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
				$title = $dbRecord->titel;
				if (isEmptyString($title)) $title = $dbRecord->paabgabe_id.' '.$dbRecord->student_uid;

				// Create a submission
				$requestBody = new stdClass();
				// Owner/submitter
				$requestBody->owner = $this->_ci->config->item(self::TURNITIN_OWNER);
				$requestBody->owner_default_permission_set = 'USER';
				$requestBody->submitter = $this->_ci->config->item(self::TURNITIN_SUBMITTER);
				$requestBody->submitter_default_permission_set = 'USER';
				// Submission title
				$requestBody->title = $title;
				// From the Turnitin API doc:
				// Indicates if the submission should be treated as a text only submission.
				// A text only submission cannot generate full reports or be viewed in the viewer,
				// but can use the index only endpoint to be indexed
				$requestBody->extract_text_only = false;
				// Metadata to give name, surname and email to the owner/submitter
				$requestBody->metadata = new stdClass();
				// Metadata -> owner
				$metaDataOwner = new stdClass();
				// NOTE: this is right, we use the same owner id but with different name, surname and email
				$metaDataOwner->id = $this->_ci->config->item(self::TURNITIN_OWNER);
				$metaDataOwner->family_name = $dbRecord->nachname;
				$metaDataOwner->given_name = $dbRecord->vorname;
				$metaDataOwner->email = $dbRecord->kontakt;
				$requestBody->metadata->owners = array($metaDataOwner);
				// Metadata -> submitter
				$requestBody->metadata->submitter = new stdClass();
				// NOTE: this is right, we use the same submitter id but with different name, surname and email
				$requestBody->metadata->submitter->id = $this->_ci->config->item(self::TURNITIN_SUBMITTER);
				$requestBody->metadata->submitter->family_name = $dbRecord->nachname;
				$requestBody->metadata->submitter->given_name = $dbRecord->vorname;
				$requestBody->metadata->submitter->email = $dbRecord->kontakt;

				// Calls the TII API to create a new submission
				$rSubmission = $this->_ci->apiclientlib->call(
					'/submissions',
					APIClientLib::HTTP_POST_METHOD,
					$requestBody
				);

				// Error handling
				if ($this->_ci->apiclientlib->isError()) return error($this->_ci->apiclientlib->getError(), $this->_ci->apiclientlib->getErrorCode());
				if ((!isset($rSubmission->id) || (isset($rSubmission->id) && isEmptyString($rSubmission->id)))
					|| (!isset($rSubmission->status) || (isset($rSubmission->status) && $rSubmission->status != self::SUBMISSION_CREATED)))
				{
					return error('The submission creation went wrong');
				}

				// Calls the TII API to upload file for the submission
				$rSubmissionUpload = $this->_ci->apiclientlib->call(
					'/submissions/'.$rSubmission->id.'/original',
					APIClientLib::HTTP_PUT_UPLOAD_METHOD,
					array(
						'filename' => $dbRecord->paabgabe_id.'_'.$dbRecord->student_uid.'.pdf',
						'filepath' => PAABGABE_PATH
					)
				);

				// Error handling
				if ($this->_ci->apiclientlib->isError()) return error($this->_ci->apiclientlib->getError(), $this->_ci->apiclientlib->getErrorCode());

				// Store into DB
				$syncResult = $this->_ci->TTISyncModel->insert(
					array(
						'paabgabe_id' => $dbRecord->paabgabe_id,
						'projektarbeit_id' => $dbRecord->projektarbeit_id,
						'submission_id' => $rSubmission->id,
						'status' => self::SUBMISSION_CREATED
					)
				);

				// If error occurred then return it
				if (isError($syncResult)) return $syncResult;
			}
		}
		else
		{
			return success('There are no submissions to work');
		}

		// If here then it is a success!
		return success('Submissions created');
	}

	/**
	 * Gets all the CREATED submissions from the sync table and check on the Turnitin side if their status is COMPLETE,
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
				self::SUBMISSION_CREATED
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
					'/submissions/'.$dbRecord->submission_id,
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
							'status' => self::SUBMISSION_COMPLETE
						)
					);

					// If error occurred then return it
					if (isError($syncResult)) return $syncResult;
				}
			}
		}
		else
		{
			return success('There are no submission with status: '.self::SUBMISSION_CREATED);
		}

		return success('Submissions updated to status: '.self::SUBMISSION_COMPLETE);
	}
}

