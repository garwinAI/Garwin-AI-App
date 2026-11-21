#!/usr/bin/env bash
# Starts the PHP development server on port 8000 each time the Codespace starts.
cd "$(dirname "$0")/.."
if ! pgrep -f "php -S 0.0.0.0:8000" > /dev/null; then
  nohup php -S 0.0.0.0:8000 -t public > /tmp/garwinai-server.log 2>&1 &
fi
