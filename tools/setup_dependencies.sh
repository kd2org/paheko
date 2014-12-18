#!/bin/sh

# Mise en place/à jour des dépendances

SCRIPTPATH="$( cd "$(dirname "$0")" ; pwd -P )"

if [ ! -d "$SCRIPTPATH/../src" ]
then
	echo "$SCRIPTPATH n'est pas le bon chemin, ou répertoire src/ manquant"
	exit 0
fi

KEYWORD="checkout"
SRCPATH="$SCRIPTPATH/../src/include/libs"

# Template Lite
svn ${KEYWORD} https://svn.kd2.org/svn/misc/libs/template_lite/ "$SRCPATH/template_lite/"

# Liste des pays
svn ${KEYWORD} https://svn.kd2.org/svn/misc/libs/i18n/countries/ "$SRCPATH/countries/"
