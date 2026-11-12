#!/bin/sh
set -eu

: "${MYSQL_USER:?MYSQL_USER is required}"
: "${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD is required}"
: "${LRC_TEST_DATABASE:=lrc_testing}"

case "$LRC_TEST_DATABASE" in
  ""|*[!A-Za-z0-9_]*) echo "Invalid LRC_TEST_DATABASE name" >&2; exit 1 ;;
esac

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS $LRC_TEST_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON $LRC_TEST_DATABASE.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
