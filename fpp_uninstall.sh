#!/usr/bin/php
<?php
// common.php pulls in config.php, which echoes the web UI's JS header; swallow it.
ob_start();
include_once '/opt/fpp/www/common.php';
ob_end_clean();

// Remove the PROJECTOR-* scripts this plugin generated (see createScripts() in functions.inc.php).
// glob() returns an empty array if the directory is missing, so this can't abort the uninstall.
foreach (glob($scriptDirectory . "/PROJECTOR-*") ?: array() as $file) {
	@unlink($file);
}

WriteSettingToFile('restartFlag', 1);

?>
