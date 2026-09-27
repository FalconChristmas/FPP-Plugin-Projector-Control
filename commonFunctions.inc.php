<?php

$myPid = getmypid();
$gitURL = "https://github.com/FalconChristmas/FPP-Plugin-Projector-Control.git";
$pluginName = basename(dirname(__FILE__));  //pjd 7-10-2019   added per dkulp
$logFile = $settings['logDirectory']."/plugin-".$pluginName.".log";
$pluginUpdateFile = $settings['pluginDirectory']."/".$pluginName."/"."pluginUpdate.inc";


?>
