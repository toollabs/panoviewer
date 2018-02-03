#!/bin/bash
cd `dirname $0`

DIR=$1
MD5=$2
FILE=$3

# fetch the full res file
TMP=${DIR}${MD5}.full.jpg
MD51=`echo $MD5 | cut -c1`
MD52=`echo $MD5 | cut -c1-2`
wget -O "$TMP" "https://upload.wikimedia.org/wikipedia/commons/${MD51}/${MD52}/${FILE}"

# generate tiled cube faces
MULTI=${DIR}${MD5}
rm -rf $MULTI
./generate.py -o "$MULTI" "$TMP"
