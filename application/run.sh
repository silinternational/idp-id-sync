#!/usr/bin/env bash

if [[ "x" == "x$LOGENTRIES_KEY" ]]; then
    echo "Missing LOGENTRIES_KEY environment variable";
else
    # Set logentries key based on environment variable
    sed -i /etc/rsyslog.conf -e "s/LOGENTRIESKEY/${LOGENTRIES_KEY}/"
    # Start syslog
    rsyslogd
fi

# Configure (and start) cron.
./start-cron.sh

# If the cron stuff failed, exit.
rc=$?; if [[ $rc != 0 ]]; then exit $rc; fi

# Run apache in foreground
apache2ctl -D FOREGROUND
