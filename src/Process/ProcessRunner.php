<?php

namespace Halleck45\AstMetrics\Process;

use RuntimeException;

/**
 * Runs the analyzer as a child process that inherits the standard streams of
 * the current process.
 *
 * This matters more than it looks. Capturing the output (with exec() or
 * shell_exec()) has three consequences:
 *
 *   - nothing is displayed until the analysis is over, which reads as a freeze
 *     on a large codebase;
 *   - the child no longer writes to a terminal, so it turns colours and
 *     progress rendering off;
 *   - the exit code has to be re-created by hand, and is easy to lose.
 *
 * Inheriting the descriptors solves all three: output is streamed as it is
 * produced, the analyzer still sees a terminal when there is one, and the real
 * exit code is returned.
 */
class ProcessRunner
{
    /**
     * @param array $command Program and arguments, unescaped.
     * @return int The exit code of the child process.
     * @throws RuntimeException When the process cannot be started.
     */
    public function run(array $command)
    {
        if ($command === []) {
            throw new RuntimeException('No command to run.');
        }

        $descriptors = [
            0 => defined('STDIN') ? STDIN : ['pipe', 'r'],
            1 => defined('STDOUT') ? STDOUT : ['pipe', 'w'],
            2 => defined('STDERR') ? STDERR : ['pipe', 'w'],
        ];

        $pipes = [];
        $process = @proc_open($this->prepare($command), $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException(sprintf(
                'Could not start "%s". Check that the binary is executable and that proc_open() is not disabled.',
                $command[0]
            ));
        }

        // Nothing to read: the child writes straight to our own streams.
        return proc_close($process);
    }

    /**
     * PHP 7.4 and later accept the command as an array and start the program
     * directly, with no shell in between: arguments containing spaces, quotes
     * or globs are passed through untouched. Older versions need a string, so
     * every argument is escaped.
     *
     * @param array $command
     * @return array|string
     */
    private function prepare(array $command)
    {
        if (PHP_VERSION_ID >= 70400) {
            return $command;
        }

        return implode(' ', array_map('escapeshellarg', $command));
    }
}
