#!/bin/bash

logger -p 1 -t batch.warning "{\"message\": \"Preparing to run ID Sync batch\", \"batch\": \"full\", \"idp_name\": \"${IDP_NAME}\", \"app_env\": \"${APP_ENV}\"}"

output=$(/data/yii batch/full 2>&1)

# If it failed, exit.
rc=$?;
if [[ $rc != 0 ]]; then
  echo $output;
  logger -p 1 -t batch.warning "{\"message\": \"FAILED: ID Sync batch. Exit code ${rc}. Message: ${output}\", \"batch\": \"full\", \"idp_name\": \"${IDP_NAME}\", \"app_env\": \"${APP_ENV}\"}"
  exit $rc;
fi

logger -p 1 -t batch.warning "{\"message\": \"Ran ID Sync batch\", \"batch\": \"full\", \"idp_name\": \"${IDP_NAME}\", \"app_env\": \"${APP_ENV}\"}"
