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
 * Library that contains the logic to generate new jobs
 */
class JQMSchedulerLib
{
	private $_ci; // Code igniter instance

	// Job types
	const JOB_TYPE_TII_NEW_UPLOADED_DOCUMENT = 'TTINewUploadedDocument';

	// End document type
	const END_DOCUMENT_TYPE = 'end';

	// Time interval to check for new data
	const JOB_TIME_INTERVAL = '24 hours';

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Looks into the table campus.tbl_paabgabe to check for new uploaded documents to be submitted to TII
	 */
	public function newUploadedDocuments()
	{
		$jobInput = null;
		$dbModel = new DB_Model();

		//
		$result = $dbModel->execReadOnlyQuery(
			'SELECT pg.paabgabe_id
			  FROM campus.tbl_paabgabe pg
			 WHERE pg.abgabedatum > NOW() + INTERVAL ?
			   AND pg.paabgabetyp_kurzbz = ?
			',
			array(
				JQMSchedulerLib::JOB_TIME_INTERVAL,
				JQMSchedulerLib::END_DOCUMENT_TYPE
			)
		);

		// If an error occurred then return the error itself
		if (isError($result)) return $result;

		// If data has been found
		if (hasData($result)) $jobInput = json_encode(getData($result));

		return success($jobInput);
	}
}

