#!/bin/bash
set -e
pushd $(dirname $(which $0))
. /opt/fpp/scripts/common
/bin/chmod g+w /dev/tty*
echo ; echo "The plugin is checking for the required library." ; echo
echo ; echo "This can take a couple minutes." ; echo

PERL_MM_USE_DEFAULT=1 cpan install Net::PJLink

# A projector may already be saved (an update, a reinstall, or settings restored
# from a backup).  The settings page then shows it as already chosen, picking it
# again fires no change, and nothing would ever build its command scripts, so
# build them here.  Run as the fpp user, not root: the scripts and the plugin's
# commands/descriptions.json have to stay writable by the web page.
if [ "$(id -u)" = "0" ]; then
    runuser -u "${FPPUSER}" -- /usr/bin/php "$(pwd)/ensure_scripts.php" || true
else
    /usr/bin/php "$(pwd)/ensure_scripts.php" || true
fi
setSetting restartFlag 1
popd
