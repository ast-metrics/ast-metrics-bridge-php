<?php

use Halleck45\AstMetrics\Compatibility\PhpMetricsArguments;

// ---------------------------------------------------------------------------
// A native command line has to survive translation untouched
// ---------------------------------------------------------------------------

$compatibility = new PhpMetricsArguments();

assertTrue('translation is on by default', $compatibility->isEnabled());

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
    'a repeated option is kept as is',
    ['analyze', '--exclude=vendor', '--exclude=build', 'src'],
    $compatibility->translate(['analyze', '--exclude=vendor', '--exclude=build', 'src'])
);

assertSame(
    'no exclusion is added, so tests stay analyzed and test quality keeps working',
    ['analyze', 'src'],
    $compatibility->translate(['analyze', 'src'])
);

assertSame(
    'every subcommand is recognised',
    ['lint', 'src'],
    $compatibility->translate(['lint', 'src'])
);

assertSame(
    'the mcp transport is left strictly alone',
    ['mcp', '.'],
    $compatibility->translate(['mcp', '.'])
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
    'a redundant extension list is dropped rather than passed on',
    ['analyze', 'src'],
    $compatibility->translate(['--extensions=php', 'src'])
);

assertSame(
    'an extension is given its leading dot',
    ['analyze', '--php-extensions=.inc,.module', 'src'],
    $compatibility->translate(['--extensions=php,inc,.module', 'src'])
);

assertSame(
    'an existing subcommand is kept when other options are translated',
    true,
    in_array('lint', $compatibility->translate(['lint', '--extensions=php,inc', 'src,lib']), true)
);

assertSame(
    'trailing separators do not produce empty paths',
    ['analyze', 'src'],
    $compatibility->translate(['src,'])
);

// ---------------------------------------------------------------------------
// Informational invocations need no subcommand
// ---------------------------------------------------------------------------

assertSame('--version is passed through', ['--version'], $compatibility->translate(['--version']));
assertSame('--help is passed through', ['--help'], $compatibility->translate(['--help']));
assertSame('an empty command line is passed through', [], $compatibility->translate([]));

// ---------------------------------------------------------------------------
// Options that changed nothing are dropped; options that lose a result stop
// ---------------------------------------------------------------------------

assertSame(
    'git history analysis is built in, so --git is dropped rather than refused',
    ['analyze', 'src'],
    $compatibility->translate(['--git', 'src'])
);

assertTrue(
    'dropping --git is explained',
    strpos(implode(' ', $compatibility->notices()), 'built in') !== false
);

assertSame(
    'a --git with an explicit binary path is dropped too',
    ['analyze', 'src'],
    $compatibility->translate(['--git=/usr/bin/git', 'src'])
);

assertSame(
    '--composer has nothing to switch off, so it is dropped',
    ['analyze', 'src'],
    $compatibility->translate(['--composer=false', 'src'])
);

assertSame(
    '--quiet is dropped',
    ['analyze', 'src'],
    $compatibility->translate(['--quiet', 'src'])
);

assertSame('dropped options are not errors', [], $compatibility->errors());

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

$compatibility->translate(['--report-violations=build/pmd.xml', 'src']);
assertTrue(
    'the refusal names the replacement',
    strpos(implode(' ', $compatibility->errors()), '--report-sarif') !== false
);

$compatibility->translate(['analyze', 'src']);
assertSame('errors are reset between runs', [], $compatibility->errors());
