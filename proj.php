#!/usr/bin/php
<?php
// pat added 3 lines below for debugging 2/4/2024
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//added Dec 3 2015
ob_implicit_flush();
$skipJSsettings = 1;
//include 'php_serial.class.php';
include 'PhpSerial.php';                         //added as replacement pat 2/4/2024
include_once('projectorCommands.inc');
include_once 'functions.inc.php';
include_once 'commonFunctions.inc.php';
include_once '/opt/fpp/www/config.php';
include_once '/opt/fpp/www/common.php';

$logFile = $settings['logDirectory'] . "/plugin-".$pluginName.".log";
 

//$cfgServer="192.168.192.15";
$cfgPort="3001";
$cfgTimeOut=10;
$DEBUG=false;
$SERIAL_DEVICE="";
$callBackPid="";

$DEVICE = $pluginSettings['DEVICE'];
$DEVICE_CONNECTION_TYPE = $pluginSettings['PROJ_PROTOCOL'];
$IP = $pluginSettings['IP'];
$PORT = $pluginSettings['PORT'];
$ENABLED = urldecode($pluginSettings['ENABLED']);
$PROJECTOR = urldecode($pluginSettings['PROJECTOR']);
$PROJ_PASSWORD = urldecode($pluginSettings['PROJ_PASSWORD']);

logEntry("PROJECTOR: ".$PROJECTOR);

if(trim($PROJECTOR) == "") {
	logEntry("No Projector configured in plugin, exiting");
	exit(0);	
}

if($ENABLED != "ON") {
	logEntry("Plugin is DISABLED, exiting");
	exit(0);
}

$options = getopt("c:");

$SERIAL_DEVICE="/dev/".$DEVICE;


if($options["z"] != "") {
	$callBackPid = $options["z"];
}

logEntry("option C: ".$options["c"]);
$cmd= strtoupper(trim($options["c"]));

//@@@ is this needed? For loop initializes projectorIndex 
$projectorIndex = 0; 
//set the found flag, do not send a command if the name and command cannot be found

$PROJECTOR_FOUND=false;

for($projectorIndex=0;$projectorIndex<=count($PROJECTORS)-1;$projectorIndex++) {	
	if($PROJECTORS[$projectorIndex]['NAME'] == $PROJECTOR) {
		logEntry("proj.php Projector found: ".$PROJECTOR);
		logEntry("Looking for command string for cmd: ".$cmd);
		foreach($PROJECTORS[$projectorIndex] as $key => $val) {			
			if(strtoupper(trim($key)) == $cmd) {
				$PROJECTOR_FOUND=true;
				$PROJECTOR_CMD = $val;
				
				logEntry("--------------");
				logEntry("PROJECTOR FOUND");
				logEntry("PROJECTOR: ".$PROJECTOR);
				
				if($PROJECTORS[$projectorIndex]['PROTOCOL'] == "PJLINK") {
					$DEVICE_CONNECTION_TYPE = "PJLINK";
					logEntry("PJLINK Projector");
					
					if(trim($PROJECTORS[$projectorIndex]['IP']) != "") {
						$IP= $PROJECTORS[$projectorIndex]['IP'];
					}					
					
					if(trim($PROJECTORS[$projectorIndex]['PASSWORD']) != "") {
						$PROJ_PASSWORD = $PROJECTORS[$projectorIndex]['PASSWORD'];
					}

					$PJLINK_CMD =  $settings['pluginDirectory'] . "/" .$pluginName. "/pjlinkutil.pl ";
					$PJLINK_CMD .= $IP." ";
					$PJLINK_CMD .= $PROJECTOR_CMD." ";
					$PJLINK_CMD .= "-p ".$PROJ_PASSWORD;
					
					logEntry("PJLINK CMD: ".$PJLINK_CMD);
					$PROJECTOR_CMD = $PJLINK_CMD;
					
				} elseif ($PROJECTORS[$projectorIndex]['PROTOCOL'] == "TCP") {
					$DEVICE_CONNECTION_TYPE = "IP";
					logEntry("TCP/IP Projector");
					
					if(trim($PROJECTORS[$projectorIndex]['IP']) != "") {
						$IP= $PROJECTORS[$projectorIndex]['IP'];
					}					
					
					if(trim($PROJECTORS[$projectorIndex]['PASSWORD']) != "") {
						$PROJ_PASSWORD = $PROJECTORS[$projectorIndex]['PASSWORD'];
					}
					$PORT= $PROJECTORS[$projectorIndex]['PORT'];
					
					logEntry("TCPIP IP: ".$IP." PORT: ".$PORT." CMD: ".$PROJECTOR_CMD);					
				} else {
					
					if($pluginSettings['BAUD_RATE'] !="")
					{
						$PROJECTOR_BAUD = $pluginSettings['BAUD_RATE'];
					} else {
						$PROJECTOR_BAUD=$PROJECTORS[$projectorIndex]['BAUD_RATE'];
					}

					if($pluginSettings['CHAR_BITS'] !="")
					{
						$PROJECTOR_CHAR_BITS = $pluginSettings['CHAR_BITS'];
					} else {
						$PROJECTOR_CHAR_BITS=$PROJECTORS[$projectorIndex]['CHAR_BITS'];
					}

					if($pluginSettings['STOP_BITS'] !="")
					{
						$PROJECTOR_STOP_BITS = $pluginSettings['STOP_BITS'];
					} else {
						$PROJECTOR_STOP_BITS=$PROJECTORS[$projectorIndex]['STOP_BITS'];
					}

					if($pluginSettings['PARITY'] !="")
					{
						$PROJECTOR_PARITY = $pluginSettings['PARITY'];
					} else {
						$PROJECTOR_PARITY=$PROJECTORS[$projectorIndex]['PARITY'];
					}		
				
					logEntry("BAUD RATE: ".$PROJECTOR_BAUD);
					logEntry("CHAR BITS: ".$PROJECTOR_CHAR_BITS);
					logEntry("STOP BITS: ".$PROJECTOR_STOP_BITS);
					logEntry("PARITY: ".$PROJECTOR_PARITY);							
				}
			}
		}	
	}
}

if(!$PROJECTOR_FOUND) {
	logEntry("projector command not found: exiting");
	exit(0);
}

logEntry("-------");
$PCMD = hex_dump($PROJECTOR_CMD, $newline="\n");
logEntry("Sending command");
logEntry("HEX DECODED COMMAND: ".$PCMD);

switch ($DEVICE_CONNECTION_TYPE) {
	
	case "PJLINK":
		logEntry("Sending PJLINK Command");
		logEntry("PJLINK CMD: ".$PROJECTOR_CMD);
		
		system($PROJECTOR_CMD,$PJLINK_RESULT);
		logEntry("PJLINK RESULT: ".$PJLINK_RESULT[0]);
		
		exit(0);
		
		break;
		
	case "SERIAL":
	logEntry("Sending SERIAL COMMAND");
	logEntry("SERIAL DEVICE: ".$SERIAL_DEVICE);

	if (!preg_match('/^tty[ASU][A-Z0-9]+$/', $DEVICE)) {
		logEntry("Invalid serial device configured: ".$DEVICE.", exiting");
		exit(1);
	}

        $serial = new phpSerial;

        $serial->deviceSet($SERIAL_DEVICE);
        $serial->confBaudRate($PROJECTOR_BAUD);
        $serial->confParity($PROJECTOR_PARITY);
        $serial->confCharacterLength($PROJECTOR_CHAR_BITS);
        $serial->confStopBits($PROJECTOR_STOP_BITS);
        $serial->deviceOpen();
	
	$serial->sendMessage("$PROJECTOR_CMD");
	sleep(1);
	logEntry("RETURN DATA: ".hex_dump($serial->readPort()));
	
	
	$serial->deviceClose();
	exit(0);
	
	break;
	
	case "IP":       
	logEntry("SENDING IP COMMAND");
       
		sendTCP($IP, $PORT, $PROJECTOR_CMD);
	exit(0);
	break;
	
}
?>