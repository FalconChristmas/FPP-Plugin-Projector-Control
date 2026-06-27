#!/bin/bash
pushd $(dirname $(which $0))
. /opt/fpp/scripts/common
/usr/bin/sudo /bin/chmod a+w /dev/tty*
echo ; echo "The plugin is checking for the required library." ; echo
echo ; echo "This can take a couple minutes." ; echo

PERL_MM_USE_DEFAULT=1 cpan install Net::PJLink
setSetting restartFlag 1
popd
