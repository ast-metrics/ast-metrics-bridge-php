<?php

use Halleck45\AstMetrics\AstMetricsProxy;
use Halleck45\AstMetrics\Compatibility\PhpMetricsArguments;

/**
 * @param array $arguments
 * @return array [RecordingRunner, string captured output]
 */
function runProxy(array $arguments, $exitCode = 0)
{
    $runner = new RecordingRunner();
    $runner->exitCode = $exitCode;

    $output = captureOutput(function ($stream) use ($arguments, $runner) {
        $proxy = new AstMetricsProxy(new FakeBinaryProvider(), $runner, new PhpMetricsArguments(), $stream);
        $proxy->run($arguments);
    });

    return [$runner, $output];
}

// ---------------------------------------------------------------------------
// Where --non-interactive may be inserted
//
// Only "analyze" declares the option: passing it to lint or ci is rejected by
// the CLI with "flag provided but not defined", so it cannot just be prepended
// to every invocation.
// ---------------------------------------------------------------------------

list($runner) = runProxy(['analyze', 'src']);
assertSame(
    'the option goes right after the analyze subcommand',
    ['analyze', '--non-interactive', 'src'],
    $runner->arguments()
);

list($runner) = runProxy(['a', 'src']);
assertSame(
    'the analyze alias is recognised',
    ['a', '--non-interactive', 'src'],
    $runner->arguments()
);

list($runner) = runProxy(['lint', 'src']);
assertSame(
    'lint does not declare the option, so it is not inserted',
    ['lint', 'src'],
    $runner->arguments()
);

list($runner) = runProxy(['ci', 'src']);
assertSame(
    'ci does not declare the option either',
    ['ci', 'src'],
    $runner->arguments()
);

list($runner) = runProxy(['mcp', '.']);
assertSame(
    'the mcp transport is left untouched',
    ['mcp', '.'],
    $runner->arguments()
);

list($runner) = runProxy(['--version']);
assertSame(
    'with no subcommand the option is global, so it goes first',
    ['--non-interactive', '--version'],
    $runner->arguments()
);

list($runner) = runProxy(['analyze', '--non-interactive', 'src']);
assertSame(
    'an option already there is not duplicated',
    ['analyze', '--non-interactive', 'src'],
    $runner->arguments()
);

list($runner) = runProxy(['analyze', '--ci', 'src']);
assertSame(
    '--ci already implies it',
    ['analyze', '--ci', 'src'],
    $runner->arguments()
);

// ---------------------------------------------------------------------------
// The binary comes first
// ---------------------------------------------------------------------------

list($runner) = runProxy(['analyze', 'src']);
assertSame(
    'the resolved binary is the first element of the command',
    FakeBinaryProvider::PATH,
    $runner->command[0]
);

// ---------------------------------------------------------------------------
// Exit codes
// ---------------------------------------------------------------------------

$runner = new RecordingRunner();
$runner->exitCode = 3;
$returned = null;
captureOutput(function ($stream) use ($runner, &$returned) {
    $proxy = new AstMetricsProxy(new FakeBinaryProvider(), $runner, new PhpMetricsArguments(), $stream);
    $returned = $proxy->run(['analyze', 'src']);
});
assertSame(
    'the analyzer exit code is returned untouched, so a failed gate fails the build',
    3,
    $returned
);

$runner = new RecordingRunner();
$returned = null;
captureOutput(function ($stream) use ($runner, &$returned) {
    $proxy = new AstMetricsProxy(new FakeBinaryProvider(), $runner, new PhpMetricsArguments(), $stream);
    $returned = $proxy->run(['--report-csv=metrics.csv', 'src']);
});
assertSame(
    'an untranslatable command line exits 2 and never reaches the analyzer',
    AstMetricsProxy::EXIT_INVALID_ARGUMENTS,
    $returned
);
assertSame(
    'the analyzer is not run at all in that case',
    null,
    $runner->command
);

// ---------------------------------------------------------------------------
// What is written to stderr
// ---------------------------------------------------------------------------

list($runner, $output) = runProxy(['analyze', 'src']);
assertSame('a native command line prints nothing', '', $output);

list($runner, $output) = runProxy(['.']);
assertSame(
    'a missing subcommand is one short warning, not a banner',
    1,
    count(array_filter(explode(PHP_EOL, trim($output))))
);
assertTrue(
    'the warning names the compatibility mode and the preferred form',
    strpos($output, 'PhpMetrics compatibility') !== false && strpos($output, 'ast-metrics analyze .') !== false
);

list($runner, $output) = runProxy(['--report-violations=build/pmd.xml', 'src']);
assertTrue(
    'a refusal explains itself',
    strpos($output, 'cannot be translated') !== false && strpos($output, '--report-sarif') !== false
);
