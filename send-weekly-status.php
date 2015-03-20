<?php

require(dirname(__FILE__) . '/vendor/autoload.php');
                          
use AJT\Toggl\TogglClient;
                         
$config = json_decode(file_get_contents('config.json'));

$toggl_client = TogglClient::factory(
    array('api_key' => $config->toggl_api_key, 'debug' => true));

foreach ($config->clients as $client) {
    echo $client->name . "\n";
}

?>