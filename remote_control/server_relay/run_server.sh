#!/bin/bash
# Runner Linux / VPS untuk Relay Gateway Server
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "==================================================="
echo "    ANDROID REMOTE CONTROL - RELAY SERVER GATEWAY   "
echo "==================================================="

# Port dan Host Default
export RELAY_HOST=${RELAY_HOST:-"0.0.0.0"}
export RELAY_PORT=${RELAY_PORT:-8080}

if [ -f "../../.venv/bin/python" ]; then
    ../../.venv/bin/python server.py
else
    python3 server.py
fi
