#!/bin/bash

CWD=$(readlink -f "$0")
PWD=$(dirname "$CWD")

MAGENTOROOT=$(readlink -f "${PWD}/../../")
#Set magento root if "automatic" fail
#MAGENTOROOT=/home/carco/htdocs/magento/


#You can define here connection data (if you don't want to read from local.xml)
declare -A CONN
#CONN[host]="127.0.01"
#CONN[dbname]="magento"
#CONN[username]="username"
#CONN[password]="password"
#CONN[table_prefix]=""

LOCALXMLPATH="${MAGENTOROOT}/app/etc/local.xml"
BACKUPPATH="${MAGENTOROOT}/var/backups"
mkdir -p "$BACKUPPATH"

SKIPLOGS=1

DATETIME=`date -u +"%Y%m%d%H%M"`
DBFILENAME="$DATETIME.sql.gz"

#Add here ignored tables
IGNOREDTABLES="core_cache
core_cache_option
core_cache_tag
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
        CONN[$PARAM]=$(sed -n "/<connection>/,/<\/connection>/p" $LOCALXMLPATH | sed -n -e "s/.*<$PARAM><!\[CDATA\[\(.*\)\]\]><\/$PARAM>.*/\1/p" | head -n 1)
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
        IGNOREPARAMS="$IGNOREPARAMS --ignore-table='${CONN[dbname]}'.'${CONN[table_prefix]}$TABLENAME'"
    done
fi

if [ -z "$IGNOREPARAMS" ]; then
    CODEDUMPCMD="mysqldump $CONNECTIONPARAMS"
else
    #FIRST, EXPORT SCHEMA, THEN DATA
    CODEDUMPCMD="(mysqldump --no-data $CONNECTIONPARAMS; mysqldump --no-create-info $CONNECTIONPARAMS $IGNOREPARAMS )"
fi


CODEDUMPCMD="$CODEDUMPCMD | gzip > $BACKUPPATH/$DBFILENAME"
#echo $CODEDUMPCMD
eval "$CODEDUMPCMD"
