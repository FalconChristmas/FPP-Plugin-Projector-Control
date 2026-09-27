<?php

require_once("projectorCommands.inc");
require_once("commonFunctions.inc.php");
include_once '/opt/fpp/www/common.php';


if (!isset($pluginSettings) || empty($pluginSettings)) {
	$pluginSettings = array();
    $pluginSettingInfos = array();

    LoadPluginSettings("FPP-Plugin-Projector-Control");
}

$pluginName = basename(dirname(__FILE__)); //delete? defined in commonFunctions 
$DEVICE = $pluginSettings['DEVICE'];
$BAUD_RATE = $pluginSettings['BAUD_RATE'];
$CHAR_BITS = $pluginSettings['CHAR_BITS'];
$STOP_BITS = $pluginSettings['STOP_BITS'];
$PARITY = $pluginSettings['PARITY'];	
$IP = $pluginSettings['IP'];
$PORT = $pluginSettings['PORT'];
$ENABLED = urldecode($pluginSettings['ENABLED']);
$PROJECTOR = urldecode($pluginSettings['PROJECTOR']);
$PROJ_PASSWORD = urldecode($pluginSettings['PROJ_PASSWORD']);
$PROJ_PROTOCOL = urldecode($pluginSettings['PROJ_PROTOCOL']);
$PROJECTOR_READ = $PROJECTOR;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] == 'create_scripts') {
   create_scripts();
}
function sendTCP($IP, $PORT, $cmd) {
	if($PORT == "23") {
		logEntry("We have a TELNET port");
		$fp=pfsockopen($IP,23);
		logEntry("Telnet session opening ...");
		sleep(4);
		$cmd .= "\r";
		fputs($fp,$cmd);
		sleep(2); 
		fclose($fp);
		return;
	}
	/* Create a TCP/IP socket. */
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if ($socket === false) {
    	logEntry("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
	} else {
   		logEntry("TCPIP Socket Created");
	}
	$result = socket_connect($socket, $IP, $PORT);
	if ($result === false) {
		logEntry("socket_connect() failed. Reason: ($result) " . socket_strerror(socket_last_error($socket)));
	} else {
		logEntry("TCPIP CONNECTED");
	}
	socket_write($socket, $cmd, strlen($cmd));
	logEntry("Reading response");
	while ($out = socket_read($socket, 2048)) {
    	logEntry($out);
	}
	logEntry("Closing socket...");
	socket_close($socket);
	logEntry("OK");
}

function get_serialDevices() {
	$SERIAL_DEVICES=array();
	logEntry("In get_serialDevices");
	foreach(scandir("/dev/") as $fileName){
        if (preg_match("/tty[ASU][A-Z0-9]+/", $fileName)){
			$SERIAL_DEVICES[$fileName] = $fileName;
			logEntry("serialDevices " .$fileName);
		}				
	}
	//logEntry("serialDevices array " .var_dump ($SERIAL_DEVICES));
	return $SERIAL_DEVICES;
}

function hex_dump($data, $newline="\n") {
	static $from = '';
	static $to = '';
	static $width = 16; # number of bytes per line
	static $pad = '.'; # padding for non-visible characters
	if ($from===''){
		for ($i=0; $i<=0xFF; $i++){
			$from .= chr($i);
			$to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
		}
	}
	$hex = str_split(bin2hex($data), $width*2);
	$chars = str_split(strtr($data, $from, $to), $width);
	$HEX_OUT ="";
	$offset = 0;
	foreach ($hex as $i => $line)
	{
		$HEX_OUT.= sprintf('%6X',$offset).' : '.implode(' ', str_split($line,2)) . ' [' . $chars[$i] . ']';
		$offset += $width;
	}
	return $HEX_OUT;
}

function decode_code($code) {
    return preg_replace_callback('@\\\(x)?([0-9a-f]{2,3})@',
        function ($m) {
            if ($m[1]) {
                $hex = substr($m[2], 0, 2);
                $unhex = chr(hexdec($hex));
		echo "UNHEX: ".$unhex;
                if (strlen($m[2]) > 2) {
                    $unhex .= substr($m[2], 2);
                }
                return $unhex;
            } else {
                return chr(octdec($m[2]));
            }
        }, $code);
}

function getProjectors(){
	global $PROJECTORS;
	foreach ($PROJECTORS as $projector) {
		$PROJECTOR_LIST[$projector['NAME']] = $projector['NAME'];			
	}
	ksort($PROJECTOR_LIST);	
	return $PROJECTOR_LIST;
}

function create_scripts() {
	logEntry("in create scripts");
	
	//delete the old files and create new ones based on the projector chosen.
	global $PROJECTORS,$PROJECTOR_READ,$pluginDirectory,$pluginName,$scriptDirectory,$IP,
	$DEVICE,$BAUD_RATE,$CHAR_BITS,$STOP_BITS,$PARITY,$PROJ_PASSWORD,$PORT,$PROJ_PROTOCOL;

	// delete files in the script directory
	$delDir = $scriptDirectory."/";
    $pattern = 'PROJECTOR-*';	
    $files = glob($delDir . $pattern);
    foreach ($files as $file) {
        if (unlink($file)) {
            //echo "Deleted: $file<br>";
        } else {
            //echo "Error deleting: $file<br>";
        }
    }
	
	// delete the files in the $pluginName/commands directory
	
	$delDir = $pluginDirectory."/".$pluginName."/"."commands/";
	logEntry("@@@@ delete directory ".$delDir);
    $files = glob($delDir . $pattern);
	
    foreach ($files as $file) {
		logEntry("@@@@ delete file ".$file);
        if (unlink($file)) {
            logEntry("@@@@ file deleted ".$file);
        } else {
            logEntry("@@@@ file not deleted ".$file);
        }
    }

	
	$PROJ_PROTOCOL = "SERIAL"; // Default value
	$index = -1; // Default index, indicating not found
	
foreach ($PROJECTORS as $key => $projector) {
    if ($projector["NAME"] === $PROJECTOR_READ) {

        $PROJ_PROTOCOL = isset($projector["PROTOCOL"]) ? $projector["PROTOCOL"] : "SERIAL";		
		WriteSettingToFile("PROJ_PROTOCOL", $PROJ_PROTOCOL, $pluginName);

		if ($PROJ_PROTOCOL=="PJLINK" || $PROJ_PROTOCOL=="TCP"){
			$DEVICE_CONNECTION_TYPE="IP";
		}else{
			$DEVICE_CONNECTION_TYPE="SERIAL";
		}

		$BAUD_RATE=isset($projector["BAUD_RATE"]) ? $projector["BAUD_RATE"] : "";
		WriteSettingToFile("BAUD_RATE", $BAUD_RATE, $pluginName);

		$CHAR_BITS=isset($projector["CHAR_BITS"]) ? $projector["CHAR_BITS"] : "";
		WriteSettingToFile("CHAR_BITS", $CHAR_BITS, $pluginName);

		$STOP_BITS=isset($projector["STOP_BITS"]) ? $projector["STOP_BITS"] : "";
		WriteSettingToFile("STOP_BITS", $STOP_BITS, $pluginName);

		$PARITY=isset($projector["PARITY"]) ? $projector["PARITY"] : "";
		WriteSettingToFile("STOP_BITS", $PARITY, $pluginName);

		$PROJ_PASSWORD=isset($projector["PROJ_PASSWORD"]) ? $projector["PROJ_PASSWORD"] : "";
		WriteSettingToFile("PROJ_PASSWORD", $PROJ_PASSWORD, $pluginName);

		$PORT=isset($projector["PORT"]) ? $projector["PORT"] : "";
		WriteSettingToFile("PORT", $PORT, $pluginName);

		$index = $key; // Save the index
		foreach($PROJECTORS[$index] as $key => $value){
			if($key != "NAME" && $key != "BAUD_RATE" && $key != "CHAR_BITS" && $key != "PARITY" && $key != "STOP_BITS" && $key != "VALID_STATUS_0" && $key != "VALID_STATUS_1" && $key != "VALID_STATUS_2"){
				$scriptCMD = $pluginDirectory."/".$pluginName."/"."proj.php -d".$DEVICE_CONNECTION_TYPE." -s".$DEVICE." -c".$key; 
				$scriptFilename = $scriptDirectory."/PROJECTOR-".$key.".sh";
				$description= array(
					"name" => "Projector Control-Projector " . $key,
        			"script" => "PROJECTOR-" . $key . ".sh",
        			"args" => array()
    			);
				$descriptions[] = $description;
				$ext = pathinfo($scriptFilename, PATHINFO_EXTENSION);
				$data = "";
				$data .="#!/bin/sh\n";	
				$data .= "\n";
				$data .= "#Created by ".$pluginName."\n";
				$data .= "#\n";
				$data .= "/usr/bin/php ".$scriptCMD."\n";
				//save to FPP scripts directory
				$fs = fopen($scriptFilename,"w");
				fputs($fs, $data);
				fclose($fs);
				chmod($scriptFilename, 0755);
				//save scripts to commands $pluginName/commands directory
				$saveDir = $pluginDirectory."/".$pluginName."/"."commands/";
				$fileName = "PROJECTOR-".$key.".sh";
				$filePath = $saveDir . $fileName;
				file_put_contents($filePath, $data);
				chmod($filePath,0755);

			}	
         
    	}
		//save $descriptions.json file
		$json = json_encode($descriptions, JSON_PRETTY_PRINT);
		$saveDir = $pluginDirectory."/".$pluginName."/"."commands/";
		$fileName = "descriptions.json";
		$filePath = $saveDir . $fileName;
		file_put_contents($filePath, $json);		
		break; // Stop searching once found	}
	}
}
	$result = Array("status" => "OK");
	echo json_encode($result);
	return;
}

Function getBaudRates(){
	return $Baud= array("9600"=>"9600","19200"=>"19200","38400"=>"38400","115200"=>"115200");
}

function getCharBits(){
	return $Char= array("5"=>"5","6"=>"6","7"=>"7","8"=>"8");	
}

function logEntry($data) {
	global $logFile,$myPid,$callBackPid;
	
	if($callBackPid != "") {
		$data = $_SERVER['PHP_SELF']." : [".$callBackPid.":".$myPid."] ".$data;
	} else { 	
		$data = $_SERVER['PHP_SELF']." : [".$myPid."] ".$data;
	}
	$logWrite= fopen($logFile, "a") or die("Unable to open file!");
	fwrite($logWrite, date('Y-m-d h:i:s A',time()).": ".$data."\n");
	fclose($logWrite);
}

function escapeshellarg_special($file) {
	return "'" . str_replace("'", "'\"'\"'", $file) . "'";
}

//function send the message
function sendCommand($projectorCommand) {
	global $pluginName,$myPid,$pluginDirectory,$DEVICE,$DEVICE_CONNECTION_TYPE,$IP,$PORT;
	
	//# Send line to  Projector
	$cmd = $pluginDirectory."/".$pluginName."/proj.php ";
	$cmd .= "-d".$DEVICE_CONNECTION_TYPE;
	switch ($DEVICE_CONNECTION_TYPE) {
		case "SERIAL":
			$SERIALCMD = " -s".$DEVICE." -c".$projectorCommand;
			$cmd .= $SERIALCMD;				
			break;				
		case "IP":
			$IPCMD = " -h".$IP. " -c".$projectorCommand;
			$cmd .= $IPCMD;
			break;				
	}

	$cmd .= " -z".$myPid;
	
	logEntry("COMMAND: ".$cmd);
	system($cmd,$output);	
}

function processSequenceName($sequenceName) {	
	global $projectorONSequence, $projectorOFFSequence,$projectorVIDEOSequence;
	
	logEntry("Sequence name: ".$sequenceName);

	$sequenceName = strtoupper($sequenceName);

	switch ($sequenceName) {

		case "PROJ-ON.FSEQ":

			logEntry("Projector On");
			sendCommand("ON");
			break;
			exit(0);
			
		case "PROJ-OFF.FSEQ":
		
			logEntry("Projector OFF");
			sendCommand("OFF");
			break;
			exit(0);
			
		case "PROJ-VIDEO-INPUT.FSEQ":
		
			logEntry("Projector Video Input Select");
			sendCommand("VIDEO");
			break;
			exit(0);
				
		default:
			logEntry("We do not support sequence name: ".$sequenceName." at this time");				
			exit(0);				
	}
}

function processCallback($argv) {

	global $DEBUG,$pluginName;	
	
	if($DEBUG)
		print_r($argv);
	//argv0 = program
	
	//argv2 should equal our registration // need to process all the rgistrations we may have, array??
	//argv3 should be --data
	//argv4 should be json data
	
	$registrationType = $argv[2];
	$data =  $argv[4];
	
	logEntry("PROCESSING CALLBACK");
	$clearMessage=FALSE;
	
	switch ($registrationType)
	{
		case "media":
			if($argv[3] == "--data")
			{
				$data=trim($data);
				logEntry("DATA: ".$data);
				$obj = json_decode($data);
	
				$type = $obj->{'type'};
	
				switch ($type) {
						
					case "sequence":
	
						//$sequenceName = ;
						processSequenceName($obj->{'Sequence'});
							
						break;
					case "media":
							
						logEntry("We do not understand type media at this time");
							
						exit(0);
	
						break;
	
					default:
						logEntry("We do not understand: type: ".$obj->{'type'}. " at this time");
						exit(0);
						break;
	
				}
	
	
			}
	
			break;
			exit(0);
				
		default:
			exit(0);	
	}
}

?>