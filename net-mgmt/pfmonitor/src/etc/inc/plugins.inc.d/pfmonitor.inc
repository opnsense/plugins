<?php

function pfmonitor_cron()
{
    $jobs = array();

    $jobs[]['autocron'] = array('/usr/local/opnsense/scripts/PFMonitor/checkin.php', '*');

    return $jobs;
}
