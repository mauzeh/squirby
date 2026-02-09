#!/bin/bash

# Sync production storage to local
# Usage: ./bin/sync-storage.sh

# Configuration
FORGE_USER="forge"
FORGE_HOST="squirby.ai"
FORGE_PATH="/home/forge/quantifiedathletics.com/storage/app/public"
LOCAL_PATH="storage/app/public"

echo "🔄 Syncing storage from production..."
echo "From: $FORGE_USER@$FORGE_HOST:$FORGE_PATH"
echo "To: $LOCAL_PATH"
echo ""

# Sync with rsync
# -a: archive mode (preserves permissions, timestamps, etc.)
# -v: verbose
# -z: compress during transfer
# --progress: show progress
# --delete: remove files locally that don't exist on production (optional)
rsync -avz --progress \
  "$FORGE_USER@$FORGE_HOST:$FORGE_PATH/" \
  "$LOCAL_PATH/"

echo ""
echo "✅ Storage sync complete!"
