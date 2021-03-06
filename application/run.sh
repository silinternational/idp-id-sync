#!/usr/bin/env bash

set -x

# establish a signal handler to catch the SIGTERM from a 'docker stop'
# reference: https://medium.com/@gchudnov/trapping-signals-in-docker-containers-7a57fdda7d86
term_handler() {
  apache2ctl stop
  killall cron
  exit 143; # 128 + 15 -- SIGTERM
}
trap 'kill ${!}; term_handler' SIGTERM

# Configure (and start) cron.
output=$(./start-cron.sh 2>&1)

# If the cron stuff failed, exit.
rc=$?;
if [[ $rc != 0 ]]; then
  echo "FAILED to start cron jobs. Exit code ${rc}. Message: ${output}"
  exit $rc;
fi

if [[ $APP_ENV == "dev" ]]; then
    export XDEBUG_CONFIG="remote_enable=1 remote_host=${REMOTE_DEBUG_IP}"
    apt-get -y -q install php-xdebug
fi

apache2ctl -k start -D FOREGROUND

# endless loop with a wait is needed for the trap to work
while true
do
  tail -f /dev/null & wait ${!}
done
