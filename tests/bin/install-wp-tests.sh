#!/usr/bin/env bash

set -euo pipefail

if [[ $# -lt 3 ]]; then
  echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
  exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASS="$3"
DB_HOST="${4:-127.0.0.1}"
WP_VERSION="${5:-6.9.1}"
INSTALLER_PATH="/tmp/install-wp-tests.sh"
TMP_ROOT="${TMPDIR:-/tmp}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

install_with_wp_cli_scaffold() {
  if [[ ! -f "${INSTALLER_PATH}" ]]; then
    curl -fsSL "https://raw.githubusercontent.com/wp-cli/scaffold-command/master/templates/install-wp-tests.sh" -o "${INSTALLER_PATH}"
  fi
  chmod +x "${INSTALLER_PATH}"
  "${INSTALLER_PATH}" "${DB_NAME}" "${DB_USER}" "${DB_PASS}" "${DB_HOST}" "${WP_VERSION}"
}

install_without_svn() {
  local core_archive="${TMP_ROOT}/wordpress-${WP_VERSION}.tar.gz"
  local tests_archive="${TMP_ROOT}/wordpress-develop-${WP_VERSION}.tar.gz"
  local extracted_base=""

  mkdir -p "${WP_TESTS_DIR}" "${WP_CORE_DIR}"

  if [[ ! -f "${WP_CORE_DIR}/wp-load.php" ]]; then
    echo "Downloading WordPress core ${WP_VERSION}..."
    curl -fsSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" -o "${core_archive}"
    rm -rf "${WP_CORE_DIR:?}/"*
    tar -xzf "${core_archive}" --strip-components=1 -C "${WP_CORE_DIR}"
  fi

  if [[ ! -f "${WP_TESTS_DIR}/includes/functions.php" ]]; then
    echo "Downloading WordPress test suite ${WP_VERSION}..."
    if ! curl -fsSL "https://codeload.github.com/WordPress/wordpress-develop/tar.gz/refs/tags/${WP_VERSION}" -o "${tests_archive}"; then
      curl -fsSL "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz" -o "${tests_archive}"
    fi
    extracted_base="$(tar -tzf "${tests_archive}" | head -1 | cut -d/ -f1)"
    rm -rf "${TMP_ROOT:?}/${extracted_base}"
    tar -xzf "${tests_archive}" -C "${TMP_ROOT}"
    cp -R "${TMP_ROOT}/${extracted_base}/tests/phpunit/includes" "${WP_TESTS_DIR}/"
    cp -R "${TMP_ROOT}/${extracted_base}/tests/phpunit/data" "${WP_TESTS_DIR}/"
    cp "${TMP_ROOT}/${extracted_base}/wp-tests-config-sample.php" "${WP_TESTS_DIR}/wp-tests-config.php"
  fi

  # Configure db and path settings for local PHPUnit bootstrap.
  perl -0777 -i -pe "s/youremptytestdbnamehere/${DB_NAME}/g; s/yourusernamehere/${DB_USER}/g; s/yourpasswordhere/${DB_PASS}/g; s/localhost/${DB_HOST}/g; s#define\\(\\s*'ABSPATH'\\s*,\\s*[^;]+;#define( 'ABSPATH', '${WP_CORE_DIR}/' );#g" "${WP_TESTS_DIR}/wp-tests-config.php"
}

if command -v svn >/dev/null 2>&1; then
  install_with_wp_cli_scaffold
else
  echo "svn not found; using GitHub fallback installer."
  install_without_svn
fi

echo "WordPress PHPUnit test suite installed for WordPress ${WP_VERSION}."
