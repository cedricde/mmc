#!/bin/bash

# This script maps PO and POT files to transifex ressources
# on https://transifex.mandriva.com

if [[ ! -d core && ! -d mds && ! -d pulse2 ]]; then
	echo "Run this script at the top dir of the repository"
	exit 1
fi

which tx > /dev/null
if [ $? -ne 0 ]; then
	echo "Install the transifex client v0.4 (pip install transifex-client==0.4)"
	exit 1
fi

test -d .tx || tx init --host=https://transifex.mandriva.com

args=$@

modules="bulkimport mail network proxy samba sshlpk userquota shorewall squid radius"

for mod in $modules
do
	tx push -r mds.${mod} -s
done
