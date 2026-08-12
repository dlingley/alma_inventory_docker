#!/usr/bin/env bash
set -e

# Configuration (Overridable via environment variables: REMOTE_HOST, REMOTE_USER, SSH_PORT)
REMOTE_HOST="${REMOTE_HOST:-web02p.lib.purdue.edu}"
REMOTE_USER="${REMOTE_USER:-dlingley}"
SSH_PORT="${SSH_PORT:-22}"
REMOTE_CACHE_DIR="${REMOTE_CACHE_DIR:-/var/www/html/apps/alma/inventory/cache}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

LOCAL_INPUT_DIR="$PROJECT_ROOT/tests/fixtures/cache_history/input"
LOCAL_OUTPUT_DIR="$PROJECT_ROOT/tests/fixtures/cache_history/output"

mkdir -p "$LOCAL_INPUT_DIR"
mkdir -p "$LOCAL_OUTPUT_DIR"

SSH_CMD="ssh -p $SSH_PORT"

echo "================================================================="
echo " Syncing 6-Month Cache History from $REMOTE_USER@$REMOTE_HOST (port $SSH_PORT)"
echo "================================================================="
echo "Local Input Directory : $LOCAL_INPUT_DIR"
echo "Local Output Directory: $LOCAL_OUTPUT_DIR"
echo ""

echo "[1/3] Syncing input files (.xlsx) from $REMOTE_CACHE_DIR/upload/ ..."
rsync -avz -e "$SSH_CMD" --progress \
  "$REMOTE_USER@$REMOTE_HOST:$REMOTE_CACHE_DIR/upload/*.xlsx" "$LOCAL_INPUT_DIR/" || true

echo ""
echo "[2/3] Syncing input files (.xlsx) from $REMOTE_CACHE_DIR/input/ ..."
rsync -avz -e "$SSH_CMD" --progress \
  "$REMOTE_USER@$REMOTE_HOST:$REMOTE_CACHE_DIR/input/*.xlsx" "$LOCAL_INPUT_DIR/" || true

echo ""
echo "[3/3] Syncing output files (.csv) from $REMOTE_CACHE_DIR/output/ ..."
rsync -avz -e "$SSH_CMD" --progress \
  "$REMOTE_USER@$REMOTE_HOST:$REMOTE_CACHE_DIR/output/*.csv" "$LOCAL_OUTPUT_DIR/" || true

echo ""
echo "Sync attempt complete."
echo "Input files downloaded : $(ls -1 "$LOCAL_INPUT_DIR"/*.xlsx 2>/dev/null | wc -l || echo 0)"
echo "Output files downloaded: $(ls -1 "$LOCAL_OUTPUT_DIR"/*.csv 2>/dev/null | wc -l || echo 0)"
