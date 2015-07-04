<?php

require(dirname(__FILE__) . '/vendor/autoload.php');
require(dirname(__FILE__) . '/includes.php');

use AJT\Toggl\TogglClient;
use AJT\Toggl\ReportsClient;

$options = getopt("v");
$debug = array_key_exists('v', $options);
$config = json_decode(file_get_contents('config.json'), true);

$toggl_client = TogglClient::factory(
    array(
        'api_key' => $config['toggl_api_key'], 
        'debug'   => $debug,
    )
);

$reports_client = ReportsClient::factory(
    array(
        'api_key' => $config['toggl_api_key'], 
        'debug'   => $debug,
    )
);

$current_ws = getWorkspaceByName($config['toggl_workspace'], $toggl_client);

if (!$current_ws) {
    die("ERROR: Workspace '{$config['toggl_workspace']}' not found. Dying.\n");
}
$users = $toggl_client->getWorkspaceUsers(array('id' => $current_ws['id']));

$yesterday = (new DateTime('yesterday'))->format('Y-m-d');

$output = "\nHello all,\n\n";
$output .= "Please find the team's billable summary for {$yesterday} below:\n\n";

foreach ($users as $user) {

    // get hours billed for the day
    $daily_report = $reports_client->details(
        array(
            'user_agent'   => $config['recipient_email'],
            'workspace_id' => $current_ws['id'],
            'user_ids'     => "{$user['id']}",
            'order_desc'   => 'off',
            'since'        => $yesterday,
            'until'        => $yesterday
        )
    );
    $total = $daily_report['total_grand'] / 1000 / 60 / 60;
    $title = "{$user['fullname']} : $total hour";
    if ($total != 1) {
        $title .= "s";
    }
    $output .= "$title\n";
    $output .= str_repeat("-", strlen($title)) . "\n";
    
    foreach ($daily_report['data'] as $row_i => $time_entry) {
        $time = $time_entry['dur'] / 1000 / 60 / 60;
        $output .= "* {$time_entry['client']}: {$time_entry['project']}: {$time_entry['description']}: {$time} hour";
        if ($time != 1) {
            $output .= "s";
        }
        $output .= "\n";
    }
    if ($total == 0) {
        $output .= "N/A\n";
    }
    $output .= "\n";
}
$output .= "Thanks, {$current_ws['name']} Bot\n\n";
mail($config['team_email'], "[{$current_ws['name']}] Team Report for {$yesterday}", $output);
// echo $output;
