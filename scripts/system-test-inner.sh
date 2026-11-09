#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

php -v | tee /tmp/php-version.txt
php -r '
$v = PHP_VERSION;
if (!str_starts_with($v, "8.2.")) {
    fwrite(STDERR, "PHP {$v} is not an 8.2.x release\n");
    exit(1);
}
'

for ext in bcmath ctype curl dom gmp json mbstring phar tokenizer xml xmlwriter; do
    if ! php -m | grep -qi "^${ext}$"; then
        echo "Missing required PHP extension: ${ext}" >&2
        exit 1
    fi
done

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

composer_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
if ! grep -Fxq 'Generating autoload files' "${composer_out}"; then
    echo 'composer update: missing exact success line Generating autoload files' >&2
    exit 1
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${composer_out}"; then
    echo 'composer update: missing exact advisory success line' >&2
    exit 1
fi
rm -f "${composer_out}"

php scripts/check-cluster-pins.php

php scripts/check-no-old-names.php

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo 'composer audit: missing exact success line' >&2
    exit 1
fi
rm -f "${audit_out}"

run_phpunit() {
    local label="$1"
    shift
    local phpunit_out
    phpunit_out="$(mktemp)"
    set +e
    env "$@" vendor/bin/phpunit --colors=never --do-not-cache-result --no-progress >"${phpunit_out}" 2>&1
    local phpunit_status=$?
    set -e
    cat "${phpunit_out}"
    fail_deprecation_output "phpunit ${label}" "${phpunit_out}"
    if [[ ${phpunit_status} -ne 0 ]]; then
        rm -f "${phpunit_out}"
        exit "${phpunit_status}"
    fi
    if ! grep -Eq '^OK \(12915 tests, 51429 assertions\)$' "${phpunit_out}"; then
        echo "phpunit ${label}: missing strict OK success line" >&2
        exit 1
    fi
    rm -f "${phpunit_out}"
}

run_phpunit GMP CALCULATOR=GMP
run_phpunit BCMath CALCULATOR=BCMath
run_phpunit Native CALCULATOR=Native
run_phpunit BCMath-scale-8 CALCULATOR=BCMath BCMATH_DEFAULT_SCALE=8

phpstan_out="$(mktemp)"
set +e
vendor/bin/phpstan analyse --memory-limit=1G --no-progress --no-ansi >"${phpstan_out}" 2>&1
phpstan_status=$?
set -e
cat "${phpstan_out}"
fail_deprecation_output "phpstan" "${phpstan_out}"
if [[ ${phpstan_status} -ne 0 ]]; then
    exit "${phpstan_status}"
fi
if ! grep -Eq '^ \[OK\] No errors *$' "${phpstan_out}"; then
    echo 'phpstan: missing strict [OK] No errors line' >&2
    exit 1
fi
rm -f "${phpstan_out}"

echo 'system-test: all steps passed.'
