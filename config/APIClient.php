<?php

$config['api_active_connection'] = 'DEFAULT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['api_connections'] = array(
	'DEFAULT' => array(
		'protocol' => 'https', // ssl by default... better!
		'host' => 'technikum-wien.turnitin.com', // server name
		'path' => 'api/v1', // usually this is the path for the API
		'authorization' => '9b0c7ec7468b4287864aa17c727291f2', // authorization header value
                'integration_name' => 'fhcdev',
                'integration_version' => 'v1'
	)
);

