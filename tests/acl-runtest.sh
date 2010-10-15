#!/bin/sh
#############################################################
#
# Main script for unit tests
#
# Usage:
#
# ./runtest.sh --exclude-group nonreusable,restore,logbook
# ./runtest.sh --group test-test
# ./runtest.sh --filter testJobFindByVolumeName
# phpunit --group test1 --stop-on-failure AllTests.php
#
#############################################################

SRC_DIR=".."
F_INDEX_PHP="${SRC_DIR}/html/index.php"
F_README="${SRC_DIR}/README"
F_SPEC="${SRC_DIR}/packaging/Fedora/webacula.spec"
LINE1="*********************************************************************************************"

VERSION=`grep -e "^.*define('WEBACULA_VERSION.*$" ${F_INDEX_PHP} | awk -F "'" '{print($4)}'`
VER_README=`grep -e "^Version:" ${F_README} | awk '{print($2)}'`
VER_SPEC=`grep -e "^Version:" ${F_SPEC} | awk '{print($2)}'`

if [ ${VERSION} == ${VER_SPEC} ] && [ ${VERSION} == ${VER_README} ]
   then
      echo -e "\nOK. Versions correct."
   else
		echo -e "\nVersions not match. Correct this (file/version) :\n"
		echo -e "$F_INDEX_PHP\t${VERSION}"
		echo -e "${F_SPEC}\t${VER_SPEC}"
		echo -e "${F_README}\t${VER_README}"
		echo -e "\n"
		exit 10
fi

diff -q ../application/config.ini  ../application/config.ini.original
if [ $? == 0 ]
   then
      echo "OK. config.ini"
   else
      echo -e "\nMake application/config.ini and application/config.ini.original to be identical\n\n"
      exit 11
fi

sudo rm -f /tmp/webacula_restore_*

cd prepare_tests
sudo ./clean_all.sh
sudo ./prepare.sh
if test $? -ne 0; then
    exit
fi
cd ..

# Main tests
echo -e "\n\n${LINE1}"
echo "Main tests"
echo -e "${LINE1}\n"
# phpunit $* --configuration phpunit_report.xml --colors --stop-on-failure AllTests.php
cp -f conf/config.ini.mysql  ../application/config.ini
phpunit --colors --stop-on-failure AllTests.php
ret=$?
if [ $ret -ne 0 ]
then
    exit $ret
fi

echo -e "\n\n"
sh ./locale-test.sh

sudo service postgresql stop

sudo rm -f /tmp/webacula_restore_*

# restore original conf
cp -f ../application/config.ini.original  ../application/config.ini
