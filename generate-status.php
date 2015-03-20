<?php

require(dirname(__FILE__) . '/vendor/autoload.php');
require(dirname(__FILE__) . '/vendor/phpoffice/phpexcel/Classes/PHPExcel.php');
require(dirname(__FILE__) . '/vendor/phpoffice/phpexcel/Classes/PHPExcel/Writer/Excel2007.php');
                 
use AJT\Toggl\TogglClient;
use AJT\Toggl\ReportsClient;
use Cocur\Slugify\Slugify;
                         
$options = getopt("s:e:v");
$debug = array_key_exists('v', $options);
if (array_key_exists('e', $options)) {
    $end_date = $options['e'];
} else {
    $tmp= new DateTime();
    $tmp->add(DateInterval::createFromDateString('yesterday'));
    $end_date = $tmp->format('Y-m-d');
}
if (array_key_exists('s', $options)) {
    $start_date = $options['s'];
} else {
    $tmp = new DateTime($end_date);
    $start_date = $tmp->sub(new DateInterval('P6D'))->format('Y-m-d');
}

$config = json_decode(file_get_contents('config.json'), true);
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
    'font' => array('bold' => true),
    'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
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

        // write out a title
        $title_range = array_keys($columns)[0] . '1:' . array_keys($columns)[count($columns)-1] . '1';
        $sheet->mergeCells($title_range);
        $sheet->SetCellValue('A1', "Hours tracked for {$client['name']} by {$config['toggl_workspace']} from {$start_date} to {$end_date}");
        $sheet->getStyle('A1')->applyFromArray($header_style);

        // write out headers
        foreach ($columns as $col_letter => $val) {
            $sheet->SetCellValue("{$col_letter}3", $nice_columns[$val]);
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
        $header_range = array_keys($columns)[0] . '3:' . array_keys($columns)[count($columns)-1] . '3';
        $sheet->getStyle($header_range)->applyFromArray($header_style);

        // write out each time entry
        $last_row = 0;
        foreach ($weekly_report['data'] as $row_i => $time_entry) {
            foreach ($columns as $col_letter => $val) {
                $cell = $col_letter . ($row_i + 4);
                if ($val == 'dur') {
                    // convert milliseconds to hours
                    $value = $time_entry[$val] / 1000 / 60 / 60;
                    $total_hours += $value;
                } else {
                    $value = $time_entry[$val];
                }
                $sheet->SetCellValue($cell, $value);
            }
            $last_row = $row_i;
        }

        // write out total hours at the bottom
        $total_label_cell = array_keys($columns)[count($columns)-2] . ($last_row + 6);
        $sheet->SetCellValue($total_label_cell, "Total Hours");
        $sheet->getStyle($total_label_cell)->applyFromArray(
            array(
                'font' => array('bold' => true),
                'alignment' => array('horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_RIGHT)
            )
        );
        $total_num_cell = array_keys($columns)[count($columns)-1] . ($last_row + 6);
        $sheet->SetCellValue($total_num_cell, $total_hours);

        // save to file
        $writer = new PHPExcel_Writer_Excel2007($report);
        $writer->save("build/{$slugify->slugify($client['name'])}-hours-{$start_date}-to-{$end_date}.xlsx");

        echo "done - $total_hours hours tracked.\n";
    }
}

?>
