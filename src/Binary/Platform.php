<?php

namespace Halleck45\AstMetrics\Binary;

use RuntimeException;

/**
 * Detects the current platform and maps it to an asset published on the
 * AST Metrics releases page.
 *
 * The OS and machine can be injected, which keeps this class testable on a
 * single machine.
 */
class Platform
{
    /**
     * Machine names, as reported by php_uname('m'), mapped to the naming used
     * by the release assets.
     */
    private static $architectures = [
        'aarch64' => 'arm64',
        'arm64'   => 'arm64',
        'amd64'   => 'x86_64',
        'x86_64'  => 'x86_64',
        'x64'     => 'x86_64',
    ];

    /**
     * Architectures actually published for each OS. Anything missing here is
     * genuinely not downloadable, so we fail early with a clear message
     * instead of letting the download return a 404 page.
     */
    private static $published = [
        'Linux'   => ['x86_64', 'arm64'],
        'Darwin'  => ['x86_64', 'arm64'],
        'Windows' => ['x86_64'],
    ];

    private $os;

    private $architecture;

    /**
     * @param string|null $os      Defaults to php_uname('s').
     * @param string|null $machine Defaults to php_uname('m').
     */
    public function __construct($os = null, $machine = null)
    {
        $this->os = $this->normalizeOs($os === null ? php_uname('s') : $os);
        $this->architecture = $this->normalizeArchitecture($machine === null ? php_uname('m') : $machine);
    }

    /**
     * @return string
     */
    public function os()
    {
        return $this->os;
    }

    /**
     * @return string
     */
    public function architecture()
    {
        return $this->architecture;
    }

    /**
     * @return bool
     */
    public function isWindows()
    {
        return $this->os === 'Windows';
    }

    /**
     * Name of the release asset for this platform.
     *
     * @return string
     * @throws RuntimeException When no binary is published for this platform.
     */
    public function assetName()
    {
        if (!isset(self::$published[$this->os])) {
            throw new RuntimeException($this->unsupportedMessage());
        }

        if (!in_array($this->architecture, self::$published[$this->os], true)) {
            throw new RuntimeException($this->unsupportedMessage());
        }

        $extension = $this->isWindows() ? '.exe' : '';

        return sprintf('ast-metrics_%s_%s%s', $this->os, $this->architecture, $extension);
    }

    /**
     * @return string
     */
    private function unsupportedMessage()
    {
        $supported = [];
        foreach (self::$published as $os => $architectures) {
            $supported[] = $os . ' (' . implode(', ', $architectures) . ')';
        }

        return sprintf(
            'No AST Metrics binary is published for %s / %s.' . PHP_EOL
            . 'Supported platforms: %s.' . PHP_EOL
            . 'If you build the binary yourself, point the bridge at it with AST_METRICS_BINARY=/path/to/ast-metrics.',
            $this->os,
            $this->architecture,
            implode('; ', $supported)
        );
    }

    /**
     * @param string $os
     * @return string
     */
    private function normalizeOs($os)
    {
        // php_uname('s') returns "Windows NT" on Windows, and Cygwin or MSYS
        // builds report their own prefixes.
        foreach (['Windows', 'CYGWIN', 'MINGW', 'MSYS'] as $needle) {
            if (stripos($os, $needle) !== false) {
                return 'Windows';
            }
        }

        return $os;
    }

    /**
     * @param string $machine
     * @return string
     */
    private function normalizeArchitecture($machine)
    {
        $machine = strtolower(trim($machine));

        return isset(self::$architectures[$machine]) ? self::$architectures[$machine] : $machine;
    }
}
