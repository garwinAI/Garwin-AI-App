#!/usr/bin/env bash
# One-time setup for a new Codespace: dependencies, local .env, SQLite database and Super Admin.
set -euo pipefail
cd "$(dirname "$0")/.."

composer install --no-interaction

if [ ! -f .env ]; then
  cp .env.example .env
  key_line="$(php bin/console key:generate)"
  admin_password="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"
  sed -i "s|^APP_KEY=.*|${key_line}|" .env
  sed -i "s|^GARWIN_ADMIN_PASSWORD=.*|GARWIN_ADMIN_PASSWORD=${admin_password}|" .env
  if [ -n "${CODESPACE_NAME:-}" ]; then
    sed -i "s|^APP_URL=.*|APP_URL=https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}|" .env
  fi
fi

php bin/console migrate
php bin/console admin:bootstrap || true

echo
echo "GarwinAI is set up. Super Admin login: $(grep '^GARWIN_ADMIN_EMAIL=' .env | cut -d= -f2)"
echo "The password is GARWIN_ADMIN_PASSWORD in the .env file."
