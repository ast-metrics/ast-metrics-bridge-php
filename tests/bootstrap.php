<?php

/**
 * Assertions and test doubles. No dependency: the whole point of this package is
 * that it works in any PHP project, so its tests must not need one either.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Halleck45\AstMetrics\Binary\BinaryProvider;
use Halleck45\AstMetrics\Binary\Platform;
use Halleck45\AstMetrics\Process\ProcessRunner;

final class TestResults
{
    public static $assertions = 0;

    public static $failures = 0;

    public static $currentFile = '';
}

/**
 * @param string $description
 * @param mixed  $expected
 * @param mixed  $actual
 */
function assertSame($description, $expected, $actual)
{
    TestResults::$assertions++;

    if ($expected === $actual) {
        return;
    }

    TestResults::$failures++;
    fwrite(STDERR, sprintf(
        'FAIL [%s] %s' . PHP_EOL . '  expected: %s' . PHP_EOL . '  actual:   %s' . PHP_EOL,
        TestResults::$currentFile,
        $description,
        var_export($expected, true),
        var_export($actual, true)
    ));
}

/**
 * @param string $description
 * @param bool   $condition
 */
function assertTrue($description, $condition)
{
    assertSame($description, true, $condition);
}

/**
 * @param string   $description
 * @param string   $expectedMessage Substring the message must contain.
 * @param callable $callable
 */
function assertThrows($description, $expectedMessage, $callable)
{
    TestResults::$assertions++;

    try {
        $callable();
    } catch (Throwable $exception) {
        if (strpos($exception->getMessage(), $expectedMessage) !== false) {
            return;
        }

        TestResults::$failures++;
        fwrite(STDERR, sprintf(
            'FAIL [%s] %s' . PHP_EOL . '  expected message containing: %s' . PHP_EOL . '  actual: %s' . PHP_EOL,
            TestResults::$currentFile,
            $description,
            $expectedMessage,
            $exception->getMessage()
        ));

        return;
    }

    TestResults::$failures++;
    fwrite(STDERR, sprintf('FAIL [%s] %s: nothing was thrown' . PHP_EOL, TestResults::$currentFile, $description));
}

/**
 * Returns a fixed path instead of downloading anything.
 */
final class FakeBinaryProvider extends BinaryProvider
{
    const PATH = '/fake/bin/ast-metrics';

    public function __construct()
    {
        parent::__construct(new Platform('Linux', 'x86_64'), null);
    }

    public function resolve()
    {
        return self::PATH;
    }
}

/**
 * Records the command instead of running it.
 */
final class RecordingRunner extends ProcessRunner
{
    /** @var array|null */
    public $command;

    /** @var int */
    public $exitCode = 0;

    public function run(array $command)
    {
        $this->command = $command;

        return $this->exitCode;
    }

    /**
     * The arguments passed to the analyzer, without the binary path.
     *
     * @return array
     */
    public function arguments()
    {
        return $this->command === null ? [] : array_slice($this->command, 1);
    }
}

/**
 * Captures what the bridge writes to its output stream.
 *
 * @param callable $callable Receives the stream to hand to the object under test.
 * @return string
 */
function captureOutput($callable)
{
    $stream = fopen('php://memory', 'w+');
    $callable($stream);
    rewind($stream);
    $captured = stream_get_contents($stream);
    fclose($stream);

    return $captured;
}
