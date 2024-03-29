#!/bin/bash

TAG="${1-current}"
ROOT="$3"
ROOT_LENGTH="${#ROOT}"

if [ "$2" != "" ]
then
	REPO="-R '${2}'"
else
	REPO=""
fi

TMPMANIFEST=$(mktemp)

if [[ "$2" =~ ^https?\:\/\/ ]]
then
	if [[ $(curl -L $2/raw/${TAG} -o ${TMPMANIFEST} -w '%{http_code}\n' -s) != "200" ]]
	then
		echo "Cannot download from $2"
		exit 1
	fi
else
	fossil artifact ${REPO} "${TAG}" > ${TMPMANIFEST}
fi

gpg --verify ${TMPMANIFEST} 2> /dev/null

if [ $? != 0 ]
then
	echo "Manifest signature failed to verify"
	exit 2
fi

TMPFILE=$(mktemp)

while IFS= read -r LINE
do
	if [ "${LINE:0:2}" != "F " ]
	then
		echo "$LINE" >> $TMPFILE
		continue
	fi

	# Split string by spaces
	PARTS=($LINE)

	FILE_ENCODED="${PARTS[1]}"
	FILE="${PARTS[1]//\\s/ }"
	HASH="${PARTS[2]}"

	if [ ${ROOT_LENGTH} -gt 0 ]
	then
		if [[ ${FILE:0:$ROOT_LENGTH} != ${ROOT} ]]
		then
			# Ignoring files out of the path
			echo "$LINE" >> $TMPFILE
			continue
		else
			FILE=${FILE:$ROOT_LENGTH+1}
		fi
	fi

	if [ ! -f "$FILE" ]
	then
		echo "! File is missing: $FILE"
		continue
	fi

	if [ -L "$FILE" ]
	then
		echo " . Skipping symlink $FILE"
		echo "$LINE" >> $TMPFILE
		continue
	fi

	if [ "${#HASH}" = 40 ]
	then
		NEW_HASH=$(sha1sum "$FILE" | awk '{print $1}')
	else
		NEW_HASH=$(openssl dgst -sha3-256 -binary "$FILE" | xxd -p -c 100)
	fi

	if [ "$HASH" != "$NEW_HASH" ]
	then
		echo " ! Local file has changed: $FILE"
		echo "   Manifest hash:   $HASH"
		echo "   Local file hash: $NEW_HASH"
		continue
	fi

	PARTS[2]="$HASH"

	# join parts in a new string
	NEW_LINE="$(printf " %s" "${PARTS[@]}")"
	NEW_LINE="${NEW_LINE:1}"

	echo "$NEW_LINE" >> $TMPFILE
done < ${TMPMANIFEST}

gpg --verify $TMPFILE 2>/dev/null

if [ $? != 0 ]
then
	echo "Something has changed between manifest and check?!"
	diff ${TMPMANIFEST} ${TMPFILE}
	rm -f $TMPFILE
	exit 2
fi

rm -f $TMPFILE
exit 0