#!/bin/bash
# get argument dev-server.sh start|stop
if [ "$1" == "stop" ]; then
  docker compose -f compose.yml down
  exit 0
fi

if [ "$1" == "start" ]; then
  docker compose -f compose.yml up -d --build --remove-orphans
  exit 0
fi
