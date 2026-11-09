<?php

declare(strict_types=1);

/**
 * Verify Packagist pins for phpstan/phpstan and phpunit/phpunit using installed.php and installed.json.
 */

const EXPECTED_PHPSTAN_SHA = '41600c8379eb5aee63e9413fe9e97273e25d57e4';
const EXPECTED_PHPSTAN_VERSION = '2.1.22';

/** @var array<string, list<string>> */
const FORBIDDEN_SHAS = [
    'phpunit/phpunit' => [
        'a37d66fabf01af0eedabffe271b70dbe71723001',
        '698cbbc1e75a1ef1968f9cbfec89be056b288d0f',
    ],
    'phpstan/phpstan' => [
        '63f848a4b1dbe2f2cb87acb3ff32596661e45417',
        'ff62edb2f684beab2738a854e9becf425d3e662e',
    ],
];

/**
 * @return array{versions: array<string, array<string, mixed>>}
 */
function load_installed_php(string $path): array
{
    if (!is_readable($path)) {
        fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
        exit(1);
    }

    /** @var array{versions: array<string, array<string, mixed>>} $installed */
    $installed = require $path;

    return $installed;
}

/**
 * @return array<string, string>
 */
function load_installed_json_refs(string $path): array
{
    if (!is_readable($path)) {
        fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
        exit(1);
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
        exit(1);
    }

    /** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $data */
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $refs = [];
    foreach (array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []) as $package) {
        if (!isset($package['name'], $package['source']['reference'])) {
            continue;
        }
        $refs[(string) $package['name']] = strtolower((string) $package['source']['reference']);
    }

    return $refs;
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installedPhp
 * @param array<string, string>                               $jsonRefs
 */
function verify_pins(array $installedPhp, array $jsonRefs, bool $quiet = false): bool
{
    $versions = $installedPhp['versions'] ?? [];
    $failed = false;

    $err = static function (string $message) use ($quiet): void {
        if (!$quiet) {
            fwrite(STDERR, $message);
        }
    };

    foreach (['phpstan/phpstan', 'phpunit/phpunit'] as $name) {
        if (!isset($versions[$name])) {
            $err(sprintf("check-cluster-pins: %s is not installed.\n", $name));
            $failed = true;
            continue;
        }

        $pkg = $versions[$name];
        $phpRef = isset($pkg['reference']) && is_string($pkg['reference']) ? strtolower($pkg['reference']) : '';
        if ($phpRef === '') {
            $err(sprintf("check-cluster-pins: %s has no reference in installed.php.\n", $name));
            $failed = true;
            continue;
        }

        if (!isset($jsonRefs[$name])) {
            $err(sprintf("check-cluster-pins: %s missing from installed.json.\n", $name));
            $failed = true;
            continue;
        }

        if ($phpRef !== $jsonRefs[$name]) {
            $err(sprintf(
                "check-cluster-pins: %s reference mismatch installed.php=%s installed.json=%s.\n",
                $name,
                $phpRef,
                $jsonRefs[$name]
            ));
            $failed = true;
        }

        foreach (FORBIDDEN_SHAS[$name] ?? [] as $forbidden) {
            if (hash_equals(strtolower($forbidden), $phpRef)) {
                $err(sprintf("check-cluster-pins: %s is pinned to forbidden index SHA %s.\n", $name, $forbidden));
                $failed = true;
            }
        }

        $aliases = $pkg['aliases'] ?? [];
        if ($aliases !== []) {
            $err(sprintf(
                "check-cluster-pins: %s expected empty aliases, got %s.\n",
                $name,
                json_encode($aliases)
            ));
            $failed = true;
        }
    }

  $phpstan = $versions['phpstan/phpstan'] ?? null;
    if (is_array($phpstan)) {
        $pretty = $phpstan['pretty_version'] ?? '';
        if ($pretty !== EXPECTED_PHPSTAN_VERSION) {
            $err(sprintf(
                "check-cluster-pins: phpstan/phpstan pretty_version expected %s, got %s.\n",
                EXPECTED_PHPSTAN_VERSION,
                $pretty
            ));
            $failed = true;
        }
        $ref = strtolower((string) ($phpstan['reference'] ?? ''));
        if (!hash_equals(EXPECTED_PHPSTAN_SHA, $ref)) {
            $err(sprintf(
                "check-cluster-pins: phpstan/phpstan reference expected %s, got %s.\n",
                EXPECTED_PHPSTAN_SHA,
                $ref
            ));
            $failed = true;
        }
    }

    $phpunit = $versions['phpunit/phpunit'] ?? null;
    if (is_array($phpunit)) {
        $pretty = $phpunit['pretty_version'] ?? '';
        if ($pretty === '' || version_compare($pretty, '11.5', '<') || version_compare($pretty, '12.0', '>=')) {
            $err(sprintf(
                "check-cluster-pins: phpunit/phpunit must be 11.5.x, got %s.\n",
                $pretty
            ));
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installedPhp
 * @param array<string, string>                               $jsonRefs
 */
function run_wrong_sha_demo(array $installedPhp, array $jsonRefs): bool
{
  $demoFailedCorrectly = true;

    $phpCopy = $installedPhp;
    $phpCopy['versions']['phpstan/phpstan']['reference'] = str_repeat('0', 40);
    if (verify_pins($phpCopy, $jsonRefs, true)) {
        fwrite(STDERR, "check-cluster-pins: wrong installed.php SHA demo incorrectly passed.\n");
        $demoFailedCorrectly = false;
    }

    $jsonCopy = $jsonRefs;
    $jsonCopy['phpstan/phpstan'] = str_repeat('0', 40);
    if (verify_pins($installedPhp, $jsonCopy)) {
        fwrite(STDERR, "check-cluster-pins: wrong installed.json SHA demo incorrectly passed.\n");
        $demoFailedCorrectly = false;
    }

    return $demoFailedCorrectly;
}

$root = dirname(__DIR__);
$installedPhpPath = $root . '/vendor/composer/installed.php';
$installedJsonPath = $root . '/vendor/composer/installed.json';

$installedPhp = load_installed_php($installedPhpPath);
$jsonRefs = load_installed_json_refs($installedJsonPath);

if (!run_wrong_sha_demo($installedPhp, $jsonRefs)) {
    exit(1);
}

if (!verify_pins($installedPhp, $jsonRefs)) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: installed.php and installed.json agree; Packagist pins held; wrong-SHA demo failed as required.\n");
exit(0);
