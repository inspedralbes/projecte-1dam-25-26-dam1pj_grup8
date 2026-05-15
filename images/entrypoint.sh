#!/usr/bin/env sh
set -eu

APP_DIR="/var/www/html"
LOCK_FILE="${APP_DIR}/composer.lock"
LOCK_STAMP="${APP_DIR}/vendor/.composer.lock.sha1"

needs_install=0

if [ -f "${APP_DIR}/composer.json" ] && [ ! -f "${APP_DIR}/vendor/autoload.php" ]; then
  needs_install=1
fi

if [ -f "${LOCK_FILE}" ] && [ -f "${APP_DIR}/composer.json" ]; then
  current_hash="$(sha1sum "${LOCK_FILE}" | awk '{print $1}')"
  stamp_hash=""
  if [ -f "${LOCK_STAMP}" ]; then
    stamp_hash="$(cat "${LOCK_STAMP}" || true)"
  fi
  if [ "${stamp_hash}" != "${current_hash}" ]; then
    needs_install=1
  fi
fi

# Auto-install PHP deps when running in dev (bind mount) or when vendor/lock are out of sync.
if [ "${needs_install}" -eq 1 ]; then
  echo "[entrypoint] PHP dependencies missing; running composer install..."
  composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --working-dir="${APP_DIR}"

  if [ -f "${LOCK_FILE}" ]; then
    mkdir -p "${APP_DIR}/vendor"
    sha1sum "${LOCK_FILE}" | awk '{print $1}' > "${LOCK_STAMP}"
  fi
fi

exec docker-php-entrypoint "$@"
