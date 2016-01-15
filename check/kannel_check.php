<?php
$end = function($res) {
    header('Content-Type: application/json');
    print json_encode($res);
    end();
};
$res = new stdClass();
$res->numOfProcesses = 0;
$res->bearer = false;
$res->smsbox = false;

$output = null; $return = null;
exec("/usr/bin/pgrep bearerbox", $output, $return);

if ($return == 0) {
    $res->numOfProcesses++;
    $res->bearer = true;
    $res->bearerPid = $output;
}
$output = null; $return = null;
exec("/usr/bin/pgrep smsbox", $output, $return);

if ($return == 0) {
    $res->numOfProcesses++;
    $res->smsbox = true;
    $res->smsboxPid = $output;
}

$end($res);
