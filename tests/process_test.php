<?php

use Halleck45\AstMetrics\Process\ProcessRunner;

$runner = new ProcessRunner();

// A shell is used here only because it is guaranteed to be present and its exit
// code is predictable. The commands write nothing, so the test output stays clean
// even though the runner inherits the standard streams on purpose.
if (DIRECTORY_SEPARATOR === '/') {
    assertSame('a successful process returns 0', 0, $runner->run(['/bin/sh', '-c', 'exit 0']));
    assertSame('a failing process returns its own code', 7, $runner->run(['/bin/sh', '-c', 'exit 7']));
    assertSame('the highest usual code survives', 255, $runner->run(['/bin/sh', '-c', 'exit 255']));

    assertSame(
        'an argument containing spaces is passed as a single argument',
        0,
        $runner->run(['/bin/sh', '-c', 'test "$1" = "two words"', 'sh', 'two words'])
    );

    assertSame(
        'an argument containing a quote is not re-interpreted',
        0,
        $runner->run(['/bin/sh', '-c', 'test "$1" = "it'."'".'s"', 'sh', "it's"])
    );
}

assertThrows(
    'an empty command is refused',
    'No command to run',
    function () use ($runner) {
        $runner->run([]);
    }
);

assertThrows(
    'a missing binary is reported with a usable message',
    'Could not start',
    function () use ($runner) {
        $runner->run([__DIR__ . '/does-not-exist']);
    }
);
