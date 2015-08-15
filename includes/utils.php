<?php

function getWorkspaceByName($name, $toggl_client) {
    try {
        $workspaces = $toggl_client->getWorkspaces(array());
    } catch (Exception $e) {
        die("ERROR: Communication with Toggl failed. Dying.\n");
    }

    $current_ws = null;
    foreach ($workspaces as $workspace) {
        if ($workspace['name'] == $name) {
            $current_ws = $workspace;
            break;
        }
    }
    return $current_ws;
}

?>
