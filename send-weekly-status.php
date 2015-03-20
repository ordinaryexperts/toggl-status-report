<?php

require(dirname(__FILE__) . '/vendor/autoload.php');
                          
use AJT\Toggl\TogglClient;
                         
$debug  = in_array('-v', $argv);
$config = json_decode(file_get_contents('config.json'), true);

$toggl_client = TogglClient::factory(
    array(
        'api_key' => $config['toggl_api_key'], 
        'debug'   => $debug,
    )
);

try {
    $workspaces = $toggl_client->getWorkspaces(array());
} catch (Exception $e) {
    die("ERROR: Communication with Toggl failed. Dying.\n");
}

$current_ws = null;
foreach ($workspaces as $workspace) {
    if ($workspace['name'] == $config['toggl_workspace']) {
        $current_ws = $workspace;
        break;
    }
}

if (!$current_ws) {
    die("ERROR: Workspace '{$config['toggl_workspace']}' not found. Dying.\n");
}

$clients = $toggl_client->getClients(array());
foreach ($clients as $client) {
    if ($client['wid'] == $current_ws['id']) {
        echo "Generating report for client '{$client['name']}'...";
        echo "done\n";
    }
}

?>