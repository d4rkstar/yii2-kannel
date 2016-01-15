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
$res->socket = false;

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

$output = null; $return = null;
exec("/usr/sbin/iptstate -1 -d 10.97.1.160 | grep ESTABLISHED");
if ($return == 0) {
    $res->socket = true;
    $res->iptstate = $output;
}
$end($res);
