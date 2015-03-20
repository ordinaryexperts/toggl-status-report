<?php

require(dirname(__FILE__) . '/vendor/autoload.php');
require(dirname(__FILE__) . '/vendor/phpoffice/phpexcel/Classes/PHPExcel.php');
require(dirname(__FILE__) . '/vendor/phpoffice/phpexcel/Classes/PHPExcel/Writer/Excel2007.php');
                 
use AJT\Toggl\TogglClient;
use AJT\Toggl\ReportsClient;
use Cocur\Slugify\Slugify;
                         
$options = getopt("s:e:v");
echo print_r($options, true);
$debug = false;
if (array_key_exists('v', $options)) {
    $debug = true;
}

if (array_key_exists('s', $options)) {
    $start_date = $options['s'];
} else {
    $start_date = date("Y-m-d");
}
if (array_key_exists('e', $options)) {
    $end_date = $options['e'];
} else {
    $tmp = new DateTime($start_date);
    $end_date = $tmp->add(new DateInterval('P6D'))->format('Y-m-d');
}

$config  = json_decode(file_get_contents('config.json'), true);
$slugify = new Slugify();

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

$header_style = array(
	'font' => array(
		'bold' => true,
	),
	'alignment' => array(
		'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
	),
);

$clients = $toggl_client->getClients(array());
$columns = array(
    'A' => 'user',
    'B' => 'project',
    'C' => 'description',
    'D' => 'start',
    'E' => 'end',
    'F' => 'dur'
);
$nice_columns = array(
    'user'        => 'Resource',
    'project'     => 'Project',
    'description' => 'Description',
    'start'       => 'Start Time',
    'end'         => 'End Time',
    'dur'         => 'Duration (hours)'
);

// clean up old reports
exec('rm -rf build');
mkdir('build');

// generate report for each client
foreach ($clients as $client) {
    if ($client['wid'] == $current_ws['id']) {
        echo "Generating report for client '{$client['name']}'...";

        $total_hours = 0;
        $weekly_report = $reports_client->details(
            array(
                'user_agent'   => $config['recipient_email'],
                'workspace_id' => $current_ws['id'],
                'client_ids'   => "{$client['id']}",
                'order_desc'   => 'off',
                'since'        => $start_date,
                'until'        => $end_date
            )
        );

        $report = new PHPExcel();
        $report->setActiveSheetIndex(0);
        $sheet = $report->getActiveSheet();

        // write out headers and do some basic formatting
        foreach ($columns as $col_letter => $val) {
            $sheet->SetCellValue("{$col_letter}1", $nice_columns[$val]);
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
        $header_range = array_keys($columns)[0] . '1:' . array_keys($columns)[count($columns)-1] . '1';
        $sheet->getStyle($header_range)->applyFromArray($header_style);

        // write out each time entry
        foreach ($weekly_report['data'] as $row_i => $time_entry) {
            foreach ($columns as $col_letter => $val) {
                $cell = $col_letter . ($row_i + 2);
                if ($val == 'dur') {
                    // convert milliseconds to hours
                    $value = $time_entry[$val] / 1000 / 60 / 60;
                    $total_hours += $value;
                } else {
                    $value = $time_entry[$val];
                }
                $sheet->SetCellValue($cell, $value);
            }
        }

        // save to file
        $writer = new PHPExcel_Writer_Excel2007($report);
        $writer->save("build/{$slugify->slugify($client['name'])}-hours-{$start_date}-to-{$end_date}.xlsx");
        echo "done - $total_hours hours tracked.\n";
    }
}

?>
