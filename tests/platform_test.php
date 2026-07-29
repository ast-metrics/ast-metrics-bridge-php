<?php

use Halleck45\AstMetrics\Binary\Platform;

assertSame('linux x86_64', 'ast-metrics_Linux_x86_64', (new Platform('Linux', 'x86_64'))->assetName());
assertSame('linux aarch64 is arm64', 'ast-metrics_Linux_arm64', (new Platform('Linux', 'aarch64'))->assetName());
assertSame('amd64 is x86_64', 'ast-metrics_Linux_x86_64', (new Platform('Linux', 'amd64'))->assetName());
assertSame('macos arm64', 'ast-metrics_Darwin_arm64', (new Platform('Darwin', 'arm64'))->assetName());
assertSame('macos intel', 'ast-metrics_Darwin_x86_64', (new Platform('Darwin', 'x86_64'))->assetName());

assertSame(
    'windows gets the exe suffix',
    'ast-metrics_Windows_x86_64.exe',
    (new Platform('Windows NT', 'AMD64'))->assetName()
);

assertSame('msys reports as windows', 'Windows', (new Platform('MINGW64_NT-10.0', 'x86_64'))->os());
assertSame('cygwin reports as windows', 'Windows', (new Platform('CYGWIN_NT-10.0', 'x86_64'))->os());
assertTrue('windows is detected', (new Platform('Windows NT', 'x86_64'))->isWindows());
assertSame('linux is not windows', false, (new Platform('Linux', 'x86_64'))->isWindows());

// The release only publishes these combinations. Advertising more turned a 404
// into a file that was made executable and then run.
assertThrows(
    'an architecture with no published binary points at the way out',
    'AST_METRICS_BINARY',
    function () {
        (new Platform('Linux', 'i386'))->assetName();
    }
);

assertThrows(
    'arm64 on Windows is not published',
    'No AST Metrics binary is published',
    function () {
        (new Platform('Windows NT', 'arm64'))->assetName();
    }
);

assertThrows(
    'an unknown system is refused',
    'No AST Metrics binary is published',
    function () {
        (new Platform('FreeBSD', 'x86_64'))->assetName();
    }
);

assertThrows(
    'the message lists what is available',
    'Darwin (x86_64, arm64)',
    function () {
        (new Platform('Linux', 'sparc'))->assetName();
    }
);
