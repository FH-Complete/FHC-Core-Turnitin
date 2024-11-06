<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class Similarity extends Auth_Controller
{
	// Config entries
	const TURNITIN_OWNER = 'turnitin_owner';

	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct(array(
			'getReportResults' => 'admin:r',
			'getReportURL' => 'admin:r'
		));

		// Loads global config
                $this->load->config('extensions/FHC-Core-Turnitin/global');

		// Loads APIClientLib
		$this->load->library('extensions/FHC-Core-Turnitin/APIClientLib');
	}

	/**
	 *
	 */
	public function getReportResults()
	{
		$id = $this->input->get('id');

		$response = $this->apiclientlib->call(
			'/submissions/'.$id.'/similarity',
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
	public function getReportURL()
	{
		$id = $this->input->get('id');

		// View configuration
		// Generic
		$viewURLParameters = new stdClass();
		$viewURLParameters->viewer_user_id = $this->config->item(self::TURNITIN_OWNER);
		$viewURLParameters->locale = 'de-DE';
		$viewURLParameters->viewer_default_permission_set = 'USER';
		// Permissions
		$viewURLParameters->viewer_permissions = new stdClass();
		$viewURLParameters->viewer_permissions->may_view_submission_full_source = true;
		$viewURLParameters->viewer_permissions->may_view_match_submission_info = true;
		$viewURLParameters->viewer_permissions->may_view_flags_panel = true;
		$viewURLParameters->viewer_permissions->may_view_document_details_panel = true;
		$viewURLParameters->viewer_permissions->may_view_sections_exclusion_panel = true;
		// Sidebar
		$viewURLParameters->sidebar = new stdClass();
		$viewURLParameters->sidebar->default_mode = 'similarity';
		// Report
		$viewURLParameters->similarity = new stdClass;
		$viewURLParameters->similarity->default_mode = 'match_overview';
		$viewURLParameters->similarity->modes = new stdClass();
		$viewURLParameters->similarity->modes->match_overview = true;
		$viewURLParameters->similarity->modes->all_sources = true;
		$viewURLParameters->similarity->view_settings = new stdClass();
		$viewURLParameters->similarity->view_settings->save_changes = false;
		// Annotations
		$viewURLParameters->annotations = new stdClass();
		$viewURLParameters->annotations->enabled = true;

		$response = $this->apiclientlib->call(
			'/submissions/'.$id.'/viewer-url',
			APIClientLib::HTTP_POST_METHOD,
			$viewURLParameters
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

