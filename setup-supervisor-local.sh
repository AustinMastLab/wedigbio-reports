#!/bin/bash

# WeDigBio Reports — Local Supervisor Config Setup Script
# This script generates the local supervisor configuration from the template.
# Run this in your local development environment to set up the supervisor config.
#
# Usage: ./setup-supervisor-local.sh

set -e

TEMPLATE_FILE="ops/supervisor/wedigbio-ingest.conf.template"
CONFIG_FILE="ops/supervisor/wedigbio-ingest.conf"
DIRECTORY="/data/web/wedigbio-reports"
APP_ENV="local"
LOG_FILE="/data/web/wedigbio-reports/storage/logs/wedigbio-ingest.log"

# Check if template exists
if [ ! -f "$TEMPLATE_FILE" ]; then
    echo "❌ Error: Template file not found: $TEMPLATE_FILE"
    exit 1
fi

# Read template
TEMPLATE=$(cat "$TEMPLATE_FILE")

# Replace placeholders
CONFIG=$(echo "$TEMPLATE" | \
    sed "s|{{SUPERVISOR_DIRECTORY}}|$DIRECTORY|g" | \
    sed "s|{{APP_ENV}}|$APP_ENV|g" | \
    sed "s|{{SUPERVISOR_LOG_FILE}}|$LOG_FILE|g")

# Write generated config
echo "$CONFIG" > "$CONFIG_FILE"

echo "✅ Local supervisor config generated successfully"
echo "   File: $CONFIG_FILE"
echo "   Directory: $DIRECTORY"
echo "   Environment: $APP_ENV"
echo "   Log file: $LOG_FILE"
echo ""
echo "📝 Next steps (if using Supervisor locally):"
echo "   1. Copy the config: sudo cp $CONFIG_FILE /etc/supervisor/conf.d/"
echo "   2. Reload supervisor: sudo supervisorctl reread && sudo supervisorctl update"
echo "   3. Start the worker: sudo supervisorctl start wedigbio-ingest:*"

