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

$today    = (new DateTime('today'))->format('Y-m-d');
$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

foreach ($users as $user) {
    $output = '';

    // get hours billed for the day
    $daily_report = $reports_client->details(
        array(
            'user_agent'   => $config['recipient_email'],
            'workspace_id' => $current_ws['id'],
            'user_ids'     => "{$user['id']}",
            'order_desc'   => 'off',
            'since'        => $today,
            'until'        => $tomorrow
        )
    );
    $total = $daily_report['total_grand'] / 1000 / 60 / 60;
    $output .= "Hi {$user['fullname']},\n\n";
    $output .= "Total for the day: $total hour";
    if ($output != 1) {
        $output .= "s";
    }
    $output .= "\n\n";
    foreach ($daily_report['data'] as $row_i => $time_entry) {
        $time = $time_entry['dur'] / 1000 / 60 / 60;
        $output .= "* {$time_entry['client']}: {$time_entry['project']}: {$time_entry['description']}: {$time} hours\n";
    }
    $output .= "\nLook good?  Great!\n\nNeed to make changes?\n\nhttps://www.toggl.com/app/reports/detailed/{$current_ws['id']}/period/today/billable/both";
    $output .= "\n\nThanks, {$current_ws['name']} Bot\n";
    // echo $user['email'] . "\n";
    // echo $output . "\n";
    $headers = "From: {$current_ws['name']} <{$config['team_email']}>";
    mail($user['email'], "[{$current_ws['name']}] Daily Hours Report for {$today}", $output, $headers);
}
