<?php

namespace Halleck45\AstMetrics\Compatibility;

/**
 * Translates a PhpMetrics command line into an AST Metrics one.
 *
 * A project already running PhpMetrics in CI can install this bridge, change the
 * binary name, and keep its existing command line.
 *
 * Translation is on by default, because the audience for this package is
 * PhpMetrics users and the native command line is left untouched anyway: nothing
 * is rewritten unless it means something to PhpMetrics and not to AST Metrics.
 * Set AST_METRICS_NO_COMPAT=1 to pass every argument through verbatim.
 *
 * Every rewrite is reported, so the command line can be migrated for real rather
 * than staying on a compatibility layer forever.
 *
 * Options that would silently change what the analysis enforces are a hard
 * error: a CI job whose quality gate came from --report-violations must not turn
 * green just because the gate quietly disappeared. Options whose absence changes
 * nothing are dropped with a note.
 */
class PhpMetricsArguments
{
    /**
     * Options whose result cannot be produced. Ignoring them would lose output a
     * pipeline depends on, so they stop the run.
     */
    private static $unsupported = [
        'report-csv' => 'AST Metrics has no CSV report. Use --report-json=<file> for machine-readable output, or --report-markdown=<file> for a readable summary.',
        'report-violations' => 'The PMD violations XML format is not produced. Use --report-sarif=<file> instead: SARIF 2.1.0 is read by GitHub code scanning, GitLab and most CI platforms.',
        'report-summary-json' => 'There is no separate summary report. --report-json=<file> already contains the project-level aggregates.',
        'report-summary-html' => 'There is no separate summary report. --report-html=<directory> produces a full explorable report.',
        'junit' => 'JUnit logs are not read, so test results cannot be crossed with the metrics.',
        'metrics' => 'Use "ruleset list" to see the available rules, or https://ast-metrics.dev for the list of metrics.',
    ];

    /**
     * Options with nothing to translate, because AST Metrics either already does
     * it or has nothing to switch off. Dropping them changes no result.
     */
    private static $obsolete = [
        'git' => 'Git history analysis is built in and always on: bus factor, churn and activity are part of the report.',
        'composer' => 'There is nothing to enable: dependencies are derived from the code itself, and reported as afferent and efferent coupling.',
        'quiet' => 'The bridge already runs the analyzer non-interactively.',
    ];

    /**
     * Subcommands of the AST Metrics CLI. PhpMetrics has none, so one is
     * injected when the command line does not name any.
     */
    private static $subcommands = [
        'analyze', 'a',
        'clean', 'c',
        'self-update', 'u',
        'ruleset',
        'version', 'v',
        'lint', 'l',
        'ci',
        'review',
        'deploy:github',
        'init', 'i',
        'mcp',
        'help', 'h',
    ];

    private $notices = [];

    private $errors = [];

    /**
     * @return bool
     */
    public function isEnabled()
    {
        $disabled = getenv('AST_METRICS_NO_COMPAT');

        return $disabled !== '1' && $disabled !== 'true';
    }

    /**
     * @param array $arguments Raw argv, without the script name.
     * @return array The translated arguments.
     */
    public function translate(array $arguments)
    {
        $this->notices = [];
        $this->errors = [];

        // "--version" and "--help" need no analysis and no subcommand.
        if ($this->isInformationalOnly($arguments)) {
            return $arguments;
        }

        $translated = [];
        $paths = [];
        $hasSubcommand = false;

        foreach ($arguments as $argument) {
            $name = $this->optionName($argument);

            if ($name === null) {
                if (in_array($argument, self::$subcommands, true) && $translated === [] && $paths === []) {
                    $hasSubcommand = true;
                    $translated[] = $argument;
                    continue;
                }

                foreach ($this->splitList($argument) as $path) {
                    $paths[] = $path;
                }
                continue;
            }

            if (isset(self::$unsupported[$name])) {
                $this->errors[] = sprintf('--%s: %s', $name, self::$unsupported[$name]);
                continue;
            }

            if (isset(self::$obsolete[$name])) {
                $this->notices[] = sprintf('--%s was dropped: %s', $name, self::$obsolete[$name]);
                continue;
            }

            $value = $this->optionValue($argument);

            switch ($name) {
                case 'exclude':
                    foreach ($this->excludeOptions($value) as $option) {
                        $translated[] = $option;
                    }
                    break;

                case 'extensions':
                    $option = $this->extensionsOption($value);
                    if ($option !== null) {
                        $translated[] = $option;
                    }
                    break;

                case 'config':
                    $this->errors[] = '--config: PhpMetrics configuration files (JSON, YAML, INI) are not translated, because groups, searches and violation thresholds have no equivalent. Run "ast-metrics init" to create a .ast-metrics.yaml, then pass it with --config.';
                    break;

                default:
                    $translated[] = $argument;
                    break;
            }
        }

        if (!$hasSubcommand) {
            array_unshift($translated, 'analyze');
            $this->notices[] = 'Inserted the "analyze" subcommand: AST Metrics groups its features into subcommands (analyze, lint, ci, review).';
        }

        if (count($paths) > 1) {
            $this->notices[] = sprintf(
                'Split the directory list into separate arguments: %s.',
                implode(' ', $paths)
            );
        }

        return array_merge($translated, $paths);
    }

    /**
     * @return array
     */
    public function notices()
    {
        return $this->notices;
    }

    /**
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * @param array $arguments
     * @return bool
     */
    private function isInformationalOnly(array $arguments)
    {
        if ($arguments === []) {
            return true;
        }

        foreach ($arguments as $argument) {
            if (!in_array($argument, ['--version', '-V', '--help', '-h'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * PhpMetrics excludes a comma-separated list of directory names; AST Metrics
     * excludes paths matching a regular expression, and accepts the option more
     * than once. So the list is split, and each part is escaped only when it
     * reads as a plain path: an expression a native user wrote on purpose, such
     * as "Test.*Case", must keep working.
     *
     * @param string|null $value
     * @return array
     */
    private function excludeOptions($value)
    {
        $options = [];
        $parts = $this->splitList((string) $value);

        foreach ($parts as $part) {
            $options[] = '--exclude=' . ($this->isPathLike($part) ? $this->quoteRegularExpression($part) : $part);
        }

        if (count($parts) > 1) {
            $this->notices[] = sprintf('--exclude became %s.', implode(' ', $options));
        }

        return $options;
    }

    /**
     * A directory name, as opposed to a regular expression written on purpose.
     *
     * @param string $value
     * @return bool
     */
    private function isPathLike($value)
    {
        return preg_match('#^[A-Za-z0-9_./-]+$#', $value) === 1;
    }

    /**
     * PhpMetrics is given the full list of PHP extensions to parse. AST Metrics
     * already parses .php, and takes the extra ones, with a leading dot.
     *
     * @param string|null $value
     * @return string|null
     */
    private function extensionsOption($value)
    {
        $extra = [];

        foreach ($this->splitList((string) $value) as $extension) {
            $extension = '.' . ltrim($extension, '.');
            if ($extension !== '.php') {
                $extra[] = $extension;
            }
        }

        if ($extra === []) {
            $this->notices[] = '--extensions was dropped: .php is parsed by default.';
            return null;
        }

        $this->notices[] = sprintf('--extensions became --php-extensions=%s.', implode(',', $extra));

        return '--php-extensions=' . implode(',', $extra);
    }

    /**
     * Escapes the characters that mean something in a Go regular expression, so
     * a directory name is matched literally.
     *
     * @param string $literal
     * @return string
     */
    private function quoteRegularExpression($literal)
    {
        $metacharacters = ['\\', '.', '+', '*', '?', '(', ')', '|', '[', ']', '{', '}', '^', '$'];

        foreach ($metacharacters as $metacharacter) {
            $literal = str_replace($metacharacter, '\\' . $metacharacter, $literal);
        }

        return $literal;
    }

    /**
     * @param string $value
     * @return array
     */
    private function splitList($value)
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), function ($item) {
            return $item !== '';
        }));
    }

    /**
     * @param string $argument
     * @return string|null The option name, or null when the argument is not an option.
     */
    private function optionName($argument)
    {
        if (strpos($argument, '--') !== 0) {
            return null;
        }

        $argument = substr($argument, 2);
        $position = strpos($argument, '=');

        return $position === false ? $argument : substr($argument, 0, $position);
    }

    /**
     * @param string $argument
     * @return string|null
     */
    private function optionValue($argument)
    {
        $position = strpos($argument, '=');

        return $position === false ? null : substr($argument, $position + 1);
    }
}
