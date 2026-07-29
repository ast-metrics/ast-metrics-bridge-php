#!/usr/bin/env php
<?php

/**
 * Dependency-free test suite: run it with "php tests/run.php".
 *
 * The translation table and the platform mapping are pure logic, and they are
 * the two places where a mistake silently changes what gets analyzed, so they
 * are worth pinning down.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Halleck45\AstMetrics\Binary\Platform;
use Halleck45\AstMetrics\Compatibility\PhpMetricsArguments;

$failures = 0;
$assertions = 0;

/**
 * @param string $description
 * @param mixed  $expected
 * @param mixed  $actual
 */
function assertSame($description, $expected, $actual)
{
    global $failures, $assertions;
    $assertions++;

    if ($expected === $actual) {
        return;
    }

    $failures++;
    fwrite(STDERR, sprintf(
        'FAIL %s' . PHP_EOL . '  expected: %s' . PHP_EOL . '  actual:   %s' . PHP_EOL,
        $description,
        var_export($expected, true),
        var_export($actual, true)
    ));
}

/**
 * @param string $description
 * @param string $expectedMessage
 * @param callable $callable
 */
function assertThrows($description, $expectedMessage, $callable)
{
    global $failures, $assertions;
    $assertions++;

    try {
        $callable();
    } catch (Throwable $exception) {
        if (strpos($exception->getMessage(), $expectedMessage) !== false) {
            return;
        }

        $failures++;
        fwrite(STDERR, sprintf(
            'FAIL %s' . PHP_EOL . '  expected message containing: %s' . PHP_EOL . '  actual: %s' . PHP_EOL,
            $description,
            $expectedMessage,
            $exception->getMessage()
        ));

        return;
    }

    $failures++;
    fwrite(STDERR, sprintf('FAIL %s: nothing was thrown' . PHP_EOL, $description));
}

// ---------------------------------------------------------------------------
// Translating: a native command line has to survive it untouched
// ---------------------------------------------------------------------------

$compatibility = new PhpMetricsArguments();

assertSame(
    'translation is on by default',
    true,
    $compatibility->isEnabled()
);

assertSame(
    'a native command line comes out unchanged',
    ['analyze', '--report-html=./report', '--report-sarif=out.sarif', 'src'],
    $compatibility->translate(['analyze', '--report-html=./report', '--report-sarif=out.sarif', 'src'])
);

assertSame(
    'nothing is reported when nothing was rewritten',
    [],
    $compatibility->notices()
);

assertSame(
    'a regular expression written on purpose is not escaped',
    ['analyze', '--exclude=Test.*Case', 'src'],
    $compatibility->translate(['analyze', '--exclude=Test.*Case', 'src'])
);

assertSame(
    'no exclusion is added, so tests stay analyzed and test quality keeps working',
    ['analyze', 'src'],
    $compatibility->translate(['analyze', 'src'])
);

// ---------------------------------------------------------------------------
// Translating a PhpMetrics command line
// ---------------------------------------------------------------------------

assertSame(
    'directories, extensions and exclusions are translated',
    ['analyze', '--report-html=./report', '--php-extensions=.inc', '--exclude=tests', '--exclude=vendor', 'src', 'lib'],
    $compatibility->translate(['--report-html=./report', '--extensions=php,inc', '--exclude=tests,vendor', 'src,lib'])
);

assertSame(
    'a directory name is escaped, so a dot is matched literally',
    ['analyze', '--exclude=app\\.legacy', '--exclude=vendor', 'src'],
    $compatibility->translate(['--exclude=app.legacy,vendor', 'src'])
);

assertSame(
    'an existing subcommand is kept',
    true,
    in_array('lint', $compatibility->translate(['lint', '--extensions=php', 'src,lib']), true)
);

assertSame(
    '--version needs no subcommand',
    ['--version'],
    $compatibility->translate(['--version'])
);

// ---------------------------------------------------------------------------
// Options that changed nothing are dropped; options that lose a result stop
// ---------------------------------------------------------------------------

assertSame(
    'git history analysis is built in, so --git is dropped rather than refused',
    ['analyze', 'src'],
    $compatibility->translate(['--git', 'src'])
);

assertSame(
    'dropping --git is explained',
    true,
    strpos(implode(' ', $compatibility->notices()), 'built in') !== false
);

assertSame(
    '--composer has nothing to switch off, so it is dropped',
    ['analyze', 'src'],
    $compatibility->translate(['--composer=false', 'src'])
);

assertSame(
    'dropped options are not errors',
    [],
    $compatibility->errors()
);

$compatibility->translate(['--report-csv=build/metrics.csv', 'src']);
assertSame(
    'an option whose output cannot be produced is an error, not a silent drop',
    1,
    count($compatibility->errors())
);

$compatibility->translate(['--report-violations=build/pmd.xml', '--junit=build/junit.xml', 'src']);
assertSame(
    'every unsupported option is reported at once',
    2,
    count($compatibility->errors())
);

$compatibility->translate(['--config=phpmetrics.json', 'src']);
assertSame(
    'a PhpMetrics configuration file is refused rather than half-read',
    1,
    count($compatibility->errors())
);

// ---------------------------------------------------------------------------
// Platform mapping
// ---------------------------------------------------------------------------

assertSame('linux x86_64', 'ast-metrics_Linux_x86_64', (new Platform('Linux', 'x86_64'))->assetName());
assertSame('linux aarch64 is arm64', 'ast-metrics_Linux_arm64', (new Platform('Linux', 'aarch64'))->assetName());
assertSame('macos arm64', 'ast-metrics_Darwin_arm64', (new Platform('Darwin', 'arm64'))->assetName());
assertSame('windows gets the exe suffix', 'ast-metrics_Windows_x86_64.exe', (new Platform('Windows NT', 'AMD64'))->assetName());
assertSame('msys reports as windows', 'Windows', (new Platform('MINGW64_NT-10.0', 'x86_64'))->os());

assertThrows(
    'an architecture with no published binary fails with a usable message',
    'AST_METRICS_BINARY',
    function () {
        (new Platform('Linux', 'i386'))->assetName();
    }
);

assertThrows(
    'arm64 on Windows has no published binary',
    'No AST Metrics binary is published',
    function () {
        (new Platform('Windows NT', 'arm64'))->assetName();
    }
);

// ---------------------------------------------------------------------------

echo PHP_EOL;
if ($failures === 0) {
    echo sprintf('%d assertions, all passing.' . PHP_EOL, $assertions);
    exit(0);
}

echo sprintf('%d of %d assertions failed.' . PHP_EOL, $failures, $assertions);
exit(1);
