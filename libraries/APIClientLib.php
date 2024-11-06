<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages API calls
 */
class APIClientLib
{
	const HTTP_GET_METHOD = 'GET'; // http get method name
	const HTTP_POST_METHOD = 'POST'; // http post method name
	const HTTP_PUT_METHOD = 'PUT'; // http put method name
	const HTTP_PUT_UPLOAD_METHOD = 'PUT_UPLOAD'; // http put to upload a file method name
	const HTTP_DELETE_METHOD = 'DELETE'; // http delete method name
	const URI_TEMPLATE = '%s://%s/%s/%s'; // URI format
	const AUTHORIZATION_HEADER_NAME = 'Authorization'; // authorization header name
	const AUTHORIZATION_TYPE = 'Bearer'; // authorization type
	const INTEGRATION_NAME_HEADER_NAME = 'X-Turnitin-Integration-Name';
	const INTEGRATION_VERSION_HEADER_NAME = 'X-Turnitin-Integration-Version';
	const CONTENT_TYPE_PUT_UPLOAD_NAME = 'Content-Type';
	const CONTENT_TYPE_PUT_UPLOAD_VALUE = 'binary/octet-stream';
	const CONTENT_DISPOSITION_PUT_UPLOAD_NAME = 'Content-Disposition';
	const CONTENT_DISPOSITION_PUT_UPLOAD_VALUE = 'inline; filename="%s"';

	// Configs parameters names
	const ACTIVE_CONNECTION = 'api_active_connection';
	const CONNECTIONS = 'api_connections';

	// HTTP codes
	const HTTP_ERROR = 400; // HTTP success code

	// Blocking errors
	const ERROR = 'ERR0001';
	const CONNECTION_ERROR = 'ERR0002';
	const JSON_PARSE_ERROR = 'ERR0003';
	const WRONG_WS_PARAMETERS = 'ERR0004';

	// Connection parameters names
	const PROTOCOL = 'protocol';
	const HOST = 'host';
	const PATH = 'path';
	const AUTHORIZATION = 'authorization';
	const INTEGRATION_NAME = 'integration_name';
	const INTEGRATION_VERSION = 'integration_version';

	private $_connectionsArray;	// contains the connection parameters configuration array

	private $_wsFunction;		// path to the webservice

	private $_httpMethod;		// http method used to call this server
	private $_callParameters;	// contains the parameters to give to the remote web service

	private $_error;		// true if an error occurred
	private $_errorMessage;		// contains the error message
	private $_errorCode;		// contains the error code

	private $_hasData;		// indicates if there are data in the response or not
	private $_emptyResponse;	// indicates if the response is empty or not

	private $_ci;			// Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance
		
		$this->_ci->config->load('extensions/FHC-Core-Turnitin/APIClient'); // Loads the configs
		
		$this->_setPropertiesDefault(); // properties initialization

		$this->_setConnection(); // loads the configurations
	}

	// --------------------------------------------------------------------------------------------
	// Public methods
	
	/**
	 * Performs a call to a remote web service
	 */
	public function call($wsFunction, $httpMethod = self::HTTP_GET_METHOD, $callParameters = array())
	{
		// Checks if the webservice name is provided and it is valid
		if ($wsFunction != null && trim($wsFunction) != '')
		{
			$this->_wsFunction = $wsFunction;
		}
		else
		{
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Forgot something?');
		}
	
		// Checks that the HTTP method required is valid
		if ($httpMethod != null
			&& ($httpMethod == self::HTTP_GET_METHOD || $httpMethod == self::HTTP_POST_METHOD
			|| $httpMethod == self::HTTP_PUT_METHOD || $httpMethod == self::HTTP_PUT_UPLOAD_METHOD
			|| $httpMethod == self::HTTP_DELETE_METHOD))
		{
			$this->_httpMethod = $httpMethod;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Have you ever herd about HTTP methods?');
		}
	
		// Checks that the webservice parameters are fine
		if ($httpMethod == self::HTTP_GET_METHOD && !is_array($callParameters))
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Are those parameters?');
		}
		else
		{
			$this->_callParameters = $callParameters;
		}
	
		if ($this->isError()) return null; // If an error was raised then return a null value
	
		return $this->_callRemoteWS($this->_generateURI()); // perform a remote ws call with the given uri
	}

	/**
	 * Returns the error message stored in property _errorMessage
	 */
	public function getError()
	{
		return $this->_errorMessage;
	}
	
	/**
	 * Returns the error code stored in property _errorCode
	 */
	public function getErrorCode()
	{
		return $this->_errorCode;
	}

	/**
	 * Returns true if an error occurred, otherwise false
	 */
	public function isError()
	{
		return $this->_error;
	}

	/**
	 * Returns false if an error occurred, otherwise true
	 */
	public function isSuccess()
	{
		return !$this->isError();
	}

	/**
	 * Reset the library properties to default values
	 */
	public function resetToDefault()
	{
		$this->_wsFunction = null;
		$this->_httpMethod = null;
		$this->_callParameters = array();
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
		$this->_emptyResponse = false;
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Initialization of the properties of this object
	 */
	private function _setPropertiesDefault()
	{
		$this->_connectionsArray = null;
		$this->_wsFunction = null;
		$this->_httpMethod = null;
		$this->_callParameters = array();
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
		$this->_emptyResponse = false;
	}

	/**
	 * Sets the connection
	 */
	private function _setConnection()
	{
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);
	
		$this->_connectionsArray = $connectionsArray[$activeConnectionName];
	}

	/**
	 * Returns true if the HTTP method used to call this server is GET
	 */
	private function _isGET()
	{
		return $this->_httpMethod == self::HTTP_GET_METHOD;
	}
	
	/**
	 * Returns true if the HTTP method used to call this server is POST
	 */
	private function _isPOST()
	{
		return $this->_httpMethod == self::HTTP_POST_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is PUT
	 */
	private function _isPUT()
	{
		return $this->_httpMethod == self::HTTP_PUT_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is PUT to upload a file
	 */
	private function _isPUTUpload()
	{
		return $this->_httpMethod == self::HTTP_PUT_UPLOAD_METHOD;
	}

	/**
	 * Returns true if the HTTP method used to call this server is DELETE
	 */
	private function _isDELETE()
	{
		return $this->_httpMethod == self::HTTP_DELETE_METHOD;
	}

	/**
	 * Generate the URI to call the remote web service
	 */
	private function _generateURI()
	{
		return sprintf(
			self::URI_TEMPLATE,
			$this->_connectionsArray[self::PROTOCOL],
			$this->_connectionsArray[self::HOST],
			$this->_connectionsArray[self::PATH],
			$this->_wsFunction
		);
	}

	/**
	 * Performs a remote web service call with the given uri and returns the result after having checked it
	 */
	private function _callRemoteWS($uri)
	{
		$response = null;

		try
		{
			if ($this->_isGET()) // if the call was performed using a HTTP GET...
			{
				$response = $this->_callGET($uri); // ...calls the remote web service with the HTTP GET method
			}
			elseif ($this->_isPOST()) // else if the call was performed using a HTTP POST...
			{
				$response = $this->_callPOST($uri); // ...calls the remote web service with the HTTP POST method
			}
			elseif ($this->_isPUT()) // else if the call was performed using a HTTP PUT...
			{
				$response = $this->_callPUT($uri); // ...calls the remote web service with the HTTP PUT method
			}
			elseif ($this->_isPUTUpload()) // else if the call was performed using a HTTP PUT to upload a file
			{
				$response = $this->_callPUTUpload($uri); // ...calls the remote web service with the HTTP PUT method to upload a file
			}
			elseif ($this->_isDELETE()) // else if the call was performed using a HTTP DELETE...
			{
				$response = $this->_callDELETE($uri); // ...calls the remote web service with the HTTP DELETE method
			}

			// Checks the response of the remote web service and handles possible errors
			// Eventually here is also called a hook, so the data could have been manipulated
			$response = $this->_checkResponse($response);
		}
		catch (\Httpful\Exception\ConnectionErrorException $cee) // connection error
		{
			$this->_error(self::CONNECTION_ERROR, 'A connection error occurred while calling the remote server');
		}
		// Otherwise another error has occurred, most likely the result of the
		// remote web service is not json so a parse error is raised
		catch (Exception $e)
		{
			$this->_error(self::JSON_PARSE_ERROR, 'The remote server answered with a not valid json');
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

		return $response;
	}

	/**
	 * Performs a remote call using the GET HTTP method
	 */
	private function _callGET($uri)
	{
		return \Httpful\Request::get($uri.$this->_generateQueryString())
			->expectsJson()
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_TYPE.' '.$this->_connectionsArray[self::AUTHORIZATION])
			->addHeader(self::INTEGRATION_NAME_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_NAME])
			->addHeader(self::INTEGRATION_VERSION_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_VERSION])
			->send();
	}

	/**
	 * Performs a remote call using the POST HTTP method
	 */
	private function _callPOST($uri)
	{
		return \Httpful\Request::post($uri)
			->expectsJson() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_TYPE.' '.$this->_connectionsArray[self::AUTHORIZATION])
			->addHeader(self::INTEGRATION_NAME_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_NAME])
			->addHeader(self::INTEGRATION_VERSION_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_VERSION])
			->body($this->_callParameters) // post parameters
			->sendsJson() // content type json
			->send();
	}

	/**
	 * Performs a remote call using the PUT HTTP method
	 */
	private function _callPUT($uri)
	{
		return \Httpful\Request::put($uri)
			->expectsJson() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_TYPE.' '.$this->_connectionsArray[self::AUTHORIZATION])
			->addHeader(self::INTEGRATION_NAME_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_NAME])
			->addHeader(self::INTEGRATION_VERSION_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_VERSION])
			->body($this->_callParameters) // post parameters
			->sendsJson() // content type json
			->send();
	}

	/**
	 * Performs a remote call using the PUT HTTP method to upload a file
	 */
	private function _callPUTUpload($uri)
	{
		return \Httpful\Request::put($uri)
			->expectsJson() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_TYPE.' '.$this->_connectionsArray[self::AUTHORIZATION])
			->addHeader(self::INTEGRATION_NAME_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_NAME])
			->addHeader(self::INTEGRATION_VERSION_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_VERSION])
			->addHeader(self::CONTENT_TYPE_PUT_UPLOAD_NAME, self::CONTENT_TYPE_PUT_UPLOAD_VALUE)
			->addHeader(self::CONTENT_DISPOSITION_PUT_UPLOAD_NAME, sprintf(self::CONTENT_DISPOSITION_PUT_UPLOAD_VALUE, $this->_callParameters['filename']))
			->body(file_get_contents($this->_callParameters['filepath'].$this->_callParameters['filename']))
			->send();
	}

	/**
	 * Performs a remote call using the DELETE HTTP method
	 */
	private function _callDELETE($uri)
	{
		return \Httpful\Request::delete($uri.$this->_generateQueryString())
			->expectsJson() // dangerous expectations
			->addHeader(self::AUTHORIZATION_HEADER_NAME, self::AUTHORIZATION_TYPE.' '.$this->_connectionsArray[self::AUTHORIZATION])
			->addHeader(self::INTEGRATION_NAME_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_NAME])
			->addHeader(self::INTEGRATION_VERSION_HEADER_NAME, $this->_connectionsArray[self::INTEGRATION_VERSION])
			->send();
	}

	/**
	 * Checks the response from the remote web service
	 */
	private function _checkResponse($response)
	{
		$checkResponse = null;
	
		// If NOT an empty response
		if (is_object($response) && isset($response->code))
		{
			// Checks the HTTP response code
			// If it is a success
			if ($response->code < self::HTTP_ERROR)
			{
				$checkResponse = $response->body;
			}
			else // otherwise set the error
			{
				$this->_error($response->code, json_encode($response->body));
			}
		}
		else // otherwise set the error
		{
			$this->_error($response->code, json_encode($response->body));
		}

		return $checkResponse;
	}

	/**
	 * Sets property _error to true and stores an error message in property _errorMessage
	 */
	private function _error($code, $message = 'Generic error')
	{
		$this->_error = true;
		$this->_errorCode = $code;
		$this->_errorMessage = $message;
	}

	/**
	 *
	 */
	private function _generateQueryString()
	{
		$queryString = '';
		$firstParam = true;

		// Create the query string
		foreach ($this->_callParameters as $name => $value)
		{
			if (is_array($value)) // if is an array
			{
				foreach ($value as $key => $val)
				{
					$queryString .= ($firstParam == true ? '?' : '&').$name.'[]='.urlencode($val);
				}
			}
			else // otherwise
			{
				$queryString .= ($firstParam == true ? '?' : '&').$name.'='.urlencode($value);
			}
		
			$firstParam = false;
		}

		return $queryString;
	}
}

