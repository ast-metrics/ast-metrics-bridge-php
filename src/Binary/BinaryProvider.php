<?php

namespace Halleck45\AstMetrics\Binary;

use RuntimeException;

/**
 * Provides a path to a runnable AST Metrics binary, downloading it once and
 * caching it per version and per platform.
 *
 * Two environment variables are honoured:
 *
 *   AST_METRICS_BINARY     Absolute path to an existing binary. Nothing is
 *                          downloaded. Useful for air-gapped CI, for distro
 *                          packages, and when developing AST Metrics itself.
 *   AST_METRICS_VERSION    Release tag to download, or "latest". Defaults to
 *                          the version this bridge was released against, so
 *                          that a locked composer.lock gives a reproducible
 *                          analysis.
 *   AST_METRICS_CACHE_DIR  Where to store downloaded binaries.
 */
class BinaryProvider
{
    /**
     * Version this release of the bridge is tested against.
     *
     * Pinning it here (rather than always resolving "latest") is what makes a
     * locked composer.lock reproducible: two runs of the same project get the
     * same analyzer, so metrics do not move under your feet.
     */
    const DEFAULT_VERSION = 'v0.41.0';

    const REPOSITORY = 'ast-metrics/ast-metrics';

    /**
     * A real build weighs tens of megabytes. Anything smaller is an error page
     * that we must not chmod +x and execute.
     */
    const MINIMUM_SIZE = 1048576;

    const TIMEOUT = 120;

    private $platform;

    private $output;

    /**
     * @param Platform      $platform
     * @param resource|null $output   Where progress is written. Defaults to stderr,
     *                                because stdout belongs to the analyzer.
     */
    public function __construct(Platform $platform, $output = null)
    {
        $this->platform = $platform;
        $this->output = $output;
    }

    /**
     * @return BinaryProvider
     */
    public static function create()
    {
        return new self(new Platform(), defined('STDERR') ? STDERR : null);
    }

    /**
     * @return string Path to a runnable binary.
     * @throws RuntimeException
     */
    public function resolve()
    {
        $provided = getenv('AST_METRICS_BINARY');
        if (is_string($provided) && $provided !== '') {
            return $this->validateProvidedBinary($provided);
        }

        $version = $this->version();
        $path = $this->cachedPath($version);

        if (is_file($path) && is_executable($path)) {
            return $path;
        }

        $this->download($this->downloadUrl($version), $path);

        return $path;
    }

    /**
     * @param string $provided
     * @return string
     */
    private function validateProvidedBinary($provided)
    {
        if (!is_file($provided)) {
            throw new RuntimeException(sprintf(
                'AST_METRICS_BINARY points to "%s", which does not exist.',
                $provided
            ));
        }

        if (!is_executable($provided)) {
            throw new RuntimeException(sprintf(
                'AST_METRICS_BINARY points to "%s", which is not executable. Try: chmod +x %s',
                $provided,
                $provided
            ));
        }

        return $provided;
    }

    /**
     * @return string
     */
    private function version()
    {
        $requested = getenv('AST_METRICS_VERSION');
        if (!is_string($requested) || $requested === '') {
            return self::DEFAULT_VERSION;
        }

        if ($requested === 'latest') {
            return $this->resolveLatestVersion();
        }

        return $requested;
    }

    /**
     * @return string
     */
    private function resolveLatestVersion()
    {
        $url = sprintf('https://api.github.com/repos/%s/releases/latest', self::REPOSITORY);
        $payload = $this->read($url);
        $release = json_decode($payload, true);

        if (!is_array($release) || !isset($release['tag_name'])) {
            throw new RuntimeException(sprintf(
                'Could not read the latest AST Metrics release from %s.' . PHP_EOL
                . 'Pin a version instead, with AST_METRICS_VERSION=%s.',
                $url,
                self::DEFAULT_VERSION
            ));
        }

        return $release['tag_name'];
    }

    /**
     * @param string $version
     * @return string
     */
    private function downloadUrl($version)
    {
        return sprintf(
            'https://github.com/%s/releases/download/%s/%s',
            self::REPOSITORY,
            $version,
            $this->platform->assetName()
        );
    }

    /**
     * Cache path, scoped by version so that upgrading the bridge upgrades the
     * binary, and by user so that a shared /tmp cannot be used to plant an
     * executable that we would then run.
     *
     * @param string $version
     * @return string
     */
    private function cachedPath($version)
    {
        $directory = $this->cacheDirectory() . DIRECTORY_SEPARATOR . $version;

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create the cache directory "%s".', $directory));
        }

        return $directory . DIRECTORY_SEPARATOR . $this->platform->assetName();
    }

    /**
     * @return string
     */
    private function cacheDirectory()
    {
        $explicit = getenv('AST_METRICS_CACHE_DIR');
        if (is_string($explicit) && $explicit !== '') {
            return rtrim($explicit, '/\\');
        }

        $xdg = getenv('XDG_CACHE_HOME');
        if (is_string($xdg) && $xdg !== '') {
            return rtrim($xdg, '/\\') . DIRECTORY_SEPARATOR . 'ast-metrics';
        }

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            return rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR . 'ast-metrics';
        }

        // Windows, or a hardened environment without HOME.
        $local = getenv('LOCALAPPDATA');
        if (is_string($local) && $local !== '') {
            return rtrim($local, '/\\') . DIRECTORY_SEPARATOR . 'ast-metrics';
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ast-metrics-' . $this->userIdentifier();
    }

    /**
     * @return string
     */
    private function userIdentifier()
    {
        if (function_exists('posix_geteuid')) {
            return (string) posix_geteuid();
        }

        $user = getenv('USER');
        if (!is_string($user) || $user === '') {
            $user = getenv('USERNAME');
        }

        return is_string($user) && $user !== '' ? preg_replace('/[^A-Za-z0-9_.-]/', '_', $user) : 'shared';
    }

    /**
     * Streams the download to a temporary file, then moves it into place, so an
     * interrupted download never leaves a half-written binary in the cache.
     *
     * @param string $url
     * @param string $destination
     */
    private function download($url, $destination)
    {
        $this->write(sprintf('Downloading AST Metrics (%s)...', basename(dirname($destination))));

        $temporary = $destination . '.download.' . getmypid();
        $source = @fopen($url, 'rb', false, $this->streamContext(true));

        if ($source === false) {
            throw new RuntimeException($this->downloadFailureMessage($url));
        }

        $target = @fopen($temporary, 'wb');
        if ($target === false) {
            fclose($source);
            throw new RuntimeException(sprintf('Could not write to "%s".', $temporary));
        }

        $copied = stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        if ($copied === false || $copied < self::MINIMUM_SIZE) {
            @unlink($temporary);
            throw new RuntimeException(sprintf(
                'The download from %s returned %s bytes, which is not a valid binary.' . PHP_EOL
                . 'Check that this version exists, or pin another one with AST_METRICS_VERSION.',
                $url,
                $copied === false ? '0' : (string) $copied
            ));
        }

        if (!$this->platform->isWindows() && !@chmod($temporary, 0755)) {
            @unlink($temporary);
            throw new RuntimeException(sprintf('Could not make "%s" executable.', $temporary));
        }

        if (!@rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException(sprintf('Could not move the downloaded binary to "%s".', $destination));
        }

        $this->write(sprintf('Done (%s MB).', round($copied / 1048576)));
    }

    /**
     * @param string $url
     * @return string
     */
    private function read($url)
    {
        $payload = @file_get_contents($url, false, $this->streamContext(false));

        if ($payload === false) {
            throw new RuntimeException($this->downloadFailureMessage($url));
        }

        return $payload;
    }

    /**
     * @param bool $withProgress
     * @return resource
     */
    private function streamContext($withProgress)
    {
        $options = [
            'http' => [
                'method'          => 'GET',
                'timeout'         => self::TIMEOUT,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'header'          => [
                    'User-Agent: halleck45/ast-metrics (PHP bridge)',
                    'Accept: */*',
                ],
            ],
        ];

        if (!$withProgress) {
            return stream_context_create($options);
        }

        $reported = -1;
        $total = 0;

        $notification = function ($code, $severity, $message, $messageCode, $transferred, $maximum) use (&$reported, &$total) {
            if ($code === STREAM_NOTIFY_FILE_SIZE_IS) {
                $total = $maximum;
                return;
            }

            if ($code !== STREAM_NOTIFY_PROGRESS || $total <= 0) {
                return;
            }

            $percentage = (int) floor($transferred / $total * 100);
            // Only redraw every 5%, to stay readable in CI logs.
            if ($percentage >= $reported + 5) {
                $reported = $percentage - ($percentage % 5);
                $this->write(sprintf('  %d%%', $reported));
            }
        };

        return stream_context_create($options, ['notification' => $notification]);
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

    /**
     * @param string $url
     * @return string
     */
    private function downloadFailureMessage($url)
    {
        $hint = ini_get('allow_url_fopen')
            ? 'Check your network access and any proxy settings (the HTTP_PROXY and HTTPS_PROXY variables are honoured).'
            : 'PHP is configured with allow_url_fopen=Off, so the bridge cannot download anything. Install the binary yourself and set AST_METRICS_BINARY=/path/to/ast-metrics.';

        return sprintf('Could not download from %s.' . PHP_EOL . '%s', $url, $hint);
    }
}
