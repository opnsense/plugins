#!/bin/sh

set -e

DIR=${1}

if [ -z "${DIR}" ]; then
	DIR=.
fi

REV=$(make -C ${DIR} -v PLUGIN_REVISION)
REV=$(expr ${REV} \+ 1)

grep -v ^PLUGIN_REVISION ${DIR}/Makefile > ${DIR}/Makefile.tmp
sed -e "s/^\(PLUGIN_VERSION.*\)/\1%PLUGIN_REVISION=	${REV}/g" \
    ${DIR}/Makefile.tmp | tr '%' '\n' > ${DIR}/Makefile
rm -f ${DIR}/Makefile.tmp
