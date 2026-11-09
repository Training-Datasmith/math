<?php

declare(strict_types=1);

/**
 * Fail if legacy camelCase Brick\Math identifiers remain in the wired tree.
 */

const SUCCESS_LINE = 'check-no-old-names: no pre-rename identifiers remain.';

/** @var list<string> */
const FORBIDDEN_TOKENS = [
    'BigDecimal',
    'BigInteger',
    'BigRational',
    'BigNumber',
    'RoundingMode',
    'DivisionByZeroException',
    'IntegerOverflowException',
    'MathException',
    'NegativeNumberException',
    'NoInverseException',
    'NumberFormatException',
    'RandomSourceException',
    'RoundingNecessaryException',
    'UnsupportedPlatformException',
    'BcMathCalculator',
    'GmpCalculator',
    'NativeCalculator',
    'CalculatorRegistry',
    'DecimalHelper',
    'AbstractTestCase',
    'BigDecimalTest',
    'BigIntegerTest',
    'BigNumberTest',
    'BigRationalTest',
    'CalculatorDetectTest',
    'SafeTest',
    'ofUnscaledValue',
    'fromFloatExact',
    'fromFloatShortest',
    'multipliedBy',
    'dividedBy',
    'quotientAndRemainder',
    'withPointMovedLeft',
    'withPointMovedRight',
    'strippedOfTrailingZeros',
    'fromBase',
    'fromArbitraryBase',
    'fromBytes',
    'randomBits',
    'randomRange',
    'gcdAll',
    'lcmAll',
    'modInverse',
    'modPow',
    'divQR',
    'InvalidArgumentException',
];

/** @return list<string> */
function scan_paths(string $root): array
{
    return [
        $root . '/src',
        $root . '/tests',
        $root . '/examples',
        $root . '/phpunit.php',
        $root . '/random-tests.php',
        $root . '/phpstan.neon',
        $root . '/README.md',
        $root . '/ARCHITECTURE.md',
        $root . '/tools/ecs/ecs.php',
        $root . '/scripts/check-cluster-pins.php',
        $root . '/scripts/system-test-inner.sh',
        $root . '/scripts/system-test.sh',
    ];
}

/**
 * @return list<array{path: string, token: string, line: int}>
 */
function find_forbidden_tokens(string $contents, string $path): array
{
    $hits = [];
    $lines = preg_split('/\R/', $contents) ?: [];

    foreach ($lines as $index => $line) {
        if (str_contains($line, '\\InvalidArgumentException')) {
            $line = str_replace('\\InvalidArgumentException', '', $line);
        }

        foreach (FORBIDDEN_TOKENS as $token) {
            if ($token === 'motionX') {
                continue;
            }
            if (preg_match('/\b' . preg_quote($token, '/') . '\b/', $line) === 1) {
                $hits[] = ['path' => $path, 'token' => $token, 'line' => $index + 1];
            }
        }
    }

    return $hits;
}

/**
 * @return list<string>
 */
function php_files_under(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * @return list<array{path: string, message: string}>
 */
function verify_class_stems(string $root): array
{
    $failures = [];
    foreach ([$root . '/src', $root . '/tests'] as $dir) {
        foreach (php_files_under($dir) as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }
            $stem = pathinfo($file, PATHINFO_FILENAME);
            if (!preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)*((?:class|interface|enum)\s+(\w+))/m', $contents, $match)) {
                continue;
            }
            $declared = $match[2];
            if ($declared !== $stem && !($stem === 'Calculator' && $declared === 'Calculator')) {
                $failures[] = [
                    'path' => $file,
                    'message' => sprintf('declares %s but filename stem is %s', $declared, $stem),
                ];
            }
        }
    }

    return $failures;
}

/**
 * @return list<array{path: string, token: string, line: int}>
 */
function scan_tree(string $root): array
{
    $hits = [];
    foreach (scan_paths($root) as $target) {
        if (is_dir($target)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                $contents = file_get_contents($path);
                if ($contents === false) {
                    continue;
                }
                array_push($hits, ...find_forbidden_tokens($contents, $path));
            }
            continue;
        }

        if (!is_file($target)) {
            continue;
        }

        $contents = file_get_contents($target);
        if ($contents === false) {
            continue;
        }
        array_push($hits, ...find_forbidden_tokens($contents, $target));
    }

    return $hits;
}

function run_self_test(): bool
{
    $badFixture = "<?php\nBigInteger::of(1);\n";
    if (find_forbidden_tokens($badFixture, 'fixture-bad.php') === []) {
        fwrite(STDERR, "check-no-old-names: self-test bad fixture did not fail.\n");
        return false;
    }

    $goodFixture = "<?php\nBig_Integer::of(1);\n";
    if (find_forbidden_tokens($goodFixture, 'fixture-good.php') !== []) {
        fwrite(STDERR, "check-no-old-names: self-test good fixture incorrectly failed.\n");
        return false;
    }

    return true;
}

$root = dirname(__DIR__);

if (in_array('--self-test', $argv, true)) {
    exit(run_self_test() ? 0 : 1);
}

if (!run_self_test()) {
    exit(1);
}

$hits = scan_tree($root);
foreach ($hits as $hit) {
    fwrite(STDERR, sprintf(
        "check-no-old-names: forbidden token %s in %s:%d\n",
        $hit['token'],
        $hit['path'],
        $hit['line']
    ));
}

foreach (verify_class_stems($root) as $failure) {
    fwrite(STDERR, sprintf("check-no-old-names: %s (%s)\n", $failure['message'], $failure['path']));
}

if ($hits !== [] || verify_class_stems($root) !== []) {
    exit(1);
}

fwrite(STDOUT, SUCCESS_LINE . PHP_EOL);
exit(0);
