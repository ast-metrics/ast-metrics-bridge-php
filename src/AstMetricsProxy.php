<?php

namespace Halleck45\AstMetrics;

use Halleck45\AstMetrics\Binary\BinaryProvider;
use Halleck45\AstMetrics\Compatibility\PhpMetricsArguments;
use Halleck45\AstMetrics\Process\ProcessRunner;
use RuntimeException;

/**
 * Runs the AST Metrics binary from a PHP project.
 *
 * The binary is downloaded once and cached; the arguments are forwarded, after
 * an optional translation from the PhpMetrics command line.
 */
class AstMetricsProxy
{
    /** Exit code used when the command line itself cannot be honoured. */
    const EXIT_INVALID_ARGUMENTS = 2;

    /**
     * Subcommands that declare --non-interactive. Passing it to any other one is
     * rejected with "flag provided but not defined", so the flag cannot simply
     * be prepended to every invocation.
     */
    private static $acceptsNonInteractive = ['analyze', 'a'];

    /**
     * Subcommands driven by a stream rather than by a human. They are left
     * strictly alone: the MCP transport speaks over stdin and stdout.
     */
    private static $streamSubcommands = ['mcp'];

    private $binaries;

    private $runner;

    private $compatibility;

    private $output;

    public function __construct(
        BinaryProvider $binaries,
        ProcessRunner $runner,
        PhpMetricsArguments $compatibility,
        $output = null
    ) {
        $this->binaries = $binaries;
        $this->runner = $runner;
        $this->compatibility = $compatibility;
        $this->output = $output;
    }

    /**
     * @return AstMetricsProxy
     */
    public static function create()
    {
        return new self(
            BinaryProvider::create(),
            new ProcessRunner(),
            new PhpMetricsArguments(),
            defined('STDERR') ? STDERR : null
        );
    }

    /**
     * @param array $arguments Raw argv, without the script name.
     * @return int The exit code to return to the shell.
     * @throws RuntimeException
     */
    public function run(array $arguments)
    {
        $translating = $this->compatibility->isEnabled();

        if ($translating) {
            $arguments = $this->compatibility->translate($arguments);

            if ($this->compatibility->errors() !== []) {
                $this->reportErrors($this->compatibility->errors());

                return self::EXIT_INVALID_ARGUMENTS;
            }
        }

        $arguments = $this->nonInteractive($arguments);

        if ($translating) {
            $this->reportTranslation($this->compatibility->notices(), $arguments);
        }

        $command = array_merge([$this->binaries->resolve()], $arguments);

        return $this->runner->run($command);
    }

    /**
     * @param array $errors
     */
    private function reportErrors(array $errors)
    {
        $this->write('This PhpMetrics command line cannot be translated:');
        $this->write('');
        foreach ($errors as $error) {
            $this->write('  ' . $error);
        }
        $this->write('');
        $this->write('Remove or replace those options and run the command again.');
    }

    /**
     * @param array $notices
     * @param array $arguments The final arguments, as they will be passed on.
     */
    private function reportTranslation(array $notices, array $arguments)
    {
        if ($notices === []) {
            return;
        }

        $this->write('Reading this as a PhpMetrics command line:');
        foreach ($notices as $notice) {
            $this->write('  ' . $notice);
        }
        $this->write('');
        $this->write('Equivalent AST Metrics command: ast-metrics ' . implode(' ', $arguments));
        $this->write('');
    }

    /**
     * Asks for non-interactive output, because the interactive interface expects
     * to own the terminal, which a Composer-installed tool driven from a script
     * or a CI job cannot assume.
     *
     * Colours and live output are unaffected: they follow the terminal, not this
     * flag.
     *
     * @param array $arguments
     * @return array
     */
    private function nonInteractive(array $arguments)
    {
        if ($this->hasNonInteractiveOption($arguments)) {
            return $arguments;
        }

        $position = $this->subcommandPosition($arguments);

        // With no subcommand at all, the analyzer prints its welcome screen, and
        // reads the flag as a global one.
        if ($position === null) {
            return array_merge(['--non-interactive'], $arguments);
        }

        $subcommand = $arguments[$position];

        if (in_array($subcommand, self::$streamSubcommands, true)) {
            return $arguments;
        }

        if (!in_array($subcommand, self::$acceptsNonInteractive, true)) {
            return $arguments;
        }

        array_splice($arguments, $position + 1, 0, ['--non-interactive']);

        return $arguments;
    }

    /**
     * @param array $arguments
     * @return bool
     */
    private function hasNonInteractiveOption(array $arguments)
    {
        foreach (['--non-interactive', '-i', '--ci'] as $option) {
            if (in_array($option, $arguments, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $arguments
     * @return int|null Index of the subcommand, or null when there is none.
     */
    private function subcommandPosition(array $arguments)
    {
        foreach ($arguments as $position => $argument) {
            if (strpos($argument, '-') !== 0) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param string $message
     */
    private function write($message)
    {
        if ($this->output === null) {
            return;
        }

        fwrite($this->output, $message . PHP_EOL);
    }
}
