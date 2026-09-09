#!/usr/bin/env bash

set -euo pipefail

DB_NAME="${WP_TEST_DB_NAME:-wordpress_test}"
DB_USER="${WP_TEST_DB_USER:-wordpress}"
DB_PASS="${WP_TEST_DB_PASS:-wordpress}"
DB_HOST="${WP_TEST_DB_HOST:-127.0.0.1:3308}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

resolve_tests_dir() {
  if [[ -n "${WP_TESTS_DIR:-}" && -f "${WP_TESTS_DIR}/wp-tests-config.php" ]]; then
    echo "${WP_TESTS_DIR}"
    return
  fi

  if [[ -f "/tmp/wordpress-tests-lib/wp-tests-config.php" ]]; then
    echo "/tmp/wordpress-tests-lib"
    return
  fi

  local tmpdir_candidate="${TMPDIR:-}/wordpress-tests-lib"
  if [[ -n "${TMPDIR:-}" && -f "${tmpdir_candidate}/wp-tests-config.php" ]]; then
    echo "${tmpdir_candidate}"
    return
  fi

  echo ""
}

WP_TESTS_DIR_RESOLVED="$(resolve_tests_dir)"
if [[ -z "${WP_TESTS_DIR_RESOLVED}" ]]; then
  echo "wp-tests-config.php not found yet; skipping normalization."
  exit 0
fi

CONFIG_FILE="${WP_TESTS_DIR_RESOLVED}/wp-tests-config.php"

DB_NAME="${DB_NAME}" DB_USER="${DB_USER}" DB_PASS="${DB_PASS}" DB_HOST="${DB_HOST}" WP_CORE_DIR="${WP_CORE_DIR}" perl -0777 -i -pe '
my $db_name = $ENV{DB_NAME};
my $db_user = $ENV{DB_USER};
my $db_pass = $ENV{DB_PASS};
my $db_host = $ENV{DB_HOST};
my $wp_core = $ENV{WP_CORE_DIR};

s#define\(\s*["\x27]DB_NAME["\x27]\s*,\s*["\x27][^"\x27]*["\x27]\s*\);#define( \x27DB_NAME\x27, \x27${db_name}\x27 );#g;
s#define\(\s*["\x27]DB_USER["\x27]\s*,\s*["\x27][^"\x27]*["\x27]\s*\);#define( \x27DB_USER\x27, \x27${db_user}\x27 );#g;
s#define\(\s*["\x27]DB_PASSWORD["\x27]\s*,\s*["\x27][^"\x27]*["\x27]\s*\);#define( \x27DB_PASSWORD\x27, \x27${db_pass}\x27 );#g;
s#define\(\s*["\x27]DB_HOST["\x27]\s*,\s*["\x27][^"\x27]*["\x27]\s*\);#define( \x27DB_HOST\x27, \x27${db_host}\x27 );#g;
s#define\(\s*["\x27]ABSPATH["\x27]\s*,\s*[^;]+;#define( \x27ABSPATH\x27, \x27${wp_core}/\x27 );#g;
' "${CONFIG_FILE}"

echo "Normalized ${CONFIG_FILE} (DB_HOST=${DB_HOST})."
