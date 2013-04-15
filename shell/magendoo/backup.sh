#!/bin/bash

CWD=$(readlink -f "$0")
PWD=$(dirname "$CWD")

die () { [ -n "$2" ] && echo "$2" >&2; exit $1; }

MAGENTOROOT=$(readlink -f "${PWD}/../../")
#Set magento root if "automatic" fail
#MAGENTOROOT=/home/carco/htdocs/magento/
[ -d "${MAGENTOROOT}" ] || die 100 "Magento root ${MAGENTOROOT}, set manual."

#You can define here connection data (if you don't want to read from local.xml)
declare -A CONN
#CONN[host]="127.0.01"
#CONN[dbname]="magento"
#CONN[username]="username"
#CONN[password]="password"
#CONN[table_prefix]=""



LOCALXMLPATH="${MAGENTOROOT}/app/etc/local.xml"
BACKUPPATH="${MAGENTOROOT}/var/backup"
mkdir -p "$BACKUPPATH" || die 125 "Cannot create backup directory, $BACKUPPATH"

[ -f "${LOCALXMLPATH}" ] || die 150 "local.xml not found, $LOCALXMLPATH"
[ -d "${BACKUPPATH}" ]   || die 175 "backup directory not found, $BACKUPPATH"


DATETIME=`date -u +"%Y%m%d%H%M"`
DBFILENAME="$DATETIME.sql.gz"

SKIPLOGS=1

#Add here ignored tables
IGNOREDTABLES="
log_customer
log_quote
log_summary
log_summary_type
log_url log_url_info
log_visitor
log_visitor_info
log_visitor_online
enterprise_logging_event
enterprise_logging_event_changes
index_event
index_process_event
report_event
report_viewed_product_index
dataflow_batch_export
dataflow_batch_import"


PARAMS="host username dbname password table_prefix"
for PARAM in $PARAMS; do
    if [ -z "${CONN[$PARAM]}" ]; then
        CONN[$PARAM]=$(sed -n "/<connection>/,/<\/connection>/p" ${LOCALXMLPATH} | sed -n -e "s/.*<${PARAM}><!\[CDATA\[\(.*\)\]\]><\/${PARAM}>.*/\1/p" | head -n 1)
    fi;
done


if [ -z "${CONN[host]}" -o -z "${CONN[username]}" -o -z "${CONN[dbname]}" -o -z "${CONN[password]}" ]; then
    echo "Skip DB dumping due lack of parameters host=${CONN[host]}, username=${CONN[username]}, dbname=${CONN[dbname]}"
    exit 100
fi

CONNECTIONPARAMS=" -u'${CONN[username]}' -h'${CONN[host]}' -p'${CONN[password]}' '${CONN[dbname]}' --single-transaction --opt --skip-lock-tables"


# Create DB dump
IGNOREPARAMS=
if [ -n "$SKIPLOGS" ] ; then
    for TABLENAME in $IGNOREDTABLES; do
        IGNOREPARAMS="${IGNOREPARAMS} --ignore-table='${CONN[dbname]}'.'${CONN[table_prefix]}${TABLENAME}'"
    done
fi

if [ -z "$IGNOREPARAMS" ]; then
    CODEDUMPCMD="mysqldump $CONNECTIONPARAMS"
else
    #FIRST, EXPORT SCHEMA, THEN DATA
    CODEDUMPCMD="(mysqldump --no-data ${CONNECTIONPARAMS}; mysqldump --no-create-info ${CONNECTIONPARAMS} ${IGNOREPARAMS} )"
fi


CODEDUMPCMD="${CODEDUMPCMD} | gzip > ${BACKUPPATH}/${DBFILENAME}"
#echo $CODEDUMPCMD
echo -n "Dump ${CONN[dbname]} database to ${BACKUPPATH}/${DBFILENAME}... "
eval "$CODEDUMPCMD" && echo "DONE" || die 254 "FAIL"


