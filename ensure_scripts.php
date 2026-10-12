<?php
// Run by fpp_install.sh, which FPP runs for an install, a plugin update and a
// reinstall.  Builds the command scripts for the projector already saved in the
// plugin's settings, if there is one and they are missing; does nothing otherwise.

// common.php pulls in config.php, which echoes the web UI's JS header; swallow it.
// FPP's common.php goes first: the plugin's own files read its $settings (for the
// log file's path, among other things), and inside a web request plugin.php has
// already loaded it, but here nothing has.
ob_start();
// FPP's config.php expects to be serving a page
if (!isset($_SERVER['REQUEST_URI'])) {
	$_SERVER['REQUEST_URI'] = '/cli/projector_ensure_scripts';
}
if (!isset($_SERVER['SCRIPT_NAME'])) {
	$_SERVER['SCRIPT_NAME'] = '/cli/projector_ensure_scripts';
}
include_once '/opt/fpp/www/common.php';
include_once __DIR__ . '/functions.inc.php';
ob_end_clean();

if (ensure_scripts()) {
	echo "Projector Control: created the command scripts for " . $PROJECTOR_READ . "\n";
} else {
	echo "Projector Control: no command scripts to create\n";
}
