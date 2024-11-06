<?php

class TTISync_model extends DB_Model
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_tii_submissions';
		$this->pk = array('paabgabe_id', 'projektarbeit_id', 'submission_id');
		$this->hasSequence = false;
	}
}

