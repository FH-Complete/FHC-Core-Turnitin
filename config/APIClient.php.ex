<?php

$config['api_active_connection'] = 'DEFAULT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['api_connections'] = array(
	'DEFAULT' => array(
		'protocol' => 'https', // ssl by default... better!
		'host' => 'api.technikum-wien.at', // server name
		'path' => 'v1', // usually this is the path for the API
		'authorization' => 'secret0', // authorization header value
                'integration_name' => 'fhcdev',
                'integration_version' => 'v1'
	)
);

