#!/usr/bin/env sh
# Creates .env with fresh random secrets. Safe to run again: it never overwrites an existing .env.
set -e
cd "$(dirname "$0")"

if [ -f .env ]; then
  echo ".env already exists; leaving it alone."
  exit 0
fi

rand() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "$1"; }

cp .env.example .env
APP_KEY="base64:$(openssl rand -base64 32)"
# Use a temp file so this works with both GNU and BSD (macOS) sed.
sed -e "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(rand 32)|" \
    -e "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$(rand 24)|" \
    -e "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(rand 40)|" \
    .env > .env.tmp && mv .env.tmp .env
chmod 600 .env

echo "Created .env with new secrets. Next: docker compose up --build"
